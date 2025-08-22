<?php

namespace Artryazanov\LaravelSteamAppsDb\Database\Factories;

use Artryazanov\LaravelSteamAppsDb\Models\SteamApp;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SteamAppDetail>
 */
class SteamAppDetailFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SteamAppDetail::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'steam_app_id' => SteamApp::factory(),
            'type' => $this->faker->randomElement(['game', 'dlc', 'demo', 'application']),
            'name' => $this->faker->words(3, true),
            'required_age' => $this->faker->numberBetween(0, 18),
            'is_free' => $this->faker->boolean,
            'detailed_description' => $this->faker->paragraphs(3, true),
            'about_the_game' => $this->faker->paragraphs(2, true),
            'short_description' => $this->faker->sentence,
            'supported_languages' => 'English, French, German',
            'header_image' => $this->faker->imageUrl(460, 215),
            'library_image' => $this->faker->optional()->imageUrl(600, 900),
            'capsule_image' => $this->faker->imageUrl(231, 87),
            'capsule_imagev5' => $this->faker->imageUrl(184, 69),
            'website' => $this->faker->url,
            'legal_notice' => $this->faker->paragraph,
            'windows' => $this->faker->boolean(90), // 90% chance of being true
            'mac' => $this->faker->boolean(50),
            'linux' => $this->faker->boolean(30),
            'background' => $this->faker->imageUrl(1920, 1080),
            'background_raw' => $this->faker->imageUrl(1920, 1080),
            'release_date' => $this->faker->dateTimeBetween('-5 years', '+1 year'),
            'coming_soon' => $this->faker->boolean(20),
            'support_url' => $this->faker->url,
            'support_email' => $this->faker->email,
        ];
    }
}
