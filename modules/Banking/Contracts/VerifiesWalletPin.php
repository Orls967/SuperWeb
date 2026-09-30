<?php

declare(strict_types=1);

namespace Modules\Banking\Contracts;

use App\Models\User;
use Modules\Banking\Domain\Exceptions\InvalidPinException;
use Modules\Banking\Domain\Exceptions\PinLockedException;

interface VerifiesWalletPin
{
    /**
     * Verifikasi PIN dompet user.
     *
     * @throws InvalidPinException Bila PIN salah
     * @throws PinLockedException Bila akun terkunci karena salah 5x
     */
    public function execute(User $user, string $pin): bool;
}
