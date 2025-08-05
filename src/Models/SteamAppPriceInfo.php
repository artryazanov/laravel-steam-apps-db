<?php

namespace Artryazanov\LaravelSteamAppsDb\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Artryazanov\LaravelSteamAppsDb\Models\SteamAppPriceInfo
 *
 * @property int $id
 * @property int $steam_app_id Reference to the steam_apps table
 * @property string|null $currency Currency code (e.g., USD, EUR)
 * @property int|null $initial Initial price in the smallest currency unit (e.g., cents)
 * @property int|null $final Final price after discount in the smallest currency unit
 * @property int $discount_percent Discount percentage
 * @property string|null $initial_formatted Formatted initial price string
 * @property string|null $final_formatted Formatted final price string
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SteamAppDetail $steamAppDetail
 */
class SteamAppPriceInfo extends Model
{
    use HasFactory;

    protected $table = 'steam_app_price_info';

    protected $fillable = [
        'steam_app_id',
        'currency',
        'initial',
        'final',
        'discount_percent',
        'initial_formatted',
        'final_formatted',
    ];

    protected $casts = [
        'initial' => 'integer',
        'final' => 'integer',
        'discount_percent' => 'integer',
    ];

    /**
     * Get the Steam app that this price info belongs to.
     *
     * @return BelongsTo
     */
    public function steamApp(): BelongsTo
    {
        return $this->belongsTo(SteamApp::class, 'steam_app_id', 'id');
    }
}
