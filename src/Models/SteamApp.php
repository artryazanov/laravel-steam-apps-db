<?php

namespace Artryazanov\LaravelSteamAppsDb\Models;

use Artryazanov\LaravelSteamAppsDb\Database\Factories\SteamAppFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Artryazanov\LaravelSteamAppsDb\Models\SteamApp
 *
 * @property int $id
 * @property int $appid Steam application ID
 * @property string $name Steam application name
 * @property Carbon|null $last_details_update When the detailed data was last loaded from Steam
 * @property Carbon|null $last_news_update When the news data was last loaded from Steam
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SteamAppDetail|null $detail
 * @property-read SteamAppRequirement[] $requirements
 * @property-read SteamAppScreenshot[] $screenshots
 * @property-read SteamAppMovie[] $movies
 * @property-read SteamAppCategory[] $categories
 * @property-read SteamAppGenre[] $genres
 * @property-read SteamAppDeveloper[] $developers
 * @property-read SteamAppPublisher[] $publishers
 * @property-read SteamAppPriceInfo|null $priceInfo
 *
 * @method static Builder|SteamApp newModelQuery()
 * @method static Builder|SteamApp newQuery()
 * @method static Builder|SteamApp query()
 */
class SteamApp extends Model
{
    use HasFactory;

    protected $table = 'steam_apps';

    protected $fillable = [
        'appid',
        'name',
        'last_details_update',
        'last_news_update',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return SteamAppFactory::new();
    }

    protected $casts = [
        'last_details_update' => 'datetime',
        'last_news_update' => 'datetime',
    ];

    /**
     * Get the detailed Steam app information for this app.
     */
    public function detail(): HasOne
    {
        return $this->hasOne(SteamAppDetail::class, 'steam_app_id', 'id');
    }

    /**
     * Get the requirements for this app.
     */
    public function requirements(): HasMany
    {
        return $this->hasMany(SteamAppRequirement::class, 'steam_app_id', 'id');
    }

    /**
     * Get the screenshots for this app.
     */
    public function screenshots(): HasMany
    {
        return $this->hasMany(SteamAppScreenshot::class, 'steam_app_id', 'id');
    }

    /**
     * Get the movies for this app.
     */
    public function movies(): HasMany
    {
        return $this->hasMany(SteamAppMovie::class, 'steam_app_id', 'id');
    }

    /**
     * Get the categories for this app.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            SteamAppCategory::class,
            'steam_app_category',
            'steam_app_id',
            'steam_app_category_id'
        )->withTimestamps();
    }

    /**
     * Get the genres for this app.
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(
            SteamAppGenre::class,
            'steam_app_genre',
            'steam_app_id',
            'steam_app_genre_id'
        )->withTimestamps();
    }

    /**
     * Get the developers for this app.
     */
    public function developers(): BelongsToMany
    {
        return $this->belongsToMany(
            SteamAppDeveloper::class,
            'steam_app_developer',
            'steam_app_id',
            'steam_app_developer_id'
        )->withTimestamps();
    }

    /**
     * Get the publishers for this app.
     */
    public function publishers(): BelongsToMany
    {
        return $this->belongsToMany(
            SteamAppPublisher::class,
            'steam_app_publisher',
            'steam_app_id',
            'steam_app_publisher_id'
        )->withTimestamps();
    }

    /**
     * Get the price information for this app.
     */
    public function priceInfo(): HasOne
    {
        return $this->hasOne(SteamAppPriceInfo::class, 'steam_app_id', 'id');
    }

    /**
     * Get the news items for this app.
     */
    public function news(): HasMany
    {
        return $this->hasMany(SteamAppNews::class, 'steam_app_id', 'id');
    }
}
