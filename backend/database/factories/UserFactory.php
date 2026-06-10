<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => 'password',
        ];
    }

    public function admin(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->forceFill(['is_admin' => true])->save();
        });
    }
}
