<?php

// Migracja: arrival_time i departure_time w gtfs_stop_times jako VARCHAR(12).
// GTFS potrafi mieć godziny > 24:00 (np. 25:30), więc zwykły TIME w bazie nie wystarcza.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Zmienia kolumny czasu na VARCHAR - działa na Postgresie i MySQL/MariaDB.
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

    // Przywraca typ TIME (gdyby ktoś robił rollback).
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
