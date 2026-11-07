<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Mining\Application\Sagas\OfftakeShipmentLifecycleSaga;
use Modules\Mining\Domain\Models\MiningOfftakeContract;

class MiningOfftakerPortalController extends Controller
{
    /**
     * Dashboard portal pembeli komoditas offtake (D94-8).
     * Menerapkan isolasi ketat kepemilikan data (anti-IDOR).
     */
    public function index()
    {
        $userId = (string) Auth::id();

        // Hanya kontrak milik user offtaker yang sedang login
        $contracts = MiningOfftakeContract::where('buyer_party_ref', $userId)->get();

        return view('mining::offtaker_portal', [
            'contracts' => $contracts,
            'buyerId' => $userId,
        ]);
    }

    /**
     * Pengajuan nominasi kapal oleh offtaker dengan pengecekan anti-IDOR.
     */
    public function nominate(Request $request, OfftakeShipmentLifecycleSaga $saga)
    {
        $request->validate([
            'contract_id' => 'required|uuid',
            'quantity_kg' => 'required|integer|min:1000',
        ]);

        $contract = MiningOfftakeContract::findOrFail($request->input('contract_id'));

        // Anti-IDOR Guard: Pastikan kontrak milik offtaker yang sedang login
        if ((string) $contract->buyer_party_ref !== (string) Auth::id()) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki otoritas atas kontrak offtake pembeli lain.');
        }

        $result = $saga->execute([
            'contract_id' => (string) $contract->id,
            'shipment_code' => 'NOM-'.strtoupper(uniqid()),
            'quantity_kg' => (int) $request->input('quantity_kg'),
            'shipment_date' => now()->toDateString(),
        ]);

        return redirect()->back()->with('success', 'Nominasi kapal berhasil diajukan: '.$result['message']);
    }
}
