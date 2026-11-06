<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('steam_apps', function (Blueprint $table) {
            try {
                $table->index('name');
            } catch (Throwable $e) {
                // Ignore if index already exists
            }
        });
    }

    public function down(): void
    {
        Schema::table('steam_apps', function (Blueprint $table) {
            try {
                $table->dropIndex('steam_apps_name_index');
            } catch (Throwable $e) {
                // Ignore if index does not exist
            }
        });
    }
};
