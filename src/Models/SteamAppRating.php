<?php

namespace Artryazanov\LaravelSteamAppsDb\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SteamAppRating extends Model
{
    use HasFactory;

    protected $table = 'steam_app_ratings';

    protected $fillable = [
        'steam_app_id',
        'board',
        'rating',
        'descriptors',
        'display_online_notice',
        'required_age',
        'use_age_gate',
        'banned',
        'rating_generated',
    ];

    public function steamApp(): BelongsTo
    {
        return $this->belongsTo(SteamApp::class, 'steam_app_id');
    }
}
