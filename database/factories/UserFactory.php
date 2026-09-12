<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'role' => UserRole::Contributor,
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (): array => ['role' => UserRole::Owner]);
    }

    public function editor(): static
    {
        return $this->state(fn (): array => ['role' => UserRole::Editor]);
    }
}
