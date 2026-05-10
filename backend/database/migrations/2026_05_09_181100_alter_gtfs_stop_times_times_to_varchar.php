<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE gtfs_stop_times ALTER COLUMN arrival_time TYPE VARCHAR(12) USING arrival_time::text');
            DB::statement('ALTER TABLE gtfs_stop_times ALTER COLUMN departure_time TYPE VARCHAR(12) USING departure_time::text');
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE gtfs_stop_times MODIFY arrival_time VARCHAR(12) NOT NULL');
            DB::statement('ALTER TABLE gtfs_stop_times MODIFY departure_time VARCHAR(12) NOT NULL');
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE gtfs_stop_times ALTER COLUMN arrival_time TYPE TIME USING arrival_time::time');
            DB::statement('ALTER TABLE gtfs_stop_times ALTER COLUMN departure_time TYPE TIME USING departure_time::time');
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE gtfs_stop_times MODIFY arrival_time TIME NOT NULL');
            DB::statement('ALTER TABLE gtfs_stop_times MODIFY departure_time TIME NOT NULL');
        }
    }
};
