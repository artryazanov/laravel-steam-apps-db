<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration creates all Steam app-related tables.
     */
    public function up(): void
    {
        // 1. Create the main steam_apps table first
        Schema::create('steam_apps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appid')->unique()->comment('Steam application ID');
            $table->string('name')->comment('Steam application name');
            $table->timestamp('last_details_update')->nullable()->comment('When the detailed data was last loaded from Steam');
            $table->timestamp('last_news_update')->nullable()->comment('When the news data was last loaded from Steam');
            $table->timestamps();
        });

        // 2. Create the steam_app_details table
        Schema::create('steam_app_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('steam_app_id')->unique()->comment('Reference to the steam_apps table')->constrained('steam_apps')->cascadeOnDelete();
            $table->string('type')->nullable()->comment('Type of the application (game, dlc, etc.)')->index();
            $table->string('name')->comment('Name of the game');
            $table->integer('required_age')->default(0)->comment('Required age to play the game')->index();
            $table->boolean('is_free')->default(false)->comment('Whether the game is free to play')->index();
            $table->longText('detailed_description')->nullable()->comment('Detailed description of the game');
            $table->longText('about_the_game')->nullable()->comment('About the game text');
            $table->longText('short_description')->nullable()->comment('Short description of the game');
            $table->longText('supported_languages')->nullable()->comment('Supported languages');
            $table->string('header_image')->nullable()->comment('URL to the header image');
            $table->string('capsule_image')->nullable()->comment('URL to the capsule image');
            $table->string('capsule_imagev5')->nullable()->comment('URL to the capsule image v5');
            $table->string('website')->nullable()->comment('Game website URL');
            $table->longText('legal_notice')->nullable()->comment('Legal notice');
            $table->boolean('windows')->default(false)->comment('Whether the game is available on Windows')->index();
            $table->boolean('mac')->default(false)->comment('Whether the game is available on Mac')->index();
            $table->boolean('linux')->default(false)->comment('Whether the game is available on Linux')->index();
            $table->string('background')->nullable()->comment('URL to the background image');
            $table->string('background_raw')->nullable()->comment('URL to the raw background image');
            $table->date('release_date')->nullable()->comment('Release date of the game')->index();
            $table->boolean('coming_soon')->default(false)->comment('Whether the game is coming soon')->index();
            $table->string('support_url')->nullable()->comment('Support URL');
            $table->string('support_email')->nullable()->comment('Support email');
            $table->timestamps();
        });

        // 3. Create a steam_app_requirements table
        Schema::create('steam_app_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('steam_app_id')->comment('Reference to the steam_apps table')->constrained('steam_apps')->cascadeOnDelete();
            $table->enum('platform', ['pc', 'mac', 'linux'])->comment('Platform for the requirements');
            $table->longText('minimum')->nullable()->comment('Minimum requirements');
            $table->longText('recommended')->nullable()->comment('Recommended requirements');
            $table->timestamps();

            // Ensure each game has only one set of requirements per platform
            $table->unique(['steam_app_id', 'platform']);
        });

        // 4. Create a steam_app_screenshots table
        Schema::create('steam_app_screenshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('steam_app_id')->comment('Reference to the steam_apps table')->constrained('steam_apps')->cascadeOnDelete();
            $table->unsignedBigInteger('screenshot_id')->comment('Screenshot ID from Steam');
            $table->string('path_thumbnail')->nullable()->comment('URL to the thumbnail image');
            $table->string('path_full')->nullable()->comment('URL to the full-size image');
            $table->timestamps();
            $table->softDeletes();

            // Ensure each screenshot is only stored once per game
            $table->unique(['steam_app_id', 'screenshot_id']);
        });

        // 5. Create a steam_app_movies table
        Schema::create('steam_app_movies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('steam_app_id')->comment('Reference to the steam_apps table')->constrained('steam_apps')->cascadeOnDelete();
            $table->unsignedBigInteger('movie_id')->comment('Movie ID from Steam');
            $table->string('name')->nullable()->comment('Name of the movie');
            $table->string('thumbnail')->nullable()->comment('URL to the thumbnail image');
            $table->string('webm_480')->nullable()->comment('URL to the 480p WebM video');
            $table->string('webm_max')->nullable()->comment('URL to the max quality WebM video');
            $table->string('mp4_480')->nullable()->comment('URL to the 480p MP4 video');
            $table->string('mp4_max')->nullable()->comment('URL to the max quality MP4 video');
            $table->boolean('highlight')->default(false)->comment('Whether this is a highlight video');
            $table->timestamps();
            $table->softDeletes();

            // Ensure each movie is only stored once per game
            $table->unique(['steam_app_id', 'movie_id']);
        });

        // 6. Create a steam_app_categories table (reference table)
        Schema::create('steam_app_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('category_id')->comment('Category ID from Steam');
            $table->string('description')->comment('Description of the category');
            $table->timestamps();

            // Make category_id and description unique
            $table->unique(['category_id']);
            $table->unique(['description']);
        });

        // 6.1 Create a pivot table for the relationship between steam_apps and steam_app_categories
        Schema::create('steam_app_category', function (Blueprint $table) {
            $table->foreignId('steam_app_id')->comment('Reference to the steam_apps table')->constrained('steam_apps')->cascadeOnDelete();
            $table->foreignId('steam_app_category_id')->comment('Reference to the steam_app_categories table')->constrained('steam_app_categories')->cascadeOnDelete();
            $table->timestamps();

            // Ensure each category is only stored once per game
            $table->unique(['steam_app_id', 'steam_app_category_id']);
        });

        // 7. Create a steam_app_genres table (reference table)
        Schema::create('steam_app_genres', function (Blueprint $table) {
            $table->id();
            $table->string('genre_id')->comment('Genre ID from Steam');
            $table->string('description')->comment('Description of the genre');
            $table->timestamps();

            // Make genre_id and description unique
            $table->unique(['genre_id']);
            $table->unique(['description']);
        });

        // 7.1 Create a pivot table for the relationship between steam_apps and steam_app_genres
        Schema::create('steam_app_genre', function (Blueprint $table) {
            $table->foreignId('steam_app_id')->comment('Reference to the steam_apps table')->constrained('steam_apps')->cascadeOnDelete();
            $table->foreignId('steam_app_genre_id')->comment('Reference to the steam_app_genres table')->constrained('steam_app_genres')->cascadeOnDelete();
            $table->timestamps();

            // Ensure each genre is only stored once per game
            $table->unique(['steam_app_id', 'steam_app_genre_id']);
        });

        // 8. Create a steam_app_developers table (reference table)
        Schema::create('steam_app_developers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Name of the developer');
            $table->timestamps();

            // Make name unique
            $table->unique(['name']);
        });

        // 8.1 Create a pivot table for the relationship between steam_apps and steam_app_developers
        Schema::create('steam_app_developer', function (Blueprint $table) {
            $table->foreignId('steam_app_id')->comment('Reference to the steam_apps table')->constrained('steam_apps')->cascadeOnDelete();
            $table->foreignId('steam_app_developer_id')->comment('Reference to the steam_app_developers table')->constrained('steam_app_developers')->cascadeOnDelete();
            $table->timestamps();

            // Ensure each developer is only stored once per game
            $table->unique(['steam_app_id', 'steam_app_developer_id']);
        });

        // 9. Create a steam_app_publishers table (reference table)
        Schema::create('steam_app_publishers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Name of the publisher');
            $table->timestamps();

            // Make name unique
            $table->unique(['name']);
        });

        // 9.1 Create a pivot table for the relationship between steam_apps and steam_app_publishers
        Schema::create('steam_app_publisher', function (Blueprint $table) {
            $table->foreignId('steam_app_id')->comment('Reference to the steam_apps table')->constrained('steam_apps')->cascadeOnDelete();
            $table->foreignId('steam_app_publisher_id')->comment('Reference to the steam_app_publishers table')->constrained('steam_app_publishers')->cascadeOnDelete();
            $table->timestamps();

            // Ensure each publisher is only stored once per game
            $table->unique(['steam_app_id', 'steam_app_publisher_id']);
        });

        // 10. Create a steam_app_price_info table
        Schema::create('steam_app_price_info', function (Blueprint $table) {
            $table->id();
            $table->foreignId('steam_app_id')->unique()->comment('Reference to the steam_apps table')->constrained('steam_apps')->cascadeOnDelete();
            $table->string('currency')->nullable()->comment('Currency code (e.g., USD, EUR)');
            $table->unsignedInteger('initial')->nullable()->comment('Initial price in the smallest currency unit (e.g., cents)');
            $table->unsignedInteger('final')->nullable()->comment('Final price after discount in the smallest currency unit');
            $table->unsignedInteger('discount_percent')->default(0)->comment('Discount percentage');
            $table->string('initial_formatted')->nullable()->comment('Formatted initial price string');
            $table->string('final_formatted')->nullable()->comment('Formatted final price string');
            $table->timestamps();
        });

        // 11. Create a steam_app_news table
        Schema::create('steam_app_news', function (Blueprint $table) {
            $table->id();
            $table->foreignId('steam_app_id')->comment('Reference to the steam_apps table')->constrained('steam_apps')->cascadeOnDelete();
            $table->string('gid')->comment('News item ID from Steam');
            $table->string('title')->comment('Title of the news item');
            $table->text('url')->nullable()->comment('URL to the news item');
            $table->boolean('is_external_url')->default(false)->comment('Whether the URL is external');
            $table->string('author')->nullable()->comment('Author of the news item');
            $table->longText('contents')->nullable()->comment('Contents of the news item');
            $table->string('feedlabel')->nullable()->comment('Feed label');
            $table->unsignedBigInteger('date')->nullable()->comment('Date of the news item as Unix timestamp');
            $table->string('feedname')->nullable()->comment('Feed name');
            $table->unsignedInteger('feed_type')->default(0)->comment('Feed type');
            $table->json('tags')->nullable()->comment('Tags associated with the news item');
            $table->timestamps();

            // Ensure each news item is only stored once
            $table->unique(['gid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop tables in reverse order to avoid foreign key constraint issues
        Schema::dropIfExists('steam_app_news');
        Schema::dropIfExists('steam_app_price_info');
        Schema::dropIfExists('steam_app_publisher'); // Drop the pivot table first
        Schema::dropIfExists('steam_app_publishers');
        Schema::dropIfExists('steam_app_developer'); // Drop the pivot table first
        Schema::dropIfExists('steam_app_developers');
        Schema::dropIfExists('steam_app_genre'); // Drop the pivot table first
        Schema::dropIfExists('steam_app_genres');
        Schema::dropIfExists('steam_app_category'); // Drop the pivot table first
        Schema::dropIfExists('steam_app_categories');
        Schema::dropIfExists('steam_app_movies');
        Schema::dropIfExists('steam_app_screenshots');
        Schema::dropIfExists('steam_app_requirements');
        Schema::dropIfExists('steam_app_details');
        Schema::dropIfExists('steam_apps');
    }
};
