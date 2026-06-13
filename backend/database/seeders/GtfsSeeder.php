<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class GtfsSeeder extends Seeder
{
    public function run(): void
    {
        try {
            Artisan::call('gtfs:sync');
        } catch (\Throwable $e) {
            $this->command?->warn('GTFS sync skipped: '.$e->getMessage());
        }
    }
}
