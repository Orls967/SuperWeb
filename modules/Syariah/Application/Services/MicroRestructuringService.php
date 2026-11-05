<?php

declare(strict_types=1);

namespace Modules\Syariah\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Syariah\Domain\Enums\MicroFacilityStatus;
use Modules\Syariah\Domain\Models\SybMicroFacility;
use Modules\Syariah\Domain\Models\SybMicroRestructuring;
use Modules\Syariah\Domain\Models\SybMicroSchedule;

class MicroRestructuringService
{
    /**
     * Menilai apakah kerugian usaha murni risiko bisnis / force majeure (shohibul maal menanggung porsi modal)
     * atau kelalaian / tafrith / ta'addi mudharib (moral hazard guard).
     */
    public function evaluateBusinessLoss(
        int $actualRevenueIdr,
        int $operationalCostIdr,
        string $lossCause = 'business_risk'
    ): array {
        $netProfitOrLoss = $actualRevenueIdr - $operationalCostIdr;

        if ($netProfitOrLoss >= 0) {
            return [
                'has_loss' => false,
                'loss_amount_idr' => 0,
                'loss_type' => 'PROFIT_EARNED',
                'moral_hazard' => false,
                'shohibul_maal_share_idr' => 0,
                'mudharib_liability_idr' => 0,
            ];
        }

        $lossAmount = abs($netProfitOrLoss);

        // Jika penyebab kelalaian atau pelanggaran covenant
        if ($lossCause === 'negligence' || $lossCause === 'covenant_breach') {
            return [
                'has_loss' => true,
                'loss_amount_idr' => $lossAmount,
                'loss_type' => 'MORAL_HAZARD_NEGLIGENCE',
                'moral_hazard' => true,
                'shohibul_maal_share_idr' => 0,
                'mudharib_liability_idr' => $lossAmount,
                'explanation' => 'Kerugian akibat kelalaian operasional atau pelanggaran perjanjian (tafrith/taaddi). Ditanggung penuh oleh pengelola (mudharib).',
            ];
        }

        // Kerugian non-kelalaian (risiko bisnis syariah / force majeure)
        return [
            'has_loss' => true,
            'loss_amount_idr' => $lossAmount,
            'loss_type' => 'NON_NEGLIGENCE_LOSS',
            'moral_hazard' => false,
            'shohibul_maal_share_idr' => $lossAmount,
            'mudharib_liability_idr' => 0,
            'explanation' => 'Kerugian murni risiko usaha tanpa kelalaian. Shohibul maal menanggung kerugian finansial sesuai prinsip mudharabah.',
        ];
    }

    public function restructureFacility(
        string $facilityCode,
        string $reason,
        int $newNisbahBps,
        int $extensionMonths,
        string $lossCause,
        string $approverUserId
    ): SybMicroRestructuring {
        return DB::transaction(function () use (
            $facilityCode,
            $reason,
            $newNisbahBps,
            $extensionMonths,
            $lossCause,
            $approverUserId
        ) {
            $facility = SybMicroFacility::where('facility_code', $facilityCode)->lockForUpdate()->firstOrFail();

            if (! in_array($facility->status, [MicroFacilityStatus::ACTIVE, MicroFacilityStatus::DEFAULTED], true)) {
                throw new InvalidArgumentException("Fasilitas {$facilityCode} dengan status {$facility->status->value} tidak dapat direstrukturisasi.");
            }

            $lossEvaluation = $this->evaluateBusinessLoss(
                actualRevenueIdr: 0,
                operationalCostIdr: $facility->outstanding_balance_idr,
                lossCause: $lossCause
            );

            $code = 'RST-'.strtoupper(Str::random(8));

            $restructuring = SybMicroRestructuring::create([
                'restruct_code' => $code,
                'facility_code' => $facilityCode,
                'reason' => $reason,
                'loss_type' => $lossEvaluation['loss_type'],
                'previous_nisbah_bps' => $facility->margin_or_nisbah_bps,
                'new_nisbah_bps' => $newNisbahBps,
                'previous_tenor_months' => $facility->tenor_months,
                'new_tenor_months' => $facility->tenor_months + $extensionMonths,
                'restructured_principal_idr' => $facility->outstanding_balance_idr,
                'moral_hazard_detected' => $lossEvaluation['moral_hazard'],
                'approved_by' => $approverUserId,
                'approved_at' => CarbonImmutable::now(),
            ]);

            // Update status dan jadwal fasilitas mikro
            $facility->status = MicroFacilityStatus::RESTRUCTURED;
            $facility->margin_or_nisbah_bps = $newNisbahBps;
            $facility->tenor_months += $extensionMonths;
            $facility->save();

            // Reschedule angsuran tersisa
            $pendingSchedules = SybMicroSchedule::where('facility_code', $facilityCode)
                ->where('status', 'PENDING')
                ->get();

            $totalPending = $pendingSchedules->count() + $extensionMonths;
            $newPrincipalPerInstallment = (int) intdiv($facility->outstanding_balance_idr, max(1, $totalPending));

            $today = CarbonImmutable::now();
            $installmentNumber = 1;
            foreach ($pendingSchedules as $sch) {
                $sch->due_date = $today->addMonths($installmentNumber)->toDateString();
                $sch->principal_due_idr = $newPrincipalPerInstallment;
                $sch->save();
                $installmentNumber++;
            }

            for ($ext = 1; $ext <= $extensionMonths; $ext++) {
                SybMicroSchedule::create([
                    'schedule_code' => "SCH-{$code}-EXT-{$ext}",
                    'facility_code' => $facilityCode,
                    'installment_number' => $installmentNumber,
                    'due_date' => $today->addMonths($installmentNumber)->toDateString(),
                    'principal_due_idr' => $newPrincipalPerInstallment,
                    'margin_due_idr' => (int) intdiv($newPrincipalPerInstallment * $newNisbahBps, 10000),
                    'status' => 'PENDING',
                ]);
                $installmentNumber++;
            }

            return $restructuring;
        });
    }
}
