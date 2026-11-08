<?php

declare(strict_types=1);

namespace Modules\Marine\Application\DTOs;

use DomainException;
use Modules\Shared\Domain\Attributes\NotAControl;

/**
 * DTO deklarasi uji tuntas regulasi anti-deforestasi Uni Eropa (EUDR) (Fase 171).
 */
class EudrComplianceDeclarationDTO
{
    /**
     * @param  array<string, mixed>  $geolocationPolygonGeojson
     */
    public function __construct(
        public readonly string $statementNumber,
        public readonly int $batchId,
        public readonly float $centerLatitude,
        public readonly float $centerLongitude,
        public readonly array $geolocationPolygonGeojson,
        public readonly string $establishmentDate,
        #[NotAControl('Parameter status transfer objek verifikasi deforestasi')]
        public readonly bool $zeroDeforestationVerified,
        public readonly bool $indigenousRightsRespected,
        public readonly string $satelliteSensor = 'SENTINEL_2_OPTICAL_10M'
    ) {
        $this->validate();
    }

    protected function validate(): void
    {
        if ($this->batchId <= 0) {
            throw new DomainException('Batch ID harus valid.');
        }

        if (empty($this->geolocationPolygonGeojson)) {
            throw new DomainException('Poligon geolokasi GeoJSON tambak tidak boleh kosong.');
        }
    }

    public function isCompliantWith2020Cutoff(): bool
    {
        return $this->zeroDeforestationVerified && (strtotime($this->establishmentDate) <= strtotime('2020-12-31'));
    }

    public function getSummaryVerdict(): string
    {
        return $this->isCompliantWith2020Cutoff() ? 'EUDR_COMPLIANT' : 'NON_COMPLIANT_POST_2020_CONVERSION';
    }
}
