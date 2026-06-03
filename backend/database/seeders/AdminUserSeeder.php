<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $name = env('ADMIN_SEED_NAME');
        $email = env('ADMIN_SEED_EMAIL');
        $password = env('ADMIN_SEED_PASSWORD');

        if (! $email || ! $password) {
            throw new RuntimeException('Set ADMIN_SEED_EMAIL and ADMIN_SEED_PASSWORD in .env');
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name ?: 'Admin',
                'password_hash' => Hash::make($password),
                'is_admin' => true,
            ]
        );
    }
}
