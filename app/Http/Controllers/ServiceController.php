<?php

namespace App\Http\Controllers;

use App\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('table_search'));

        $query = $this->visibleServiceOrdersQuery($request)
            ->with('serviceReview')
            ->select(
                'id',
                'public_url',
                'processStatus',
                'order_id',
                'created_at',
                'client',
                'dealerCode',
                'local_status'
            );

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('order_id', 'like', "%{$search}%")
                    ->orWhere('dealerCode', 'like', "%{$search}%")
                    ->orWhere('client->customerFirstName', 'like', "%{$search}%")
                    ->orWhere('client->customerLastName', 'like', "%{$search}%")
                    ->orWhere('client->firstName', 'like', "%{$search}%")
                    ->orWhere('client->lastName', 'like', "%{$search}%");
            });
        }

        $orders = $query->orderByDesc('created_at')
            ->paginate(10)
            ->appends(['table_search' => $search]);

        return view('admin.services.index', compact('orders'));
    }

    public function edit(Request $request, int|string $id)
    {
        $service = $this->findVisibleServiceOrder($request, $id);

        return view('admin.services.edit', compact('service'));
    }

    public function video(Request $request, int|string $id)
    {
        $service = $this->findVisibleServiceOrder($request, $id);

        return view('admin.services.video', compact('service'));
    }

    public function info(Request $request, ServiceOrder $service)
    {
        $service = $this->findVisibleServiceOrder($request, $service->id)
            ->load('serviceReview');
        $payload = $this->buildServicePayload($service);
        $serviceJson = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return view('admin.services.info', [
            'service' => $service,
            'payload' => $payload,
            'serviceJson' => $serviceJson ?: '{}',
        ]);
    }

    public function update(Request $request, int|string $id)
    {
        $service = $this->findVisibleServiceOrder($request, $id);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $service->update($request->all());

        return redirect()->route('admin.services.index')
            ->with('success', 'Service updated successfully');
    }

    public function destroy(Request $request, int|string $id)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Доступ только для администратора.');

        $service = $this->findVisibleServiceOrder($request, $id);
        $service->delete();

        return redirect()->route('admin.services.index')
            ->with('success', 'Сервис удален');
    }

    private function visibleServiceOrdersQuery(Request $request): Builder
    {
        return ServiceOrder::query()->visibleToUser($request->user());
    }

    private function findVisibleServiceOrder(Request $request, int|string $id): ServiceOrder
    {
        return $this->visibleServiceOrdersQuery($request)->findOrFail($id);
    }

    private function buildServicePayload(ServiceOrder $service): array
    {
        $referenceObject = is_array($service->referenceObject) ? $service->referenceObject : [];

        if (!isset($referenceObject['orderId']) && $service->order_id) {
            $referenceObject['orderId'] = $service->order_id;
        }

        return [
            'referenceObject' => $referenceObject,
            'siteId' => $service->siteId,
            'locationCode' => $service->locationCode,
            'reviewCategory' => $service->reviewCategory,
            'changeTimeStamp' => $service->changeTimeStamp?->toISOString(),
            'closed' => (bool) $service->closed,
            'completed' => (bool) $service->completed,
            'completionTimeStamp' => $service->completionTimeStamp?->toISOString(),
            'tasks' => $service->tasks ?? [],
            'details' => $service->details ?? [],
            'creationTimestamp' => $service->creationTimestamp?->toISOString(),
            'client' => $service->client ?? null,
            'carDriver' => $service->carDriver ?? null,
            'carOwner' => $service->carOwner ?? null,
            'surveyObject' => $service->surveyObject ?? null,
            'requester' => $service->requester ?? null,
            'dealerCode' => $service->dealerCode,
            'hasSurveyRefs' => (bool) $service->hasSurveyRefs,
            'reviewId' => $service->reviewId,
            'visitStartTime' => $service->visitStartTime?->toISOString(),
            'processStatus' => $service->processStatus,
            'processStatusRecords' => $service->processStatusRecords ?? [],
            'reviewType' => $service->reviewType,
            'responsibleEmployee' => $service->responsibleEmployee ?? null,
            'systemId' => $service->systemId,
            'reviewTemplateId' => $service->reviewTemplateId,
            'reviewName' => $service->reviewName,
            'timeSpent' => $service->timeSpent,
            'localStatus' => $service->local_status,
            'review' => $service->serviceReview?->only([
                'id',
                'info_usefulness',
                'usability',
                'video_content',
                'video_image',
                'video_sound',
                'video_duration',
                'comment',
                'created_at',
            ]),
            'defects' => $service->defects ?? null,
        ];
    }
}
