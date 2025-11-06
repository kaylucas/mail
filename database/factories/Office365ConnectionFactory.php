<?php

namespace Database\Factories;

use App\Models\Office365Connection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Office365Connection>
 */
class Office365ConnectionFactory extends Factory
{
    protected $model = Office365Connection::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tenant_id' => fake()->uuid(),
            'client_id' => fake()->uuid(),
            'client_secret' => Str::random(40),
            'redirect_uri' => 'https://aery.eu.ngrok.io/auth/microsoft/callback',
            'access_token' => Str::random(200),
            'refresh_token' => Str::random(200),
            'token_expires_at' => now()->addHour(),
            'scopes' => ['Mail.Read', 'offline_access'],
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the connection has an expired token.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'token_expires_at' => now()->subHour(),
        ]);
    }

    /**
     * Indicate that the connection is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the connection is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}
