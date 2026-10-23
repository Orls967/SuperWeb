<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface TwelveLinesAuditPort
{
    public function getVenueCount(): int;

    public function getHotelCount(): int;

    public function getMiningCount(): int;

    public function getNegativeAssetDiscrepancies(): int;
}
