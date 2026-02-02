<?php

namespace Artryazanov\LaravelSteamAppsDb\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Artryazanov\LaravelSteamAppsDb\Models\SteamAppWorkshopItem
 *
 * @property int $id
 * @property int $steam_app_id Reference to the steam_apps table
 * @property int $publishedfileid Workshop Item ID
 * @property string|null $creator Author SteamID64
 * @property string $title Item Title
 * @property string|null $short_description Short description
 * @property string|null $description Full description (HTML)
 * @property string|null $filename Original filename
 * @property int $file_size Size in bytes
 * @property string|null $file_url Direct download URL
 * @property string|null $preview_url Preview Image URL
 * @property string|null $url Steam Workshop Page URL
 * @property array|null $tags Workshop tags (Genre, Type, etc)
 * @property bool $banned Is item banned
 * @property int $views
 * @property int $subscriptions
 * @property int $favorited
 * @property int $num_comments_public
 * @property Carbon|null $time_created
 * @property Carbon|null $time_updated
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SteamApp $steamApp
 *
 * @method static Builder|SteamAppWorkshopItem newModelQuery()
 * @method static Builder|SteamAppWorkshopItem newQuery()
 * @method static Builder|SteamAppWorkshopItem query()
 */
class SteamAppWorkshopItem extends Model
{
    use HasFactory;

    protected $table = 'steam_app_workshop_items';

    protected $fillable = [
        'steam_app_id',
        'publishedfileid',
        'creator',
        'title',
        'short_description',
        'description',
        'filename',
        'file_size',
        'file_url',
        'preview_url',
        'url',
        'tags',
        'banned',
        'views',
        'subscriptions',
        'favorited',
        'num_comments_public',
        'time_created',
        'time_updated',
    ];

    protected $casts = [
        'time_created' => 'datetime',
        'time_updated' => 'datetime',
        'tags' => 'array',
        'banned' => 'boolean',
    ];

    public function steamApp(): BelongsTo
    {
        return $this->belongsTo(SteamApp::class, 'steam_app_id');
    }
}
