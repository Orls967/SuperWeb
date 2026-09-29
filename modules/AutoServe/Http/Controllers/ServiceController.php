<?php

declare(strict_types=1);

namespace Modules\AutoServe\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\AutoServe\Domain\Models\Service;

class ServiceController extends Controller
{
    public function index()
    {
        $services = Service::latest()->get();

        return view('serve::services.index', compact('services'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
        ]);

        Service::create($validated);

        return back()->with('success', 'Jenis servis berhasil ditambahkan.');
    }

    public function update(Request $request, Service $service)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $service->update($validated);

        return back()->with('success', 'Jenis servis berhasil diperbarui.');
    }

    public function destroy(Service $service)
    {
        $service->delete();

        return back()->with('success', 'Jenis servis berhasil dihapus.');
    }
}
