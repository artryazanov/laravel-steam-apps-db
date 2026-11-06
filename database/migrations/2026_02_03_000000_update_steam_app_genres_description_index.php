<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('steam_app_genres', function (Blueprint $table) {
            // Drop unique index on description if it exists, then add a non-unique index
            try {
                $table->dropUnique('steam_app_genres_description_unique');
            } catch (Throwable $e) {
                // Ignore if the unique index doesn't exist
            }

            try {
                $table->index('description');
            } catch (Throwable $e) {
                // Ignore if the index already exists
            }
        });
    }

    public function down(): void
    {
        Schema::table('steam_app_genres', function (Blueprint $table) {
            try {
                $table->dropIndex('steam_app_genres_description_index');
            } catch (Throwable $e) {
                // Ignore if the non-unique index doesn't exist
            }

            try {
                $table->unique('description');
            } catch (Throwable $e) {
                // Ignore if the unique index already exists
            }
        });
    }
};
