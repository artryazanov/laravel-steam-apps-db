<?php

namespace Artryazanov\LaravelSteamAppsDb\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Artryazanov\LaravelSteamAppsDb\Models\SteamAppScreenshot
 *
 * @property int $id
 * @property int $steam_app_id Reference to the steam_apps table
 * @property int $screenshot_id Screenshot ID from Steam
 * @property string|null $path_thumbnail URL to the thumbnail image
 * @property string|null $path_full URL to the full-size image
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read SteamApp $steamApp
 */
class SteamAppScreenshot extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'steam_app_screenshots';

    protected $fillable = [
        'steam_app_id',
        'screenshot_id',
        'path_thumbnail',
        'path_full',
    ];

    /**
     * Get the Steam app that this screenshot belongs to.
     *
     * @return BelongsTo
     */
    public function steamApp(): BelongsTo
    {
        return $this->belongsTo(SteamApp::class, 'steam_app_id', 'id');
    }
}
