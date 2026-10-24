<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveAdminShipRequest;
use App\Models\Ship;
use App\Models\TravelPackage;
use App\Support\AssetLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminShipController extends Controller
{
    public function index(Request $request): View
    {
        $query = Ship::query()->withCount('cabins')->orderBy('name');
        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(fn ($builder) => $builder->where('name', 'like', $term)
                ->orWhere('class_name', 'like', $term));
        }
        $ships = $query->get();
        $cruiseCounts = TravelPackage::query()
            ->whereIn('ship', $ships->pluck('name'))
            ->selectRaw('ship, COUNT(*) as total')
            ->groupBy('ship')
            ->pluck('total', 'ship');

        return view('admin.ships.index', [
            'ships' => $ships,
            'cruiseCounts' => $cruiseCounts,
        ]);
    }

    public function create(): View
    {
        return view('admin.ships.form', [
            'ship' => new Ship([
                'gallery' => [],
                'amenities' => [],
                'decks' => [],
                'capacity' => 1200,
                'crew' => 500,
                'rating' => 4.5,
                'year' => now()->year,
            ]),
            'shipClasses' => $this->shipClasses(),
            'mediaAssets' => AssetLibrary::all(),
        ]);
    }

    public function store(SaveAdminShipRequest $request): RedirectResponse
    {
        $ship = DB::transaction(function () use ($request): Ship {
            $ship = new Ship;
            $this->persist($ship, $request->validated());

            return $ship;
        });

        return redirect()->route('admin.ships')->with('success', $ship->name.' created.');
    }

    public function edit(Ship $ship): View
    {
        return view('admin.ships.form', [
            'ship' => $ship->load('cabins'),
            'shipClasses' => $this->shipClasses(),
            'mediaAssets' => AssetLibrary::all(),
        ]);
    }

    public function update(SaveAdminShipRequest $request, Ship $ship): RedirectResponse
    {
        $previousName = $ship->name;

        DB::transaction(function () use ($request, $ship, $previousName): void {
            $this->persist($ship, $request->validated());
            if ($previousName !== $ship->name) {
                TravelPackage::query()->where('ship', $previousName)->update(['ship' => $ship->name]);
            }
        });

        return redirect()->route('admin.ships')->with('success', $ship->name.' updated.');
    }

    public function destroy(Ship $ship): RedirectResponse
    {
        $name = $ship->name;

        DB::transaction(function () use ($ship, $name): void {
            TravelPackage::query()->where('ship', $name)->update(['ship' => null]);
            $ship->delete();
        });

        return redirect()->route('admin.ships')->with('success', $name.' deleted.');
    }

    private function persist(Ship $ship, array $data): void
    {
        $ship->fill([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'image' => $data['image'],
            'gallery' => array_values($data['gallery'] ?? []),
            'capacity' => (int) $data['capacity'],
            'crew' => (int) $data['crew'],
            'class_name' => $data['class_name'],
            'rating' => round((float) $data['rating'], 1),
            'year' => (int) $data['year'],
            'overview' => $data['overview'],
            'amenities' => array_values($data['amenities'] ?? []),
            'decks' => array_values(array_map(fn (array $deck): array => [
                'n' => $deck['n'] ?? '',
                'label' => $deck['label'],
                'items' => $deck['items'],
            ], $data['decks'] ?? [])),
        ])->save();

        $ship->cabins()->delete();
        foreach ($data['cabins'] ?? [] as $cabin) {
            $ship->cabins()->create([
                'type' => $cabin['type'],
                'size' => $cabin['size'] ?? '',
                'price_from' => (int) $cabin['price_from'],
                'note' => $cabin['note'] ?? '',
            ]);
        }
    }

    private function shipClasses(): Collection
    {
        return Ship::query()->distinct()->orderBy('class_name')->pluck('class_name');
    }
}
