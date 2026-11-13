<?php

namespace Tests\Feature;

use App\Models\PromoCode;
use App\Models\TravelDeal;
use App\Models\TravelPackage;
use App\Models\User;
use App\Support\Totp;
use Database\Seeders\CountrySeeder;
use Database\Seeders\FaqSeeder;
use Database\Seeders\GuideSeeder;
use Database\Seeders\PromoCodeSeeder;
use Database\Seeders\ShipSeeder;
use Database\Seeders\TravelDealSeeder;
use Database\Seeders\TravelPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * A real administrator is signed in on the "admin" guard only, while the default guard
 * stays "web". The other admin tests call actingAs($admin, 'admin'), which also switches
 * the default guard to "admin" and therefore hides any code that forgets to name the guard.
 */
class AdminGuardRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            CountrySeeder::class, ShipSeeder::class, TravelPackageSeeder::class, TravelDealSeeder::class,
            PromoCodeSeeder::class, GuideSeeder::class, FaqSeeder::class,
        ]);

        $admin = User::create(['name' => 'Site Admin', 'email' => 'admin@example.test', 'password' => 'SafePass1234']);
        $admin->forceFill(['is_admin' => true, 'mfa_enabled' => true, 'mfa_secret' => Totp::generateSecret()])->save();
        $this->actingAs($admin, 'admin')->withSession(['admin_mfa_verified' => true, 'admin_last_activity' => time()]);
        Auth::shouldUse('web');
    }

    public function test_admin_can_create_and_update_a_promo_code_with_only_the_admin_guard(): void
    {
        $this->post(route('admin.promos.store'), [
            'code' => 'guardcheck',
            'discount_type' => 'percent',
            'discount_value' => '15',
            'all_packages' => '1',
            'terms' => 'Guard regression check.',
            'active' => '1',
        ])->assertRedirect(route('admin.deals'))->assertSessionHas('success', 'Promo code saved.');

        $promo = PromoCode::where('code', 'GUARDCHECK')->firstOrFail();
        $this->put(route('admin.promos.update', $promo), [
            'code' => 'GUARDCHECK',
            'discount_type' => 'percent',
            'discount_value' => '20',
            'all_packages' => '1',
            'terms' => 'Updated.',
            'active' => '1',
        ])->assertRedirect(route('admin.deals'));
        $this->assertEquals(20, $promo->fresh()->discount_percent);
    }

    public function test_admin_can_create_and_update_a_deal_with_only_the_admin_guard(): void
    {
        $package = TravelPackage::where('slug', 'sakura-rail')->firstOrFail();
        $payload = [
            'name' => 'Guard check offer',
            'type' => 'Limited-time',
            'package_id' => $package->id,
            'discount_percent' => 12,
            'terms' => 'Guard regression check.',
            'expires_at' => now()->addDays(5)->format('Y-m-d\TH:i'),
            'active' => '1',
        ];

        $this->post(route('admin.deals.store'), $payload)->assertRedirect(route('admin.deals'));
        $deal = TravelDeal::where('name', 'Guard check offer')->firstOrFail();
        $this->put(route('admin.deals.update', $deal), array_merge($payload, ['discount_percent' => 18]))
            ->assertRedirect(route('admin.deals'));
        $this->assertSame(18, (int) $deal->fresh()->discount_percent);
    }

    public function test_ship_form_submission_reaches_validation_instead_of_being_forbidden(): void
    {
        $this->from(route('admin.ships.create'))->post(route('admin.ships.store'), ['name' => ''])
            ->assertRedirect(route('admin.ships.create'))
            ->assertSessionHasErrors();
    }

    public function test_package_form_submission_reaches_validation_instead_of_being_forbidden(): void
    {
        $this->from(route('admin.packages.create'))->post(route('admin.packages.store'), ['name' => ''])
            ->assertRedirect(route('admin.packages.create'))
            ->assertSessionHasErrors();
    }

    public function test_media_picker_only_treats_folder_list_links_as_folders(): void
    {
        $script = file_get_contents(public_path('js/admin-asset-picker.js'));

        $this->assertStringContainsString('[data-asset-picker-folders] [data-asset-folder]', $script);
        $this->assertStringNotContainsString('closest("[data-asset-folder]")', $script);
        $this->assertStringNotContainsString("querySelectorAll(\"[data-asset-folder]\")", $script);
    }

    public function test_admin_can_apply_a_library_picture_to_a_package_and_see_it_on_the_site(): void
    {
        $package = TravelPackage::where('slug', 'sakura-rail')->firstOrFail();
        $picked = 'countries/argentina-package-thumbnail.jpg';

        $this->get(route('admin.packages.edit', $package))->assertOk()
            ->assertSee('Browse library')
            ->assertSee('js/admin-asset-picker.js?v=', false)
            ->assertSee('data-asset-path="'.$picked.'"', false);

        $payload = [
            'builder_form' => '1',
            'name' => $package->name,
            'slug' => $package->slug,
            'country' => $package->countryRecord->slug,
            'type' => $package->type,
            'ship' => $package->ship,
            'days' => $package->days,
            'nights' => $package->nights,
            'description' => $package->description,
            'long_description' => $package->long_description,
            'price_from' => $package->price_from,
            'price_to' => $package->price_to,
            'image' => $picked,
            'departure_port' => $package->departure_port,
            'destination_port' => $package->destination_port,
            'departures' => [Carbon::now()->addMonth()->toDateString()],
            'guest_types' => [['id' => 'adult', 'label' => 'Adult', 'age' => '', 'role' => 'adult']],
            'departure_prices' => [['adult' => max(1, $package->price_from)]],
            'highlights' => ['A memorable journey'],
            'trip_plan' => array_fill(0, $package->days, ['place' => 'On the journey', 'activities' => 'Explore.', 'transport' => '']),
            'status' => 'Published',
        ];

        $this->put(route('admin.packages.update', $package), $payload)
            ->assertRedirect(route('admin.packages'))
            ->assertSessionHas('success', 'Package saved.');

        $this->assertSame($picked, $package->fresh()->image);
        $this->get(route('packages.show', ['package' => $package->slug]))->assertOk()
            ->assertSee('assets/'.$picked, false);
    }

    public function test_admin_pages_load_versioned_scripts_so_fixes_are_not_hidden_by_the_browser_cache(): void
    {
        $this->get(route('admin.dashboard'))->assertOk()
            ->assertSee('js/admin.js?v=', false)
            ->assertSee('css/site.css?v=', false);
    }

    public function test_customers_are_still_refused_the_admin_forms(): void
    {
        Auth::guard('admin')->logout();
        $customer = User::create(['name' => 'Customer', 'email' => 'customer@example.test', 'password' => 'SafePass1234']);

        $this->actingAs($customer, 'web')->post(route('admin.promos.store'), ['code' => 'NOPE'])->assertForbidden();
    }
}
