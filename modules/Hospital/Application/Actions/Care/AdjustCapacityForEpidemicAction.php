<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use DomainException;
use Modules\Hospital\Domain\Models\HospitalBed;
use Modules\Hospital\Domain\Services\HospitalExternalFactors;
use Modules\Hospital\Domain\StateMachines\HospitalBedStateMachine;

/**
 * Aksi Penyesuaian Kapasitas Darurat Epidemi DBD / ISPA (T-5 / Fase 87.6).
 * Membaca indikator dari HospitalExternalFactors dan mengalihkan status ranjang.
 */
class AdjustCapacityForEpidemicAction
{
    public function __construct(
        protected readonly HospitalExternalFactors $externalFactors
    ) {}

    /**
     * @param string $epidemicType 'DENGUE' atau 'RESPIRATORY'
     * @param int $bedQuota Jumlah ranjang reguler yang dialihkan menjadi isolasi
     * @param int $actorId Petugas rs_admin yang menyetujui
     * @return array{beds_converted: int, status: string, message: string}
     */
    public function execute(string $epidemicType, int $bedQuota, int $actorId): array
    {
        $cityCode = 'JKT';
        $isEmergency = match (strtoupper($epidemicType)) {
            'DENGUE' => $this->externalFactors->isDengueOutbreak($cityCode),
            'RESPIRATORY' => $this->externalFactors->isAirQualityEmergency($cityCode),
            default => false,
        };

        if (! $isEmergency) {
            throw new DomainException("Indeks faktor eksternal belum mencapai ambang kedaruratan epidemi.");
        }

        // Cari ranjang kelas reguler yang AVAILABLE
        $beds = HospitalBed::where('status', 'AVAILABLE')
            ->whereIn('bed_class', ['CLASS_2', 'CLASS_3'])
            ->limit($bedQuota)
            ->get();

        $converted = 0;
        foreach ($beds as $b) {
            HospitalBedStateMachine::transition(
                $b,
                HospitalBedStateMachine::STATUS_ISOLATED_HOLD,
                "Pencadangan darurat wabah {$epidemicType}",
                $actorId
            );
            $converted++;
        }

        return [
            'beds_converted' => $converted,
            'status' => 'ACTIVE',
            'message' => "Berhasil mencadangkan {$converted} ranjang menjadi status isolasi darurat.",
        ];
    }
}
