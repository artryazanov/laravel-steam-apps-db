<?php

namespace Artryazanov\LaravelSteamAppsDb\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Artryazanov\LaravelSteamAppsDb\Models\SteamAppDeveloper
 *
 * @property int $id
 * @property string $name Name of the developer
 */
class SteamAppDeveloper extends Model
{
    use HasFactory;

    protected $table = 'steam_app_developers';

    protected $fillable = [
        'name',
    ];

    /**
     * Get the Steam apps that belong to this developer.
     */
    public function steamApps(): BelongsToMany
    {
        return $this->belongsToMany(
            SteamApp::class,
            'steam_app_developer',
            'steam_app_developer_id',
            'steam_app_id'
        )->withTimestamps();
    }
}
