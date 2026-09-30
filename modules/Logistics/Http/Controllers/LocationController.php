<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Logistics\Domain\Enums\LocationType;
use Modules\Logistics\Domain\Models\Lane;
use Modules\Logistics\Domain\Models\Location;

class LocationController extends Controller
{
    public function index(Request $request): View
    {
        $query = Location::query();

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->filled('country')) {
            $query->where('country', $request->query('country'));
        }

        if ($request->filled('q')) {
            $search = '%'.$request->query('q').'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('code', 'like', $search)
                    ->orWhere('city', 'like', $search)
                    ->orWhere('unlocode', 'like', $search)
                    ->orWhere('iata', 'like', $search);
            });
        }

        $locations = $query->orderBy('country')->orderBy('city')->paginate(20)->withQueryString();
        $allLocations = Location::where('is_active', true)->get();
        $lanes = Lane::with(['origin', 'destination'])->where('is_active', true)->get();

        return view('logistics::locations.index', [
            'locations' => $locations,
            'allLocations' => $allLocations,
            'lanes' => $lanes,
            'types' => LocationType::cases(),
            'filters' => $request->only(['type', 'country', 'q']),
        ]);
    }

    public function create(): View
    {
        return view('logistics::locations.create', [
            'types' => LocationType::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:lgx_locations,code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(LocationType::class)],
            'unlocode' => ['nullable', 'string', 'size:5'],
            'iata' => ['nullable', 'string', 'size:3'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'country' => ['required', 'string', 'size:2'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'timezone' => ['required', 'string', 'max:50'],
            'min_connection_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
        ]);

        $validated['lat_e6'] = (int) round(((float) $validated['latitude']) * 1_000_000);
        $validated['lng_e6'] = (int) round(((float) $validated['longitude']) * 1_000_000);
        unset($validated['latitude'], $validated['longitude']);

        Location::create($validated);

        return redirect()->route('logistics.locations.index')->with('success', 'Lokasi berhasil ditambahkan ke jaringan.');
    }

    public function edit(Location $location): View
    {
        return view('logistics::locations.edit', [
            'location' => $location,
            'types' => LocationType::cases(),
        ]);
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(LocationType::class)],
            'unlocode' => ['nullable', 'string', 'size:5'],
            'iata' => ['nullable', 'string', 'size:3'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'country' => ['required', 'string', 'size:2'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'timezone' => ['required', 'string', 'max:50'],
            'min_connection_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['lat_e6'] = (int) round(((float) $validated['latitude']) * 1_000_000);
        $validated['lng_e6'] = (int) round(((float) $validated['longitude']) * 1_000_000);
        unset($validated['latitude'], $validated['longitude']);

        $location->update($validated);

        return redirect()->route('logistics.locations.index')->with('success', 'Data lokasi berhasil diperbarui.');
    }

    public function destroy(Location $location): RedirectResponse
    {
        $location->delete();

        return redirect()->route('logistics.locations.index')->with('success', 'Lokasi berhasil dihapus.');
    }
}
