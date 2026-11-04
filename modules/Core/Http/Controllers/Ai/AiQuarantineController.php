<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Domain\Models\CoreAiAnomalyQuarantine;

class AiQuarantineController extends Controller
{
    public function index(): View
    {
        $quarantines = CoreAiAnomalyQuarantine::where('status', 'QUARANTINED')
            ->orderBy('quarantined_at', 'desc')
            ->paginate(20);

        return view('core::ai.quarantine', [
            'quarantines' => $quarantines,
        ]);
    }

    public function release(Request $request, CoreAiAnomalyQuarantine $quarantine): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(403, 'Akses ditolak.');
        }

        // Periksa kewenangan peran: hanya risk_manager atau admin
        if (! in_array($user->role, ['risk_manager', 'admin'], true)) {
            abort(403, 'Akses ditolak: Hanya Risk Manager yang berhak merilis karantina (D64-2).');
        }

        // Penegakan Four-Eyes: risk_manager != pembuat karantina
        if ($quarantine->created_by !== null && $quarantine->created_by === $user->name) {
            abort(403, 'Prinsip Four-Eyes: Pembuat karantina tidak dapat merilis transaksinya sendiri.');
        }

        $validated = $request->validate([
            'release_justification' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        // Operasi idempoten: bila sudah RELEASED, tidak merubah status ulang
        if ($quarantine->status === 'RELEASED') {
            return redirect()->route('core.ai.quarantine.index')
                ->with('info', "Karantina transaksi {$quarantine->transaction_ref} telah dirilis sebelumnya.");
        }

        $quarantine->update([
            'status' => 'RELEASED',
            'released_at' => now(),
            'released_by' => $user->name,
            'release_justification' => (string) $validated['release_justification'],
        ]);

        return redirect()->route('core.ai.quarantine.index')
            ->with('success', "Karantina transaksi {$quarantine->transaction_ref} berhasil dirilis oleh {$user->name}.");
    }
}
