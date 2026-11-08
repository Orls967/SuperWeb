<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Hotel\Domain\Algorithms\MilestoneScheduleBuilder;
use Modules\Hotel\Domain\Algorithms\MilestoneScheduleCertificate;
use Modules\Hotel\Domain\Models\HtlWeddingMilestoneSchedule;
use Modules\Hotel\Domain\Models\MiceContract;

final class HotelWeddingContractEscalationService
{
    public const PLATINUM_BRIDAL_SUITE_THRESHOLD = 250000000;

    public function __construct(
        private readonly MilestoneScheduleBuilder $scheduleBuilder
    ) {}

    public function generateWeddingMilestonesAndPrivileges(MiceContract $contract): MilestoneScheduleCertificate
    {
        return DB::transaction(function () use ($contract) {
            $totalAmount = (int) ($contract->contract_total_value_idr ?: $contract->total_contract_amount_idr);
            $signDate = $contract->signing_date ?: ($contract->created_at?->format('Y-m-d') ?? date('Y-m-d'));
            $eventStart = $contract->event_start_date ?: $contract->event_date;

            $certificate = $this->scheduleBuilder->build(
                contractId: $contract->event_code,
                totalContractAmountIdr: $totalAmount,
                signingDate: $signDate,
                eventStartDate: $eventStart
            );

            // Bridal suite complimentary upgrade if >= 250M
            if ($totalAmount >= self::PLATINUM_BRIDAL_SUITE_THRESHOLD) {
                $contract->is_bridal_suite_complimentary = true;
                $contract->save();
            }

            // Hapus draf jadwal lama jika ada
            HtlWeddingMilestoneSchedule::where('mice_contract_id', $contract->id)->delete();

            foreach ($certificate->milestones as $ms) {
                HtlWeddingMilestoneSchedule::create([
                    'id' => (string) Str::uuid(),
                    'mice_contract_id' => $contract->id,
                    'milestone_index' => $ms['milestone_index'],
                    'name' => $ms['name'],
                    'percentage' => $ms['percentage'],
                    'amount_idr' => $ms['amount_idr'],
                    'due_date' => $ms['due_date'],
                    'status' => 'PENDING',
                    'paid_amount_idr' => 0,
                ]);
            }

            return $certificate;
        });
    }
}
