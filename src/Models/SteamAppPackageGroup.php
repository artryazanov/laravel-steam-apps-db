<?php

namespace Artryazanov\LaravelSteamAppsDb\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SteamAppPackageGroup extends Model
{
    use HasFactory;

    protected $table = 'steam_app_package_groups';

    protected $fillable = [
        'steam_app_id',
        'name',
        'title',
        'description',
        'selection_text',
        'save_text',
        'display_type',
        'is_recurring_subscription',
    ];

    public function steamApp(): BelongsTo
    {
        return $this->belongsTo(SteamApp::class, 'steam_app_id');
    }

    public function subs(): HasMany
    {
        return $this->hasMany(SteamAppPackageGroupSub::class, 'steam_app_package_group_id');
    }
}

