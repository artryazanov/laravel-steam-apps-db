<?php

namespace Artryazanov\LaravelSteamAppsDb\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SteamAppAchievementHighlighted extends Model
{
    use HasFactory;

    protected $table = 'steam_app_achievements_highlighted';

    protected $fillable = [
        'steam_app_id',
        'name',
        'path',
    ];

    public function steamApp(): BelongsTo
    {
        return $this->belongsTo(SteamApp::class, 'steam_app_id');
    }
}
