<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Mining\Domain\Enums\MinePlanStatus;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningPlan;
use Modules\Mining\Domain\Models\MiningPlanShift;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Domain\Services\MiningExternalFactors;
use Modules\Mining\Domain\StateMachines\MinePlanStateMachine;
use Modules\Shared\Domain\Algorithms\Scheduling\WeightedTimePhasing;

/**
 * Aksi Perencanaan Tambang Bulanan & Pemecahan Kuota Shift (D93-1, Algoritma M1).
 */
class PlanMineBlockAction
{
    public function __construct(
        private readonly WeightedTimePhasing $timePhasing,
        private readonly MiningExternalFactors $externalFactors,
        private readonly ?MinePlanStateMachine $stateMachine = null
    ) {}

    /**
     * @param  array{
     *     site_id: string,
     *     pit_id: string,
     *     period_month: string,
     *     commodity: string,
     *     target_tonnage_kg: int,
     *     target_grade_ppm: int,
     *     target_cut_kg?: int,
     *     target_fill_kg?: int,
     *     user_id?: int
     * }  $params
     */
    public function execute(array $params): MiningPlan
    {
        return DB::transaction(function () use ($params) {
            $site = MiningSite::findOrFail($params['site_id']);
            $pit = MiningPit::findOrFail($params['pit_id']);

            $planCode = "PLAN-{$site->site_code}-{$pit->pit_code}-{$params['period_month']}";

            $existing = MiningPlan::where('plan_code', $planCode)->first();
            $version = 1;
            if ($existing) {
                $version = $existing->version + 1;
                $planCode = "{$planCode}-V{$version}";
            }

            $plan = MiningPlan::create([
                'site_id' => $site->id,
                'plan_code' => $planCode,
                'period_month' => $params['period_month'],
                'commodity' => $params['commodity'],
                'target_cut_kg' => $params['target_cut_kg'] ?? intdiv($params['target_tonnage_kg'] * 12, 10),
                'target_fill_kg' => $params['target_fill_kg'] ?? intdiv($params['target_tonnage_kg'] * 2, 10),
                'target_ore_kg' => $params['target_tonnage_kg'],
                'target_grade_ppm' => $params['target_grade_ppm'],
                'status' => MinePlanStatus::DRAFT->value,
                'version' => $version,
                'approved_by_user_id' => $params['user_id'] ?? null,
            ]);

            $sm = $this->stateMachine ?? new MinePlanStateMachine;
            $actorRef = isset($params['user_id']) ? (string) $params['user_id'] : 'system';
            $sm->advanceAlongPath(
                $plan,
                [MinePlanStatus::SUBMITTED, MinePlanStatus::REVIEWED, MinePlanStatus::APPROVED],
                $actorRef,
                'Pengesahan otomatis rencana tambang bulanan M1'
            );

            $weights = [5, 5, 5, 5, 4, 4, 2];
            $totalTargetTon = intdiv($params['target_tonnage_kg'] + 500, 1000);

            $phasingResult = $this->timePhasing->phase($totalTargetTon, $weights);

            $startDate = CarbonImmutable::parse("{$params['period_month']}-01");
            $shiftsPerDay = ['SHIFT_A', 'SHIFT_B'];

            foreach ($phasingResult->dailyAllocations as $dayIdx => $dailyTonnageTon) {
                $currentDate = $startDate->addDays($dayIdx);

                $weather = $this->externalFactors->getRainfallDowntime($site->site_code, $currentDate);
                $rainHours = $weather['downtime_hours'];

                $halfTonnageKg = intdiv($dailyTonnageTon * 1000, 2);

                foreach ($shiftsPerDay as $shiftCode) {
                    $nominalHours = 10;
                    $adjustedHours = max(0, $nominalHours - $rainHours);

                    MiningPlanShift::create([
                        'mine_plan_id' => $plan->id,
                        'pit_id' => $pit->id,
                        'shift_date' => $currentDate->toDateString(),
                        'shift_code' => $shiftCode,
                        'target_tonnage_kg' => $halfTonnageKg,
                        'actual_tonnage_kg' => 0,
                        'effective_hours_nominal' => $nominalHours,
                        'effective_hours_adjusted' => $adjustedHours,
                        'rain_downtime_hours' => $rainHours,
                        'maintenance_downtime_hours' => 0,
                        'variance_kg' => 0,
                    ]);
                }
            }

            return $plan->fresh() ?? $plan;
        });
    }
}
