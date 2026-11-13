<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Kartu tarif jasa gudang pihak ketiga (3PL) dalam integer IDR minor units.
 */
final readonly class ThreePlRateCard
{
    public function __construct(
        public string $clientCode,
        public int $storageRatePerPalletDayIdr,
        public int $inboundHandlingPerUnitIdr,
        public int $outboundHandlingPerUnitIdr,
        public int $minimumMonthlyFeeIdr = 0,
    ) {
        if (
            $clientCode === ''
            || $storageRatePerPalletDayIdr < 0
            || $inboundHandlingPerUnitIdr < 0
            || $outboundHandlingPerUnitIdr < 0
            || $minimumMonthlyFeeIdr < 0
        ) {
            throw new InvalidArgumentException('Parameter ThreePlRateCard tidak valid.');
        }
    }

    public function computeTotalFeeIdr(int $palletDays, int $inboundUnits, int $outboundUnits): int
    {
        $storage = $palletDays * $this->storageRatePerPalletDayIdr;
        $inbound = $inboundUnits * $this->inboundHandlingPerUnitIdr;
        $outbound = $outboundUnits * $this->outboundHandlingPerUnitIdr;
        $subtotal = $storage + $inbound + $outbound;

        return max($this->minimumMonthlyFeeIdr, $subtotal);
    }
}
