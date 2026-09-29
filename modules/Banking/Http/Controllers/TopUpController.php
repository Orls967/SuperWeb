<?php

declare(strict_types=1);

namespace Modules\Banking\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Shared\Domain\ValueObjects\Money;

class TopUpController extends Controller
{
    public function store(Request $request, TopUpAction $topUpAction): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:10000', 'max:50000000'],
        ], [
            'amount.required' => 'Nominal top up wajib diisi.',
            'amount.min' => 'Minimal top up adalah Rp 10.000.',
            'amount.max' => 'Maksimal top up adalah Rp 50.000.000 per transaksi.',
        ]);

        $tx = $topUpAction->execute($request->user(), (string) $validated['amount']);

        $formatted = Money::IDR($validated['amount'])->format();

        return redirect()->route('wallet.index')->with('status', "Top up sebesar {$formatted} berhasil diproses.");
    }
}
