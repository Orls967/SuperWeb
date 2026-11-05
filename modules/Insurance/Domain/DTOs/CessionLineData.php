<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\DTOs;

final readonly class CessionLineData
{
    public function __construct(
        public int $treatyId,
        public string $layerCode,
        public string $reinsurerPartyId,
        public int $cededIdr,
        public int $commissionIdr = 0,
        public int $reinstatementIdr = 0,
    ) {}

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'treaty_id' => $this->treatyId,
            'layer_code' => $this->layerCode,
            'reinsurer_party_id' => $this->reinsurerPartyId,
            'ceded_idr' => $this->cededIdr,
            'commission_idr' => $this->commissionIdr,
            'reinstatement_idr' => $this->reinstatementIdr,
        ];
    }
}
