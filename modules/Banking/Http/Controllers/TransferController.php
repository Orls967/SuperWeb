<?php

declare(strict_types=1);

namespace Modules\Banking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Banking\Application\Actions\TransferAction;
use Modules\Banking\Domain\Exceptions\AccountFrozenException;
use Modules\Banking\Domain\Exceptions\InsufficientFundsException;
use Modules\Banking\Domain\Exceptions\InvalidPinException;
use Modules\Banking\Domain\Exceptions\PinLockedException;
use Modules\Banking\Domain\Exceptions\SelfTransferException;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Shared\Domain\ValueObjects\Money;
use RuntimeException;

class TransferController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('banking::transfer.index', [
            'user' => $user,
            'idrBalance' => $user->walletBalance('IDR'),
            'hasPin' => $user->hasPin(),
            'isPinLocked' => $user->isPinLocked(),
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('query', ''));
        $currentUser = $request->user();

        if ($query === '') {
            return response()->json(['found' => false, 'message' => 'Masukkan email atau nomor akun tujuan.']);
        }

        // Try lookup by account code first
        $account = LedgerAccount::where('code', $query)->first();
        $targetUser = null;

        if ($account && $account->owner_type === User::class && $account->owner_id) {
            $targetUser = User::find($account->owner_id);
        }

        // If not found by code, try lookup by email
        if (! $targetUser) {
            $targetUser = User::where('email', $query)->first();
        }

        if (! $targetUser) {
            return response()->json(['found' => false, 'message' => 'Penerima tidak ditemukan. Pastikan email atau kode akun benar.']);
        }

        if ($targetUser->id === $currentUser->id) {
            return response()->json(['found' => false, 'message' => 'Tidak dapat mentransfer ke diri sendiri.']);
        }

        return response()->json([
            'found' => true,
            'user_id' => $targetUser->id,
            'name' => TransferAction::maskName($targetUser->name),
            'email' => $targetUser->email,
            'account_code' => $targetUser->walletAccount('IDR')->code,
        ]);
    }

    public function store(Request $request, TransferAction $transferAction): RedirectResponse
    {
        $validated = $request->validate([
            'recipient_query' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:1000', 'max:100000000'],
            'pin' => ['required', 'digits:6'],
            'note' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ], [
            'recipient_query.required' => 'Penerima transfer wajib ditentukan.',
            'amount.required' => 'Nominal transfer wajib diisi.',
            'amount.min' => 'Minimal transfer adalah Rp 1.000.',
            'pin.required' => 'PIN transaksi 6-digit wajib diisi.',
            'pin.digits' => 'PIN harus terdiri dari tepat 6 digit.',
        ]);

        $query = trim($validated['recipient_query']);
        $sender = $request->user();

        // Find recipient
        $targetUser = null;
        $account = LedgerAccount::where('code', $query)->first();
        if ($account && $account->owner_type === User::class && $account->owner_id) {
            $targetUser = User::find($account->owner_id);
        }

        if (! $targetUser) {
            $targetUser = User::where('email', $query)->first();
        }

        if (! $targetUser) {
            return back()->withInput()->withErrors(['recipient_query' => 'Penerima tidak ditemukan.']);
        }

        try {
            $tx = $transferAction->execute(
                sender: $sender,
                recipient: $targetUser,
                amount: (string) $validated['amount'],
                pin: $validated['pin'],
                note: $validated['note'] ?? null,
                idempotencyKey: $validated['idempotency_key'] ?? null,
            );

            $formatted = Money::IDR($validated['amount'])->format();
            $maskedRecipient = TransferAction::maskName($targetUser->name);

            return redirect()->route('wallet.index')->with(
                'status',
                "Transfer sebesar {$formatted} ke {$maskedRecipient} berhasil dikirim."
            );
        } catch (InvalidPinException $e) {
            return back()->withInput()->withErrors(['pin' => $e->getMessage()]);
        } catch (PinLockedException $e) {
            return back()->withInput()->withErrors(['pin' => $e->getMessage()]);
        } catch (InsufficientFundsException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        } catch (SelfTransferException $e) {
            return back()->withInput()->withErrors(['recipient_query' => $e->getMessage()]);
        } catch (AccountFrozenException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }
    }
}
