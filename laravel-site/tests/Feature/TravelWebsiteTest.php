<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Enquiry;
use App\Models\Faq;
use App\Models\NewsletterSubscriber;
use App\Models\SearchLog;
use App\Models\Ship;
use App\Models\TravelDeal;
use App\Models\TravelPackage;
use App\Models\User;
use App\Support\Totp;
use Database\Seeders\CountrySeeder;
use Database\Seeders\FaqSeeder;
use Database\Seeders\GuideSeeder;
use Database\Seeders\PackagePhotoSeeder;
use Database\Seeders\PromoCodeSeeder;
use Database\Seeders\ShipMediaSeeder;
use Database\Seeders\ShipSeeder;
use Database\Seeders\TourPackageSeeder;
use Database\Seeders\TravelDealSeeder;
use Database\Seeders\TravelPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TravelWebsiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            CountrySeeder::class,
            ShipSeeder::class,
            TravelPackageSeeder::class,
            TravelDealSeeder::class,
            PromoCodeSeeder::class,
            GuideSeeder::class,
            FaqSeeder::class,
        ]);
    }

    public function test_package_photo_seeder_maps_local_photos_for_the_full_catalog(): void
    {
        $this->seed(TourPackageSeeder::class);
        $sakura = TravelPackage::where('slug', 'sakura-rail')->firstOrFail();
        $sakuraName = $sakura->name;
        $sakuraPrice = $sakura->price_from;
        $hokkaido = TravelPackage::where('source_id', 'JPD-12')->firstOrFail();
        $hokkaidoName = $hokkaido->name;
        $hokkaidoPrice = $hokkaido->price_from;

        $this->seed(PackagePhotoSeeder::class);

        $packages = TravelPackage::query()->where('image', 'like', 'package-photo-%')->get();
        $this->assertCount(27, $packages);

        foreach ($packages as $package) {
            $this->assertCount(3, $package->gallery);
            $this->assertSame($package->image, $package->gallery[0]);
            $this->assertFileExists(public_path('assets/'.$package->image.'.jpg'));

            foreach ($package->gallery as $image) {
                $this->assertFileExists(public_path('assets/'.$image.'.jpg'));
            }
        }

        $this->assertSame($sakuraName, $sakura->refresh()->name);
        $this->assertSame($sakuraPrice, $sakura->price_from);
        $this->assertSame($hokkaidoName, $hokkaido->refresh()->name);
        $this->assertSame($hokkaidoPrice, $hokkaido->price_from);
        $this->assertSame(
            ['package-photo-133', 'package-photo-134', 'package-photo-45'],
            $hokkaido->gallery,
        );

        $this->get(route('packages.show', ['package' => 'sakura-rail']))
            ->assertOk()
            ->assertSee('assets/package-photo-2.jpg');
    }

    public function test_home_and_package_catalogue_render_seeded_journeys(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Most popular trips')
            ->assertSee('The fleet')
            ->assertSee('Travel guides')
            ->assertSee('data-carousel-pause', false)
            ->assertSee('All deals')
            ->assertSee('value="Coral Horizon"', false);
        $allTrips = $this->get(route('search'))
            ->assertOk()
            ->assertSee('Sakura Rail Journey')
            ->assertSee('name="ship"', false);
        $allTrips->assertSee('alt="TravelWithYou"', false)
            ->assertSee('placeholder="Search trips, countries…"', false)
            ->assertSee('Tour packages and cruises to forty countries, planned by people who have been there.')
            ->assertSee('© 2026 TravelWithYou Ltd. All rights reserved.')
            ->assertSee('href="'.route('search').'">Trip</a>', false)
            ->assertSee('href="'.route('ships').'">Cruise</a>', false)
            ->assertDontSee('>Journal</a>', false);
        $nav = substr($allTrips->getContent(), strpos($allTrips->getContent(), '<nav class="main-nav"'));
        $this->assertLessThan(strpos($nav, '>Trip</a>'), strpos($nav, '>Destinations</a>'));
        $this->assertLessThan(strpos($nav, '>Deals</a>'), strpos($nav, '>Trip</a>'));
        $this->assertSame(1, substr_count($allTrips->getContent(), 'class="header-search"'));
        $this->assertSame(1, substr_count($allTrips->getContent(), 'class="search-panel hybrid-search-panel"'));

        $this->get(route('search', ['type' => 'Cruise']))
            ->assertOk()
            ->assertSee('name="ship"', false)
            ->assertSee('Cruise');
        $this->get(route('packages.show', ['package' => 'sakura-rail']))
            ->assertOk()
            ->assertSee('Daily trip plan')
            ->assertSee('Book Now')
            ->assertSee('Save')
            ->assertSee('Ask us')
            ->assertDontSee('Choose a departure')
            ->assertDontSee('Add to your trip');
    }

    public function test_home_search_clear_link_stays_on_home_without_query_filters(): void
    {
        $this->get(route('home', ['q' => 'Japan', 'price' => 'lt2']))
            ->assertOk()
            ->assertSee('href="'.rtrim(route('home'), '/').'"', false)
            ->assertSee('data-clear-filters-in-place', false)
            ->assertSee('>Clear filters</a>', false);
    }

    public function test_trip_search_clear_links_preserve_scroll_position(): void
    {
        $this->get(route('search'))
            ->assertOk()
            ->assertSee('class="text-link"', false)
            ->assertSee('data-preserve-scroll-on-clear', false);

        $this->get(route('search', ['q' => 'no-matching-trip']))
            ->assertOk()
            ->assertSee('class="search-empty"', false)
            ->assertSee('href="'.route('search').'" data-preserve-scroll-on-clear', false);
    }

    public function test_home_destinations_are_four_random_distinct_source_countries_and_uncached(): void
    {
        $response = $this->get(route('home'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
        $content = $response->getContent();
        preg_match('/<div class="destination-grid destination-mosaic">(.*?)<\/div>\s*<\/section>/s', $content, $sectionMatch);
        $this->assertNotEmpty($sectionMatch[1] ?? null);
        preg_match_all('/href="[^"]*\/destinations\/([^"]+)"/', $sectionMatch[1], $countryMatches);
        $slugs = $countryMatches[1] ?? [];
        $this->assertCount(4, $slugs);
        $this->assertCount(4, array_unique($slugs));

        $countries = \App\Models\Country::query()->whereIn('slug', $slugs)->get()->keyBy('slug');
        foreach ($slugs as $slug) {
            $country = $countries->get($slug);
            $this->assertNotNull($country);
            $this->assertStringContainsString('src="'.asset_img($country->image).'"', $sectionMatch[1]);
            $this->assertStringContainsString($country->region, $sectionMatch[1]);
            $this->assertStringContainsString($country->name, $sectionMatch[1]);
        }
        $this->assertStringContainsString('destination-mosaic-small-row', $sectionMatch[1]);
        $this->assertStringContainsString('destination-tile-medium', $sectionMatch[1]);
    }

    public function test_home_slideshow_includes_the_navigator_video_slide(): void
    {
        $response = $this->get(route('home'))
            ->assertOk()
            ->assertSee('Amped up at sea.')
            ->assertSee('Ship tour · Navigator of the Seas')
            ->assertSee('href="'.route('ships.show', ['ship' => 'star-voyager']).'">View ship', false)
            ->assertSee('href="'.route('ships.show', ['ship' => 'navigator-of-the-seas']).'">View ship', false)
            ->assertSee('href="'.route('ships').'">Explore Cruise', false)
            ->assertSee('assets/star-voyager-1-xnakcrup.png', false)
            ->assertSee('assets/video/Ship_example.mp4', false)
            ->assertSee('<video', false)
            ->assertSee('hero-video', false);

        $this->assertSame(5, substr_count($response->getContent(), 'class="hero-slide'));
    }

    public function test_all_prototype_package_details_render_every_section(): void
    {
        $packages = TravelPackage::query()->whereIn('slug', [
            'sakura-rail', 'kyoto-retreat', 'amalfi-escape', 'med-grand', 'aegean-hopper',
            'inca-trail', 'mara-migration', 'iceland-aurora', 'andaman-escape',
            'reef-cruise', 'atlantic-crossing', 'bali-terraces', 'pacific-crossing', '5DCY4',
        ])->get();
        $this->assertCount(14, $packages);

        foreach ($packages as $package) {
            $response = $this->get(route('packages.show', ['package' => $package->slug]))
                ->assertOk()
                ->assertSee('Trip at a glance')
                ->assertSeeInOrder(['Ask us', 'Trip at a glance', 'Highlights'])
                ->assertSee('Highlights')
                ->assertSee('Daily trip plan')
                ->assertSee('Included')
                ->assertSee('Not included')
                ->assertSee($package->slug.'-TravelWithYou.pdf')
                ->assertSee('Ask us');
            $content = $response->getContent();
            $asideStart = strpos($content, '<aside class="package-booking-aside">');
            $includedPosition = strpos($content, 'class="detail-inclusions"', $asideStart);
            $pdfPosition = strpos($content, 'class="package-pdf-card"', $asideStart);
            $this->assertNotFalse($asideStart);
            $this->assertNotFalse($includedPosition);
            $this->assertNotFalse($pdfPosition);
            $this->assertGreaterThan($includedPosition, $pdfPosition);
            $this->assertStringNotContainsString('Price table', $content);
            $this->assertStringNotContainsString('package-pax-price-table', $content);
        }

        $this->get(route('packages.show', ['package' => 'med-grand']))
            ->assertSee('Ship room types · Aurelia Voyager')
            ->assertSeeInOrder(['Ship room types · Aurelia Voyager', 'Included', 'Not included'])
            ->assertSee('Sailing aboard');
    }

    public function test_package_recommendations_only_include_packages_from_the_same_country(): void
    {
        $this->seed(TourPackageSeeder::class);
        $switzerland = TravelPackage::where('country', 'Switzerland')->where('published', true)->firstOrFail();
        $switzerlandHtml = $this->get(route('packages.show', ['package' => $switzerland->slug]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('package-coming-soon', $switzerlandHtml);
        $this->assertStringContainsString('More journeys in Switzerland are in the works.', $switzerlandHtml);
        $this->assertStringContainsString('We’re carefully curating new ways to experience this destination.', $switzerlandHtml);
        $this->assertStringContainsString(asset('assets/countries/switzerland-package-thumbnail.jpg'), $switzerlandHtml);
        $this->assertStringContainsString('href="'.route('search').'"', $switzerlandHtml);
        $this->assertStringContainsString('Browse all trips', $switzerlandHtml);

        $template = TravelPackage::where('slug', 'sakura-rail')->firstOrFail();
        $attributes = $template->only([
            'region', 'days', 'description', 'details', 'price_from', 'price_to',
            'rating', 'reviews', 'departure_port', 'destination_port', 'departures',
            'image', 'published',
        ]);
        $createPackage = fn (string $slug, string $name, string $country) => TravelPackage::create($attributes + [
            'slug' => $slug,
            'name' => $name,
            'country' => $country,
            'type' => 'Guided tour',
        ]);

        $soloPackage = $createPackage('solo-country-trip', 'Solo Country Journey', 'Solo Country');
        $createPackage('different-country-trip', 'Different Country Journey', 'Different Country');

        $soloHtml = $this->get(route('packages.show', ['package' => $soloPackage->slug]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('package-coming-soon', $soloHtml);
        $this->assertStringNotContainsString('Different Country Journey', $soloHtml);

        $sharedCountryPackage = $createPackage('shared-country-trip', 'Shared Country Journey', 'Shared Country');
        $sameCountryPackage = $createPackage('shared-country-sibling', 'Same Country Sibling', 'Shared Country');
        $otherCountryPackage = $createPackage('shared-country-foreign', 'Foreign Country Journey', 'Foreign Country');

        $sharedHtml = $this->get(route('packages.show', ['package' => $sharedCountryPackage->slug]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('More in Shared Country', $sharedHtml);
        $this->assertStringContainsString($sameCountryPackage->name, $sharedHtml);
        $this->assertStringNotContainsString($otherCountryPackage->name, $sharedHtml);
    }

    public function test_package_detail_does_not_render_the_price_table_with_or_without_a_deal(): void
    {
        $package = TravelPackage::where('slug', 'amalfi-escape')->firstOrFail();
        $deal = TravelDeal::where('package_id', $package->id)->firstOrFail();
        $deal->forceFill(['active' => true, 'expires_at' => now()->addDay(), 'ends_at' => now()->addDay()])->save();

        $this->get(route('packages.show', ['package' => $package->slug]))
            ->assertOk()
            ->assertDontSee('package-pax-price-table')
            ->assertDontSee('Limited-time: 20% off');

        $deal->update(['active' => false]);
        $this->get(route('packages.show', ['package' => $package->slug]))
            ->assertOk()
            ->assertDontSee('package-pax-price-table')
            ->assertDontSee('Price table');
    }

    public function test_bogo_fare_is_halved_and_its_date_can_be_booked(): void
    {
        $package = TravelPackage::where('slug', '5DCY4')->firstOrFail();

        $this->get(route('packages.show', ['package' => $package->slug]))
            ->assertOk()
            ->assertSee('3 Apr 2027 — RM 1,600 pp (buy 1 free 1)', false);

        $this->get(route('booking', ['id' => $package->slug, 'date' => '2027-04-03']))
            ->assertOk()
            ->assertSee('2027-04-03');
    }

    public function test_package_pdf_streams_inline_and_downloads_as_an_attachment(): void
    {
        $inline = $this->get(route('packages.pdf', ['package' => 'sakura-rail']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $inline->getContent());
        $this->assertStringContainsString('inline', $inline->headers->get('Content-Disposition'));

        $download = $this->get(route('packages.pdf', ['package' => 'sakura-rail', 'download' => 1]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment', $download->headers->get('Content-Disposition'));
        $this->assertStringContainsString('sakura-rail-TravelWithYou.pdf', $download->headers->get('Content-Disposition'));
    }

    public function test_package_brochure_previews_and_downloads_the_matching_supplied_pdf(): void
    {
        $this->seed(TourPackageSeeder::class);
        $brochures = config('package_brochures.by_source_id');
        $this->assertCount(13, $brochures);
        $this->assertSame(
            count($brochures),
            TravelPackage::whereIn('source_id', array_keys($brochures))->count(),
        );

        foreach ($brochures as $brochure) {
            $this->assertFileExists(public_path('assets/Package_Example/'.$brochure));
        }

        $brochure = config('package_brochures.by_source_id.SILV-ENDEAVOUR-JUL2027');
        $path = public_path('assets/Package_Example/'.$brochure);
        $package = TravelPackage::where('source_id', 'SILV-ENDEAVOUR-JUL2027')->firstOrFail();
        $expectedHash = hash_file('sha256', $path);

        $this->assertNotFalse($expectedHash);

        $inline = $this->get(route('packages.pdf', ['package' => $package->slug]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        ob_start();
        $inline->baseResponse->sendContent();
        $this->assertSame($expectedHash, hash('sha256', ob_get_clean()));
        $this->assertStringContainsString('inline', $inline->headers->get('Content-Disposition'));
        $this->assertStringContainsString($package->slug.'-TravelWithYou.pdf', $inline->headers->get('Content-Disposition'));

        $download = $this->get(route('packages.pdf', ['package' => $package->slug, 'download' => 1]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        ob_start();
        $download->baseResponse->sendContent();
        $this->assertSame($expectedHash, hash('sha256', ob_get_clean()));
        $this->assertStringContainsString('attachment', $download->headers->get('Content-Disposition'));
        $this->assertStringContainsString($package->slug.'-TravelWithYou.pdf', $download->headers->get('Content-Disposition'));
    }

    public function test_guest_can_toggle_a_package_in_and_out_of_the_wishlist(): void
    {
        $package = TravelPackage::where('slug', 'sakura-rail')->firstOrFail();
        $this->post(route('wishlist.toggle', ['package' => $package->id]))
            ->assertRedirect()
            ->assertSessionHas('wishlist', [$package->id]);
        $this->get(route('home'))->assertOk()->assertSee('aria-pressed="true"', false);

        $this->post(route('wishlist.toggle', ['package' => $package->id]))
            ->assertRedirect()
            ->assertSessionHas('wishlist', []);
        $this->get(route('home'))->assertOk()->assertSee('aria-pressed="false"', false);
    }

    public function test_guest_can_toggle_wishlist_without_redirect_when_requesting_json(): void
    {
        $package = TravelPackage::where('slug', 'sakura-rail')->firstOrFail();

        $this->postJson(route('wishlist.toggle', ['package' => $package->id]))
            ->assertOk()
            ->assertJson(['saved' => true, 'message' => 'Journey saved to your list.'])
            ->assertSessionHas('wishlist', [$package->id]);

        $this->postJson(route('wishlist.toggle', ['package' => $package->id]))
            ->assertOk()
            ->assertJson(['saved' => false, 'message' => 'Journey removed from your saved list.'])
            ->assertSessionHas('wishlist', []);

        $user = User::factory()->create();
        $this->actingAs($user)
            ->postJson(route('wishlist.toggle', ['package' => $package->id]))
            ->assertOk()
            ->assertJson(['saved' => true])
            ->assertSessionHasNoErrors();
        $this->assertTrue($user->favorites()->whereKey($package->id)->exists());

        $this->postJson(route('wishlist.toggle', ['package' => $package->id]))
            ->assertOk()
            ->assertJson(['saved' => false]);
        $this->assertFalse($user->favorites()->whereKey($package->id)->exists());
    }

    public function test_guest_wishlist_is_kept_when_the_guest_registers(): void
    {
        $package = TravelPackage::where('slug', 'sakura-rail')->firstOrFail();
        $this->post(route('wishlist.toggle', ['package' => $package->id]))
            ->assertRedirect()
            ->assertSessionHas('wishlist', [$package->id]);

        $this->post(route('register.submit'), [
            'name' => 'Taylor Traveller',
            'email' => 'taylor@example.test',
            'password' => 'TravelTest2026!',
            'password_confirmation' => 'TravelTest2026!',
        ])->assertRedirect(route('account'))
            ->assertSessionMissing('wishlist');

        $user = User::where('email', 'taylor@example.test')->firstOrFail();
        $this->assertNotEmpty($user->username);
        $this->assertTrue($user->favorites()->whereKey($package->id)->exists());
        $this->get(route('account'))->assertOk()->assertSee('Sakura Rail Journey')->assertSee('Hello, Taylor Traveller.');
    }

    public function test_search_filters_the_database_catalogue(): void
    {
        $this->get(route('search', ['type' => 'Cruise']))
            ->assertOk()
            ->assertSee('Mediterranean Grand Cruise')
            ->assertDontSee('Kyoto Temple Retreat');

        $this->get(route('search', ['ship' => 'Aurelia Voyager']))
            ->assertOk()
            ->assertSee('Mediterranean Grand Cruise')
            ->assertDontSee('Aegean Island Hopper');
    }

    public function test_each_search_filter_matches_the_catalogue(): void
    {
        $cases = [
            [['q' => 'sakura'], 'Sakura Rail Journey', 'Kyoto Temple Retreat'],
            [['ship' => 'Coral Horizon'], 'Aegean Island Hopper', 'Mediterranean Grand Cruise'],
            [['dep_port' => 'Tokyo'], 'Sakura Rail Journey', 'Kyoto Temple Retreat'],
            [['dest_port' => 'Osaka'], 'Sakura Rail Journey', 'Kyoto Temple Retreat'],
            [['month' => '2027-03'], 'Sakura Rail Journey', 'Kyoto Temple Retreat'],
            [['price' => 'lt2'], 'Voyage Hainan', 'Sakura Rail Journey'],
            [['region' => 'Asia'], 'Sakura Rail Journey', 'Amalfi Coast Slow Escape'],
            [['country' => 'Japan'], 'Sakura Rail Journey', 'Amalfi Coast Slow Escape'],
            [['type' => 'Wildlife'], 'Maasai Mara Great Migration', 'Kyoto Temple Retreat'],
            [['duration' => '1-7'], 'Kyoto Temple Retreat', 'Sakura Rail Journey'],
        ];

        foreach ($cases as [$filters, $included, $excluded]) {
            $this->get(route('search', $filters))
                ->assertOk()
                ->assertSee($included)
                ->assertDontSee($excluded);
        }
    }

    public function test_search_bar_shows_all_scrollable_ship_and_port_filters(): void
    {
        $response = $this->get(route('home'))
            ->assertOk()
            ->assertSee('<span>Departure Port/City</span>', false)
            ->assertSee('data-filter-select', false)
            ->assertDontSee('__other_ships__')
            ->assertDontSee('data-other-ships-url')
            ->assertSee('href="mailto:enquiry@travelwithyou.my"', false);

        preg_match('/<select name="month">(.*?)<\/select>/s', $response->getContent(), $monthSelect);
        $this->assertNotEmpty($monthSelect);
        preg_match_all('/<option value="(\d{4}-\d{2})"/', $monthSelect[1], $months);

        $expectedMonths = collect(range(0, 11))
            ->map(fn ($offset) => now()->startOfMonth()->addMonths($offset)->format('Y-m'))
            ->all();
        $this->assertSame($expectedMonths, $months[1]);

        preg_match('/<select name="ship"[^>]*>(.*?)<\/select>/s', $response->getContent(), $shipSelect);
        $this->assertNotEmpty($shipSelect);
        preg_match_all('/<option value="([^"]*)"/', $shipSelect[1], $shipValues);
        $shipOptions = array_values(array_filter($shipValues[1], fn ($value) => $value !== ''));
        $expectedShips = TravelPackage::query()
            ->where('published', true)
            ->pluck('ship')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->assertSame($expectedShips, $shipOptions);

        preg_match('/<select name="dep_port"[^>]*>(.*?)<\/select>/s', $response->getContent(), $portSelect);
        $this->assertNotEmpty($portSelect);
        preg_match_all('/<option value="([^"]*)"/', $portSelect[1], $portValues);
        $portOptions = array_values(array_filter($portValues[1], fn ($value) => $value !== ''));
        $expectedPorts = TravelPackage::query()
            ->where('published', true)
            ->pluck('departure_port')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->assertSame($expectedPorts, $portOptions);
    }

    public function test_trending_searches_return_their_expected_packages(): void
    {
        $searches = [
            [['q' => 'sakura'], 'Sakura Rail Journey'],
            [['q' => 'Mediterranean'], 'Mediterranean Grand Cruise'],
            [['q' => 'Wildlife'], 'Maasai Mara Great Migration'],
            [['q' => 'Beach', 'price' => '2-3.5'], 'Amalfi Coast Slow Escape'],
            [['q' => 'aurora'], 'Iceland Ring Road & Aurora'],
            [['q' => 'Greece'], 'Aegean Island Hopper'],
        ];

        foreach ($searches as [$filters, $expected]) {
            $this->get(route('search', $filters))->assertOk()->assertSee($expected);
        }
    }

    public function test_country_page_filters_by_month_duration_price_and_sort(): void
    {
        $url = fn (array $filters = []) => route('destinations.country', ['country' => 'japan'] + $filters);

        $this->get($url(['month' => '2027-03']))
            ->assertOk()
            ->assertSee('Sakura Rail Journey')
            ->assertDontSee('Kyoto Temple Retreat');

        $this->get($url(['duration' => 'short']))
            ->assertOk()
            ->assertSee('Kyoto Temple Retreat')
            ->assertDontSee('Sakura Rail Journey');

        $this->get($url(['duration' => 'long']))
            ->assertOk()
            ->assertSee('Sakura Rail Journey')
            ->assertDontSee('Kyoto Temple Retreat');

        $this->get($url(['max_price' => '2500']))
            ->assertOk()
            ->assertSee('Kyoto Temple Retreat')
            ->assertDontSee('Sakura Rail Journey');

        $content = $this->get($url(['sort' => 'price-asc']))->assertOk()->getContent();
        $this->assertLessThan(strpos($content, 'Sakura Rail Journey'), strpos($content, 'Kyoto Temple Retreat'));
    }

    public function test_search_logs_nonempty_queries_and_filters(): void
    {
        $this->get(route('search', ['q' => 'Greece']));
        $this->assertDatabaseHas('search_logs', ['query' => 'Greece']);

        $this->get(route('search', ['region' => 'Asia']));
        $filterSearch = SearchLog::query()->whereNull('query')->latest('id')->firstOrFail();
        $this->assertSame(['region' => 'Asia'], $filterSearch->filters);
    }

    public function test_search_pagination_preserves_active_filters(): void
    {
        $package = TravelPackage::where('slug', 'sakura-rail')->firstOrFail();
        $attributes = $package->only([
            'country', 'country_id', 'region', 'type', 'days', 'description', 'details',
            'price_from', 'price_to', 'rating', 'reviews', 'departure_port',
            'destination_port', 'departures', 'image', 'published',
        ]);

        for ($index = 1; $index <= 13; $index++) {
            TravelPackage::create($attributes + [
                'slug' => 'filtered-pagination-'.$index,
                'name' => 'Filtered Japan cultural trip '.$index,
            ]);
        }

        $response = $this->get(route('search', ['type' => 'Cultural', 'region' => 'Asia']))
            ->assertOk()
            ->assertSee('Filtered Japan cultural trip 1');
        $this->assertStringContainsString('type=Cultural', $response->getContent());
        $this->assertStringContainsString('region=Asia', $response->getContent());
        $this->assertStringContainsString('page=2', $response->getContent());
    }

    public function test_search_sort_options_order_matching_packages(): void
    {
        $url = fn (string $sort) => route('search', ['country' => 'Japan', 'sort' => $sort]);
        $firstPosition = fn (string $content, string $package) => strpos($content, $package);

        $ascending = $this->get($url('price-asc'))->assertOk()->getContent();
        $descending = $this->get($url('price-desc'))->assertOk()->getContent();
        $rating = $this->get($url('rating'))->assertOk()->getContent();
        $duration = $this->get($url('duration'))->assertOk()->getContent();

        $this->assertLessThan($firstPosition($ascending, 'Sakura Rail Journey'), $firstPosition($ascending, 'Kyoto Temple Retreat'));
        $this->assertLessThan($firstPosition($descending, 'Kyoto Temple Retreat'), $firstPosition($descending, 'Sakura Rail Journey'));
        $this->assertLessThan($firstPosition($rating, 'Kyoto Temple Retreat'), $firstPosition($rating, 'Sakura Rail Journey'));
        $this->assertLessThan($firstPosition($duration, 'Sakura Rail Journey'), $firstPosition($duration, 'Kyoto Temple Retreat'));
    }

    public function test_destinations_title_uses_seeded_country_and_region_counts(): void
    {
        $this->get(route('destinations'))
            ->assertOk()
            ->assertSee('Eleven countries, six regions.')
            ->assertSee('Choose a country to see every tour package we run there.');
    }

    public function test_trip_pagination_keeps_the_last_page_summary_and_controls_together(): void
    {
        $package = TravelPackage::firstOrFail();
        $attributes = $package->only([
            'country',
            'region',
            'type',
            'days',
            'description',
            'details',
            'price_from',
            'price_to',
            'rating',
            'reviews',
            'departure_port',
            'destination_port',
            'departures',
            'image',
            'published',
        ]);

        for ($index = 1; $index <= 13; $index++) {
            TravelPackage::create($attributes + [
                'slug' => 'pagination-trip-'.$index,
                'name' => 'Pagination trip '.$index,
            ]);
        }

        $this->get(route('search', ['page' => 3]))
            ->assertOk()
            ->assertSee('Showing')
            ->assertSee('25')
            ->assertSee('27')
            ->assertSee('rel="prev"', false)
            ->assertSee('aria-label="Pagination Navigation"', false);
    }

    public function test_checkout_persists_a_paid_booking_and_clears_the_cart(): void
    {
        $package = TravelPackage::where('slug', 'sakura-rail')->firstOrFail();
        $this->post(route('cart.add', ['package' => $package->id]), [
            'departure_date' => $package->departures[0],
            'guests' => 2,
        ])->assertRedirect(route('cart'));

        $this->get(route('checkout'))->assertOk();
        $response = $this->post(route('checkout.place'), [
            'name' => 'Taylor Traveller',
            'email' => 'taylor@example.test',
            'phone' => '+60123456789',
            ...$this->validCheckoutDetails(),
        ]);
        $response->assertRedirect(route('confirmation'))->assertSessionMissing('cart');

        $booking = Booking::firstOrFail();
        $this->assertSame('Paid', $booking->status);
        $this->assertSame(2, $booking->guests);
        $this->assertEqualsWithDelta(7492.14, $booking->total, 0.01);
        $this->get(route('confirmation'))->assertOk()->assertSee($booking->order_reference);
    }

    public function test_valid_offer_code_is_applied_to_booking_totals(): void
    {
        $package = TravelPackage::where('slug', 'amalfi-escape')->firstOrFail();
        $this->post(route('cart.add', ['package' => $package->id]), [
            'departure_date' => $package->departures[0],
            'guests' => 1,
        ]);
        $this->post(route('cart.promo'), ['code' => 'amalfi20'])->assertSessionHas('promo_code', 'AMALFI20');
        $this->post(route('checkout.place'), [
            'name' => 'Taylor Traveller',
            'email' => 'taylor@example.test',
            'phone' => '+60123456789',
            ...$this->validCheckoutDetails(),
        ]);

        $booking = Booking::firstOrFail();
        $this->assertEqualsWithDelta(476.8, $booking->discount, 0.01);
        $this->assertEqualsWithDelta(133.5, $booking->taxes, 0.01);
        $this->assertEqualsWithDelta(2040.7, $booking->total, 0.01);
    }

    public function test_contact_enquiry_is_saved_to_the_database(): void
    {
        $this->post(route('contact.send'), [
            'name' => 'Taylor Traveller',
            'email' => 'taylor@example.test',
            'subject' => 'Booking enquiry',
            'message' => 'Could you help us plan a spring departure?',
        ])->assertRedirect()
            ->assertSessionHas('success', 'Message sent. We reply within one working day.');

        $this->assertDatabaseHas('enquiries', ['email' => 'taylor@example.test', 'subject' => 'Booking enquiry', 'status' => 'New']);
        $this->assertSame(1, Enquiry::count());
    }

    public function test_contact_form_rejects_unlisted_topics_and_uses_the_prototype_error(): void
    {
        $this->from(route('contact'))->post(route('contact.send'), [
            'name' => 'Taylor Traveller',
            'email' => 'taylor@example.test',
            'subject' => 'A trip to Japan',
            'message' => 'Could you help us plan a spring departure?',
        ])->assertRedirect(route('contact'))
            ->assertSessionHas('error', 'Please add your name, a valid email and a message.')
            ->assertSessionHasErrors('subject');

        $this->assertSame(0, Enquiry::count());
    }

    public function test_auth_pages_use_prototype_copy_without_demo_hints(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Welcome back')
            ->assertSee('Log in to see your trips and saved packages.')
            ->assertSee('Email or username')
            ->assertDontSee('Demo account')
            ->assertDontSee('sarah.lim')
            ->assertDontSee('travel123');

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Join TravelWithYou')
            ->assertSee('Save trips, speed up checkout and get new-user deals.')
            ->assertSee('Confirm password')
            ->assertDontSee('name="username"', false);

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Reset password')
            ->assertSee('We will email you a reset link.');
    }

    public function test_registration_does_not_require_username_and_enforces_password_rules(): void
    {
        $this->from(route('register'))->post(route('register.submit'), [
            'name' => 'Morgan Traveller',
            'email' => 'morgan@example.test',
            'password' => 'Ab1',
            'password_confirmation' => 'Ab1',
        ])->assertRedirect(route('register'))
            ->assertSessionHasErrors('password');

        $this->from(route('register'))->post(route('register.submit'), [
            'name' => 'Morgan Traveller',
            'email' => 'morgan@example.test',
            'password' => 'LongPassword',
            'password_confirmation' => 'LongPassword',
        ])->assertRedirect(route('register'))
            ->assertSessionHasErrors('password');

        $this->post(route('register.submit'), [
            'name' => 'Morgan Traveller',
            'email' => 'morgan@example.test',
            'password' => 'LongPass123',
            'password_confirmation' => 'LongPass123',
        ])->assertRedirect(route('account'));

        $user = User::where('email', 'morgan@example.test')->firstOrFail();
        $this->assertNotEmpty($user->username);
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_accepts_email_and_username(): void
    {
        $user = User::factory()->create(['email' => 'login@example.test', 'username' => 'morgan.login']);

        $this->post(route('login.submit'), [
            'email' => 'login@example.test',
            'password' => 'password',
        ])->assertRedirect(route('account'));
        $this->assertAuthenticatedAs($user);

        auth()->logout();
        $this->post(route('login.submit'), [
            'email' => 'morgan.login',
            'password' => 'password',
        ])->assertRedirect(route('account'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_faq_uses_grouped_exclusive_native_details_items_and_blog_uses_seeded_slugs(): void
    {
        Faq::create([
            'question' => 'How do I book a tour package?',
            'answer' => 'Old FAQ content.',
            'sort_order' => 99,
        ]);
        $this->seed(FaqSeeder::class);
        $this->assertDatabaseMissing('faqs', ['question' => 'How do I book a tour package?']);
        $this->assertDatabaseCount('faqs', 17);

        $faq = $this->get(route('faq'))->assertOk()->getContent();
        $this->assertSame(17, substr_count($faq, 'name="faq"'));
        $this->assertSame(0, substr_count($faq, '<details name="faq" open'));
        $this->assertStringContainsString('.faq-item[open] .faq-toggle::before { content: "\\2212"; }', file_get_contents(public_path('css/site.css')));
        $this->assertStringNotContainsString('âˆ’', file_get_contents(public_path('css/site.css')));
        $this->assertSame(5, substr_count($faq, 'href="#faq-category-'));
        $this->assertSame(5, substr_count($faq, 'id="faq-category-'));
        $this->assertSame(5, substr_count($faq, 'class="faq-category-heading" id="faq-category-'));
        $this->assertStringContainsString('.faq-category-heading {', file_get_contents(public_path('css/site.css')));
        $this->assertStringContainsString('scroll-margin-top: 88px;', file_get_contents(public_path('css/site.css')));
        $this->assertStringNotContainsString('17 ANSWERS', $faq);
        $this->assertStringContainsString('General Information', $faq);
        $this->assertStringContainsString('Booking and Reservation', $faq);
        $this->assertStringContainsString('Payment and Pricing', $faq);
        $this->assertStringContainsString('Tour and Travel Details', $faq);
        $this->assertStringContainsString('Special Services', $faq);
        $this->assertStringContainsString('Who is Travel With You?', $faq);
        $this->assertStringContainsString('Do you offer assistance for unique activities, like adventure travel or cultural tours?', $faq);
        $this->assertStringNotContainsString('i. Who is Travel With You?', $faq);
        $this->assertStringNotContainsString('1. General Information', $faq);
        $this->assertStringContainsString('We arrange a variety of activities and experiences tailored to specific interests, including adventure, cultural tours, and immersive local experiences.', $faq);
        $this->assertStringContainsString('Still need help?', $faq);
        $this->assertStringContainsString('Contact us', $faq);
        $this->assertStringContainsString('Good questions. Thoughtful answers.', $faq);
        $this->assertStringContainsString('Everything you need to know before planning your next trip with us. Browse by topic or open a question to find the details.', $faq);

        $this->get(route('blog'))
            ->assertOk()
            ->assertSee('Travel guides')
            ->assertSee('Field notes.')
            ->assertSee('Japan · 6 min read');

        foreach (['sakura-timing', 'cruise-first', 'migration', 'iceland-winter'] as $slug) {
            $this->get(route('blog.show', ['slug' => $slug]))
                ->assertOk()
                ->assertSee('Travel guides')
                ->assertSee('guide-hero-image', false);
        }
    }

    public function test_about_and_legal_pages_use_prototype_copy_and_configured_date(): void
    {
        $about = $this->get(route('about'))
            ->assertOk()
            ->assertSee('About Us')
            ->assertSee('Your Trusted Partner for Travel and Tours in Malaysia')
            ->assertSee('Established in early 2018, Travel With You Sdn Bhd is built on a solid foundation of over 15 years of expertise in the travel and tourism industry. Our mission is to deliver unparalleled service and professional guidance for both local and international travel planning, ensuring every journey is seamless and memorable.')
            ->assertSee('Our Services')
            ->assertSee('We offer an extensive range of travel services designed to cater to diverse needs. These include MICE Tours (Meetings, Incentives, Conferences, and Events), group tours, personalized free-and-easy packages, ground-only tours, hotel and resort bookings, airport transfers, and exciting travel activities. Additionally, our team provides valuable insights and expert tips for destinations across Asia, Europe, and America, helping clients enjoy well-prepared and enriching journeys.')
            ->assertSee('Tailored Tour Packages')
            ->assertSee('Our thoughtfully curated tour packages are crafted to meet market demand while showcasing the best of local and international destinations. We aim to create unforgettable travel experiences tailored to suit the unique interests and preferences of each client.')
            ->assertSee('Our Team')
            ->assertSee('Our team represents a vibrant mix of cultures, reflecting Malaysia’s rich diversity. This multicultural foundation not only strengthens our relationships with loyal clients but also inspires new travelers to join us in discovering the wonders of the world. Together, we are dedicated to making every journey extraordinary.')
            ->assertSee('Director of Sales')
            ->assertSee('Secretary')
            ->getContent();
        $this->assertSame(4, substr_count($about, 'class="section-number"'));
        $this->assertSame(2, substr_count($about, 'about-section-reversed'));
        $this->assertSame(3, substr_count($about, 'class="about-story-photo '));
        $this->assertSame(2, substr_count($about, 'about-story-photo-ratio-4-3'));
        $this->assertSame(1, substr_count($about, 'about-story-photo-ratio-16-9'));
        $this->assertSame(2, substr_count($about, 'about-team-photo-empty'));
        $this->assertSame(2, substr_count($about, 'about-team-photo-ratio-3-4'));
        $this->assertStringNotContainsString('Mission &amp; Vision', $about);
        $this->assertStringNotContainsString('Awards &amp; Certifications', $about);
        $this->assertStringNotContainsString('Press / Media', $about);
        $this->assertStringNotContainsString('Amara Okafor', $about);
        $this->assertStringNotContainsString('Daniel Reyes', $about);

        config(['company.legal_updated' => '2026-09-01']);
        foreach (['terms', 'privacy'] as $policy) {
            $response = $this->get(route($policy))
                ->assertOk()
                ->assertSee('Legal')
                ->assertSee('Last updated 1 September 2026.')
                ->assertSee('policy-sections');
            $this->assertSame($policy === 'terms' ? 12 : 9, substr_count($response->getContent(), '<article>'));
            if ($policy === 'terms') {
                $response->assertSee('TOURISM INDUSTRY ACT 1992')
                    ->assertSee('TOURISM INDUSTRY (TOUR OPERATING BUSINESS AND TRAVEL AGENCY BUSINESS) REGULATIONS 1992')
                    ->assertSee('A maximum deposit of')
                    ->assertSee('25% of the tour fare per person')
                    ->assertSee('Free Independent Traveller (FIT)')
                    ->assertSee('3.1')
                    ->assertSee('For FIT Tour Packages')
                    ->assertSee('3.2')
                    ->assertSee('For Group Tour Packages')
                    ->assertSee('Cancellation must be made in')
                    ->assertSee('id="terms-cancellation"', false)
                    ->assertSee('writting.')
                    ->assertSee('Act of God, war, strikes, or govermnent orders.')
                    ->assertSee('at least 6 months')
                    ->assertSee('accomodation')
                    ->assertSee('setails')
                    ->assertSee('All tour members are encouraged to purchase travel insurance');
            } elseif ($policy === 'privacy') {
                $response->assertSee("Your privacy is important to us. Here's how we handle your data.", false)
                    ->assertSee('Welcome to Travel With You Sdn Bhd.')
                    ->assertSee('Personal Information:')
                    ->assertSee('Payment Information:')
                    ->assertSee('Travel Information:')
                    ->assertSee('Marketing and Promotions:')
                    ->assertSee('Legal and Compliance:')
                    ->assertSee('Correction:')
                    ->assertSee('enquiry@travelwithyou.my')
                    ->assertSee('Stellar Suites SS-10-02, Jalan Puteri 4/7, Bandar Puteri 47100 Puchong, Selangor')
                    ->assertSee('+60 13-2883003');
            }
        }

        $this->get('/cancellation')->assertNotFound();
        $this->get(route('terms'))->assertDontSee('Cancellation &amp; Refunds', false);
    }

    public function test_unknown_page_uses_prototype_not_found_page(): void
    {
        $this->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertSee('Error 404')
            ->assertSee('Off the map.')
            ->assertSee('That page has sailed. Let us point you back to dry land.')
            ->assertSee('Back home')
            ->assertSee('Browse destinations');
    }

    private function validCheckoutDetails(): array
    {
        return [
            'contact_address' => '12 Jalan Example, Kuala Lumpur',
            'traveller_name' => 'Taylor Traveller',
            'traveller_dob' => '1990-04-12',
            'nationality' => 'Malaysian',
            'passport_number' => 'A12345678',
            'passport_expiry' => '2030-01-01',
            'payment_method' => 'card',
            'billing_same' => '1',
            'terms' => '1',
            'privacy' => '1',
            'cancellation' => '1',
            'card_number' => '4242424242424242',
            'card_name' => 'Taylor Traveller',
            'card_expiry' => '08/29',
            'card_cvc' => '123',
        ];
    }

    public function test_legacy_packages_url_redirects_to_search_and_preserves_query(): void
    {
        $this->get('/packages?q=Japan')
            ->assertStatus(301)
            ->assertRedirect(route('search', ['q' => 'Japan']));
    }

    public function test_newsletter_subscription_validates_and_deduplicates_email(): void
    {
        $this->from(route('home'))->post(route('newsletter.subscribe'), ['email' => 'Taylor@Example.test'])
            ->assertRedirect(route('home'))
            ->assertSessionHas('success', 'You are subscribed. Welcome aboard.');
        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'taylor@example.test']);
        $this->assertSame(1, NewsletterSubscriber::count());

        $this->from(route('home'))->post(route('newsletter.subscribe'), ['email' => 'taylor@example.test'])
            ->assertRedirect(route('home'))
            ->assertSessionHas('success', 'You are subscribed. Welcome aboard.');
        $this->assertSame(1, NewsletterSubscriber::count());

        $this->from(route('home'))->post(route('newsletter.subscribe'), ['email' => 'not-an-email'])
            ->assertRedirect(route('home'))
            ->assertSessionHas('error', 'Please enter a valid email');
        $this->assertSame(1, NewsletterSubscriber::count());
    }

    public function test_toasts_have_distinct_success_and_error_semantics(): void
    {
        $this->withSession(['success' => 'Saved successfully.'])->get(route('home'))
            ->assertSee('role="status"', false)
            ->assertSee('data-auto-dismiss="true"', false)
            ->assertDontSee('data-toast-close', false);

        $this->withSession(['error' => 'Please try again.'])->get(route('home'))
            ->assertSee('role="alert"', false)
            ->assertSee('data-auto-dismiss="false"', false)
            ->assertSee('data-toast-close', false);
    }

    public function test_footer_displays_only_enabled_payment_methods(): void
    {
        config(['payments.methods' => ['visa' => true, 'mc' => false, 'amex' => false, 'paypal' => false, 'bank' => false]]);

        $this->get(route('home'))
            ->assertSee('VISA')
            ->assertDontSee('PayPal')
            ->assertDontSee('AMEX')
            ->assertDontSee('>Admin</a>', false);
    }

    public function test_public_information_routes_render(): void
    {
        $routes = [
            route('destinations'),
            route('destinations.region', ['region' => 'Asia']),
            route('destinations.country', ['country' => 'japan']),
            route('deals'),
            route('ships'),
            route('ships.show', ['ship' => 'coral-horizon']),
            route('about'),
            route('contact'),
            route('faq'),
            route('terms'),
            route('privacy'),
            route('blog'),
            route('blog.show', ['slug' => 'sakura-timing']),
            route('login'),
            route('register'),
            route('password.request'),
        ];

        foreach ($routes as $url) {
            $this->get($url)->assertOk()->assertDontSee('@if');
        }

        $this->get(route('blog.show', ['slug' => 'slow-travel-in-kyoto']))
            ->assertRedirect(route('blog.show', ['slug' => 'a-slower-way-through-kyoto']))
            ->assertStatus(301);
        $this->get(route('blog.show', ['slug' => 'when-to-see-the-great-migration']))
            ->assertRedirect(route('blog.show', ['slug' => 'when-to-see-the-mara-river-crossings']))
            ->assertStatus(301);
        $this->get(route('blog.show', ['slug' => 'amalfi-without-the-rush']))
            ->assertRedirect(route('blog.show', ['slug' => 'the-amalfi-coast-without-the-rush']))
            ->assertStatus(301);

        $this->get(route('ships'))->assertOk()->assertSee('Aurelia Voyager')->assertSee('Navigator of the Seas');
        $this->get(route('ships.show', ['ship' => 'coral-horizon']))
            ->assertOk()
            ->assertSee('Aegean Island Hopper')
            ->assertDontSee('Mediterranean Grand Cruise');
        $this->get(route('ships.show', ['ship' => 'unknown-vessel']))->assertNotFound();
    }

    public function test_footer_groups_explore_and_about_links_without_cart_or_account(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertSame(1, preg_match('/<footer class="site-footer">(.*?)<\/footer>/s', $html, $matches));
        $footer = $matches[1];

        $this->assertStringContainsString('<h2>Explore</h2>', $footer);
        $this->assertStringContainsString('<h2>About</h2>', $footer);
        $this->assertStringContainsString('Travel Guides', $footer);
        $this->assertStringContainsString('Contact Us', $footer);
        $this->assertStringNotContainsString('<h2>Contact</h2>', $footer);
        $this->assertStringContainsString('class="footer-email" href="mailto:enquiry@travelwithyou.my"', $footer);
        $this->assertStringContainsString('enquiry@travelwithyou.my', $footer);
        $this->assertStringNotContainsString('href="'.route('cart').'"', $footer);
        $this->assertStringNotContainsString('href="'.route('account').'"', $footer);
    }

    public function test_deals_show_seeded_active_offers_and_filter_by_type_and_search(): void
    {
        $allDeals = $this->get(route('deals'))->assertOk()->getContent();
        $this->assertSame(8, substr_count($allDeals, 'class="deal-card deal-card-port"'));
        $this->assertStringContainsString('Savings with the small print up front.', $allDeals);
        $this->assertStringContainsString('Terms:', $allDeals);

        $this->get(route('deals', ['tab' => 'Group discount']))
            ->assertOk()
            ->assertSee('Maasai Mara Great Migration')
            ->assertSee('Andaman Island Escape')
            ->assertDontSee('Sakura Rail Journey');

        $this->get(route('deals', ['q' => 'Amalfi']))
            ->assertOk()
            ->assertSee('Amalfi Coast Slow Escape')
            ->assertDontSee('Aegean Island Hopper');

        TravelDeal::where('slug', 'd1')->update(['active' => false]);
        TravelDeal::where('slug', 'd2')->update(['expires_at' => now()->subSecond()]);
        $activeOnly = $this->get(route('deals'))->assertOk()->getContent();
        $this->assertSame(6, substr_count($activeOnly, 'class="deal-card deal-card-port"'));
        $this->assertStringNotContainsString('Amalfi Coast Slow Escape', $activeOnly);
        $this->assertStringNotContainsString('Aegean Island Hopper', $activeOnly);

        TravelDeal::where('slug', 'd3')->update(['type' => null]);
        $typedDealsOnly = $this->get(route('deals'))->assertOk()->getContent();
        $this->assertSame(5, substr_count($typedDealsOnly, 'class="deal-card deal-card-port"'));
    }

    public function test_deal_book_now_adds_two_or_six_guests_to_the_cart(): void
    {
        $amalfi = TravelPackage::where('slug', 'amalfi-escape')->firstOrFail();
        $this->get(route('deals'))->assertSee('name="guests" value="2"', false);
        $this->post(route('cart.add', ['package' => $amalfi->id]), [
            'departure_date' => $amalfi->departures[0],
            'guests' => 2,
        ])->assertRedirect(route('cart'));
        $this->assertSame(2, session('cart.0.guests'));

        $mara = TravelPackage::where('slug', 'mara-migration')->firstOrFail();
        $this->get(route('deals', ['tab' => 'Group discount']))->assertSee('name="guests" value="6"', false);
        $this->post(route('cart.add', ['package' => $mara->id]), [
            'departure_date' => $mara->departures[0],
            'guests' => 6,
        ])->assertRedirect(route('cart'));
        $this->assertSame(6, session('cart.1.guests'));
    }

    public function test_ship_listing_and_detail_render_all_database_tabs(): void
    {
        $listing = $this->get(route('ships'))->assertOk()->getContent();
        $this->assertCount(5, Ship::all());
        $this->assertSame(5, substr_count($listing, 'class="ship-list-card"'));

        foreach (Ship::all() as $ship) {
            $response = $this->get(route('ships.show', ['ship' => $ship->slug]))
                ->assertOk()
                ->assertSee($ship->name)
                ->assertSee('Overview')
                ->assertSee('Gallery')
                ->assertSee('Deck plans')
                ->assertSee('Amenities')
                ->assertSee('Cabins')
                ->assertSee('Recent cruises');
            $this->assertSame(6, substr_count($response->getContent(), 'role="tabpanel"'));
        }

        $this->get(route('ships.show', ['ship' => 'coral-horizon', 'tab' => 'Gallery']))
            ->assertOk()
            ->assertSee('aria-selected="true"', false)
            ->assertSee('data-ship-panel="Gallery"', false);
    }

    public function test_navigator_gallery_includes_its_video_without_adding_video_to_other_ships(): void
    {
        $this->seed(ShipMediaSeeder::class);

        $this->get(route('ships.show', ['ship' => 'navigator-of-the-seas', 'tab' => 'Gallery']))
            ->assertOk()
            ->assertSee('<video', false)
            ->assertSee('assets/video/Ship_example.mp4', false)
            ->assertSee('poster=', false);

        $this->get(route('ships.show', ['ship' => 'coral-horizon', 'tab' => 'Gallery']))
            ->assertOk()
            ->assertDontSee('<video', false);
    }

    public function test_admin_area_requires_an_administrator_account(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

        $admin = User::create([
            'name' => 'Site Admin',
            'email' => 'admin@example.test',
            'password' => 'SafePass1234',
        ]);
        $admin->forceFill(['is_admin' => true, 'mfa_enabled' => true, 'mfa_secret' => Totp::generateSecret()])->save();

        $this->actingAs($admin)->withSession(['admin_mfa_verified' => true, 'admin_last_activity' => time()])
            ->get(route('admin.dashboard'))->assertOk()->assertSee('Users');
    }

    public function test_admin_sections_and_package_form_render(): void
    {
        $admin = User::create([
            'name' => 'Site Admin',
            'email' => 'admin@example.test',
            'password' => 'SafePass1234',
        ]);
        $admin->forceFill(['is_admin' => true, 'mfa_enabled' => true, 'mfa_secret' => Totp::generateSecret()])->save();
        $this->actingAs($admin)->withSession(['admin_mfa_verified' => true, 'admin_last_activity' => time()]);

        foreach (['packages', 'deals', 'bookings', 'enquiries', 'guides', 'media', 'users', 'company'] as $section) {
            $this->get(route('admin.'.$section))->assertOk()->assertDontSee('@if');
        }
        $this->get(route('admin.packages.create'))
            ->assertOk()
            ->assertDontSee('Advanced · brochure data')
            ->assertDontSee('name="source_data_json"', false)
            ->assertSee('data-guest-types', false)
            ->assertSee('data-departure-prices', false)
            ->assertSee('data-package-addons', false);
        $package = TravelPackage::create([
            'slug' => 'package-form-test',
            'name' => 'Package Form Test',
            'country' => 'Malaysia',
            'region' => 'Asia',
            'type' => 'Adventure',
            'days' => 1,
            'description' => 'Test package summary.',
            'details' => 'Test package details.',
            'price_from' => 100,
            'price_to' => 200,
            'departures' => ['2027-01-01'],
            'image' => 'package-form-test.jpg',
        ]);
        $this->get(route('admin.packages.edit', $package))
            ->assertOk()
            ->assertDontSee('Advanced · brochure data')
            ->assertDontSee('name="source_data_json"', false)
            ->assertSee('data-guest-types', false)
            ->assertSee('data-departure-prices', false)
            ->assertSee('data-package-addons', false);
    }

    public function test_admin_can_add_a_database_package(): void
    {
        $admin = User::create([
            'name' => 'Site Admin',
            'email' => 'admin@example.test',
            'password' => 'SafePass1234',
        ]);
        $admin->forceFill(['is_admin' => true, 'mfa_enabled' => true, 'mfa_secret' => Totp::generateSecret()])->save();

        $this->actingAs($admin)->withSession(['admin_mfa_verified' => true, 'admin_last_activity' => time()])
            ->post(route('admin.packages.store'), [
                'name' => 'Highlands Weekend',
                'country' => 'Malaysia',
                'region' => 'Asia',
                'type' => 'Adventure',
                'days' => 4,
                'description' => 'A small group escape to the highlands.',
                'details' => 'Walk forest trails and spend slow afternoons in the hills.',
                'price_from' => 800,
                'price_to' => 1200,
                'image' => '1624253321171-1be53e12f5f4',
                'departure_port' => 'Kuala Lumpur',
                'destination_port' => 'Cameron Highlands',
                'departures_text' => "2027-08-01\n2027-09-01",
                'published' => '1',
            ])->assertRedirect(route('admin.packages'));

        $this->assertDatabaseHas('travel_packages', ['slug' => 'highlands-weekend', 'published' => true]);
    }

    public function test_imported_tour_package_renders_and_can_be_edited_by_admin(): void
    {
        $this->seed(TourPackageSeeder::class);
        $package = TravelPackage::where('source_id', 'JPD-12')->firstOrFail();
        $this->assertSame(13, TravelPackage::whereNotNull('source_id')->count());

        $this->get(route('packages.show', ['package' => $package->slug]))
            ->assertOk()
            ->assertSee('Daily trip plan')
            ->assertSee('Not included')
            ->assertSee('Noboribetsu Date Jidaimura')
            ->assertSee('4,990');
        $this->get(route('search', ['type' => 'Ocean cruise']))
            ->assertOk()
            ->assertSee('Alaska Discoverer');

        $admin = User::create([
            'name' => 'Site Admin',
            'email' => 'tour-admin@example.test',
            'password' => 'SafePass1234',
        ]);
        $admin->forceFill(['is_admin' => true, 'mfa_enabled' => true, 'mfa_secret' => Totp::generateSecret()])->save();
        $sourceData = $package->source_data;
        $sourceData['itinerary'][1]['title'] = 'Updated itinerary title';

        $this->actingAs($admin)->withSession(['admin_mfa_verified' => true, 'admin_last_activity' => time()])
            ->put(route('admin.packages.update', $package), [
                'name' => 'Updated Hokkaido Journey',
                'slug' => $package->slug,
                'country' => $package->country,
                'region' => $package->region,
                'type' => 'Guided tour',
                'ship' => '',
                'brand' => $package->brand,
                'operator' => $package->operator,
                'days' => $package->days,
                'nights' => $package->nights,
                'description' => $package->description,
                'details' => $package->details,
                'price_from' => $package->price_from,
                'price_to' => $package->price_to,
                'image' => $package->image,
                'departure_port' => $package->departure_port ?: 'Kuala Lumpur',
                'destination_port' => $package->destination_port ?: 'Sapporo',
                'departures_text' => implode("\n", $package->departures),
                'destinations_text' => implode("\n", $package->destinations),
                'source_data_json' => json_encode($sourceData, JSON_UNESCAPED_UNICODE),
                'published' => '1',
            ])->assertRedirect(route('admin.packages'));

        $package->refresh();
        $this->assertSame('Updated Hokkaido Journey', $package->name);
        $this->assertSame('Updated itinerary title', $package->itinerary[1]['title']);
        $this->get(route('packages.show', ['package' => $package->slug]))
            ->assertOk()
            ->assertSee('Updated itinerary title');
    }

    public function test_admin_login_requires_a_valid_totp_code(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $admin = User::create([
            'name' => 'Site Admin',
            'email' => 'admin@example.test',
            'password' => 'SafePass1234',
        ]);
        $admin->forceFill(['is_admin' => true, 'mfa_enabled' => true, 'mfa_secret' => $secret])->save();

        $this->post(route('admin.login.submit'), [
            'email' => $admin->email,
            'password' => 'SafePass1234',
        ])->assertRedirect(route('admin.mfa'));

        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.mfa'));

        $this->post(route('admin.mfa.verify'), ['code' => Totp::codeForTime($secret, time())])
            ->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_first_admin_sign_in_requires_authenticator_setup(): void
    {
        $admin = User::create([
            'name' => 'Site Admin',
            'email' => 'admin@example.test',
            'password' => 'SafePass1234',
        ]);
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)->withSession(['admin_mfa_verified' => false])
            ->get(route('admin.mfa.setup'))
            ->assertOk()
            ->assertSee('Setup key')
            ->assertSee('Open authenticator setup link');
    }

    public function test_inactive_admin_session_is_invalidated(): void
    {
        $admin = User::create([
            'name' => 'Site Admin',
            'email' => 'admin@example.test',
            'password' => 'SafePass1234',
        ]);
        $admin->forceFill(['is_admin' => true, 'mfa_enabled' => true, 'mfa_secret' => Totp::generateSecret()])->save();

        $this->actingAs($admin)
            ->withSession(['admin_mfa_verified' => true, 'admin_last_activity' => time() - 901])
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('error');
    }

    public function test_public_registration_cannot_grant_admin_access(): void
    {
        $this->post(route('register.submit'), [
            'name' => 'Taylor Traveller',
            'email' => 'taylor@example.test',
            'phone' => '+60123456789',
            'password' => 'SafePass1234',
            'password_confirmation' => 'SafePass1234',
            'is_admin' => true,
        ])->assertRedirect(route('account'));

        $this->assertDatabaseHas('users', ['email' => 'taylor@example.test', 'is_admin' => false]);
    }

    public function test_account_requires_authentication_and_renders_all_tabs(): void
    {
        $this->get(route('account'))->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('account'))
            ->assertOk()
            ->assertSee('My account')
            ->assertSee('Hello, '.$user->name.'.')
            ->assertSee('Bookings')
            ->assertSee('Wishlist')
            ->assertSee('Travellers')
            ->assertSee('Payment methods')
            ->assertSee('Notifications')
            ->assertSee('Support')
            ->assertSee('Security')
            ->assertSee('No saved packages yet.')
            ->assertSee('Your username is unique and is used to log in.')
            ->assertSee('900101-14-5678')
            ->assertSee('Visa ending 4242')
            ->assertSee('Change travel date for TWY-48215')
            ->assertSee('Add an extra layer of security');
    }

    public function test_profile_updates_username_and_username_can_be_used_to_log_in(): void
    {
        $user = User::factory()->create(['username' => 'old.traveller']);

        $this->actingAs($user)->put(route('account.profile.update'), [
            'username' => 'New.Traveller',
            'email' => $user->email,
            'phone' => '+60123456789',
        ])->assertRedirect(route('account', ['tab' => 'profile']))
            ->assertSessionHas('success', 'Profile updated');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'username' => 'new.traveller',
            'phone' => '+60123456789',
        ]);
        auth()->logout();

        $this->post(route('login.submit'), [
            'email' => 'NEW.TRAVELLER',
            'password' => 'password',
        ])->assertRedirect(route('account'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_profile_username_validation_rejects_invalid_and_duplicate_values(): void
    {
        $user = User::factory()->create(['username' => 'first.traveller']);
        User::factory()->create(['username' => 'other.traveller']);

        $this->actingAs($user)->from(route('account'))->put(route('account.profile.update'), [
            'username' => 'bad username',
            'email' => $user->email,
        ])->assertSessionHasErrors(['username' => 'Use 3-20 letters, numbers, dots, dashes or underscores.']);

        $this->from(route('account'))->put(route('account.profile.update'), [
            'username' => 'OTHER.TRAVELLER',
            'email' => $user->email,
        ])->assertSessionHasErrors(['username' => 'That username is already taken.']);
    }

    public function test_traveller_preferences_password_and_two_factor_settings_are_saved(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->put(route('account.travellers.update'), [
                'name_as_per_ic' => 'Taylor Traveller',
                'passport_number' => 'X1234567',
                'passport_expiry' => '2030-08-01',
                'date_of_birth' => '1990-01-01',
                'nationality' => 'Malaysian',
            ])->assertRedirect(route('account', ['tab' => 'travellers']))
            ->assertSessionHas('success', 'Traveller details saved');

        $this->put(route('account.notifications.update'), [
            'deals' => '1',
            'news' => '1',
        ])->assertRedirect(route('account', ['tab' => 'notifications']));

        $this->put(route('account.two-factor.update'), ['enabled' => '1'])
            ->assertRedirect(route('account', ['tab' => 'security']));

        $this->put(route('account.password.update'), [
            'current_password' => 'password',
            'new_password' => 'ChangedPass123',
            'new_password_confirmation' => 'ChangedPass123',
        ])->assertRedirect(route('account', ['tab' => 'security']))
            ->assertSessionHas('success', 'Password changed');

        $user->refresh();
        $this->assertSame('Taylor Traveller', $user->traveller_details['name_as_per_ic']);
        $this->assertSame(['deals' => true, 'trips' => false, 'news' => true], $user->notification_preferences);
        $this->assertTrue($user->account_mfa_enabled);
        $this->assertTrue(Hash::check('ChangedPass123', $user->password));
    }
}
