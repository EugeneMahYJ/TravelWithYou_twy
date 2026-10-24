<?php

namespace Tests\Feature;

use App\Models\Ship;
use App\Models\TravelPackage;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminShipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_manage_ships(): void
    {
        $user = User::create(['name' => 'Traveller', 'email' => 'ships@example.test', 'password' => 'SafePass1234']);

        $this->actingAs($user)->get(route('admin.ships'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.ships.store'), $this->payload())->assertForbidden();
        $this->actingAs($user)->delete(route('admin.ships.delete', $this->ship()))->assertForbidden();
    }

    public function test_admin_can_view_the_ships_module(): void
    {
        $admin = $this->admin();
        $ship = $this->ship();
        $this->actingAs($admin)->withSession($this->verifiedSession());

        $this->get(route('admin.ships'))->assertOk()->assertSee('Ships')->assertSee($ship->name);
        $this->get(route('admin.ships', ['q' => $ship->name]))->assertOk()->assertSee($ship->name);
        $this->get(route('admin.ships', ['q' => 'no-such-ship']))->assertOk()->assertSee('No ships match this search.');
        $this->get(route('admin.ships.create'))->assertOk()->assertSee('New ship');
        $this->get(route('admin.ships.edit', $ship))
            ->assertOk()
            ->assertSee($ship->name)
            ->assertSee('Balcony')
            ->assertSee('name="cabins[0][type]"', false)
            ->assertSee('name="decks[0][label]"', false);
    }

    public function test_admin_can_create_a_ship_and_the_client_site_reflects_it(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->withSession($this->verifiedSession());

        $this->post(route('admin.ships.store'), $this->payload())->assertRedirect(route('admin.ships'));

        $ship = Ship::where('slug', 'aurora-pearl')->firstOrFail();
        $this->assertSame('Aurora Pearl', $ship->name);
        $this->assertSame(['Pool deck', 'Spa'], $ship->amenities);
        $this->assertCount(1, $ship->gallery);
        $this->assertSame('Sun Deck', $ship->decks[0]['label']);
        $this->assertCount(1, $ship->cabins);
        $this->assertSame('Balcony', $ship->cabins->first()->type);

        $this->get(route('ships'))->assertOk()->assertSee('Aurora Pearl');
        $this->get(route('ships.show', ['ship' => 'aurora-pearl']))
            ->assertOk()
            ->assertSee('A small ship for quiet harbours.')
            ->assertSee('Balcony')
            ->assertSee('Sun Deck')
            ->assertSee('Pool deck');
        $this->get(route('home'))->assertOk()->assertSee('Aurora Pearl');
    }

    public function test_admin_can_update_a_ship_and_the_client_site_reflects_the_change(): void
    {
        $admin = $this->admin();
        $ship = $this->ship();
        $this->actingAs($admin)->withSession($this->verifiedSession());

        $this->put(route('admin.ships.update', $ship), $this->payload([
            'name' => 'Aurora Pearl II',
            'slug' => 'aurora-pearl-ii',
            'overview' => 'Refitted for longer voyages.',
            'capacity' => '980',
            'rating' => '4.9',
        ]))->assertRedirect(route('admin.ships'));

        $ship->refresh();
        $this->assertSame('Aurora Pearl II', $ship->name);
        $this->assertSame('aurora-pearl-ii', $ship->slug);
        $this->assertSame(980, $ship->capacity);
        $this->assertSame(4.9, $ship->rating);
        $this->assertSame(['Pool deck', 'Spa'], $ship->amenities);

        $this->get(route('ships.show', ['ship' => 'aurora-pearl-ii']))
            ->assertOk()
            ->assertSee('Aurora Pearl II')
            ->assertSee('Refitted for longer voyages.');
        $this->get(route('ships.show', ['ship' => 'aurora-pearl']))->assertNotFound();
    }

    public function test_renaming_a_ship_keeps_it_linked_to_its_cruise_packages(): void
    {
        $admin = $this->admin();
        $ship = $this->ship();
        $package = $this->package(['ship' => $ship->name]);
        $this->actingAs($admin)->withSession($this->verifiedSession());

        $this->put(route('admin.ships.update', $ship), $this->payload(['name' => 'Aurora Pearl II']))
            ->assertRedirect(route('admin.ships'));

        $this->assertDatabaseHas('travel_packages', ['id' => $package->id, 'ship' => 'Aurora Pearl II']);
        $this->get(route('ships.show', ['ship' => $ship->fresh()->slug]))
            ->assertOk()
            ->assertSee('Aurora Pearl Cruise');
    }

    public function test_deleting_a_ship_removes_it_from_the_client_site_and_unlinks_packages(): void
    {
        $admin = $this->admin();
        $ship = $this->ship();
        $package = $this->package(['ship' => $ship->name]);
        $this->actingAs($admin)->withSession($this->verifiedSession());

        $this->delete(route('admin.ships.delete', $ship))->assertRedirect(route('admin.ships'));

        $this->assertDatabaseMissing('ships', ['id' => $ship->id]);
        $this->assertDatabaseMissing('ship_cabins', ['ship_id' => $ship->id]);
        $this->assertDatabaseHas('travel_packages', ['id' => $package->id, 'ship' => null]);
        $this->get(route('admin.ships'))->assertOk()->assertSee('Aurora Pearl deleted.');
        $this->get(route('ships'))->assertOk()->assertDontSee($ship->name);
        $this->get(route('ships.show', ['ship' => $ship->slug]))->assertNotFound();
    }

    public function test_ship_validation_rejects_bad_input_and_duplicate_cabin_types(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->withSession($this->verifiedSession());

        $this->post(route('admin.ships.store'), $this->payload([
            'name' => '',
            'capacity' => '0',
            'rating' => '9',
        ]))->assertSessionHasErrors(['name', 'capacity', 'rating']);

        $response = $this->post(route('admin.ships.store'), $this->payload([
            'cabins' => [
                ['type' => 'Balcony', 'size' => '24 m²', 'price_from' => '4500', 'note' => 'First'],
                ['type' => 'Balcony', 'size' => '30 m²', 'price_from' => '5400', 'note' => 'Second'],
            ],
        ]));

        $response->assertSessionHasErrors();
        $this->assertTrue(collect(session('errors')->keys())->contains(fn (string $key): bool => str_starts_with($key, 'cabins.')));
        $this->assertDatabaseCount('ships', 0);
    }

    public function test_blank_gallery_and_deck_rows_are_dropped_before_validation(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->withSession($this->verifiedSession());

        $this->post(route('admin.ships.store'), $this->payload([
            'gallery' => ['1614095557130-2f4a9e1da57d', '  ', ''],
            'decks' => [
                ['n' => '', 'label' => '', 'items' => ''],
                ['n' => 'Deck 9', 'label' => 'Sun Deck', 'items' => 'Pool'],
            ],
        ]))->assertRedirect(route('admin.ships'));

        $ship = Ship::where('slug', 'aurora-pearl')->firstOrFail();
        $this->assertSame(['1614095557130-2f4a9e1da57d'], $ship->gallery);
        $this->assertCount(1, $ship->decks);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Aurora Pearl',
            'slug' => 'aurora-pearl',
            'class_name' => 'Boutique Class',
            'image' => '1614095557130-2f4a9e1da57d',
            'overview' => 'A small ship for quiet harbours.',
            'capacity' => '900',
            'crew' => '400',
            'rating' => '4.7',
            'year' => '2019',
            'gallery' => ['1614095557130-2f4a9e1da57d', ''],
            'amenities' => ['Pool deck', '', 'Spa'],
            'decks' => [['n' => 'Deck 9', 'label' => 'Sun Deck', 'items' => 'Pool, sky bar']],
            'cabins' => [['type' => 'Balcony', 'size' => '24 m²', 'price_from' => '4500', 'note' => 'Private veranda']],
        ], $overrides);
    }

    private function ship(): Ship
    {
        $ship = Ship::create([
            'slug' => 'aurora-pearl',
            'name' => 'Aurora Pearl',
            'image' => '1614095557130-2f4a9e1da57d',
            'gallery' => ['1614095557130-2f4a9e1da57d'],
            'capacity' => 900,
            'crew' => 400,
            'class_name' => 'Boutique Class',
            'rating' => 4.7,
            'year' => 2019,
            'overview' => 'A small ship for quiet harbours.',
            'amenities' => ['Pool deck'],
            'decks' => [['n' => 'Deck 9', 'label' => 'Sun Deck', 'items' => 'Pool']],
        ]);
        $ship->cabins()->create([
            'type' => 'Balcony',
            'size' => '24 m²',
            'price_from' => 4500,
            'note' => 'Private veranda',
        ]);

        return $ship;
    }

    private function package(array $overrides = []): TravelPackage
    {
        return TravelPackage::create(array_replace([
            'slug' => 'aurora-pearl-cruise',
            'name' => 'Aurora Pearl Cruise',
            'country' => 'Greece',
            'region' => 'Europe',
            'type' => 'Cruise',
            'days' => 7,
            'description' => 'Island hopping.',
            'details' => 'Island hopping in the Cyclades.',
            'price_from' => 4500,
            'price_to' => 8000,
            'departures' => [now()->addMonth()->toDateString()],
            'image' => '1614095557130-2f4a9e1da57d',
            'published' => true,
        ], $overrides));
    }

    private function admin(string $email = 'ships-admin@example.test'): User
    {
        $admin = User::create(['name' => 'Site Admin', 'email' => $email, 'password' => 'SafePass1234']);
        $admin->forceFill(['is_admin' => true, 'mfa_enabled' => true, 'mfa_secret' => Totp::generateSecret()])->save();

        return $admin;
    }

    private function verifiedSession(): array
    {
        return ['admin_mfa_verified' => true, 'admin_last_activity' => time()];
    }
}
