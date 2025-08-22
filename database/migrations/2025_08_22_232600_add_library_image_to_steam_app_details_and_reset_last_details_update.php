<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('steam_app_details', function (Blueprint $table) {
            $table->string('library_image')->nullable()->after('header_image')->comment('URL to the library image 600x900');
        });

        // Reset last_details_update for all existing apps
        DB::table('steam_apps')->update(['last_details_update' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('steam_app_details', function (Blueprint $table) {
            $table->dropColumn('library_image');
        });
    }
};
