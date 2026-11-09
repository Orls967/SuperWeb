<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Sagas;

use DomainException;
use Exception;
use Illuminate\Support\Facades\DB;
use Modules\Hospital\Domain\Algorithms\BedAssignmentOptimizer;
use Modules\Hospital\Domain\Models\Admission;
use Modules\Hospital\Domain\Models\CareSagaRecord;
use Modules\Hospital\Domain\Models\HospitalBed;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Services\HospitalLedgerPostingService;
use Modules\Hospital\Domain\StateMachines\AdmissionStateMachine;
use Modules\Hospital\Domain\StateMachines\HospitalBedStateMachine;

/**
 * Saga Admisi Rawat Inap Berkompensasi Otomatis (Fase 87.3).
 * Langkah: Reservasi Ranjang (lockForUpdate) -> Tangkap Deposit Escrow -> Admit.
 * Kompensasi: Pelepasan reservasi ranjang jika deposit gagal atau terjadi galat.
 */
class InpatientAdmissionSaga
{
    public function __construct(
        protected readonly HospitalLedgerPostingService $ledgerPostingService,
        protected readonly BedAssignmentOptimizer $bedOptimizer
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
        $idempotencyKey = $data['idempotency_key'];

        // Cek apakah saga dengan key ini sudah pernah sukses dijalankan (idempotensi)
        $existingSaga = CareSagaRecord::where('saga_ref', $idempotencyKey)->first();
        if ($existingSaga !== null && $existingSaga->status === 'COMPLETED') {
            $payload = $existingSaga->payload;
            return [
                'success' => true,
                'admission_id' => (int) $payload['admission_id'],
                'bed_code' => (string) $payload['bed_code'],
                'status' => 'admitted',
                'message' => 'Admisi berhasil diambil kembali dari riwayat idempoten.',
            ];
        }

        $sagaRecord = CareSagaRecord::create([
            'saga_ref' => $idempotencyKey,
            'saga_type' => 'INPATIENT_ADMISSION',
            'current_step' => 'STARTED',
            'status' => 'STARTED',
            'payload' => $data,
            'compensation_history' => [],
        ]);

        $patient = Patient::findOrFail($data['patient_id']);
        $reqClass = strtoupper($data['required_class']);
        $depositIdr = (int) ($data['deposit_idr'] ?? 0);
        $actorId = $data['actor_id'] ?? null;

        $targetBed = null;
        $admission = null;

        try {
            // LANGKAH 1: Kunci dan Reservasi Ranjang
            $sagaRecord->update(['current_step' => 'BED_RESERVATION']);

            DB::beginTransaction();

            if (! empty($data['bed_id'])) {
                $targetBed = HospitalBed::where('id', $data['bed_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $targetBed || strtoupper($targetBed->status) !== 'AVAILABLE') {
                    throw new DomainException("Ranjang tempat tidur #{$data['bed_id']} tidak tersedia atau sedang ditempati.");
                }
            } else {
                // Alokasi otomatis via B1
                $availableBeds = HospitalBed::where('status', 'AVAILABLE')->lockForUpdate()->get();
                $bedCandidates = $availableBeds->map(fn ($b) => [
                    'bed_code' => (string) $b->bed_code,
                    'bed_class' => (string) $b->bed_class,
                ])->all();

                $patientReq = [[
                    'id' => (string) $patient->id,
                    'required_class' => $reqClass,
                    'priority' => (int) ($data['priority'] ?? 1),
                    'waiting_minutes' => 15,
                ]];

                $optResult = $this->bedOptimizer->optimize($patientReq, $bedCandidates);
                $assignment = $optResult->findAssignment((string) $patient->id);

                if (! $assignment) {
                    throw new DomainException("Tidak ada tempat tidur yang memenuhi syarat kelas {$reqClass}.");
                }

                $targetBed = $availableBeds->firstWhere('bed_code', $assignment['bed_code']);
            }

            // Kunci ranjang ke status RESERVED
            HospitalBedStateMachine::transition($targetBed, HospitalBedStateMachine::STATUS_RESERVED, 'Reservasi kuncian admisi saga', $actorId);

            // Buat draf admisi
            $isUpgrade = strtoupper($targetBed->bed_class) !== $reqClass;
            $admission = Admission::create([
                'admission_no' => 'ADM-' . date('Ymd') . '-' . str_pad((string) $patient->id, 5, '0', STR_PAD_LEFT) . '-' . substr(md5($idempotencyKey), 0, 4),
                'patient_id' => $patient->id,
                'bed_id' => $targetBed->id,
                'entitled_class' => $reqClass,
                'current_class' => $targetBed->bed_class,
                'is_upgraded' => $isUpgrade,
                'upgrade_absorbed_by_hospital' => $isUpgrade,
                'status' => AdmissionStateMachine::STATUS_BED_RESERVED,
                'deposit_idr' => $depositIdr,
                'admitted_at' => now(),
            ]);

            DB::commit();

            // LANGKAH 2: Tangkap Deposit Escrow (Uang)
            $sagaRecord->update(['current_step' => 'DEPOSIT_ESCROW_CAPTURE']);

            if ($depositIdr > 0) {
                $depositKey = "hsp:deposit:{$admission->id}:{$idempotencyKey}";
                $this->ledgerPostingService->postInpatientDeposit($admission->id, $depositIdr, $depositKey);
            }

            // LANGKAH 3: Selesaikan Admisi dan Tandai Ranjang OCCUPIED
            $sagaRecord->update(['current_step' => 'FINALIZE_ADMISSION']);

            DB::beginTransaction();
            HospitalBedStateMachine::transition($targetBed, HospitalBedStateMachine::STATUS_OCCUPIED, 'Pasien menempati ranjang', $actorId);
            AdmissionStateMachine::transition($admission, AdmissionStateMachine::STATUS_ADMITTED, 'Deposit terverifikasi & kamar siap', $actorId);
            DB::commit();

            $sagaRecord->update([
                'status' => 'COMPLETED',
                'current_step' => 'COMPLETED',
                'payload' => array_merge($data, [
                    'admission_id' => $admission->id,
                    'bed_code' => $targetBed->bed_code,
                ]),
            ]);

            return [
                'success' => true,
                'admission_id' => $admission->id,
                'bed_code' => $targetBed->bed_code,
                'status' => 'admitted',
                'message' => 'Admisi rawat inap berhasil diselesaikan.',
            ];

        } catch (Exception $e) {
            DB::rollBack();

            // KOMPENSASI OTOMATIS: Kembalikan status ranjang dan batalkan admisi
            $compensationLog = [];

            if ($targetBed && strtoupper($targetBed->fresh()?->status ?? $targetBed->status) === 'RESERVED') {
                try {
                    HospitalBedStateMachine::transition($targetBed, HospitalBedStateMachine::STATUS_AVAILABLE, 'Kompensasi: pelepasan kuncian gagal deposit', $actorId);
                    $compensationLog[] = "Bed #{$targetBed->id} dikembalikan ke AVAILABLE";
                } catch (Exception $ex) {
                    $compensationLog[] = "Gagal kompensasi bed: " . $ex->getMessage();
                }
            }

            if ($admission && ($admission->fresh()?->status ?? $admission->status) === AdmissionStateMachine::STATUS_BED_RESERVED) {
                try {
                    AdmissionStateMachine::transition($admission, AdmissionStateMachine::STATUS_CANCELLED, 'Kompensasi: pembatalan admisi', $actorId);
                    $compensationLog[] = "Admisi #{$admission->id} dibatalkan";
                } catch (Exception $ex) {
                    $compensationLog[] = "Gagal kompensasi admisi: " . $ex->getMessage();
                }
            }

            $sagaRecord->update([
                'status' => 'COMPENSATED',
                'current_step' => 'FAILED_AND_COMPENSATED',
                'error_message' => $e->getMessage(),
                'compensation_history' => $compensationLog,
            ]);

            throw $e;
        }
    }
}
