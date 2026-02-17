<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('service_orders', 'mechanic_id') && !Schema::hasColumn('service_orders', 'user_id')) {
            DB::statement('ALTER TABLE service_orders RENAME COLUMN mechanic_id TO user_id');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('service_orders', 'user_id') && !Schema::hasColumn('service_orders', 'mechanic_id')) {
            DB::statement('ALTER TABLE service_orders RENAME COLUMN user_id TO mechanic_id');
        }
    }
};
