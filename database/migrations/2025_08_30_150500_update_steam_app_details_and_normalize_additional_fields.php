<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add flattened columns to steam_app_details
        Schema::table('steam_app_details', function (Blueprint $table) {
            if (! Schema::hasColumn('steam_app_details', 'controller_support')) {
                $table->string('controller_support')->nullable()->after('is_free');
            }
            if (! Schema::hasColumn('steam_app_details', 'drm_notice')) {
                $table->longText('drm_notice')->nullable()->after('legal_notice');
            }
            if (! Schema::hasColumn('steam_app_details', 'metacritic_score')) {
                $table->unsignedInteger('metacritic_score')->nullable()->after('capsule_imagev5');
            }
            if (! Schema::hasColumn('steam_app_details', 'metacritic_url')) {
                $table->string('metacritic_url')->nullable()->after('metacritic_score');
            }
            if (! Schema::hasColumn('steam_app_details', 'recommendations_total')) {
                $table->unsignedBigInteger('recommendations_total')->nullable()->after('metacritic_url');
            }
            if (! Schema::hasColumn('steam_app_details', 'achievements_total')) {
                $table->unsignedInteger('achievements_total')->nullable()->after('recommendations_total');
            }
            if (! Schema::hasColumn('steam_app_details', 'content_descriptors_notes')) {
                $table->longText('content_descriptors_notes')->nullable()->after('background_raw');
            }
        });

        // Create normalized tables
        if (! Schema::hasTable('steam_app_dlcs')) {
            Schema::create('steam_app_dlcs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('steam_app_id')->constrained('steam_apps')->cascadeOnDelete();
                $table->unsignedBigInteger('dlc_appid');
                $table->timestamps();
                $table->unique(['steam_app_id', 'dlc_appid'], 'dlcs_app_dlc_unique');
            });
        }

        if (! Schema::hasTable('steam_app_demos')) {
            Schema::create('steam_app_demos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('steam_app_id')->constrained('steam_apps')->cascadeOnDelete();
                $table->unsignedBigInteger('appid');
                $table->string('description')->nullable();
                $table->timestamps();
                $table->unique(['steam_app_id', 'appid'], 'demos_app_demo_unique');
            });
        }

        if (! Schema::hasTable('steam_app_packages')) {
            Schema::create('steam_app_packages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('steam_app_id')->constrained('steam_apps')->cascadeOnDelete();
                $table->unsignedBigInteger('package_id');
                $table->timestamps();
                $table->unique(['steam_app_id', 'package_id'], 'packages_app_pkg_unique');
            });
        }

        if (! Schema::hasTable('steam_app_package_groups')) {
            Schema::create('steam_app_package_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('steam_app_id')->constrained('steam_apps')->cascadeOnDelete();
                $table->string('name');
                $table->string('title')->nullable();
                $table->text('description')->nullable();
                $table->string('selection_text')->nullable();
                $table->string('save_text')->nullable();
                $table->unsignedInteger('display_type')->default(0);
                $table->string('is_recurring_subscription')->nullable();
                $table->timestamps();
                $table->unique(['steam_app_id', 'name'], 'pkg_groups_app_name_unique');
            });
        }

        if (! Schema::hasTable('steam_app_package_group_subs')) {
            Schema::create('steam_app_package_group_subs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('steam_app_package_group_id')->constrained('steam_app_package_groups')->cascadeOnDelete();
                $table->unsignedBigInteger('packageid');
                $table->string('percent_savings_text')->nullable();
                $table->integer('percent_savings')->default(0);
                $table->string('option_text')->nullable();
                $table->text('option_description')->nullable();
                $table->string('can_get_free_license')->nullable();
                $table->boolean('is_free_license')->default(false);
                $table->unsignedBigInteger('price_in_cents_with_discount')->nullable();
                $table->timestamps();
                $table->unique(['steam_app_package_group_id', 'packageid'], 'pkg_group_subs_pkgid_unique');
            });
        }

        if (! Schema::hasTable('steam_app_achievements_highlighted')) {
            Schema::create('steam_app_achievements_highlighted', function (Blueprint $table) {
                $table->id();
                $table->foreignId('steam_app_id')->constrained('steam_apps')->cascadeOnDelete();
                $table->string('name');
                $table->string('path')->nullable();
                $table->timestamps();
                $table->unique(['steam_app_id', 'name', 'path'], 'ach_highlighted_app_name_path_unique');
            });
        }

        if (! Schema::hasTable('steam_app_content_descriptor_ids')) {
            Schema::create('steam_app_content_descriptor_ids', function (Blueprint $table) {
                $table->id();
                $table->foreignId('steam_app_id')->constrained('steam_apps')->cascadeOnDelete();
                $table->unsignedInteger('descriptor_id');
                $table->timestamps();
                $table->unique(['steam_app_id', 'descriptor_id'], 'cdesc_app_desc_unique');
            });
        }

        if (! Schema::hasTable('steam_app_ratings')) {
            Schema::create('steam_app_ratings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('steam_app_id')->constrained('steam_apps')->cascadeOnDelete();
                $table->string('board');
                $table->string('rating')->nullable();
                $table->text('descriptors')->nullable();
                $table->string('display_online_notice')->nullable();
                $table->string('required_age')->nullable();
                $table->string('use_age_gate')->nullable();
                $table->string('banned')->nullable();
                $table->string('rating_generated')->nullable();
                $table->timestamps();
                $table->unique(['steam_app_id', 'board'], 'ratings_app_board_unique');
            });
        }
    }

    public function down(): void
    {
        // Drop created tables
        Schema::dropIfExists('steam_app_ratings');
        Schema::dropIfExists('steam_app_content_descriptor_ids');
        Schema::dropIfExists('steam_app_achievements_highlighted');
        Schema::dropIfExists('steam_app_package_group_subs');
        Schema::dropIfExists('steam_app_package_groups');
        Schema::dropIfExists('steam_app_packages');
        Schema::dropIfExists('steam_app_demos');
        Schema::dropIfExists('steam_app_dlcs');

        // Remove added columns from steam_app_details
        Schema::table('steam_app_details', function (Blueprint $table) {
            foreach ([
                'controller_support',
                'drm_notice',
                'metacritic_score',
                'metacritic_url',
                'recommendations_total',
                'achievements_total',
                'content_descriptors_notes',
            ] as $col) {
                if (Schema::hasColumn('steam_app_details', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
