# Laravel Steam Apps DB

A Laravel package for managing Steam application data in your database. This package provides functionality to import Steam apps, fetch detailed information, and retrieve news for Steam games.

[![License: Unlicense](https://img.shields.io/badge/license-Unlicense-blue.svg)](http://unlicense.org/)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.0-8892BF.svg)](https://www.php.net/)
[![Laravel Version](https://img.shields.io/badge/laravel-10.x%7C11.x%7C12.x-red.svg)](https://laravel.com/)

## Introduction

Laravel Steam Apps DB provides a set of tools to work with Steam application data in your Laravel application. It allows you to:

- Import basic information about all Steam applications
- Fetch detailed information about specific Steam games
- Retrieve and store news articles for Steam games
- Access Steam app data through Eloquent models

The package handles all the database schema creation and provides console commands to interact with the Steam API.

## Installation

### Requirements

- PHP 8.0 or higher
- Laravel 10.x, 11.x, or 12.x

### Installation Steps

1. Install the package via Composer:

```bash
composer require artryazanov/laravel-steam-apps-db
```

2. The package will automatically register its service provider if you're using Laravel's package auto-discovery.

3. Publish and run the migrations:

```bash
php artisan migrate
```

## Usage

### Console Commands

The package provides three main console commands:

#### 1. Import Steam Apps

This command imports basic information about all Steam applications from the Steam API.

```bash
php artisan steam:import-apps
```

This will fetch a list of all Steam applications and store them in the `steam_apps` table.

#### 2. Fetch Steam App Details

This command fetches detailed information about Steam games and stores it in the database.

```bash
php artisan steam:fetch-app-details [count]
```

Parameters:
- `count` (optional): Number of apps to process (default: 10)

The command prioritizes:
1. Apps that have never had details fetched
2. Apps with details older than a year

Example:
```bash
php artisan steam:fetch-app-details 50
```

This will fetch detailed information for 50 Steam apps and store it in various tables.

#### 3. Fetch Steam App News

This command fetches the latest news for Steam apps and stores them in the database.

```bash
php artisan steam:fetch-app-news [count]
```

Parameters:
- `count` (optional): Number of apps to process (default: 10)

The command prioritizes:
1. Apps that have never had news fetched
2. Apps with news older than a month

Example:
```bash
php artisan steam:fetch-app-news 20
```

This will fetch news for 20 Steam apps and store it in the `steam_app_news` table.

### Models

The package provides several Eloquent models to interact with the stored data:

#### Main Models

- **SteamApp**: The core model representing a Steam application
- **SteamAppDetail**: Detailed information about a Steam app
- **SteamAppNews**: News articles for a Steam app

#### Related Models

- **SteamAppCategory**: Categories for Steam apps
- **SteamAppGenre**: Genres for Steam apps
- **SteamAppDeveloper**: Developers of Steam apps
- **SteamAppPublisher**: Publishers of Steam apps
- **SteamAppRequirement**: System requirements for different platforms
- **SteamAppScreenshot**: Screenshots for Steam apps
- **SteamAppMovie**: Videos/trailers for Steam apps
- **SteamAppPriceInfo**: Price information for Steam apps

### Example Usage

```php
use Artryazanov\LaravelSteamAppsDb\Models\SteamApp;

// Get all Steam apps
$apps = SteamApp::all();

// Get a specific app by Steam appid
$app = SteamApp::where('appid', 570)->first(); // DOTA 2

// Get detailed information
$details = $app->detail;

// Get system requirements
$requirements = $app->requirements;

// Get screenshots
$screenshots = $app->screenshots;

// Get categories
$categories = $app->categories;

// Get genres
$genres = $app->genres;

// Get developers
$developers = $app->developers;

// Get publishers
$publishers = $app->publishers;

// Get price information
$priceInfo = $app->priceInfo;

// Get news articles
$news = $app->news;
```

## Database Schema

The package creates the following tables:

1. `steam_apps` - Main table with basic app info
2. `steam_app_details` - Detailed information about each app
3. `steam_app_requirements` - System requirements for different platforms
4. `steam_app_screenshots` - Screenshots for each app
5. `steam_app_movies` - Videos/trailers for each app
6. `steam_app_categories` - Categories reference table
7. `steam_app_category` - Pivot table for app-category relationship
8. `steam_app_genres` - Genres reference table
9. `steam_app_genre` - Pivot table for app-genre relationship
10. `steam_app_developers` - Developers reference table
11. `steam_app_developer` - Pivot table for app-developer relationship
12. `steam_app_publishers` - Publishers reference table
13. `steam_app_publisher` - Pivot table for app-publisher relationship
14. `steam_app_price_info` - Price information for each app
15. `steam_app_news` - News items for each app

## Testing

The package includes tests for all its functionality. To run the tests:

```bash
vendor/bin/phpunit
```

## License

This package is open-sourced software licensed under the [Unlicense](http://unlicense.org/).
