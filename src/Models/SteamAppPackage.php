<?php

declare(strict_types=1);

namespace Artryazanov\LaravelSteamAppsDb\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SteamAppPackage extends Model
{
    use HasFactory;

    protected $table = 'steam_app_packages';

    protected $fillable = [
        'steam_app_id',
        'package_id',
    ];

    protected $casts = [
        'package_id' => 'integer',
    ];

    public function steamApp(): BelongsTo
    {
        return $this->belongsTo(SteamApp::class, 'steam_app_id');
    }
}
