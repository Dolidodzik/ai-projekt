<?php

// Seeder bazy - u nas dane startowe lecą z backupu (dump), więc tu praktycznie pusto.

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    // Uruchamiany przez `php artisan db:seed` - nic nie seeduje, bo jest dump.
    public function run(): void
    {
        // Dane startowe pochodzą z backupu: backend/database/backups/ai2_projekt.dump
    }
}
