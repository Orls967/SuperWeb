<?php

declare(strict_types=1);

namespace Modules\Platform\database\seeders;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Modules\Platform\Domain\Enums\CanaryRecommendation;
use Modules\Platform\Domain\Enums\ChangeRiskTier;
use Modules\Platform\Domain\Enums\ChangeStatus;
use Modules\Platform\Domain\Enums\ReleaseTrainStatus;
use Modules\Platform\Domain\Models\CanaryObservation;
use Modules\Platform\Domain\Models\ChangeDeployment;
use Modules\Platform\Domain\Models\ChangeRequest;
use Modules\Platform\Domain\Models\ChangeRollbackPlan;
use Modules\Platform\Domain\Models\DataBackfill;
use Modules\Platform\Domain\Models\DataBackfillChunk;
use Modules\Platform\Domain\Models\ReleaseHotfix;
use Modules\Platform\Domain\Models\ReleaseTrain;

class PlatformReleaseUniverseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('role', 'admin')->first()
            ?? User::factory()->create(['role' => 'admin', 'name' => 'Release Admin']);

        $now = CarbonImmutable::now();

        // 1. Seed 5 Release Trains
        $trainStatuses = [
            ReleaseTrainStatus::OPEN->value,
            ReleaseTrainStatus::FROZEN->value,
            ReleaseTrainStatus::STAGING->value,
            ReleaseTrainStatus::DEPLOYING->value,
            ReleaseTrainStatus::ACTIVE->value,
        ];

        $trains = [];
        foreach ($trainStatuses as $idx => $status) {
            $code = sprintf('TRAIN-2026-W%02d', 40 + $idx);
            $ver = sprintf('v8.%d.0', $idx + 1);

            $train = ReleaseTrain::firstOrCreate(
                ['train_code' => $code],
                [
                    'version' => $ver,
                    'release_manager_id' => $admin->id,
                    'status' => $status,
                    'freeze_at' => $now->subDays(5 - $idx),
                    'scheduled_deploy_at' => $now->addDays($idx + 1),
                    'departure_date' => $now->toDateString(),
                ]
            );

            $trains[] = $train;
        }

        // 2. Seed 20 Change Requests
        $statuses = [
            ChangeStatus::DRAFT->value,
            ChangeStatus::ASSESSED->value,
            ChangeStatus::CAB_REVIEW->value,
            ChangeStatus::APPROVED->value,
            ChangeStatus::SCHEDULED->value,
            ChangeStatus::DEPLOYING->value,
            ChangeStatus::VERIFIED->value,
            ChangeStatus::ROLLED_BACK->value,
            ChangeStatus::CLOSED->value,
        ];

        $tiers = [
            ChangeRiskTier::LOW->value,
            ChangeRiskTier::MEDIUM->value,
            ChangeRiskTier::HIGH->value,
            ChangeRiskTier::CRITICAL->value,
        ];

        $modules = ['Core', 'Platform', 'Billing', 'Inventory', 'Finance', 'Procurement'];

        for ($i = 1; $i <= 20; $i++) {
            $assignedTrain = $trains[$i % count($trains)];
            $st = $statuses[$i % count($statuses)];
            $tier = $tiers[$i % count($tiers)];
            $mod = $modules[$i % count($modules)];

            $cr = ChangeRequest::firstOrCreate(
                ['reference_number' => sprintf('CR-2026-SEEDED-%03d', $i)],
                [
                    'title' => "Enterprise Change #{$i} for {$mod} Subsystem",
                    'description' => "Automated structural and service transition change #{$i}",
                    'requester_id' => $admin->id,
                    'target_module' => $mod,
                    'change_type' => ($i % 5 === 0) ? 'emergency' : 'normal',
                    'status' => $st,
                    'risk_score' => 2000 + ($i * 350),
                    'risk_tier' => $tier,
                    'release_train_id' => $assignedTrain->id,
                ]
            );

            // 4 Rollback plans for higher risk items
            if ($i <= 4) {
                ChangeRollbackPlan::firstOrCreate(
                    ['change_request_id' => $cr->id],
                    [
                        'strategy' => 'feature_flag_disable',
                        'estimated_rto_seconds' => 45,
                        'automated' => true,
                        'playbook_steps' => ['route_traffic_to_fallback', 'isolate_node'],
                        'verification_checklist' => ['error_rate_normal', 'qps_restored'],
                    ]
                );
            }
        }

        // 3. Seed 6 Hotfixes
        for ($h = 1; $h <= 6; $h++) {
            ReleaseHotfix::firstOrCreate(
                ['hotfix_number' => sprintf('HOTFIX-2026-%03d', $h)],
                [
                    'release_train_id' => $trains[$h % count($trains)]->id,
                    'approved_by' => $admin->id,
                    'severity' => ($h % 3 === 0) ? 'p1_blocker' : 'p2_critical',
                    'isolation_status' => 'quarantined',
                ]
            );
        }

        // 4. Seed 10 Deployments & 15 Canary Observations
        for ($d = 1; $d <= 10; $d++) {
            $dep = ChangeDeployment::create([
                'release_train_id' => $trains[$d % count($trains)]->id,
                'environment' => ($d % 2 === 0) ? 'canary' : 'production',
                'strategy' => 'canary',
                'traffic_percentage' => ($d % 2 === 0) ? 10 : 100,
                'status' => ($d === 1) ? 'rolled_back' : 'promoted',
                'started_at' => $now->subHours(10 - $d),
                'completed_at' => $now->subHours(9 - $d),
            ]);

            if ($d <= 5) {
                for ($o = 1; $o <= 3; $o++) {
                    CanaryObservation::create([
                        'deployment_id' => $dep->id,
                        'sample_window_start' => $now->subMinutes(30 - ($o * 5)),
                        'sample_window_end' => $now->subMinutes(25 - ($o * 5)),
                        'baseline_requests' => 5000,
                        'baseline_errors' => 10,
                        'canary_requests' => 500,
                        'canary_errors' => ($d === 1 && $o === 3) ? 55 : 2,
                        'z_score' => ($d === 1 && $o === 3) ? 5800 : 350,
                        'relative_risk_bps' => ($d === 1 && $o === 3) ? 3500 : 100,
                        'recommendation' => ($d === 1 && $o === 3)
                            ? CanaryRecommendation::ROLLBACK->value
                            : CanaryRecommendation::CONTINUE->value,
                    ]);
                }
            }
        }

        // 5. Seed 3 Backfill Jobs
        for ($b = 1; $b <= 3; $b++) {
            $job = DataBackfill::firstOrCreate(
                ['job_name' => "dataset_dual_write_migration_{$b}"],
                [
                    'target_table' => 'plt_change_requests',
                    'total_records' => 1000,
                    'chunk_size' => 250,
                    'status' => ($b === 1) ? 'completed' : 'running',
                ]
            );

            for ($c = 0; $c < 4; $c++) {
                DataBackfillChunk::firstOrCreate(
                    [
                        'backfill_id' => $job->id,
                        'chunk_index' => $c,
                    ],
                    [
                        'range_start' => ($c * 250) + 1,
                        'range_end' => ($c + 1) * 250,
                        'records_processed' => ($b === 1 || $c < 2) ? 250 : 0,
                        'status' => ($b === 1 || $c < 2) ? 'completed' : 'pending',
                    ]
                );
            }
        }
    }
}
