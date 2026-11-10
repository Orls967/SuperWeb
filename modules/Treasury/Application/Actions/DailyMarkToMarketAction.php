<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Actions;

use InvalidArgumentException;
use Modules\Treasury\Domain\Enums\HedgeStatus;
use Modules\Treasury\Domain\Models\ForwardContract;

class DailyMarkToMarketAction
{
    /**
     * Hitung nilai wajar pasar harian (Mark-to-Market) untuk kontrak forward aktif.
     */
    public function execute(ForwardContract $contract, int $currentSpotRateScaled): int
    {
        $currentStatus = $contract->status instanceof HedgeStatus ? $contract->status->value : (string) $contract->status;
        if (! in_array($currentStatus, [HedgeStatus::ACTIVE->value, HedgeStatus::BOOKED->value, HedgeStatus::DRAFT->value], true)) {
            throw new InvalidArgumentException("Kontrak forward {$contract->contract_number} tidak berada dalam status aktif (status: {$currentStatus}).");
        }

        if ($currentSpotRateScaled <= 0) {
            throw new InvalidArgumentException('Kurs spot pasar harus lebih besar dari 0.');
        }

        $notionalMinor = (int) $contract->notional_foreign_amount;
        $forwardRate = (int) $contract->forward_rate_scaled;

        // Nilai forward vs spot
        $forwardValueIdr = (int) intdiv($notionalMinor * $forwardRate, 1000000);
        $spotValueIdr = (int) intdiv($notionalMinor * $currentSpotRateScaled, 1000000);

        // MTM = Nilai wajar spot - Nilai komitmen kontrak forward
        $mtmValueIdr = $spotValueIdr - $forwardValueIdr;

        $contract->update([
            'mtm_value_idr' => $mtmValueIdr,
            'spot_rate_numerator' => $currentSpotRateScaled,
            'spot_rate_denominator' => 10000,
        ]);

        return $mtmValueIdr;
    }
}
