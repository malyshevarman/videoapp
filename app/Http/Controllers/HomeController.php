<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Video;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ServiceOrder;

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
        $service = ServiceOrder::where('public_url', $public_url)->firstOrFail();

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
}
