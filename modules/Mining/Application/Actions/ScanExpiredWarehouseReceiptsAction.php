<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Carbon\Carbon;
use Modules\Mining\Domain\Enums\WarehouseReceiptStatus;
use Modules\Mining\Domain\Models\MiningMetalsWarehouseReceipt;
use Modules\Mining\Domain\StateMachines\WarehouseReceiptCollateralStateMachine;

final class ScanExpiredWarehouseReceiptsAction
{
    public function __construct(
        private readonly WarehouseReceiptCollateralStateMachine $stateMachine,
    ) {}

    /**
     * @return array{scanned: int, expired: int, defaulted: int}
     */
    public function execute(?string $referenceDate = null): array
    {
        $cutoff = $referenceDate ? Carbon::parse($referenceDate) : Carbon::now();

        $activeReceipts = MiningMetalsWarehouseReceipt::query()
            ->whereIn('status', [
                WarehouseReceiptStatus::ISSUED->value,
                WarehouseReceiptStatus::VERIFIED->value,
                WarehouseReceiptStatus::PLEDGED_SCF->value,
            ])
            ->where('valid_until_at', '<', $cutoff)
            ->get();

        $scanned = 0;
        $expired = 0;
        $defaulted = 0;

        foreach ($activeReceipts as $receipt) {
            $scanned++;
            $currentStatus = WarehouseReceiptStatus::from($receipt->status);

            if ($currentStatus === WarehouseReceiptStatus::PLEDGED_SCF) {
                // If pledged to bank SCF and expired without repayment, transition to DEFAULTED for collateral foreclosure
                $this->stateMachine->transition(
                    $receipt,
                    WarehouseReceiptStatus::DEFAULTED,
                    ['expired_at' => $cutoff->toIso8601String(), 'reason' => 'SCF maturity exceeded without loan clearance']
                );
                $defaulted++;
            } else {
                $this->stateMachine->transition(
                    $receipt,
                    WarehouseReceiptStatus::EXPIRED,
                    ['expired_at' => $cutoff->toIso8601String(), 'reason' => 'Validity period expired without commercial drawdown']
                );
                $expired++;
            }
        }

        return [
            'scanned' => $scanned,
            'expired' => $expired,
            'defaulted' => $defaulted,
        ];
    }
}
