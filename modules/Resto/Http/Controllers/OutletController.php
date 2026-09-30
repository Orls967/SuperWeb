<?php

declare(strict_types=1);

namespace Modules\Resto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Resto\Domain\Enums\OutletType;
use Modules\Resto\Domain\Models\Outlet;

class OutletController extends Controller
{
    public function index(): View
    {
        $outlets = Outlet::withCount('staffAssignments')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return view('resto::outlets.index', compact('outlets'));
    }

    public function create(): View
    {
        $types = OutletType::cases();

        return view('resto::outlets.create', compact('types'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:resto_outlets,code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string'],
            'address' => ['required', 'string'],
            'city' => ['required', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'mall_unit_ref' => ['nullable', 'string', 'max:50'],
            'seats' => ['nullable', 'integer', 'min:0'],
            'opens_at' => ['required'],
            'closes_at' => ['required'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['seats'] = (int) ($validated['seats'] ?? 0);

        Outlet::create($validated);

        return redirect()->route('resto.outlets.index')
            ->with('success', 'Outlet / Dapur Sentral berhasil ditambahkan.');
    }

    public function show(Outlet $outlet): View
    {
        $outlet->load(['staffAssignments.user', 'ingredientCosts.ingredient']);

        return view('resto::outlets.show', compact('outlet'));
    }

    public function edit(Outlet $outlet): View
    {
        $types = OutletType::cases();

        return view('resto::outlets.edit', compact('outlet', 'types'));
    }

    public function update(Request $request, Outlet $outlet): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:resto_outlets,code,'.$outlet->id],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string'],
            'address' => ['required', 'string'],
            'city' => ['required', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'mall_unit_ref' => ['nullable', 'string', 'max:50'],
            'seats' => ['nullable', 'integer', 'min:0'],
            'opens_at' => ['required'],
            'closes_at' => ['required'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['seats'] = (int) ($validated['seats'] ?? 0);

        $outlet->update($validated);

        return redirect()->route('resto.outlets.index')
            ->with('success', 'Data outlet berhasil diperbarui.');
    }
}
