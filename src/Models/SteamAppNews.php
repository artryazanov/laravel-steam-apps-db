<?php

namespace Artryazanov\LaravelSteamAppsDb\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Artryazanov\LaravelSteamAppsDb\Models\SteamAppNews
 *
 * @property int $id
 * @property int $steam_app_id Reference to the steam_apps table
 * @property string $gid News item ID from Steam
 * @property string $title Title of the news item
 * @property string|null $url URL to the news item
 * @property bool $is_external_url Whether the URL is external
 * @property string|null $author Author of the news item
 * @property string|null $contents Contents of the news item
 * @property string|null $feedlabel Feed label
 * @property int|null $date Date of the news item as Unix timestamp
 * @property string|null $feedname Feed name
 * @property int $feed_type Feed type
 * @property array|null $tags Tags associated with the news item
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SteamApp $steamApp
 */
class SteamAppNews extends Model
{
    use HasFactory;

    protected $table = 'steam_app_news';

    protected $fillable = [
        'steam_app_id',
        'gid',
        'title',
        'url',
        'is_external_url',
        'author',
        'contents',
        'feedlabel',
        'date',
        'feedname',
        'feed_type',
        'tags',
    ];

    protected $casts = [
        'is_external_url' => 'boolean',
        'date' => 'integer',
        'feed_type' => 'integer',
        'tags' => 'array',
    ];

    /**
     * Get the Steam app that this news item belongs to.
     *
     * @return BelongsTo
     */
    public function steamApp(): BelongsTo
    {
        return $this->belongsTo(SteamApp::class, 'steam_app_id', 'id');
    }
}
