<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Models\Lane;
use Modules\Logistics\Domain\Models\Location;

class LaneController extends Controller
{
    public function index(Request $request): View
    {
        $query = Lane::with(['origin', 'destination']);

        if ($request->filled('mode')) {
            $query->where('mode', $request->query('mode'));
        }

        if ($request->filled('origin_id')) {
            $query->where('origin_id', $request->query('origin_id'));
        }

        if ($request->filled('destination_id')) {
            $query->where('destination_id', $request->query('destination_id'));
        }

        $lanes = $query->orderBy('mode')->paginate(25)->withQueryString();
        $locations = Location::where('is_active', true)->orderBy('name')->get();

        return view('logistics::lanes.index', [
            'lanes' => $lanes,
            'locations' => $locations,
            'modes' => TransportMode::cases(),
            'filters' => $request->only(['mode', 'origin_id', 'destination_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'origin_id' => ['required', 'exists:lgx_locations,id'],
            'destination_id' => ['required', 'different:origin_id', 'exists:lgx_locations,id'],
            'mode' => ['required', Rule::enum(TransportMode::class)],
            'distance_km' => ['required', 'numeric', 'min:0.1'],
            'standard_transit_minutes' => ['required', 'integer', 'min:1'],
        ]);

        $exists = Lane::where('origin_id', $validated['origin_id'])
            ->where('destination_id', $validated['destination_id'])
            ->where('mode', $validated['mode'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['mode' => 'Jalur dengan rute dan moda transportasi ini sudah terdaftar.'])->withInput();
        }

        Lane::create([
            'origin_id' => $validated['origin_id'],
            'destination_id' => $validated['destination_id'],
            'mode' => $validated['mode'],
            'distance_m' => (int) round(((float) $validated['distance_km']) * 1000),
            'standard_transit_minutes' => (int) $validated['standard_transit_minutes'],
            'is_active' => true,
        ]);

        return redirect()->route('logistics.lanes.index')->with('success', 'Jalur transportasi berhasil ditambahkan.');
    }

    public function destroy(Lane $lane): RedirectResponse
    {
        $lane->delete();

        return redirect()->route('logistics.lanes.index')->with('success', 'Jalur transportasi berhasil dihapus.');
    }
}
