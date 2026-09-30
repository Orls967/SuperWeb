<?php

declare(strict_types=1);

namespace Modules\Banking\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\Banking\Contracts\VerifiesWalletPin;
use Modules\Banking\Domain\Exceptions\InvalidPinException;
use Modules\Banking\Domain\Exceptions\PinLockedException;

class VerifyPinAction implements VerifiesWalletPin
{
    public function execute(User $user, string $pin): bool
    {
        $walletPin = $user->walletPin;

        if ($walletPin === null) {
            throw new InvalidPinException(0, 0);
        }

        if ($walletPin->isLocked()) {
            throw new PinLockedException($walletPin->locked_until);
        }

        if (Hash::check($pin, $walletPin->pin_hash)) {
            if ($walletPin->failed_attempts > 0 || $walletPin->locked_until !== null) {
                $walletPin->update([
                    'failed_attempts' => 0,
                    'locked_until' => null,
                ]);
            }

            return true;
        }

        $walletPin->failed_attempts += 1;

        if ($walletPin->failed_attempts >= 5) {
            $walletPin->locked_until = now()->addMinutes(15);
            $walletPin->save();

            throw new PinLockedException($walletPin->locked_until);
        }

        $walletPin->save();
        $remaining = 5 - $walletPin->failed_attempts;

        throw new InvalidPinException($walletPin->failed_attempts, $remaining);
    }
}
