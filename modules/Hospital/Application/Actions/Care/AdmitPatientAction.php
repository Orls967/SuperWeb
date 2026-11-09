<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use Modules\Hospital\Application\Sagas\InpatientAdmissionSaga;

/**
 * Aksi Inisiasi Admisi Rawat Inap (Fase 87.3).
 * Mendelegasikan eksekusi ke InpatientAdmissionSaga.
 */
class AdmitPatientAction
{
    public function __construct(
        protected readonly InpatientAdmissionSaga $admissionSaga
    ) {}

    /**
     * @param array{
     *     patient_id: int,
     *     bed_id?: ?int,
     *     required_class: string,
     *     priority?: int,
     *     deposit_idr: int,
     *     idempotency_key: string,
     *     actor_id?: ?int
     * } $data
     * @return array{success: bool, admission_id: int, bed_code: string, status: string, message: string}
     */
    public function execute(array $data): array
    {
        return $this->admissionSaga->execute($data);
    }
}
