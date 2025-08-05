<?php

namespace Artryazanov\LaravelSteamAppsDb\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Artryazanov\LaravelSteamAppsDb\Models\SteamAppRequirement
 *
 * @property int $id
 * @property int $steam_app_id Reference to the steam_apps table
 * @property string $platform Platform for the requirements (pc, mac, linux)
 * @property string|null $minimum Minimum requirements
 * @property string|null $recommended Recommended requirements
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SteamApp $steamApp
 */
class SteamAppRequirement extends Model
{
    use HasFactory;

    protected $table = 'steam_app_requirements';

    protected $fillable = [
        'steam_app_id',
        'platform',
        'minimum',
        'recommended',
    ];

    /**
     * Get the Steam app that this requirement belongs to.
     *
     * @return BelongsTo
     */
    public function steamApp(): BelongsTo
    {
        return $this->belongsTo(SteamApp::class, 'steam_app_id', 'id');
    }
}
