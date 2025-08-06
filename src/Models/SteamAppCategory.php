<?php

namespace Artryazanov\LaravelSteamAppsDb\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Artryazanov\LaravelSteamAppsDb\Models\SteamAppCategory
 *
 * @property int $id
 * @property int $category_id Category ID from Steam
 * @property string $description Description of the category
 */
class SteamAppCategory extends Model
{
    use HasFactory;

    protected $table = 'steam_app_categories';

    protected $fillable = [
        'category_id',
        'description',
    ];

    /**
     * Get the Steam apps that belong to this category.
     */
    public function steamApps(): BelongsToMany
    {
        return $this->belongsToMany(
            SteamApp::class,
            'steam_app_category',
            'steam_app_category_id',
            'steam_app_id'
        )->withTimestamps();
    }
}
