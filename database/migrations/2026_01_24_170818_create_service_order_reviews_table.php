<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('service_order_reviews', function (Blueprint $table) {
            $table->id();

            // связь с заказом (один отзыв на заказ)
            $table->unsignedBigInteger('order_id')->unique();

            // сервис
            $table->tinyInteger('info_usefulness')->nullable(); // полезность информации
            $table->tinyInteger('usability')->nullable();        // удобство использования

            // видео
            $table->tinyInteger('video_content')->nullable();    // содержание
            $table->tinyInteger('video_image')->nullable();      // изображение
            $table->tinyInteger('video_sound')->nullable();      // звук
            $table->tinyInteger('video_duration')->nullable();   // длительность

            // комментарий
            $table->text('comment')->nullable();

            $table->timestamps();

            // если есть таблица orders
            $table->foreign('order_id')
                ->references('id')
                ->on('service_orders')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_order_reviews');
    }
};
