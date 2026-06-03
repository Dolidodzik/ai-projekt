<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Validation\ValidationException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $name = env('ADMIN_SEED_NAME');
        $email = env('ADMIN_SEED_EMAIL');
        $password = env('ADMIN_SEED_PASSWORD');

        if (! is_string($email) || $email === '') {
            throw ValidationException::withMessages([
                'ADMIN_SEED_EMAIL' => ['Ustaw ADMIN_SEED_EMAIL w pliku .env (katalog glowny projektu).'],
            ]);
        }

        if (! is_string($password) || $password === '') {
            throw ValidationException::withMessages([
                'ADMIN_SEED_PASSWORD' => ['Ustaw ADMIN_SEED_PASSWORD w pliku .env (katalog glowny projektu).'],
            ]);
        }

        if (strlen($password) < 8) {
            throw ValidationException::withMessages([
                'ADMIN_SEED_PASSWORD' => ['Haslo admina musi miec co najmniej 8 znakow.'],
            ]);
        }

        $user = User::updateOrCreate(
            ['email' => strtolower($email)],
            [
                'name' => is_string($name) && $name !== '' ? $name : 'Admin',
                'password_hash' => $password,
            ]
        );

        if (! $user->is_admin) {
            $user->forceFill(['is_admin' => true])->save();
        }
    }
}
