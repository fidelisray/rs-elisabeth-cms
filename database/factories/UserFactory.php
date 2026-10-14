<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'                 => fake()->name(),
            'email'                => fake()->unique()->safeEmail(),
            'email_verified_at'    => now(),
            'password'             => static::$password ??= Hash::make('123456'),
            'role'                 => 'staff',
            'must_change_password' => true,
            'remember_token'       => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Akun Super Admin.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'name'                 => 'Super Admin',
            'email'                => 'admin@rs-elisabeth.com',
            'password'             => Hash::make('admin@123456'),
            'role'                 => 'super_admin',
            'must_change_password' => false,
        ]);
    }

    /**
     * Akun Staf Marketing.
     */
    public function marketing(): static
    {
        return $this->state(fn (array $attributes) => [
            'name'                 => 'Staf Marketing',
            'email'                => 'marketing@rs-elisabeth.com',
            'password'             => Hash::make('123456'),
            'role'                 => 'staff',
            'must_change_password' => true,
        ]);
    }

    /**
     * Akun Staf IT.
     */
    public function it(): static
    {
        return $this->state(fn (array $attributes) => [
            'name'                 => 'Staff IT',
            'email'                => 'it@rs-elisabeth.com',
            'password'             => Hash::make('123456'),
            'role'                 => 'staff',
            'must_change_password' => true,
        ]);
    }
}
