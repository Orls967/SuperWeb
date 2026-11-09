<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use Modules\Hospital\Application\Sagas\BedDischargeAndCleaningSaga;

/**
 * Aksi Pemulangan Pasien Rawat Inap (Fase 87.3).
 * Mendelegasikan ke BedDischargeAndCleaningSaga.
 */
class DischargePatientAction
{
    public function __construct(
        protected readonly BedDischargeAndCleaningSaga $dischargeSaga
    ) {}

    /**
     * @param array{
     *     admission_id: int,
     *     total_billed_idr: int,
     *     idempotency_key: string,
     *     actor_id?: ?int
     * } $data
     * @return array{success: bool, admission_id: int, bed_code: string, status: string, message: string}
     */
    public function execute(array $data): array
    {
        return $this->dischargeSaga->execute($data);
    }
}
