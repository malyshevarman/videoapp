<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ServiceOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use App\Models\Video;
use Illuminate\Support\Facades\Storage;
use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\TimeCode;
use Illuminate\Support\Str;
use OpenApi\Annotations as OA;
use Symfony\Component\Process\Process;

class ExternalServiceController extends Controller
{
    private function isNumericTaskId($value): bool
    {
        return is_numeric($value) && (string) (int) $value === (string) $value;
    }

    private function normalizeDefectsAndTasks(ServiceOrder $service, array $incomingDefects): array
    {
        $existingTasks = collect($service->tasks ?? [])->values();
        $storedDefectIds = collect($service->defects ?? [])
            ->pluck('id')
            ->filter(fn ($id) => $this->isNumericTaskId((string) $id))
            ->map(fn ($id) => (string) (int) $id)
            ->values();

        $baseTasks = $existingTasks->reject(function ($task) use ($storedDefectIds) {
            $taskId = (string) data_get($task, 'taskId');

            return $storedDefectIds->contains((string) (int) $taskId);
        })->values();

        $reservedTaskIds = $baseTasks
            ->pluck('taskId')
            ->filter(fn ($taskId) => $this->isNumericTaskId((string) $taskId))
            ->map(fn ($taskId) => (int) $taskId)
            ->values();

        $nextTaskId = max(
            0,
            $existingTasks
                ->pluck('taskId')
                ->filter(fn ($taskId) => $this->isNumericTaskId((string) $taskId))
                ->map(fn ($taskId) => (int) $taskId)
                ->max() ?? 0
        ) + 1;

        $assignedIds = [];

        $normalizedDefects = collect($incomingDefects)->map(function ($defect) use ($storedDefectIds, $reservedTaskIds, &$nextTaskId, &$assignedIds) {
            $requestedId = array_key_exists('id', $defect) ? (string) $defect['id'] : null;
            $normalizedId = null;

            if (
                $requestedId !== null &&
                $this->isNumericTaskId($requestedId) &&
                !in_array((int) $requestedId, $assignedIds, true) &&
                (
                    $storedDefectIds->contains((string) (int) $requestedId) ||
                    !$reservedTaskIds->contains((int) $requestedId)
                )
            ) {
                $normalizedId = (string) (int) $requestedId;
            }

            if ($normalizedId === null) {
                while (in_array($nextTaskId, $assignedIds, true) || $reservedTaskIds->contains($nextTaskId)) {
                    $nextTaskId++;
                }

                $normalizedId = (string) $nextTaskId;
                $nextTaskId++;
            }

            $assignedIds[] = (int) $normalizedId;

            return [
                'id' => $normalizedId,
                'time' => array_key_exists('time', $defect) ? $defect['time'] : null,
                'title' => (string) ($defect['title'] ?? ''),
                'status' => $defect['status'] ?? null,
                'customerApproved' => (string) ($defect['customerApproved'] ?? ''),
                'deferredTaskDate' => (string) ($defect['deferredTaskDate'] ?? ''),
            ];
        })->values();

        $defectTasks = $normalizedDefects->map(function ($defect) {
            return [
                'taskId' => (string) $defect['id'],
                'taskName' => $defect['title'],
                'customerApproved' => (string) ($defect['customerApproved'] ?? ''),
                'deferredTaskDate' => (string) ($defect['deferredTaskDate'] ?? ''),
            ];
        });

        return [
            'defects' => $normalizedDefects->toArray(),
            'tasks' => $baseTasks->concat($defectTasks)->values()->toArray(),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function defects(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'service_id' => 'required|integer|exists:service_orders,id',
            'defects' => 'required|array',
            'defects.*.id' => 'required',
            'defects.*.title' => 'required|string',
            'customerApproved'=>'string',
            'deferredTaskDate'=>'string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $service = ServiceOrder::findOrFail($request->service_id);
        $originalTasks = $service->tasks;
        $originalDefects = $service->defects;

        // текущие tasks (если пусто — массив)
        $existingTasks = collect($service->tasks ?? [])
            ->filter(function ($task) {
                $taskId = data_get($task, 'taskId');

                if (!is_numeric($taskId)) {
                    return true;
                }

                return (int) $taskId < 100;
            });

        // новые tasks из defects
        $newTasks = collect($request->defects)->map(function ($defect) {
            return [
                'taskId'   => (string) $defect['id'],
                'taskName' => $defect['title'],
                'customerApproved' => (string) ($defect['customerApproved'] ?? ''),
                'deferredTaskDate' => (string) ($defect['deferredTaskDate'] ?? ''),
            ];
        });

        // 🔥 мердж + уникальность по taskId
        $mergedTasks = $existingTasks
            ->concat($newTasks)
            ->values()
            ->toArray();

        // сохраняем
        $service->tasks = $originalTasks;
        $service->defects = $originalDefects;

        $normalizedPayload = $this->normalizeDefectsAndTasks($service, $request->defects);

        $service->tasks = $normalizedPayload['tasks'];
        $service->defects = $normalizedPayload['defects'];

        if (is_null($service->user_id)) {
            $service->user_id = Auth::id();
        }

        $records = $service->processStatusRecords ?? [];
        if (!is_array($records)) {
            $records = [];
        }
        $exists = collect($records)->contains(fn ($r) =>
            ($r['status'] ?? null) === 'quotesCreated'
        );

        if (!$exists) {
            $records[] = [
                'id' => (string) Str::uuid(),
                'status' => 'quotesCreated',
                'timestamp' => now()->toISOString(),
            ];

            $service->processStatusRecords = $records;
            $service->processStatus = 'quotesCreated';
            $service->save();
        }

        $service->save();

        $defects = $normalizedPayload['defects'];

        if (!empty($defects) && is_array($defects)) {
            $ffmpeg = FFMpeg::create();
            $video = $service->video()->latest()->first();
            if (!$video) {
                $service->refresh();

                return response()->json([
                    'message' => 'Дефекты сохранены, tasks обновлены',
                    'tasks' => $service->tasks,
                    'defects' => $service->defects,
                ]);
            }

            $videoName = 'videos/'.$video->filename;
            $disk = Storage::disk('videos');


            if (!$disk->exists($videoName)) {
                throw new \Exception('Видео файл не найден: ' . $videoName);
            }

            $fullPath = $disk->path($videoName);

            $videoFFMpeg = $ffmpeg->open($fullPath);

            if ($service->hasMedia('frames')) {
                $service->clearMediaCollection('frames');
            }

            foreach ($defects as $index => $defect) {
                if (isset($defect['time']) && $defect['time']>0) {
                    $timeSec = floatval($defect['time']);
                    $tempImage = tempnam(sys_get_temp_dir(), 'frame_') . '.jpg';

                    $videoFFMpeg->frame(TimeCode::fromSeconds($timeSec))
                        ->save($tempImage);


                    // Сохраняем кадр в ServiceOrder через медиабиблиотеку

                    $service->addMedia($tempImage)
                        ->usingName('frame_' . $index)
                        ->withCustomProperties([
                            'taskId' => (string) $defect['id'],
                            'index'  => $index,
                        ])
                        ->toMediaCollection('frames');

                    @unlink($tempImage);
                }
            }
        }


        $service->refresh();

        return response()->json([
            'message' => 'Дефекты сохранены, tasks обновлены',
            'tasks' => $service->tasks,
            'defects' => $service->defects,
        ]);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @OA\Post(
     *     path="/api/services",
     *     operationId="storeExternalServiceOrder",
     *     tags={"External Services"},
     *     security={{"bearerAuth":{}}},
     *     summary="Создать или обновить заказ-наряд из внешней системы",
     *     description="Метод принимает заказ-наряд, работы, детали, клиента, автомобиль и статусы процесса. Если заказ с referenceObject.orderId уже существует, запись обновляется. При обновлении внутренние поля processStatusRecords, processStatus и defects не перезаписываются внешним API.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"referenceObject"},
     *             @OA\Property(
     *                 property="referenceObject",
     *                 type="object",
     *                 description="Основной идентификатор и метаданные заказ-наряда во внешней системе.",
     *                 required={"orderId"},
     *                 @OA\Property(property="orderId", type="string", description="Идентификатор заказ-наряда во внешней системе.", example="2000057940"),
     *                 @OA\Property(property="orderBarcode", type="string", nullable=true, description="Штрихкод заказ-наряда.", example="0020000579405"),
     *                 @OA\Property(property="orderCategory", type="string", nullable=true, description="Категория заказа.", example="serviceOrder"),
     *                 @OA\Property(property="orderType", type="string", nullable=true, description="Тип заказа."),
     *                 @OA\Property(property="orderWorkType", type="string", nullable=true, description="Тип работ.", example="ISER"),
     *                 @OA\Property(property="orderAmountExVat", type="number", format="float", nullable=true, description="Сумма без НДС.", example=0),
     *                 @OA\Property(property="orderAmountIncVat", type="number", format="float", nullable=true, description="Сумма с НДС.", example=0),
     *                 @OA\Property(property="currencyCode", type="string", nullable=true, description="Код валюты.", example="RUB"),
     *                 @OA\Property(property="orderClosed", type="boolean", nullable=true, description="Признак закрытого заказа.", example=false)
     *             ),
     *             @OA\Property(property="siteId", type="string", nullable=true, description="Код площадки или филиала.", example="5070"),
     *             @OA\Property(property="locationCode", type="string", nullable=true, description="Код локации.", example="L14"),
     *             @OA\Property(property="reviewCategory", type="string", nullable=true, description="Категория осмотра.", example="VideoCapture"),
     *             @OA\Property(property="changeTimeStamp", type="string", format="date-time", nullable=true, description="Время последнего изменения данных во внешней системе.", example="2026-03-10T09:44:28.000000Z"),
     *             @OA\Property(property="closed", type="boolean", nullable=true, description="Признак закрытия карточки осмотра.", example=false),
     *             @OA\Property(property="completed", type="boolean", nullable=true, description="Признак завершения осмотра.", example=false),
     *             @OA\Property(property="completionTimeStamp", type="string", format="date-time", nullable=true, description="Дата и время завершения осмотра.", example="1970-01-01T00:00:00.000000Z"),
     *             @OA\Property(property="creationTimestamp", type="string", format="date-time", nullable=true, description="Дата и время создания осмотра.", example="2026-03-10T08:14:04.000000Z"),
     *             @OA\Property(property="dealerCode", type="string", nullable=true, description="Код дилера."),
     *             @OA\Property(property="hasSurveyRefs", type="boolean", nullable=true, description="Есть ли ссылки на связанные осмотры.", example=false),
     *             @OA\Property(property="reviewId", type="string", nullable=true, description="Идентификатор осмотра во внешней системе."),
     *             @OA\Property(property="visitStartTime", type="string", format="date-time", nullable=true, description="Дата и время начала визита.", example="2026-03-10T05:00:00.000000Z"),
     *             @OA\Property(property="processStatus", type="string", nullable=true, description="Текущий статус процесса согласования.", example="approvalLinkOpened"),
     *             @OA\Property(property="reviewType", type="string", nullable=true, description="Тип осмотра.", example="VC"),
     *             @OA\Property(property="systemId", type="string", nullable=true, description="Код внешней системы.", example="ERD"),
     *             @OA\Property(property="reviewTemplateId", type="string", nullable=true, description="Идентификатор шаблона осмотра."),
     *             @OA\Property(property="reviewName", type="string", nullable=true, description="Название осмотра."),
     *             @OA\Property(property="timeSpent", type="integer", nullable=true, description="Затраченное время в условных единицах.", example=0),
     *             @OA\Property(
     *                 property="tasks",
     *                 type="array",
     *                 description="Список работ или задач по заказ-наряду.",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="taskId", type="string", description="Идентификатор задачи.", example="3"),
     *                     @OA\Property(property="taskName", type="string", description="Название задачи.", example="Название 1 зел"),
     *                     @OA\Property(property="customerApproved", type="string", nullable=true, description="Решение клиента по работе.", example="rejected"),
     *                     @OA\Property(property="deferredTaskDate", type="string", nullable=true, description="Дата переноса выполнения, если есть.", example="")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="details",
     *                 type="array",
     *                 description="Детализация работ и материалов по задачам.",
     *                 @OA\Items(type="object", additionalProperties=true)
     *             ),
     *             @OA\Property(
     *                 property="processStatusRecords",
     *                 type="array",
     *                 description="История изменения статусов процесса.",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="string", format="uuid", description="UUID записи статуса."),
     *                     @OA\Property(property="status", type="string", description="Код статуса процесса.", example="surveyCompleted"),
     *                     @OA\Property(property="timestamp", type="string", format="date-time", description="Время фиксации статуса.")
     *                 )
     *             ),
     *             @OA\Property(property="client", type="object", nullable=true, description="Данные клиента.", additionalProperties=true),
     *             @OA\Property(property="carDriver", type="object", nullable=true, description="Данные водителя.", additionalProperties=true),
     *             @OA\Property(property="carOwner", type="object", nullable=true, description="Данные владельца автомобиля.", additionalProperties=true),
     *             @OA\Property(property="surveyObject", type="object", nullable=true, description="Данные автомобиля и объекта осмотра.", additionalProperties=true),
     *             @OA\Property(property="requester", type="object", nullable=true, description="Сотрудник, инициировавший осмотр.", additionalProperties=true),
     *             @OA\Property(property="responsibleEmployee", type="object", nullable=true, description="Ответственный сотрудник.", additionalProperties=true),
     *             @OA\Property(
     *                 property="defects",
     *                 type="array",
     *                 description="Список дефектов, привязанных к видео и задачам.",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="string", description="Идентификатор дефекта.", example="3"),
     *                     @OA\Property(property="time", type="number", format="float", nullable=true, description="Временная отметка на видео в секундах.", example=3),
     *                     @OA\Property(property="title", type="string", description="Название дефекта.", example="Название 1 зел"),
     *                     @OA\Property(property="status", type="string", nullable=true, description="Цветовой статус дефекта.", example="green"),
     *                     @OA\Property(property="customerApproved", type="string", nullable=true, description="Решение клиента по дефекту."),
     *                     @OA\Property(property="deferredTaskDate", type="string", nullable=true, description="Дата переноса устранения дефекта.")
     *                 )
     *             ),
     *             example={
     *                 "referenceObject": {
     *                     "orderId": "2000057940",
     *                     "orderBarcode": "0020000579405",
     *                     "orderCategory": "serviceOrder",
     *                     "orderType": null,
     *                     "orderWorkType": "ISER",
     *                     "orderAmountExVat": 0,
     *                     "orderAmountIncVat": 0,
     *                     "currencyCode": "RUB",
     *                     "orderClosed": false
     *                 },
     *                 "siteId": "5070",
     *                 "locationCode": "L14",
     *                 "reviewCategory": "VideoCapture",
     *                 "changeTimeStamp": "2026-03-10T09:44:28.000000Z",
     *                 "closed": false,
     *                 "completed": false,
     *                 "completionTimeStamp": "1970-01-01T00:00:00.000000Z",
     *                 "tasks": {
     *                     {"taskId": "0", "taskName": "Свободн. позиции", "customerApproved": null, "deferredTaskDate": null},
     *                     {"taskId": "1", "taskName": "Диагностика АМ при подкл. к Пакетному ТО", "customerApproved": null, "deferredTaskDate": null},
     *                     {"taskId": "3", "taskName": "Название 1 зел", "customerApproved": "rejected", "deferredTaskDate": ""},
     *                     {"taskId": "4", "taskName": "Название 2 жел", "customerApproved": "approved", "deferredTaskDate": ""},
     *                     {"taskId": "5", "taskName": "Название 3 крас", "customerApproved": "rejected", "deferredTaskDate": ""},
     *                     {"taskId": "2", "taskName": "Название 1 зел", "customerApproved": null, "deferredTaskDate": null}
     *                 },
     *                 "details": {
     *                     {"taskId": "0", "category": "start", "answerSigned": false, "text": null, "lineId": null, "positionType": null, "idLabourCatalogue": null, "positionCode": null, "positionName": null, "positionMaterialGroup": null, "positionQuantity": 0, "positionMeasure": null, "positionDiscountPercent": 0, "positionAmountExVat": 0, "positionAmountIncVat": 0},
     *                     {"taskId": "1", "category": "start", "answerSigned": false, "text": null, "lineId": "10", "positionType": "labour", "idLabourCatalogue": "TRADE_IN", "positionCode": "DMS01-001", "positionName": "Диагностика АМ при подкл. к Пакетному ТО", "positionMaterialGroup": "11000", "positionQuantity": 2, "positionMeasure": "STD", "positionDiscountPercent": 0, "positionAmountExVat": 0, "positionAmountIncVat": 0}
     *                 },
     *                 "creationTimestamp": "2026-03-10T08:14:04.000000Z",
     *                 "client": {"customerType": "person", "customerId": "0070012718", "customerFirstName": "Андрей", "customerMidName": "Вениаминович", "customerLastName": "Сильянов", "customerPhone": "9772686902", "customerAddress": "Россия, 142167, г. Москва, д. Петрово, тер. СНТ Петрово, д. 1, кв. 1", "customerEmail": "asilyanov1568@gmail.com", "customerTGId": null, "customerContactAllowed": false},
     *                 "carDriver": {"customerType": "person", "customerId": "0070012718", "customerFirstName": "Андрей", "customerMidName": "Вениаминович", "customerLastName": "Сильянов", "customerPhone": "9772686902", "customerAddress": "Россия, 142167, г. Москва, д. Петрово, тер. СНТ Петрово, д. 1, кв. 1", "customerEmail": "asilyanov1568@gmail.com", "customerTGId": null, "customerContactAllowed": false},
     *                 "carOwner": {"customerType": "person", "customerId": "0070012718", "customerFirstName": "Андрей", "customerMidName": "Вениаминович", "customerLastName": "Сильянов", "customerPhone": "9772686902", "customerAddress": "Россия, 142167, г. Москва, д. Петрово, тер. СНТ Петрово, д. 1, кв. 1", "customerEmail": "asilyanov1568@gmail.com", "customerTGId": null, "customerContactAllowed": false},
     *                 "surveyObject": {"car": null, "carId": "051Mfq8T7z2ofqPmjEO7HG", "carBrand": "BMW", "carModel": "5 SERIES", "carModelCode": "5 SERIES", "carVin": "JTMABABJ104006969", "carLicensePlate": "В555ВВ777", "carLicensePlateCountry": "RU", "carFuel": "Бензин", "keyForCatalogOfWorks": "*"},
     *                 "requester": {"category": "person", "specialistId": "00013163", "specialistFirstName": "Дмитрий", "specialistMidName": "Александрович", "specialistLastName": "Серпуховитин", "systemsUserId": "RUADSERPUKHO", "customerContactAllowed": true},
     *                 "dealerCode": null,
     *                 "hasSurveyRefs": false,
     *                 "reviewId": null,
     *                 "visitStartTime": "2026-03-10T05:00:00.000000Z",
     *                 "processStatus": "approvalLinkOpened",
     *                 "processStatusRecords": {
     *                     {"id": "27bb8699-b905-4b14-b2f4-91bfc47ad554", "status": "surveyCompleted", "timestamp": "2026-03-10T08:33:30.269659Z"},
     *                     {"id": "c7b6d6f9-21ed-49cb-87ee-446731ff93ca", "status": "quotesCreated", "timestamp": "2026-03-10T08:35:30.855196Z"},
     *                     {"id": "39c70915-9cbc-44ae-8021-68f37ab9aa67", "status": "approvalLinkSent", "timestamp": "2026-03-10T09:51:07.929521Z"},
     *                     {"id": "56bea9a1-e39a-41cc-9467-da93913fe844", "status": "approvalLinkOpened", "timestamp": "2026-03-10T09:51:09.896353Z"}
     *                 },
     *                 "reviewType": "VC",
     *                 "responsibleEmployee": {"specialistType": null, "specialistId": null, "idCategory": null, "internalId": null, "specialistFirstName": null, "specialistMiddleName": null, "specialistLastName": null, "contactAllowed": false},
     *                 "systemId": "ERD",
     *                 "reviewTemplateId": null,
     *                 "reviewName": null,
     *                 "timeSpent": 0,
     *
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Заказ-наряд успешно создан или обновлён",
     *         @OA\JsonContent(
     *             @OA\Property(property="referenceObject", type="object", additionalProperties=true),
     *             @OA\Property(property="tasks", type="array", nullable=true, @OA\Items(type="object", additionalProperties=true)),
     *             @OA\Property(property="details", type="array", nullable=true, @OA\Items(type="object", additionalProperties=true)),
     *             @OA\Property(property="processStatusRecords", type="array", nullable=true, @OA\Items(type="object", additionalProperties=true)),
     *             @OA\Property(property="client", type="object", nullable=true, additionalProperties=true),
     *             @OA\Property(property="carDriver", type="object", nullable=true, additionalProperties=true),
     *             @OA\Property(property="carOwner", type="object", nullable=true, additionalProperties=true),
     *             @OA\Property(property="surveyObject", type="object", nullable=true, additionalProperties=true),
     *             @OA\Property(property="requester", type="object", nullable=true, additionalProperties=true),
     *             @OA\Property(property="responsibleEmployee", type="object", nullable=true, additionalProperties=true),
     *             @OA\Property(property="siteId", type="string", nullable=true),
     *             @OA\Property(property="locationCode", type="string", nullable=true),
     *             @OA\Property(property="reviewCategory", type="string", nullable=true),
     *             @OA\Property(property="changeTimeStamp", type="string", format="date-time", nullable=true),
     *             @OA\Property(property="closed", type="boolean"),
     *             @OA\Property(property="completed", type="boolean"),
     *             @OA\Property(property="completionTimeStamp", type="string", format="date-time", nullable=true),
     *             @OA\Property(property="creationTimestamp", type="string", format="date-time", nullable=true),
     *             @OA\Property(property="dealerCode", type="string", nullable=true),
     *             @OA\Property(property="hasSurveyRefs", type="boolean"),
     *             @OA\Property(property="reviewId", type="string", nullable=true),
     *             @OA\Property(property="visitStartTime", type="string", format="date-time", nullable=true),
     *             @OA\Property(property="processStatus", type="string", nullable=true),
     *             @OA\Property(property="reviewType", type="string", nullable=true),
     *             @OA\Property(property="systemId", type="string", nullable=true),
     *             @OA\Property(property="reviewTemplateId", type="string", nullable=true),
     *             @OA\Property(property="reviewName", type="string", nullable=true),
     *             @OA\Property(property="timeSpent", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Отсутствует или неверный Bearer token",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid bearer token.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Ошибка валидации входных данных",
     *         @OA\JsonContent(
     *             @OA\Property(property="errors", type="object", additionalProperties=true),
     *             example={"errors": {"referenceObject": {"Поле referenceObject обязательно для заполнения."}}}
     *         )
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        // 1️⃣ Валидация под новый формат
        $validator = Validator::make($request->all(), [

            'referenceObject' => 'required|array',
            'siteId' => 'nullable|string',
            'locationCode' => 'nullable|string',
            'reviewCategory' => 'nullable|string',
            'changeTimeStamp' => 'nullable|date',

            'closed' => 'nullable|boolean',
            'completed' => 'nullable|boolean',
            'completionTimeStamp' => 'nullable|date',
            'creationTimestamp' => 'nullable|date',

            'dealerCode' => 'nullable|string',
            'hasSurveyRefs' => 'nullable|boolean',
            'reviewId' => 'nullable|string',

            'visitStartTime' => 'nullable|date',
            'processStatus' => 'nullable|string',
            'reviewType' => 'nullable|string',
            'systemId' => 'nullable|string',

            'reviewTemplateId' => 'nullable|string',
            'reviewName' => 'nullable|string',
            'timeSpent' => 'nullable|integer',

            // ===== Массивы в корне =====
            'tasks' => 'nullable|array',
            'details' => 'nullable|array',
            'processStatusRecords' => 'nullable|array',

            // ===== Объекты в корне =====
            'client' => 'nullable|array',
            'carDriver' => 'nullable|array',
            'carOwner' => 'nullable|array',
            'surveyObject' => 'nullable|array',
            'requester' => 'nullable|array',
            'responsibleEmployee' => 'nullable|array',

            'defects' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();

        // 2️⃣ Сохраняем или обновляем по order_id
        $orderId = data_get($data, 'referenceObject.orderId');
        $order = ServiceOrder::where('order_id', $orderId)->first();
        $customerDecisionRecorded = false;

        $incomingTasks = collect($data['tasks'] ?? []);
        $incomingDetails = collect($data['details'] ?? []);

        $mergedTasks = $incomingTasks;
        $mergedDetails = $incomingDetails;

        if ($order) {
            $records = is_array($order->processStatusRecords) ? $order->processStatusRecords : [];
            $customerDecisionRecorded = collect($records)->contains(
                fn ($r) => ($r['status'] ?? null) === 'customerDecisionRecorded'
            );

            $existingTasks = collect($order->tasks ?? []);


            $mergedTasks = $existingTasks
                ->concat($incomingTasks)
                ->unique('taskId')
                ->values();

            if ($customerDecisionRecorded) {

            } else {

            }
        }

        $payload = [
            // ===== JSON блоки =====
            'referenceObject'        => $data['referenceObject'],
            'tasks'                  => $mergedTasks->isNotEmpty() ? $mergedTasks->toArray() : null,
            'details'                => $mergedDetails->isNotEmpty() ? $mergedDetails->toArray() : null,
            'processStatusRecords'   => $data['processStatusRecords'] ?? null,

            'client'                 => $data['client'] ?? null,
            'carDriver'              => $data['carDriver'] ?? null,
            'carOwner'               => $data['carOwner'] ?? null,
            'surveyObject'           => $data['surveyObject'] ?? null,
            'requester'              => $data['requester'] ?? null,
            'responsibleEmployee'    => $data['responsibleEmployee'] ?? null,

            // ===== Простые поля =====
            'siteId'                 => $data['siteId'] ?? null,
            'locationCode'           => $data['locationCode'] ?? null,
            'reviewCategory'         => $data['reviewCategory'] ?? null,
            'changeTimeStamp'        => $data['changeTimeStamp'] ?? null,

            'closed'                 => $data['closed'] ?? false,
            'completed'              => $data['completed'] ?? false,
            'completionTimeStamp'    => $data['completionTimeStamp'] ?? null,
            'creationTimestamp'      => $data['creationTimestamp'] ?? null,

            'dealerCode'             => $data['dealerCode'] ?? null,
            'hasSurveyRefs'          => $data['hasSurveyRefs'] ?? false,
            'reviewId'               => $data['reviewId'] ?? null,

            'visitStartTime'         => $data['visitStartTime'] ?? null,
            'processStatus'          => $data['processStatus'] ?? null,
            'reviewType'             => $data['reviewType'] ?? null,
            'systemId'               => $data['systemId'] ?? null,

            'reviewTemplateId'       => $data['reviewTemplateId'] ?? null,
            'reviewName'             => $data['reviewName'] ?? null,
            'timeSpent'              => $data['timeSpent'] ?? 0,

            'defects'                => $data['defects'] ?? null,
        ];

        if ($order) {
            unset($payload['processStatusRecords'], $payload['processStatus'], $payload['defects']);

            // После фиксации решения клиента не даем внешнему API перетирать details.
            if ($customerDecisionRecorded) {
                unset($payload['details']);
            }

            $order->fill($payload);
            $order->save();
        } else {
            $payload['order_id'] = $orderId;
            $order = ServiceOrder::create($payload);
        }
        if ($order->wasRecentlyCreated) {
            $order->processStatusRecords = [
                [
                    'id' => (string)Str::uuid(),
                    'status' => 'surveyCompleted',
                    'timestamp' => now()->toISOString(),
                ]
            ];
            $order->processStatus = "surveyCompleted";
            $order->save();
        }

        return response()->json(
            collect($order->toArray())
                ->except(['defects', 'id', 'order_id', 'public_url','local_status'])
                ->all()
        );
    }


    /**
     * Display the specified resource.
     */
    public function uploadChunks(Request $request): JsonResponse
    {
        \Log::info('Upload started', [
            'chunks' => $request->input('total_chunks'),
            'all_files' => array_keys($request->allFiles())
        ]);

        try {
            $validator = Validator::make($request->all(), [
                'service_order_id' => 'required|exists:service_orders,id',
                'original_name' => 'required|string',
                'total_duration' => 'required|integer|min:0',
                'total_chunks' => 'required|integer|min:1',
            ]);


            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }


            $services = ServiceOrder::findOrFail($request->service_order_id);

            if (!empty($request->defects)) {
                $services->defects = $request->defects;
                $services->save();
            }

            $serviceOrderId = $request->service_order_id;
            $totalChunks = $request->input('total_chunks');

            // ВАЖНО: Используем другой способ проверки файлов
            $uploadedFiles = $request->allFiles();
            \Log::info('Files received', [
                'count' => count($uploadedFiles),
                'keys' => array_keys($uploadedFiles)
            ]);

            // Проверяем чанки через allFiles()
            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkKey = "chunk_{$i}";
                if (!isset($uploadedFiles[$chunkKey])) {
                    // Дополнительная проверка: может быть файл есть, но в другом формате
                    if ($request->has($chunkKey) && $request->input($chunkKey)) {
                        \Log::warning('Chunk exists as input but not as file', ['chunk' => $chunkKey]);
                    }
                    return response()->json([
                        'error' => "Missing chunk_{$i}",
                        'received' => array_keys($uploadedFiles),
                        'all_input_keys' => array_keys($request->all())
                    ], 400);
                }
            }

            // Создаем временную директорию для сборки файла
            $tempDir = storage_path('app/temp/videos/' . uniqid('video_', true));
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkKey = "chunk_{$i}";
                $chunkFile = $uploadedFiles[$chunkKey];

                // Сохраняем чанк во временный файл через Laravel
                $chunkFile->move($tempDir, 'chunk_' . str_pad($i, 4, '0', STR_PAD_LEFT));
            }

            $existingVideos = Video::where('service_order_id', $serviceOrderId)->get();
            foreach ($existingVideos as $existingVideo) {
                // delete() вызовет hook в модели и удалит файл с диска
                $existingVideo->delete();
            }

            $finalFileName = 'video_' . $serviceOrderId . '_' . time() . '.mp4';
            $finalPath = 'videos/' . $finalFileName;
            $finalFullPath = Storage::disk('videos')->path($finalPath);

            Storage::disk('videos')->makeDirectory('videos');

            if ($totalChunks === 1) {
                $singleChunkPath = $tempDir . '/chunk_' . str_pad(0, 4, '0', STR_PAD_LEFT);
                if (!rename($singleChunkPath, $finalFullPath)) {
                    throw new \RuntimeException('Unable to move single chunk to final video.');
                }
            } else {
                $listPath = $tempDir . '/chunks.txt';
                $listContent = '';

                for ($i = 0; $i < $totalChunks; $i++) {
                    $chunkPath = $tempDir . '/chunk_' . str_pad($i, 4, '0', STR_PAD_LEFT);
                    if (file_exists($chunkPath)) {
                        $escapedPath = str_replace("'", "'\\''", $chunkPath);
                        $listContent .= "file '{$escapedPath}'\n";
                    }
                }

                file_put_contents($listPath, $listContent);

                $process = new Process([
                    'ffmpeg',
                    '-y',
                    '-f',
                    'concat',
                    '-safe',
                    '0',
                    '-i',
                    $listPath,
                    '-c',
                    'copy',
                    $finalFullPath,
                ]);
                $process->setTimeout(120);
                $process->run();

                if (!$process->isSuccessful()) {
                    \Log::warning('FFmpeg concat failed, falling back to binary merge.', [
                        'error' => $process->getErrorOutput(),
                    ]);

                    $finalStream = fopen($finalFullPath, 'wb');
                    for ($i = 0; $i < $totalChunks; $i++) {
                        $chunkPath = $tempDir . '/chunk_' . str_pad($i, 4, '0', STR_PAD_LEFT);
                        if (file_exists($chunkPath)) {
                            $chunkHandle = fopen($chunkPath, 'rb');
                            stream_copy_to_stream($chunkHandle, $finalStream);
                            fclose($chunkHandle);
                        }
                    }
                    fclose($finalStream);
                }
            }

            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkPath = $tempDir . '/chunk_' . str_pad($i, 4, '0', STR_PAD_LEFT);
                if (file_exists($chunkPath)) {
                    unlink($chunkPath);
                }
            }

            // Удаляем временную директорию
            if (is_dir($tempDir)) {
                $this->rrmdir($tempDir);
            }

            // Создаем запись в базе данных
            $video = Video::create([
                'service_order_id' => $serviceOrderId,
                'filename' => $finalFileName,
                'original_name' => $request->original_name,
                'path' => $finalPath,
                'size' => Storage::disk('videos')->size($finalPath),
                'mime_type' => Storage::disk('videos')->mimeType($finalPath),
            ]);

            $defects = is_string($request->defects) ? json_decode($request->defects, true) : $request->defects;

            if (!empty($defects) && is_array($defects)) {
                $ffmpeg = FFMpeg::create();
                $videoFFMpeg = $ffmpeg->open($finalFullPath);

                if ($services->hasMedia('frames')) {
                    $services->clearMediaCollection('frames');
                }

                foreach ($defects as $index => $defect) {
                    if (isset($defect['time'])) {
                        $timeSec = floatval($defect['time']);
                        $tempImage = tempnam(sys_get_temp_dir(), 'frame_') . '.jpg';

                        $videoFFMpeg->frame(TimeCode::fromSeconds($timeSec))
                            ->save($tempImage);


                        // Сохраняем кадр в ServiceOrder через медиабиблиотеку

                        $services->addMedia($tempImage)
                            ->usingName('frame_' . $index)
                            ->withCustomProperties([
                                'taskId' => (string) $defect['id'],
                                'index'  => $index,
                            ])
                            ->toMediaCollection('frames');

                        @unlink($tempImage);
                    }
                }
            }


            return response()->json([
                'success' => true,
                'message' => 'Video uploaded successfully',
                'video' => $video
            ]);

        } catch (\Exception $e) {
            \Log::error('Upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['chunk_0', 'chunk_1', 'chunk_2']) // Логируем без бинарных данных
            ]);

            return response()->json([
                'error' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function rrmdir($dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->rrmdir($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
    /**
     * Update the specified resource in storage.
     */
    public function show(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'service_order_id' => 'required|exists:service_orders,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $video = Video::where('service_order_id', $request->service_order_id)
            ->first();

        if (!$video) {
            return response()->json(['video' => null]);
        }

        // Генерируем URL для просмотра через контроллер (private диск)
        $videoUrl = route('videos.play', ['id' => $video->id]);

        return response()->json([
            'video' => $video,
            'url' => $videoUrl,
        ]);
    }

    /**
     * Удаление видео
     */
    public function destroy(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'video_id' => 'required|exists:videos,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $video = Video::findOrFail($request->video_id);
        $video->delete(); // удаление файла через boot()

        return response()->json(['success' => true]);
    }

}
