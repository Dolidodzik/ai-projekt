<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class GtfsSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('gtfs:sync');
    }
}
