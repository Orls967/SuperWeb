<?php

declare(strict_types=1);

namespace Modules\Crypto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Crypto\Domain\Enums\AlertCondition;
use Modules\Crypto\Domain\Models\CryptoAlert;
use Modules\Crypto\Domain\Models\CryptoAsset;

class CryptoAlertController extends Controller
{
    public function index(Request $request): View
    {
        $alerts = CryptoAlert::with('asset')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        $assets = CryptoAsset::where('is_active', true)->get();

        return view('crypto::alerts.index', [
            'alerts' => $alerts,
            'assets' => $assets,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'asset_id' => 'required|exists:crypto_assets,id',
            'condition' => 'required|in:above,below',
            'target_price_idr' => 'required|numeric|min:1',
        ]);

        CryptoAlert::create([
            'user_id' => $request->user()->id,
            'asset_id' => (int) $validated['asset_id'],
            'condition' => AlertCondition::from($validated['condition']),
            'target_price_idr' => (string) $validated['target_price_idr'],
            'is_triggered' => false,
        ]);

        return back()->with('success', 'Pengingat harga (Price Alert) berhasil dipasang!');
    }

    public function destroy(Request $request, CryptoAlert $alert): RedirectResponse
    {
        if ($alert->user_id !== $request->user()->id) {
            abort(403);
        }

        $alert->delete();

        return back()->with('success', 'Pengingat harga berhasil dihapus.');
    }
}
