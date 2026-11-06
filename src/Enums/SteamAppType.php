<?php

declare(strict_types=1);

namespace Artryazanov\LaravelSteamAppsDb\Enums;

/**
 * Enum representing Steam application types.
 */
enum SteamAppType: string
{
    case ADVERTISING = 'advertising';
    case APPLICATION = 'application';
    case BETA = 'beta';
    case CONFIG = 'config';
    case DEMO = 'demo';
    case DLC = 'dlc';
    case EPISODE = 'episode';
    case GAME = 'game';
    case HARDWARE = 'hardware';
    case MOD = 'mod';
    case MUSIC = 'music';
    case SERIES = 'series';
    case TOOL = 'tool';
    case VIDEO = 'video';
}
