<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use App\Models\User;
use Modules\Venue\Application\Services\VenueCashlessWristbandWalletService;
use Modules\Venue\Domain\Models\VenueCashlessTransaction;

final class DebitWristbandCashlessWalletAction
{
    public function __construct(
        private readonly VenueCashlessWristbandWalletService $walletService
    ) {}

    public function execute(
        int $userId,
        int $amountIdr,
        string $outletCode,
        string $posReceiptNumber
    ): VenueCashlessTransaction {
        /** @var User $user */
        $user = User::findOrFail($userId);
        $wallet = $this->walletService->getOrCreateWallet($user);

        return $this->walletService->debit($wallet, $amountIdr, $outletCode, $posReceiptNumber);
    }
}
