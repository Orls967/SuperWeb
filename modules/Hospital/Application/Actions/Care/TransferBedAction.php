<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Hospital\Domain\Models\Admission;
use Modules\Hospital\Domain\Models\HospitalBed;
use Modules\Hospital\Domain\StateMachines\AdmissionStateMachine;
use Modules\Hospital\Domain\StateMachines\HospitalBedStateMachine;

/**
 * Aksi Pemindahan Pasien Antar-Ranjang / Bangsal (Fase 87.3).
 * Menerapkan lockForUpdate pada kedua ranjang dan mengalirkan status ke cleaning.
 */
class TransferBedAction
{
    public function execute(int $admissionId, int $newBedId, string $reason, ?int $actorId = null): Admission
    {
        return DB::transaction(function () use ($admissionId, $newBedId, $reason, $actorId): Admission {
            $admission = Admission::where('id', $admissionId)->lockForUpdate()->firstOrFail();
            if ($admission->status !== AdmissionStateMachine::STATUS_ADMITTED) {
                throw new DomainException('Pasien tidak dalam status rawat inap aktif untuk dipindahkan.');
            }

            $currentBed = $admission->bed;
            $newBed = HospitalBed::where('id', $newBedId)->lockForUpdate()->firstOrFail();

            if (strtoupper($newBed->status) !== 'AVAILABLE') {
                throw new DomainException("Ranjang tujuan #{$newBed->bed_code} tidak tersedia (status: {$newBed->status}).");
            }

            // 1. Transisi admisi ke TRANSFERRED
            AdmissionStateMachine::transition($admission, AdmissionStateMachine::STATUS_TRANSFERRED, "Transfer ke ranjang {$newBed->bed_code}: {$reason}", $actorId);

            // 2. Ranjang lama dialihkan ke status CLEANING
            if ($currentBed) {
                HospitalBedStateMachine::transition($currentBed, HospitalBedStateMachine::STATUS_CLEANING, "Pasien pindah ke {$newBed->bed_code}", $actorId);
            }

            // 3. Ranjang baru dialihkan ke status OCCUPIED
            HospitalBedStateMachine::transition($newBed, HospitalBedStateMachine::STATUS_OCCUPIED, "Menerima transfer pasien dari {$currentBed?->bed_code}", $actorId);

            // 4. Update relasi admisi dan kembalikan status ADMITTED
            $admission->bed_id = $newBed->id;
            $admission->current_class = $newBed->bed_class;
            $isUpgrade = strtoupper($newBed->bed_class) !== strtoupper($admission->entitled_class);
            $admission->is_upgraded = $isUpgrade;
            $admission->upgrade_absorbed_by_hospital = $isUpgrade;
            $admission->save();

            AdmissionStateMachine::transition($admission, AdmissionStateMachine::STATUS_ADMITTED, "Selesai transfer ke {$newBed->bed_code}", $actorId);

            return $admission->fresh();
        });
    }
}
