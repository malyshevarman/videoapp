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
        $items = $defects->map(function ($defect) use ($detailsByTaskId, $images) {
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
        $service = ServiceOrder::where('public_url', $public_url)->firstOrFail();

        $items = collect($request->input('items', []))
            ->filter(fn($i) => isset($i['id'])) // taskId
            ->keyBy(fn($i) => (string)$i['id']);

        $details = $service->details ?? [];
        if (!is_array($details)) $details = [];

        $zeroVariantMoney = function (&$variant) {
            // обнуляем approvedPrice*
            $variant['approvedPriceExVat']  = 0;
            $variant['approvedPriceIncVat'] = 0;

            // обнуляем позиции в details (если есть)
            if (!isset($variant['details']) || !is_array($variant['details'])) return;

            foreach ($variant['details'] as &$d) {
                if (is_array($d)) {
                    $d['positionAmountExVat']  = 0;
                    $d['positionAmountIncVat'] = 0;
                }
            }
            unset($d);
        };

        DB::transaction(function () use (&$details, $items, $zeroVariantMoney) {
            foreach ($details as &$detail) {
                $taskId = (string)($detail['taskId'] ?? '');
                if ($taskId === '' || !$items->has($taskId)) continue;

                $incoming = $items->get($taskId);

                // проверяем путь до variants
                if (
                    !isset($detail['answers'][0]['packages'][0]['variants']) ||
                    !is_array($detail['answers'][0]['packages'][0]['variants'])
                ) {
                    continue;
                }

                $variants =& $detail['answers'][0]['packages'][0]['variants'];

                $targetVariantId = $incoming['variantId'] ?? null;

                // найдём индекс целевого варианта
                $targetIndex = null;
                foreach ($variants as $idx => $v) {
                    if ($targetVariantId !== null && (string)($v['id'] ?? '') === (string)$targetVariantId) {
                        $targetIndex = $idx;
                        break;
                    }
                }
                // если variantId не передали — обновим первый (как в showservices())
                if ($targetIndex === null) $targetIndex = 0;

                $status = $incoming['customerApproved'] ?? null; // expected: approved | deferred | cancelled/rejected

                // 1) Проставляем customerApproved на целевом варианте (если пришёл)
                if ($status !== null) {
                    $variants[$targetIndex]['customerApproved'] = $status;
                }

                // 2) Логика по статусам
                if ($status === 'approved') {
                    // approved: убираем deferredTaskDate
                    $variants[$targetIndex]['deferredTaskDate'] = null;
                    // цены и позиции НЕ трогаем
                }

                if ($status === 'deferred') {
                    $date = $incoming['deferredTaskDate'] ?? null;

                    // у всех остальных вариантов этого taskId deferredTaskDate = null
                    foreach ($variants as $i => &$v) {
                        if ($i !== $targetIndex) {
                            $v['deferredTaskDate'] = null;
                        }
                    }
                    unset($v);

                    // целевому ставим дату
                    $variants[$targetIndex]['deferredTaskDate'] = $date;

                    // и обнуляем деньги/позиции у целевого варианта
                    $zeroVariantMoney($variants[$targetIndex]);
                }

                if ($status === 'cancelled' || $status === 'canceled' || $status === 'rejected') {
                    // отменено: deferredTaskDate = null
                    $variants[$targetIndex]['deferredTaskDate'] = null;

                    // обнуляем деньги/позиции
                    $zeroVariantMoney($variants[$targetIndex]);
                }
            }
            unset($detail);
        });

        $records = $service->processStatusRecords ?? [];
        if (!is_array($records)) {
            $records = [];
        }
        $exists = collect($records)->contains(fn ($r) =>
            ($r['status'] ?? null) === 'customerDecisionRecorded'
        );

        if (!$exists) {
            $records[] = [
                'id' => (string) Str::uuid(),
                'status' => 'customerDecisionRecorded',
                'timestamp' => now()->toISOString(),
            ];

            $service->processStatusRecords = $records;
            $service->processStatus = 'customerDecisionRecorded';
            $service->save();
        }

        $service->details = $details;
        $service->completed=true;
        $service->save();

        return response()->json(['success' => true]);
    }
}
