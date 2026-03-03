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
use Symfony\Component\Process\Process;

class ExternalServiceController extends Controller
{
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

        // текущие tasks (если пусто — массив)
        $existingTasks = collect($service->tasks ?? []);

        // новые tasks из defects
        $newTasks = collect($request->defects)->map(function ($defect) {
            return [
                'taskId'   => (string) $defect['id'],
                'taskName' => $defect['title'],
                'customerApproved' => "",
                'deferredTaskDate' => "",
            ];
        });

        // 🔥 мердж + уникальность по taskId
        $mergedTasks = $existingTasks
            ->merge($newTasks)
            ->unique('taskId')
            ->values()
            ->toArray();

        // сохраняем
        $service->tasks = $mergedTasks;
        $service->defects = $request->defects;

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

        $defects = is_string($request->defects) ? json_decode($request->defects, true) : $request->defects;

        if (!empty($defects) && is_array($defects)) {
            $ffmpeg = FFMpeg::create();
            $video = $service->video()->latest()->first();
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
        ]);
    }


    /**
     * Store a newly created resource in storage.
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
            $existingDetails = collect($order->details ?? []);

            $mergedTasks = $existingTasks
                ->concat($incomingTasks)
                ->unique('taskId')
                ->values();

            if ($customerDecisionRecorded) {
                // После решения клиента details замораживаются.
                $mergedDetails = $existingDetails;
            } else {
                // До решения клиента приоритет у свежих details из внешней системы.
                $mergedDetails = $incomingDetails
                    ->concat($existingDetails)
                    ->unique(fn ($item) => (string) ($item['taskId'] ?? ''))
                    ->values();
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
                ->except(['defects', 'id', 'order_id', 'public_url'])
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
