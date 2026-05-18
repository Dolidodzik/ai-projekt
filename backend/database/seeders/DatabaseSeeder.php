<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password_hash' => 'password',
                'is_admin' => false,
            ]
        );

        $this->call([
            AdminUserSeeder::class,
            TicketTypeSeeder::class,
            RideHistorySeeder::class,
        ]);
    }
}
