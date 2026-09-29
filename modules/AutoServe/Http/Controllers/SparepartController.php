<?php

declare(strict_types=1);

namespace Modules\AutoServe\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\AutoServe\Domain\Models\Sparepart;

class SparepartController extends Controller
{
    public function index()
    {
        $spareparts = Sparepart::latest()->get();

        return view('serve::spareparts.index', compact('spareparts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:serve_spareparts,code',
            'stock' => 'required|integer|min:0',
            'price' => 'required|numeric|min:0',
            'unit' => 'required|string|max:20',
        ]);

        Sparepart::create($validated);

        return back()->with('success', 'Sparepart berhasil ditambahkan.');
    }

    public function update(Request $request, Sparepart $sparepart)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:serve_spareparts,code,'.$sparepart->id,
            'stock' => 'required|integer|min:0',
            'price' => 'required|numeric|min:0',
            'unit' => 'required|string|max:20',
            'is_active' => 'boolean',
        ]);

        $sparepart->update($validated);

        return back()->with('success', 'Sparepart berhasil diperbarui.');
    }

    public function destroy(Sparepart $sparepart)
    {
        $sparepart->delete();

        return back()->with('success', 'Sparepart berhasil dihapus.');
    }
}
