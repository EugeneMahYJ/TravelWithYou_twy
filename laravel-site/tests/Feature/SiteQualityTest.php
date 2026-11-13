<?php

namespace Tests\Feature;

use App\Models\Ship;
use App\Models\TravelPackage;
use App\Support\PackageItineraryPdf;
use Database\Seeders\CountrySeeder;
use Database\Seeders\FaqSeeder;
use Database\Seeders\GuideSeeder;
use Database\Seeders\PromoCodeSeeder;
use Database\Seeders\ShipSeeder;
use Database\Seeders\TravelDealSeeder;
use Database\Seeders\TravelPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteQualityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            CountrySeeder::class, ShipSeeder::class, TravelPackageSeeder::class, TravelDealSeeder::class,
            PromoCodeSeeder::class, GuideSeeder::class, FaqSeeder::class,
        ]);
    }

    public function test_branded_error_pages_render(): void
    {
        Route::get('/_t/{code}', fn (int $code) => abort($code))->middleware('web');

        foreach ([403 => 'Error 403', 429 => 'Error 429', 500 => 'Error 500', 503 => 'Error 503'] as $code => $label) {
            $response = $this->get('/_t/'.$code)->assertStatus($code)->assertSee($label)->assertSee('Travel With You');
            $response->assertSee('noindex', false);
        }
    }

    public function test_server_error_pages_do_not_depend_on_the_site_layout(): void
    {
        Route::get('/_t/{code}', fn (int $code) => abort($code))->middleware('web');

        foreach ([500, 503] as $code) {
            $this->get('/_t/'.$code)->assertDontSee('site-header', false)->assertDontSee('skip-link', false);
        }
    }

    public function test_expired_public_form_goes_back_with_a_message_and_safe_input_only(): void
    {
        Route::post('/_t419', fn () => throw new TokenMismatchException('expired'))->middleware('web');

        $this->from('/contact')->post('/_t419', [
            'name' => 'Aisha',
            'message' => 'Hello',
            'password' => 'secret-pass-1',
            'card_number' => '4242424242424242',
            'code' => '123456',
        ])
            ->assertRedirect('/contact')
            ->assertSessionHas('error', 'Your session expired. Please try again.')
            ->assertSessionHas('_old_input.name', 'Aisha')
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.card_number')
            ->assertSessionMissing('_old_input.code');
    }

    public function test_expired_booking_and_checkout_forms_never_restore_input(): void
    {
        Route::post('/book/_t419', fn () => throw new TokenMismatchException('expired'))->middleware('web');

        $this->from('/cart')->post('/book/_t419', ['guests' => [['passport' => 'A1234567']]])
            ->assertRedirect('/cart')
            ->assertSessionHas('error')
            ->assertSessionMissing('_old_input');
    }

    public function test_sitemap_lists_public_pages_only(): void
    {
        $package = TravelPackage::where('published', true)->firstOrFail();
        $ship = Ship::firstOrFail();

        $response = $this->get('/sitemap.xml')->assertOk();
        $this->assertStringContainsString('application/xml', $response->headers->get('Content-Type'));
        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);
        $locations = [];
        foreach ($xml->url as $entry) {
            $locations[] = (string) $entry->loc;
        }

        $this->assertContains(route('home'), $locations);
        $this->assertContains(route('packages.show', ['package' => $package->slug]), $locations);
        $this->assertContains(route('ships.show', ['ship' => $ship->slug]), $locations);
        foreach (['cart', 'checkout', 'account', 'login', 'admin'] as $private) {
            $this->assertNotContains(url('/'.$private), $locations);
        }
    }

    public function test_unpublished_packages_stay_out_of_the_sitemap(): void
    {
        $package = TravelPackage::where('published', true)->firstOrFail();
        $package->forceFill(['published' => false])->save();
        \Illuminate\Support\Facades\Cache::forget('sitemap.xml');

        $this->get('/sitemap.xml')->assertDontSee(route('packages.show', ['package' => $package->slug]), false);
    }

    public function test_robots_txt_blocks_private_areas_and_points_to_the_sitemap(): void
    {
        // A static public/robots.txt would be served by the web server instead of this route.
        $this->assertFileDoesNotExist(public_path('robots.txt'));

        $this->get('/robots.txt')->assertOk()
            ->assertSee('Disallow: /admin', false)
            ->assertSee('Disallow: /checkout', false)
            ->assertSee('Disallow: /account', false)
            ->assertSee('Sitemap: '.url('/sitemap.xml'), false);
    }

    public function test_pages_have_canonical_and_sharing_tags(): void
    {
        $package = TravelPackage::where('published', true)->firstOrFail();

        $this->get('/')->assertOk()
            ->assertSee('<link rel="canonical" href="', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('name="twitter:card"', false)
            ->assertSee('"@type":"TravelAgency"', false);

        $page = $this->get(route('packages.show', ['package' => $package->slug]))->assertOk();
        $page->assertSee('property="og:type" content="product"', false);
        preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $page->getContent(), $match);
        $data = json_decode($match[1], true);
        $this->assertSame('TouristTrip', $data['@type']);
        $this->assertSame('MYR', $data['offers']['priceCurrency']);
        $this->assertSame((float) $package->price_from, (float) $data['offers']['price']);
    }

    public function test_filtered_search_points_to_plain_search_and_is_not_indexed(): void
    {
        $this->get(route('search'))->assertOk()->assertSee('content="index,follow"', false);

        $filtered = $this->get(route('search', ['q' => 'japan']))->assertOk();
        $filtered->assertSee('content="noindex,follow"', false);
        $filtered->assertSee('<link rel="canonical" href="'.route('search').'">', false);
    }

    public function test_private_pages_are_noindex(): void
    {
        foreach (['/cart', '/login', '/register', '/forgot', '/payment-failed'] as $path) {
            $this->get($path)->assertSee('<meta name="robots" content="noindex">', false);
        }
    }

    public function test_brand_name_is_written_consistently(): void
    {
        $package = TravelPackage::where('published', true)->firstOrFail();
        foreach (['/', '/about', '/contact', '/faq', '/deals', '/blog', '/cart', '/login', '/register', '/privacy', '/terms', '/_missing', '/package/'.$package->slug] as $path) {
            $html = $this->get($path)->getContent();
            // Download file names (for example sakura-rail-TravelWithYou.pdf) keep the one-word form.
            $visible = preg_replace('/[a-z0-9-]+-TravelWithYou\.pdf/', '', strip_tags($html));
            $this->assertStringNotContainsString('TravelWithYou', $visible, $path);
            $this->assertDoesNotMatchRegularExpression('/(alt|aria-label|title)="[^"]*TravelWithYou/', $html, $path);
        }
    }

    public function test_footer_images_load_lazily_and_the_first_hero_slide_has_priority(): void
    {
        $html = $this->get('/')->getContent();
        $footer = substr($html, strpos($html, '<footer'));
        preg_match_all('~<img\b[^>]*>~', $footer, $images);
        $this->assertNotEmpty($images[0]);
        foreach ($images[0] as $tag) {
            $this->assertStringContainsString('loading="lazy"', $tag);
        }
        $this->assertSame(1, preg_match_all('~<img class="hero-image kenburns"[^>]*fetchpriority="high"~', $html));
    }

    public function test_generated_itinerary_pdf_is_cached_and_rebuilt_when_the_package_changes(): void
    {
        Storage::fake('local');
        config(['package_brochures.by_source_id' => []]);
        $package = TravelPackage::where('published', true)->whereNull('itinerary_pdf_path')->firstOrFail();
        $generator = app(PackageItineraryPdf::class);

        $first = $generator->contents($package);
        $this->assertStringStartsWith('%PDF', $first);
        $this->assertCount(1, Storage::disk('local')->files('itinerary-cache'));

        $this->assertSame($first, $generator->contents($package));
        $this->assertCount(1, Storage::disk('local')->files('itinerary-cache'));

        $package->forceFill(['name' => $package->name.' updated', 'updated_at' => now()->addMinute()])->save();
        $generator->contents($package->fresh());
        $files = Storage::disk('local')->files('itinerary-cache');
        $this->assertCount(1, $files);
    }
}
