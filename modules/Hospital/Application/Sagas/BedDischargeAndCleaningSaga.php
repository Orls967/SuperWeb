<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Sagas;

use DomainException;
use Exception;
use Illuminate\Support\Facades\DB;
use Modules\Hospital\Domain\Models\Admission;
use Modules\Hospital\Domain\Models\CareSagaRecord;
use Modules\Hospital\Domain\Models\HospitalBed;
use Modules\Hospital\Domain\Services\HospitalLedgerPostingService;
use Modules\Hospital\Domain\StateMachines\AdmissionStateMachine;
use Modules\Hospital\Domain\StateMachines\HospitalBedStateMachine;

/**
 * Saga Pemulangan Pasien dan Alur Kebersihan Ranjang (Fase 87.3).
 * Langkah: Rencana Discharge -> Pelunasan Deposit -> Alihkan Bed ke CLEANING -> Selesaikan Discharge.
 */
class BedDischargeAndCleaningSaga
{
    public function __construct(
        protected readonly HospitalLedgerPostingService $ledgerPostingService
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
        $idempotencyKey = $data['idempotency_key'];

        $existingSaga = CareSagaRecord::where('saga_ref', $idempotencyKey)->first();
        if ($existingSaga !== null && $existingSaga->status === 'COMPLETED') {
            return [
                'success' => true,
                'admission_id' => $data['admission_id'],
                'bed_code' => (string) ($existingSaga->payload['bed_code'] ?? ''),
                'status' => 'discharged',
                'message' => 'Discharge berhasil dipulihkan dari riwayat idempoten.',
            ];
        }

        $sagaRecord = CareSagaRecord::create([
            'saga_ref' => $idempotencyKey,
            'saga_type' => 'BED_DISCHARGE_CLEANING',
            'current_step' => 'STARTED',
            'status' => 'STARTED',
            'payload' => $data,
            'compensation_history' => [],
        ]);

        $admission = Admission::findOrFail($data['admission_id']);
        $bed = $admission->bed;
        $totalBilled = (int) $data['total_billed_idr'];
        $actorId = $data['actor_id'] ?? null;

        try {
            DB::beginTransaction();

            // 1. Rencana Pemulangan
            if ($admission->status === AdmissionStateMachine::STATUS_ADMITTED) {
                AdmissionStateMachine::transition($admission, AdmissionStateMachine::STATUS_DISCHARGE_PLANNED, 'Pemeriksaan kepulangan DPJP', $actorId);
            }

            // 2. Alihkan Ranjang ke status CLEANING
            if ($bed) {
                $bedLocked = HospitalBed::where('id', $bed->id)->lockForUpdate()->first();
                if ($bedLocked) {
                    HospitalBedStateMachine::transition($bedLocked, HospitalBedStateMachine::STATUS_CLEANING, 'Pasien keluar; masuk antrean kebersihan', $actorId);
                }
            }

            // 3. Selesaikan Discharge
            AdmissionStateMachine::transition($admission, AdmissionStateMachine::STATUS_DISCHARGED, 'Pelunasan dan pelepasan kamar selesai', $actorId);
            $admission->total_billed_idr = $totalBilled;
            $admission->save();

            DB::commit();

            // 4. Penangkapan deposit jika ada
            if ($admission->deposit_idr > 0 && $totalBilled > 0) {
                $captureAmount = min($admission->deposit_idr, $totalBilled);
                $captureKey = "hsp:capture:{$admission->id}:{$idempotencyKey}";
                $this->ledgerPostingService->postDepositSettlementCapture($admission->id, $captureAmount, $captureKey);
            }

            $sagaRecord->update([
                'status' => 'COMPLETED',
                'current_step' => 'COMPLETED',
                'payload' => array_merge($data, [
                    'bed_code' => $bed?->bed_code ?? '-',
                ]),
            ]);

            return [
                'success' => true,
                'admission_id' => $admission->id,
                'bed_code' => $bed?->bed_code ?? '-',
                'status' => 'discharged',
                'message' => 'Pasien berhasil dipulangkan dan kamar masuk antrean sanitasi kebersihan.',
            ];

        } catch (Exception $e) {
            DB::rollBack();

            $sagaRecord->update([
                'status' => 'COMPENSATED',
                'current_step' => 'FAILED_AND_COMPENSATED',
                'error_message' => $e->getMessage(),
                'compensation_history' => ["Rollback transaksi discharge admisi #{$admission->id}"],
            ]);

            throw $e;
        }
    }
}
