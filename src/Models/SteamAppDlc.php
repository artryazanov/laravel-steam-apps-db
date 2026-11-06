<?php

declare(strict_types=1);

namespace Artryazanov\LaravelSteamAppsDb\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SteamAppDlc extends Model
{
    use HasFactory;

    protected $table = 'steam_app_dlcs';

    protected $fillable = [
        'steam_app_id',
        'dlc_appid',
    ];

    protected $casts = [
        'dlc_appid' => 'integer',
    ];

    public function steamApp(): BelongsTo
    {
        return $this->belongsTo(SteamApp::class, 'steam_app_id');
    }
}
