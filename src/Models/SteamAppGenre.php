<?php

namespace Artryazanov\LaravelSteamAppsDb\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Artryazanov\LaravelSteamAppsDb\Models\SteamAppGenre
 *
 * @property int $id
 * @property string $genre_id Genre ID from Steam
 * @property string $description Description of the genre
 */
class SteamAppGenre extends Model
{
    use HasFactory;

    protected $table = 'steam_app_genres';

    protected $fillable = [
        'genre_id',
        'description',
    ];

    /**
     * Get the Steam apps that belong to this genre.
     *
     * @return BelongsToMany
     */
    public function steamApps(): BelongsToMany
    {
        return $this->belongsToMany(
            SteamApp::class,
            'steam_app_genre',
            'steam_app_genre_id',
            'steam_app_id'
        )->withTimestamps();
    }
}
