<?php

namespace Artryazanov\LaravelSteamAppsDb\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SteamAppContentDescriptorId extends Model
{
    use HasFactory;

    protected $table = 'steam_app_content_descriptor_ids';

    protected $fillable = [
        'steam_app_id',
        'descriptor_id',
    ];

    public function steamApp(): BelongsTo
    {
        return $this->belongsTo(SteamApp::class, 'steam_app_id');
    }
}

