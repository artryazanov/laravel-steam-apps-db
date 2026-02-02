<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('steam_app_workshop_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('steam_app_id')->comment('Reference to the steam_apps table')->constrained('steam_apps')->cascadeOnDelete();
            
            // Main IDs
            $table->unsignedBigInteger('publishedfileid')->unique()->comment('Workshop Item ID');
            $table->string('creator')->nullable()->comment('Author SteamID64');
            
            // Descriptions and Titles
            $table->string('title')->comment('Item Title');
            $table->text('short_description')->nullable()->comment('Short description');
            $table->longText('description')->nullable()->comment('Full description (HTML)');
            
            // Files and URLs
            $table->string('filename')->nullable()->comment('Original filename');
            $table->unsignedBigInteger('file_size')->default(0)->comment('Size in bytes');
            $table->text('file_url')->nullable()->comment('Direct download URL');
            $table->string('preview_url')->nullable()->comment('Preview Image URL');
            $table->string('url')->nullable()->comment('Steam Workshop Page URL');
            
            // Metadata
            $table->json('tags')->nullable()->comment('Workshop tags (Genre, Type, etc)');
            $table->boolean('banned')->default(false)->comment('Is item banned');
            
            // Statistics
            $table->integer('views')->default(0);
            $table->integer('subscriptions')->default(0);
            $table->integer('favorited')->default(0);
            $table->integer('num_comments_public')->default(0);
            
            // Steam Timestamps
            $table->timestamp('time_created')->nullable();
            $table->timestamp('time_updated')->nullable();
            
            // Laravel Timestamps
            $table->timestamps();
            
            // Indexes
            $table->index('steam_app_id');
            $table->index('creator'); // Useful for finding all items by an author
            $table->index('banned');  // Useful for excluding banned items
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('steam_app_workshop_items');
    }
};
