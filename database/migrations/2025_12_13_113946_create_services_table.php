<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        /**
         * SERVICE ORDERS
         */


        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();

            $table->string('order_id')->unique()->index();
            $table->string('public_url')->nullable();

            $table->json('referenceObject');      // referenceObject
            $table->string('siteId')->nullable();
            $table->string('locationCode')->nullable();
            $table->string('reviewCategory')->nullable();
            $table->timestamp('changeTimeStamp')->nullable();

            $table->boolean('closed')->default(false);
            $table->boolean('completed')->default(false);
            $table->timestamp('completionTimeStamp')->nullable();
            $table->timestamp('creationTimestamp')->nullable();

            // ===== Массивы в корне =====
            $table->json('defects')->nullable(); // локальные свои
            $table->json('tasks')->nullable();              // tasks[]
            $table->json('details')->nullable();            // details[]
            $table->json('processStatusRecords')->nullable();

            // ===== Остальные корневые объекты =====
            $table->json('client')->nullable();
            $table->json('carDriver')->nullable();
            $table->json('carOwner')->nullable();
            $table->json('surveyObject')->nullable();
            $table->json('requester')->nullable();
            $table->json('responsibleEmployee')->nullable();

            // ===== Остальное =====
            $table->string('dealerCode')->nullable();
            $table->boolean('hasSurveyRefs')->default(false);
            $table->string('reviewId')->nullable();
            $table->timestamp('visitStartTime')->nullable();
            $table->string('processStatus')->nullable();
            $table->string('reviewType')->nullable();
            $table->string('systemId')->nullable();
            $table->string('reviewTemplateId')->nullable();
            $table->string('reviewName')->nullable();
            $table->integer('timeSpent')->default(0);


            $table->timestamps();
        });


        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained()->onDelete('cascade');
            $table->string('filename'); // Имя файла видео
            $table->string('original_name'); // Оригинальное имя файла
            $table->string('path'); // Путь к файлу
            $table->integer('size')->nullable(); // Размер файла
            $table->string('mime_type')->nullable(); // Тип файла
            $table->integer('order')->default(0); // Порядок отображения
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
        Schema::dropIfExists('service_orders');

    }
};
