<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $defaultThemeId = DB::table('themes')->insertGetId([
            'name' => 'Базовая тема',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('dealers', function (Blueprint $table) use ($defaultThemeId) {
            $table->foreignId('theme_id')
                ->default($defaultThemeId)
                ->after('name')
                ->constrained('themes')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dealers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('theme_id');
        });

        Schema::dropIfExists('themes');
    }
};
