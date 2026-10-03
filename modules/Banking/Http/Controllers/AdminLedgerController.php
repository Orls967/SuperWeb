<?php

declare(strict_types=1);

namespace Modules\Banking\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Banking\Application\Actions\FreezeAccountAction;
use Modules\Banking\Application\Actions\ManualAdjustmentAction;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;

class AdminLedgerController extends Controller
{
    public function index(Request $request): View
    {
        $systemAccounts = LedgerAccount::whereNull('owner_id')
            ->orWhere('kind', '!=', 'wallet')
            ->orderBy('kind')
            ->orderBy('code')
            ->get();

        $userWalletsCount = LedgerAccount::where('kind', 'wallet')->count();

        $transactions = LedgerTransaction::with(['entries.account', 'creator'])
            ->latest('id')
            ->paginate(20);

        return view('banking::admin.index', [
            'systemAccounts' => $systemAccounts,
            'userWalletsCount' => $userWalletsCount,
            'transactions' => $transactions,
        ]);
    }

    public function show(LedgerTransaction $transaction): View
    {
        $transaction->load(['entries.account', 'creator', 'reference']);

        return view('banking::admin.show', [
            'transaction' => $transaction,
        ]);
    }

    public function freeze(Request $request, LedgerAccount $account, FreezeAccountAction $freezeAction): RedirectResponse
    {
        $newStatus = ! $account->is_frozen;
        $freezeAction->execute($account, $newStatus);

        $statusText = $newStatus ? 'dibekukan' : 'diaktifkan kembali';

        return back()->with('status', "Akun '{$account->code}' berhasil {$statusText}.");
    }

    public function adjust(Request $request, LedgerAccount $account, ManualAdjustmentAction $adjustmentAction): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'not_in:0'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ], [
            'amount.required' => 'Nominal penyesuaian wajib diisi.',
            'amount.not_in' => 'Nominal penyesuaian tidak boleh bernilai 0.',
            'reason.required' => 'Alasan penyesuaian manual wajib dicantumkan.',
        ]);

        $adjustmentAction->execute(
            account: $account,
            amount: (string) $validated['amount'],
            reason: $validated['reason'],
            adminUser: $request->user(),
            idempotencyKey: $validated['idempotency_key'] ?? null,
        );

        return back()->with('status', "Penyesuaian manual pada akun '{$account->code}' berhasil diposting ke ledger.");
    }
}
