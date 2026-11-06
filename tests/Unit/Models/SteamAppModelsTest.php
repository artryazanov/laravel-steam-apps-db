<?php

declare(strict_types=1);

namespace Artryazanov\LaravelSteamAppsDb\Tests\Unit\Models;

use Artryazanov\LaravelSteamAppsDb\Models\SteamApp;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppCategory;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppDetail;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppDeveloper;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppGenre;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppPublisher;
use Artryazanov\LaravelSteamAppsDb\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;

class SteamAppModelsTest extends TestCase
{
    public function test_steam_app_accessors_and_prevent_lazy_loading_safety(): void
    {
        $app = SteamApp::factory()->create(['appid' => 570, 'name' => 'Dota 2']);

        $this->assertSame('https://store.steampowered.com/app/570', $app->steam_app_url);
        $this->assertSame(
            'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/570/header.jpg',
            $app->header_image
        );

        // With detail created
        SteamAppDetail::factory()->create([
            'steam_app_id' => $app->id,
            'header_image' => 'https://cdn.example.com/custom_header.jpg',
        ]);

        $freshApp = SteamApp::findOrFail($app->id);
        $this->assertSame('https://cdn.example.com/custom_header.jpg', $freshApp->header_image);

        // With Model::preventLazyLoading() enabled and detail NOT eager-loaded -> falls back safely
        Model::preventLazyLoading(true);
        try {
            $unloadedApp = SteamApp::findOrFail($app->id);
            $this->assertSame(
                'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/570/header.jpg',
                $unloadedApp->header_image
            );

            $eagerApp = SteamApp::with('detail')->findOrFail($app->id);
            $this->assertSame('https://cdn.example.com/custom_header.jpg', $eagerApp->header_image);
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    public function test_steam_app_and_related_models_relationships(): void
    {
        $app = SteamApp::factory()->create(['appid' => 730, 'name' => 'Counter-Strike 2']);
        $detail = SteamAppDetail::factory()->create(['steam_app_id' => $app->id]);

        $req = $app->requirements()->create(['platform' => 'pc', 'minimum' => 'Min']);
        $shot = $app->screenshots()->create(['screenshot_id' => 1]);
        $movie = $app->movies()->create(['movie_id' => 1]);
        $price = $app->priceInfo()->create(['currency' => 'USD', 'initial' => 100, 'final' => 100]);
        $dlc = $app->dlcs()->create(['dlc_appid' => 10]);
        $demo = $app->demos()->create(['appid' => 20]);
        $pkg = $app->packages()->create(['package_id' => 30]);
        $group = $app->packageGroups()->create(['name' => 'default']);
        $sub = $group->subs()->create(['packageid' => 30]);
        $ach = $app->achievementsHighlighted()->create(['name' => 'First']);
        $cdesc = $app->contentDescriptorIds()->create(['descriptor_id' => 1]);
        $rating = $app->ratings()->create(['board' => 'esrb']);
        $news = $app->news()->create(['gid' => 'g1', 'title' => 'News']);
        $workshop = $app->workshopItems()->create(['publishedfileid' => 999, 'title' => 'Skin']);

        $category = SteamAppCategory::create(['category_id' => 1, 'description' => 'Co-op']);
        $genre = SteamAppGenre::create(['genre_id' => '1', 'description' => 'Action']);
        $developer = SteamAppDeveloper::create(['name' => 'Valve']);
        $publisher = SteamAppPublisher::create(['name' => 'Valve']);

        $app->categories()->attach($category->id);
        $app->genres()->attach($genre->id);
        $app->developers()->attach($developer->id);
        $app->publishers()->attach($publisher->id);

        $this->assertTrue($detail->steamApp->is($app));
        $this->assertTrue($req->steamApp->is($app));
        $this->assertTrue($shot->steamApp->is($app));
        $this->assertTrue($movie->steamApp->is($app));
        $this->assertTrue($price->steamApp->is($app));
        $this->assertTrue($dlc->steamApp->is($app));
        $this->assertTrue($demo->steamApp->is($app));
        $this->assertTrue($pkg->steamApp->is($app));
        $this->assertTrue($group->steamApp->is($app));
        $this->assertTrue($sub->group->is($group));
        $this->assertTrue($ach->steamApp->is($app));
        $this->assertTrue($cdesc->steamApp->is($app));
        $this->assertTrue($rating->steamApp->is($app));
        $this->assertTrue($news->steamApp->is($app));
        $this->assertTrue($workshop->steamApp->is($app));

        $this->assertCount(1, $category->steamApps);
        $this->assertCount(1, $genre->steamApps);
        $this->assertCount(1, $developer->steamApps);
        $this->assertCount(1, $publisher->steamApps);

        $this->assertCount(1, $app->requirements);
        $this->assertCount(1, $app->screenshots);
        $this->assertCount(1, $app->movies);
        $this->assertNotNull($app->priceInfo);
        $this->assertCount(1, $app->dlcs);
        $this->assertCount(1, $app->demos);
        $this->assertCount(1, $app->packages);
        $this->assertCount(1, $app->packageGroups);
        $this->assertCount(1, $app->achievementsHighlighted);
        $this->assertCount(1, $app->contentDescriptorIds);
        $this->assertCount(1, $app->ratings);
        $this->assertCount(1, $app->news);
        $this->assertCount(1, $app->workshopItems);
    }
}
