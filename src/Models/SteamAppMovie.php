<?php

namespace Artryazanov\LaravelSteamAppsDb\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Artryazanov\LaravelSteamAppsDb\Models\SteamAppMovie
 *
 * @property int $id
 * @property int $steam_app_id Reference to the steam_apps table
 * @property int $movie_id Movie ID from Steam
 * @property string|null $name Name of the movie
 * @property string|null $thumbnail URL to the thumbnail image
 * @property string|null $webm_480 URL to the 480p WebM video
 * @property string|null $webm_max URL to the max quality WebM video
 * @property string|null $mp4_480 URL to the 480p MP4 video
 * @property string|null $mp4_max URL to the max quality MP4 video
 * @property bool $highlight Whether this is a highlight video
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read SteamAppDetail $steamAppDetail
 */
class SteamAppMovie extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'steam_app_movies';

    protected $fillable = [
        'steam_app_id',
        'movie_id',
        'name',
        'thumbnail',
        'webm_480',
        'webm_max',
        'mp4_480',
        'mp4_max',
        'highlight',
    ];

    protected $casts = [
        'highlight' => 'boolean',
    ];

    /**
     * Get the Steam app that this movie belongs to.
     *
     * @return BelongsTo
     */
    public function steamApp(): BelongsTo
    {
        return $this->belongsTo(SteamApp::class, 'steam_app_id', 'id');
    }
}
