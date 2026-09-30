<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Exceptions;

use DomainException;

class OverlappingRateCardException extends DomainException
{
    public static function forLane(string $laneKey, string $from, ?string $to): self
    {
        $toDate = $to ?? 'selamanya';

        return new self("Rate card aktif untuk rute/zona [{$laneKey}] bertumpang tindih dengan periode yang sudah ada ({$from} s/d {$toDate}).");
    }
}
