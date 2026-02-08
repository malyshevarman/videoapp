<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ServiceOrder extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        // идентификаторы
        'order_id',
        'public_url',

        'siteId',
        'locationCode',
        'reviewCategory',
        'changeTimeStamp',

        'closed',
        'completed',
        'completionTimeStamp',
        'creationTimestamp',

        'dealerCode',
        'hasSurveyRefs',
        'reviewId',

        'visitStartTime',
        'processStatus',
        'reviewType',
        'systemId',

        'reviewTemplateId',
        'reviewName',
        'timeSpent',

        // корневые json-объекты/массивы
        'referenceObject',
        'tasks',
        'details',
        'processStatusRecords',

        'client',
        'carDriver',
        'carOwner',
        'surveyObject',
        'requester',
        'responsibleEmployee',
        // локальные дефекты
        'defects',
        'mechanic_id'
    ];

    protected $casts = [
        'changeTimeStamp' => 'datetime',
        'completionTimeStamp' => 'datetime',
        'creationTimestamp' => 'datetime',
        'visitStartTime' => 'datetime',

        // булевы
        'closed' => 'boolean',
        'completed' => 'boolean',
        'hasSurveyRefs' => 'boolean',

        // json
        'referenceObject' => 'array',
        'tasks' => 'array',
        'details' => 'array',
        'processStatusRecords' => 'array',

        'client' => 'array',
        'carDriver' => 'array',
        'carOwner' => 'array',
        'surveyObject' => 'array',
        'requester' => 'array',
        'responsibleEmployee' => 'array',

        'defects' => 'array',
    ];


    // ===== Связи =====
    public function video(): HasOne
    {
        return $this->hasOne(Video::class);
    }

    // ===== Генерация public_url =====
    protected static function booted()
    {
        static::creating(function (ServiceOrder $order) {
            if (!$order->public_url) {
                do {
                    $order->public_url = Str::random(19);
                } while (
                    self::where('public_url', $order->public_url)->exists()
                );
            }
        });
    }

    public function serviceReview()
    {
        return $this->hasOne(ServiceOrderReview::class);
    }

    // ===== Настройка коллекций MediaLibrary =====
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('frames')
            ->useDisk('public'); // или другой диск, если нужно
    }

    public function mechanic()
    {
        return $this->belongsTo(User::class, 'mechanic_id');
    }
}
