<?php

declare(strict_types=1);

namespace Artryazanov\LaravelSteamAppsDb\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SteamAppPackageGroupSub extends Model
{
    use HasFactory;

    protected $table = 'steam_app_package_group_subs';

    protected $fillable = [
        'steam_app_package_group_id',
        'packageid',
        'percent_savings_text',
        'percent_savings',
        'option_text',
        'option_description',
        'can_get_free_license',
        'is_free_license',
        'price_in_cents_with_discount',
    ];

    protected $casts = [
        'packageid' => 'integer',
        'percent_savings' => 'integer',
        'is_free_license' => 'boolean',
        'price_in_cents_with_discount' => 'integer',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(SteamAppPackageGroup::class, 'steam_app_package_group_id');
    }
}
