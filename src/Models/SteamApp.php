<?php

declare(strict_types=1);

namespace Artryazanov\LaravelSteamAppsDb\Models;

use Artryazanov\LaravelSteamAppsDb\Database\Factories\SteamAppFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppWorkshopItem;

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
 * @property-read SteamAppDetail|null $detail The detailed Steam app information for this app
 * @property-read SteamAppRequirement[] $requirements The requirements for this app
 * @property-read SteamAppScreenshot[] $screenshots The screenshots for this app
 * @property-read SteamAppMovie[] $movies The movies for this app
 * @property-read SteamAppCategory[] $categories The categories for this app
 * @property-read SteamAppGenre[] $genres The genres for this app
 * @property-read SteamAppDeveloper[] $developers The developers for this app
 * @property-read SteamAppPublisher[] $publishers The publishers for this app
 * @property-read SteamAppPriceInfo|null $priceInfo The price information for this app
 * @property-read SteamAppNews[] $news The news items for this app
 * @property-read SteamAppWorkshopItem[] $workshopItems The workshop items for this app
 * @property-read string $headerImage URL to the header image of the Steam application
 * @property-read string $steamAppUrl URL to the Steam store page of the application
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

    public function dlcs(): HasMany
    {
        return $this->hasMany(SteamAppDlc::class, 'steam_app_id', 'id');
    }

    public function demos(): HasMany
    {
        return $this->hasMany(SteamAppDemo::class, 'steam_app_id', 'id');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(SteamAppPackage::class, 'steam_app_id', 'id');
    }

    public function packageGroups(): HasMany
    {
        return $this->hasMany(SteamAppPackageGroup::class, 'steam_app_id', 'id');
    }

    public function achievementsHighlighted(): HasMany
    {
        return $this->hasMany(SteamAppAchievementHighlighted::class, 'steam_app_id', 'id');
    }

    public function contentDescriptorIds(): HasMany
    {
        return $this->hasMany(SteamAppContentDescriptorId::class, 'steam_app_id', 'id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(SteamAppRating::class, 'steam_app_id', 'id');
    }

    /**
     * Get the news items for this app.
     */
    public function news(): HasMany
    {
        return $this->hasMany(SteamAppNews::class, 'steam_app_id', 'id');
    }

    public function workshopItems(): HasMany
    {
        return $this->hasMany(SteamAppWorkshopItem::class, 'steam_app_id', 'id');
    }

    /**
     * Get the header image URL for the Steam application.
     */
    protected function headerImage(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (isset($this->detail->header_image)) {
                    return $this->detail->header_image;
                }

                return "https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/{$this->appid}/header.jpg";
            }
        );
    }

    /**
     * Generate the URL to the Steam store page of the application.
     */
    protected function steamAppUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => "https://store.steampowered.com/app/{$this->appid}",
        );
    }
}
