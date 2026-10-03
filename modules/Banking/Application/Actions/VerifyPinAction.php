<?php

declare(strict_types=1);

namespace Modules\Banking\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Banking\Contracts\VerifiesWalletPin;
use Modules\Banking\Domain\Exceptions\InvalidPinException;
use Modules\Banking\Domain\Exceptions\PinLockedException;
use RuntimeException;

class VerifyPinAction implements VerifiesWalletPin
{
    public function execute(User $user, string $pin): bool
    {
        $pendingFailure = null;

        DB::transaction(function () use ($user, $pin, &$pendingFailure): void {
            // Kunci baris bank_wallet_pins agar hitungan percobaan tidak pernah
            // kehilangan update saat beberapa permintaan masuk bersamaan.
            $walletPin = $user->walletPin()
                ->lockForUpdate()
                ->first();

            if ($walletPin === null) {
                $pendingFailure = new InvalidPinException(0, 0);

                return;
            }

            if ($walletPin->isLocked()) {
                $pendingFailure = new PinLockedException($walletPin->locked_until);

                return;
            }

            if (Hash::check($pin, $walletPin->pin_hash)) {
                if ($walletPin->failed_attempts > 0 || $walletPin->locked_until !== null) {
                    $walletPin->update([
                        'failed_attempts' => 0,
                        'locked_until' => null,
                    ]);
                }

                return;
            }

            // Increment atomik: setiap percobaan gagal selalu menaikkan hitungan.
            $walletPin->increment('failed_attempts');
            $walletPin->refresh();

            if ($walletPin->failed_attempts >= 5) {
                $walletPin->locked_until = now()->addMinutes(15);
                $walletPin->save();

                $pendingFailure = new PinLockedException($walletPin->locked_until);

                return;
            }

            $remaining = 5 - $walletPin->failed_attempts;

            $pendingFailure = new InvalidPinException($walletPin->failed_attempts, $remaining);
        });

        // Galat dilempar setelah transaksi commit agar hitungan percobaan tetap tersimpan.
        if ($pendingFailure instanceof RuntimeException) {
            throw $pendingFailure;
        }

        return true;
    }
}
