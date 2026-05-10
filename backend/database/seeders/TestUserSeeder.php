<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        $name = (string) config('seed.user.name');
        $email = (string) config('seed.user.email');
        $password = (string) config('seed.user.password');

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name !== '' ? $name : 'User',
                'password_hash' => $password,
                'is_admin' => false,
            ]
        );
    }
}
