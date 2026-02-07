<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
