<?php

declare(strict_types=1);

namespace Modules\Banking\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Banking\Application\Actions\SetPinAction;
use Modules\Banking\Application\Queries\StatementQuery;
use Modules\Banking\Domain\Models\LedgerAccount;

class WalletController extends Controller
{
    public function index(Request $request, StatementQuery $statementQuery): View
    {
        $user = $request->user();
        $idrAccount = $user->walletAccount('IDR');
        $idrBalance = $user->walletBalance('IDR');

        $recentEntries = $statementQuery->query($idrAccount)->limit(10)->get();

        $cryptoAccounts = LedgerAccount::where('owner_type', get_class($user))
            ->where('owner_id', $user->id)
            ->where('asset_code', '!=', 'IDR')
            ->get();

        return view('banking::wallet.index', [
            'user' => $user,
            'idrAccount' => $idrAccount,
            'idrBalance' => $idrBalance,
            'hasPin' => $user->hasPin(),
            'isPinLocked' => $user->isPinLocked(),
            'recentEntries' => $recentEntries,
            'cryptoAccounts' => $cryptoAccounts,
        ]);
    }

    public function setPin(Request $request, SetPinAction $setPinAction): RedirectResponse
    {
        $request->validate([
            'pin' => ['required', 'digits:6', 'confirmed'],
        ], [
            'pin.required' => 'PIN 6-digit wajib diisi.',
            'pin.digits' => 'PIN harus terdiri dari tepat 6 digit angka.',
            'pin.confirmed' => 'Konfirmasi PIN tidak cocok.',
        ]);

        $setPinAction->execute($request->user(), (string) $request->input('pin'));

        return back()->with('status', 'PIN transaksi Anda berhasil disimpan.');
    }
}
