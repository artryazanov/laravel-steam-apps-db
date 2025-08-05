<?php

namespace Artryazanov\LaravelSteamAppsDb\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Artryazanov\LaravelSteamAppsDb\Models\SteamAppPublisher
 *
 * @property int $id
 * @property string $name Name of the publisher
 */
class SteamAppPublisher extends Model
{
    use HasFactory;

    protected $table = 'steam_app_publishers';

    protected $fillable = [
        'name',
    ];

    /**
     * Get the Steam apps that belong to this publisher.
     *
     * @return BelongsToMany
     */
    public function steamApps(): BelongsToMany
    {
        return $this->belongsToMany(
            SteamApp::class,
            'steam_app_publisher',
            'steam_app_publisher_id',
            'steam_app_id'
        )->withTimestamps();
    }
}
