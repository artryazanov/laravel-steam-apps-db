<?php

namespace Artryazanov\LaravelSteamAppsDb\Enums;

/**
 * Enum representing Steam application types.
 */
enum SteamAppType: string
{
    case ADVERTISING = 'advertising';
    case DEMO = 'demo';
    case DLC = 'dlc';
    case EPISODE = 'episode';
    case GAME = 'game';
    case HARDWARE = 'hardware';
    case MOD = 'mod';
    case MUSIC = 'music';
    case SERIES = 'series';
    case VIDEO = 'video';
}
