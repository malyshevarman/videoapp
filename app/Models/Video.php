<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Video extends Model
{
    protected $fillable = [
        'service_order_id',
        'filename',
        'original_name',
        'path',
        'size',
        'mime_type',
        'order',
        'timecodes',
    ];

    /**
     * Связь с заказом
     */
    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    /**
     * Удаление файла при удалении модели
     */
    protected static function booted()
    {
        static::deleting(function (Video $video) {
            if (!$video->path) {
                return;
            }

            // путь относительно корня диска — просто $video->path
            if (Storage::disk('videos')->exists($video->path)) {
                Storage::disk('videos')->delete($video->path);
            }

            // 2️⃣ Удаляем кадры из ServiceOrder через Spatie MediaLibrary
            $serviceOrder = $video->serviceOrder;
            if ($serviceOrder) {
                $serviceOrder->clearMediaCollection('frames');
            }
        });
    }

}
