<?php

namespace Artryazanov\LaravelSteamAppsDb\Database\Factories;

use Artryazanov\LaravelSteamAppsDb\Models\SteamApp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SteamApp>
 */
class SteamAppFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SteamApp::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'appid' => $this->faker->unique()->numberBetween(1, 1000000),
            'name' => $this->faker->unique()->words(3, true),
        ];
    }
}
