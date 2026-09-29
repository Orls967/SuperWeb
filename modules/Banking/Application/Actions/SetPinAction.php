<?php

declare(strict_types=1);

namespace Modules\Banking\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Modules\Banking\Domain\Models\WalletPin;

class SetPinAction
{
    public function execute(User $user, string $pin): WalletPin
    {
        if (! preg_match('/^[0-9]{6}$/', $pin)) {
            throw new InvalidArgumentException('PIN harus berupa 6 digit angka.');
        }

        return WalletPin::updateOrCreate(
            ['user_id' => $user->id],
            [
                'pin_hash' => Hash::make($pin),
                'failed_attempts' => 0,
                'locked_until' => null,
            ]
        );
    }
}
