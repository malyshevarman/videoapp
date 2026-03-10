<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceOrderReview extends Model
{
    protected $fillable = [
        'order_id',
        'info_usefulness',
        'usability',
        'video_content',
        'video_image',
        'video_sound',
        'video_duration',
        'comment',
    ];

    /**
     * Один отзыв принадлежит одному заказу
     */
    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class, 'order_id');
    }

    public function order(): BelongsTo
    {
        return $this->serviceOrder();
    }
}
