<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Video;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ServiceOrder;
use Illuminate\Support\Facades\DB;

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
        $service = ServiceOrder::where('public_url', $public_url)->with('mechanic')->firstOrFail();

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

        // 1) details: оставляем только где answers есть и не пустые
        $filteredDetails = $details->filter(function ($detail) {
            return isset($detail['answers'])
                && is_array($detail['answers'])
                && count($detail['answers']) > 0;
        });

        // 2) индексируем по taskId (как строка)
        $detailsByTaskId = $filteredDetails->keyBy(fn ($d) => (string) ($d['taskId'] ?? ''));

        $images = $service->getMedia('frames');

        // 3) собираем items
        $items = $defects
            ->filter(function ($defect) use ($detailsByTaskId) {
                $id = (string) ($defect['id'] ?? '');
                return $id !== '' && $detailsByTaskId->has($id);
            })
            ->map(function ($defect) use ($detailsByTaskId, $images) {
            $id = (string) ($defect['id'] ?? '');
            $detail = $detailsByTaskId->get($id);

            $imageForDefect = optional(
                $images->firstWhere('custom_properties.taskId', $id)
            )->getUrl();

            $answers = (is_array($detail) && isset($detail['answers']) && is_array($detail['answers']))
                ? $detail['answers']
                : [];

            $answer = $answers[0] ?? null;

            $package = (is_array($answer) && isset($answer['packages'][0]) && is_array($answer['packages'][0]))
                ? $answer['packages'][0]
                : null;

            $variant = (is_array($package) && isset($package['variants'][0]) && is_array($package['variants'][0]))
                ? $package['variants'][0]
                : null;

            return [
                // дефект
                'id'    => (int) ($defect['id'] ?? 0),
                'time'  => (int) ($defect['time'] ?? 0),
                'title' => $defect['title'] ?? null,
                'image' => $imageForDefect,

                // ===== answer =====
                'answerId'     => $answer['id'] ?? null,
                'answerCustom' => $answer['custom'] ?? null,
                'answerStatus' => $answer['status'] ?? null,
                'answerValue'  => $answer['value'] ?? null,

                // ===== package =====
                'packageId'          => $package['id'] ?? null,
                'packageCategory'    => $package['category'] ?? null,
                'currencyCode'       => $package['currencyCode'] ?? null,
                'packageDescription' => $package['description'] ?? null,
                'variantId'              => $variant['id'] ?? null,
                'variantDescription'     => $variant['description'] ?? null,
                'customerApproved'       => $variant['customerApproved'] ?? null,
                'deferredTaskDate'       => $variant['deferredTaskDate'] ?? null,
                'selected'               => $variant['selected'] ?? null,
                'approvedPriceExVat'     => (float) ($variant['approvedPriceExVat'] ?? 0),
                'approvedPriceIncVat'    => (float) ($variant['approvedPriceIncVat'] ?? 0),

                'details' => $variant['details'] ?? [],
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
            'items.*.customerApproved' => 'required|string|in:approved,deferred,rejected,cancelled,canceled,callback',
            'items.*.deferredTaskDate' => 'nullable|date',
        ]);

        $result = DB::transaction(function () use ($public_url, $validated) {
            $service = ServiceOrder::where('public_url', $public_url)
                ->lockForUpdate()
                ->firstOrFail();

            $items = collect($validated['items'])
                ->filter(fn ($i) => isset($i['id']))
                ->keyBy(fn ($i) => (string) $i['id']);

            $details = is_array($service->details) ? $service->details : [];

            $zeroVariantMoney = function (&$variant) {
                $variant['approvedPriceExVat'] = 0;
                $variant['approvedPriceIncVat'] = 0;

                if (!isset($variant['details']) || !is_array($variant['details'])) {
                    return;
                }

                foreach ($variant['details'] as &$d) {
                    if (!is_array($d)) {
                        continue;
                    }

                    $d['positionAmountExVat'] = 0;
                    $d['positionAmountIncVat'] = 0;
                }
                unset($d);
            };

            foreach ($details as &$detail) {
                $taskId = (string) ($detail['taskId'] ?? '');
                if ($taskId === '' || !$items->has($taskId)) {
                    continue;
                }

                $incoming = $items->get($taskId);
                $variants = data_get($detail, 'answers.0.packages.0.variants', []);
                if (!is_array($variants) || empty($variants)) {
                    continue;
                }

                $targetVariantId = $incoming['variantId'] ?? null;
                $targetIndex = null;

                foreach ($variants as $idx => $v) {
                    if ($targetVariantId !== null && (string) ($v['id'] ?? '') === (string) $targetVariantId) {
                        $targetIndex = $idx;
                        break;
                    }
                }

                if ($targetIndex === null) {
                    $targetIndex = 0;
                }

                $status = $incoming['customerApproved'] ?? null;
                if ($status !== null) {
                    $variants[$targetIndex]['customerApproved'] = $status;
                }

                if ($status === 'approved') {
                    $variants[$targetIndex]['deferredTaskDate'] = null;
                }

                if ($status === 'deferred') {
                    $date = $incoming['deferredTaskDate'] ?? null;

                    foreach ($variants as $i => &$v) {
                        if ($i !== $targetIndex) {
                            $v['deferredTaskDate'] = null;
                        }
                    }
                    unset($v);

                    $variants[$targetIndex]['deferredTaskDate'] = $date;
                    $zeroVariantMoney($variants[$targetIndex]);
                }

                if ($status === 'cancelled' || $status === 'canceled' || $status === 'rejected') {
                    $variants[$targetIndex]['deferredTaskDate'] = null;
                    $zeroVariantMoney($variants[$targetIndex]);
                }

                data_set($detail, 'answers.0.packages.0.variants', $variants);
            }
            unset($detail);

            $sumEx = 0;
            $sumInc = 0;

            foreach ($details as $detail) {
                $variants = data_get($detail, 'answers.0.packages.0.variants', []);
                if (!is_array($variants)) {
                    continue;
                }

                foreach ($variants as $variant) {
                    if (($variant['customerApproved'] ?? null) !== 'approved') {
                        continue;
                    }

                    $vDetails = $variant['details'] ?? [];
                    if (!is_array($vDetails)) {
                        continue;
                    }

                    foreach ($vDetails as $pos) {
                        if (!is_array($pos)) {
                            continue;
                        }

                        $sumEx += (float) ($pos['positionAmountExVat'] ?? 0);
                        $sumInc += (float) ($pos['positionAmountIncVat'] ?? 0);
                    }
                }
            }

            $sumEx = round($sumEx, 2);
            $sumInc = round($sumInc, 2);

            $records = is_array($service->processStatusRecords) ? $service->processStatusRecords : [];
            $exists = collect($records)->contains(fn ($r) => ($r['status'] ?? null) === 'customerDecisionRecorded');
            if (!$exists) {
                $records[] = [
                    'id' => (string) Str::uuid(),
                    'status' => 'customerDecisionRecorded',
                    'timestamp' => now()->toISOString(),
                ];
            }

            $referenceObject = is_array($service->referenceObject) ? $service->referenceObject : [];
            $referenceObject['orderAmountExVat'] = $sumEx;
            $referenceObject['orderAmountIncVat'] = $sumInc;

            $finalStatuses = ['approved', 'deferred', 'rejected', 'cancelled', 'canceled'];
            $completed = $items->isNotEmpty()
                && $items->every(fn ($item) => in_array(($item['customerApproved'] ?? null), $finalStatuses, true));

            $service->details = $details;
            $service->processStatusRecords = $records;
            $service->processStatus = 'customerDecisionRecorded';
            $service->referenceObject = $referenceObject;
            $service->completed = $completed;
            $service->save();

            return [
                'success' => true,
                'completed' => $completed,
                'orderAmountExVat' => $sumEx,
                'orderAmountIncVat' => $sumInc,
            ];
        });

        return response()->json($result);
    }
}
