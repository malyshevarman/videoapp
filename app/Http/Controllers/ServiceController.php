<?php
namespace App\Http\Controllers;

namespace App\Http\Controllers;
use App\Models\ServiceOrder;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $query = ServiceOrder::select(
            'id',
            'public_url',
            'processStatus',
            'order_id',
            'created_at',
            'client'
        );

        // Проверяем, есть ли поиск
        if ($search = $request->input('table_search')) {
            $query->where('order_id', 'like', "%{$search}%")
                ->orWhereJsonContains('client->firstName', $search)
                ->orWhereJsonContains('client->lastName', $search);
        }

        $orders = $query->orderBy('created_at', 'desc')
            ->paginate(10)
            ->appends(['table_search' => $search]); // сохраняем параметр поиска при пагинации

        return view('admin.services.index', compact('orders'));
    }

    public function edit($id)
    {
        $service = ServiceOrder::findOrFail($id);
        return view('admin.services.edit', compact('service'));
    }

    public function video($id)
    {
        $service = ServiceOrder::findOrFail($id);
        return view('admin.services.video', compact('service'));
    }

    public function info(ServiceOrder $service)
    {
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

    public function update(Request $request, $id)
    {
        $service = Service::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $service->update($request->all());

        return redirect()->route('admin.services.index')
            ->with('success', 'Service updated successfully');
    }

    public function destroy($id)
    {
        $service = Service::findOrFail($id);
        $service->delete();

        return redirect()->route('admin.services.index')
            ->with('success', 'Service deleted successfully');
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
            'defects' => $service->defects ?? null,
        ];
    }
}
