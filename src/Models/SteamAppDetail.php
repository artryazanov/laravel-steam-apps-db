<?php

namespace Artryazanov\LaravelSteamAppsDb\Models;

use Artryazanov\LaravelSteamAppsDb\Database\Factories\SteamAppDetailFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Artryazanov\LaravelSteamAppsDb\Models\SteamAppDetail
 *
 * @property int $id
 * @property int $steam_app_id Reference to the steam_apps table
 * @property string|null $type Type of the application (game, dlc, etc.)
 * @property string $name Name of the game
 * @property int $required_age Required age to play the game
 * @property bool $is_free Whether the game is free to play
 * @property string|null $detailed_description Detailed description of the game
 * @property string|null $about_the_game About the game text
 * @property string|null $short_description Short description of the game
 * @property string|null $supported_languages Supported languages
 * @property string|null $header_image URL to the header image
 * @property string|null $capsule_image URL to the capsule image
 * @property string|null $capsule_imagev5 URL to the capsule image v5
 * @property string|null $website Game website URL
 * @property string|null $legal_notice Legal notice
 * @property bool $windows Whether the game is available on Windows
 * @property bool $mac Whether the game is available on Mac
 * @property bool $linux Whether the game is available on Linux
 * @property string|null $background URL to the background image
 * @property string|null $background_raw URL to the raw background image
 * @property Carbon|null $release_date Release date of the game
 * @property bool $coming_soon Whether the game is coming soon
 * @property string|null $support_url Support URL
 * @property string|null $support_email Support email
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SteamApp $steamApp
 */
class SteamAppDetail extends Model
{
    use HasFactory;

    protected $table = 'steam_app_details';

    protected $fillable = [
        'steam_app_id',
        'type',
        'name',
        'required_age',
        'is_free',
        'detailed_description',
        'about_the_game',
        'short_description',
        'supported_languages',
        'header_image',
        'capsule_image',
        'capsule_imagev5',
        'website',
        'legal_notice',
        'windows',
        'mac',
        'linux',
        'background',
        'background_raw',
        'release_date',
        'coming_soon',
        'support_url',
        'support_email',
    ];

    protected $casts = [
        'required_age' => 'integer',
        'is_free' => 'boolean',
        'windows' => 'boolean',
        'mac' => 'boolean',
        'linux' => 'boolean',
        'coming_soon' => 'boolean',
        'release_date' => 'date',
    ];

    /**
     * Create a new factory instance for the model.
     *
     * @return Factory
     */
    protected static function newFactory(): Factory
    {
        return SteamAppDetailFactory::new();
    }

    /**
     * Get the Steam app that this detail belongs to.
     *
     * @return BelongsTo
     */
    public function steamApp(): BelongsTo
    {
        return $this->belongsTo(SteamApp::class, 'steam_app_id', 'id');
    }
}
