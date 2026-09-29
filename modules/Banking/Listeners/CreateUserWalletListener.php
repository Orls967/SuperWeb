<?php

declare(strict_types=1);

namespace Modules\Banking\Listeners;

use Illuminate\Auth\Events\Registered;

class CreateUserWalletListener
{
    public function handle(Registered $event): void
    {
        $user = $event->user;

        if (method_exists($user, 'walletAccount')) {
            $user->walletAccount('IDR');
        }
    }
}
