<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ServiceOrderReview;
use App\Models\Video;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ServiceOrder;
use Illuminate\Support\Facades\Mail;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {

    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        return view('home');
    }

    public function showservices($public_url)
    {
        $service = ServiceOrder::where('public_url', $public_url)
            ->with('dealer')
            ->with('mechanic')
            ->with('serviceReview')
            ->firstOrFail();

        $records = $service->processStatusRecords ?? [];
        if (!is_array($records)) {
            $records = [];
        }
        $exists = collect($records)->contains(fn ($r) =>
            ($r['status'] ?? null) === 'approvalLinkOpened'
        );

        if (!$exists) {
            $records[] = [
                'id' => (string) Str::uuid(),
                'status' => 'approvalLinkOpened',
                'timestamp' => now()->toISOString(),
            ];

            $service->processStatusRecords = $records;
            $service->processStatus = 'approvalLinkOpened';
            $service->save();
        }

        $defects = collect($service->defects ?? []);
        $details = collect($service->details ?? []);
        $tasks = collect($service->tasks ?? []);

        $detailsByTaskId = $details
            ->filter(fn ($d) => is_array($d))
            ->groupBy(fn ($d) => (string) ($d['taskId'] ?? ''));
        $tasksByTaskId = $tasks
            ->filter(fn ($t) => is_array($t))
            ->keyBy(fn ($t) => (string) ($t['taskId'] ?? ''));

        $images = $service->getMedia('frames');

        $items = $defects
            ->map(function ($defect) use ($detailsByTaskId, $tasksByTaskId, $images) {


            $id = (string) ($defect['id'] ?? '');
            $taskDetails = collect($detailsByTaskId->get($id, []))
                ->map(function ($detail) {
                    return [
                        'lineId' => $detail['lineId'] ?? null,
                        'positionCode' => $detail['positionCode'] ?? null,
                        'positionType' => $detail['positionType'] ?? null,
                        'positionName' => $detail['positionName'] ?? null,
                        'positionMaterialGroup' => $detail['positionMaterialGroup'] ?? null,
                        'positionQuantity' => $detail['positionQuantity'] ?? null,
                        'positionMeasure' => $detail['positionMeasure'] ?? null,
                        'positionAmountExVat' => $detail['positionAmountExVat'] ?? null,
                        'positionAmountIncVat' => $detail['positionAmountIncVat'] ?? null,
                    ];
                })
                ->values()
                ->toArray();
            $task = $tasksByTaskId->get($id, []);

            $imageForDefect = optional(
                $images->firstWhere('custom_properties.taskId', $id)
            )->getUrl();


            return [
                'id'    => (int) ($defect['id'] ?? 0),
                'time'  => (int) ($defect['time'] ?? 0),
                'title' => $defect['title'] ?? null,
                'image' => $imageForDefect,
                'answerStatus' => $defect['status'] ?? null,
                'details' => $taskDetails,
                'customerApproved' => $task['customerApproved'] ?? "",
                'deferredTaskDate' => $task['deferredTaskDate'] ?? ""
            ];
            })->values()->toArray();


        return view('services.index', [
            'service' => $service,
            'items' => $items,
        ]);
    }



    public function videoplay($id): Response
    {
        $video = Video::findOrFail($id);
        $path = Storage::disk('videos')->path($video->path);

        if (!file_exists($path)) {
            abort(404, 'Видео не найдено');
        }

        return response()->file($path, [
            'Content-Type' => $video->mime_type,
            'Content-Disposition' => 'inline', // для просмотра в браузере
        ]);
    }


    public function updateservices(Request $request, string $public_url)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required',
            'items.*.variantId' => 'nullable',
            'items.*.customerApproved' => 'required|string|in:approved,deferred,rejected',
            'items.*.deferredTaskDate' => 'nullable|date',
        ]);

        $service = ServiceOrder::where('public_url', $public_url)->firstOrFail();

        $tasks = collect($service->tasks ?? [])->map(function ($task) {
            return is_array($task) ? $task : [];
        });

        foreach ($validated['items'] as $item) {
            $taskId = (string) ($item['id'] ?? '');
            $status = (string) ($item['customerApproved'] ?? '');

            if ($status === 'deferred' && empty($item['deferredTaskDate'])) {
                return response()->json([
                    'message' => 'Для статуса deferred требуется deferredTaskDate',
                ], 422);
            }

            $deferredTaskDate = $status === 'deferred'
                ? (string) ($item['deferredTaskDate'] ?? '')
                : '';

            $tasks = $tasks->map(function ($task) use ($taskId, $status, $deferredTaskDate) {
                if ((string) ($task['taskId'] ?? '') !== $taskId) {
                    return $task;
                }

                $task['customerApproved'] = $status;
                $task['deferredTaskDate'] = $deferredTaskDate;

                return $task;
            });
        }

        $service->tasks = $tasks->values()->toArray();
        $service->local_status = 'closed';
        $service->save();

        return response()->json([
            'success' => true,
            'tasks' => $service->tasks,
            'local_status' => $service->local_status,
        ]);
    }

    public function requestCallback(Request $request, string $public_url)
    {
        $validated = $request->validate([
            'item_id' => 'required',
            'item_title' => 'nullable|string|max:255',
        ]);

        $service = ServiceOrder::where('public_url', $public_url)
            ->with('user')
            ->firstOrFail();

        $recipientEmail = $service->user?->email;

        if (empty($recipientEmail)) {
            return response()->json([
                'message' => 'Для заявки не найден email получателя.',
            ], 422);
        }

        $client = is_array($service->client) ? $service->client : [];
        $clientName = trim(implode(' ', array_filter([
            $client['customerLastName'] ?? null,
            $client['customerFirstName'] ?? null,
            $client['customerMidName'] ?? null,
        ])));
        $clientPhone = $client['customerPhone'] ?? 'не указан';
        $orderNumber = data_get($service->referenceObject, 'orderId', $service->order_id);

        $itemTitle = trim((string) ($validated['item_title'] ?? ''));
        if ($itemTitle === '') {
            $itemTitle = collect($service->defects ?? [])
                ->firstWhere('id', (string) $validated['item_id'])['title'] ?? '';
        }

        $messageLines = [
            "Клиент по заявке №{$orderNumber} запросил обратный звонок.",
            '',
            'Причина: клиент ожидает, что менеджер свяжется с ним и подскажет по вопросу.',
            'Клиент: ' . ($clientName !== '' ? $clientName : 'не указан'),
        ];

        if ($itemTitle !== '') {
            $messageLines[] = "Позиция: {$itemTitle}";
        }

        //$messageLines[] = 'Ссылка на заявку: ' . url("/services/{$service->public_url}");

        Mail::raw(implode(PHP_EOL, $messageLines), function ($message) use ($recipientEmail, $orderNumber) {
            $message
                ->to($recipientEmail)
                ->subject("Запрос обратного звонка по заявке №{$orderNumber}");
        });

        return response()->json([
            'success' => true,
        ]);
    }

    public function markApprovalLinkSent(string $public_url)
    {
        $service = ServiceOrder::where('public_url', $public_url)->firstOrFail();

        $records = $service->processStatusRecords ?? [];
        if (!is_array($records)) {
            $records = [];
        }

        $exists = collect($records)->contains(
            fn ($r) => ($r['status'] ?? null) === 'approvalLinkSent'
        );

        if (!$exists) {
            $records[] = [
                'id' => (string) Str::uuid(),
                'status' => 'approvalLinkSent',
                'timestamp' => now()->toISOString(),
            ];

            $service->processStatusRecords = $records;
            $service->processStatus = 'approvalLinkSent';
            $service->save();
        }

        return response()->json(['success' => true]);
    }

    public function storereview(Request $request, string $public_url)
    {
        $validated = $request->validate([
            'info_usefulness' => 'required|integer|min:1|max:5',
            'usability' => 'required|integer|min:1|max:5',
            'video_content' => 'required|integer|min:1|max:5',
            'video_image' => 'required|integer|min:1|max:5',
            'video_sound' => 'required|integer|min:1|max:5',
            'video_duration' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        $service = ServiceOrder::where('public_url', $public_url)->firstOrFail();

        $review = ServiceOrderReview::query()->updateOrCreate(
            ['order_id' => $service->id],
            $validated
        );

        return response()->json([
            'success' => true,
            'review' => $review,
        ]);
    }
}
