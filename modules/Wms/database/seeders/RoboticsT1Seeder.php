<?php

declare(strict_types=1);

namespace Modules\Wms\database\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Wms\Domain\Algorithms\AutomationNpvCalculator;
use Modules\Wms\Domain\Algorithms\WaveBinPacker;
use Modules\Wms\Domain\Models\Warehouse;
use Modules\Wms\Domain\StateMachines\RobotMissionStateMachine;
use Modules\Wms\Models\Robot;
use Modules\Wms\Models\RoboticsRoiProposal;
use Modules\Wms\Models\RobotMission;
use Modules\Wms\Models\RobotMissionEvent;
use Modules\Wms\Models\RobotSafetyIncident;
use Modules\Wms\Models\RobotUnit;
use Modules\Wms\Models\RobotWave;
use Modules\Wms\Models\TrafficZone;
use Modules\Wms\Models\ZoneReservation;

/**
 * Seeder deterministik T1 untuk Fase 279 (T-6):
 * >= 20 robot (>= 3 offline), >= 6 zona (>= 2 kapasitas 1, >= 1 diblok staf),
 * >= 120 misi mencakup seluruh 8 status SM (>= 10 fallback_manual),
 * >= 60 order lintas >= 5 carrier berbeda cutoff, >= 4 proposal ROI (>= 1 NPV negatif).
 */
final class RoboticsT1Seeder extends Seeder
{
    public function run(): void
    {
        $now = now()->startOfMinute();

        $warehouse = Warehouse::query()->firstOrCreate(
            ['code' => 'WH-ROB-T1'],
            [
                'name' => 'Gudang Robotika Simulasi T1',
                'kind' => 'fulfillment',
                'address' => 'Zona Simulasi T1',
                'is_active' => true,
            ],
        );
        $whId = (string) $warehouse->id;

        // 1. 20 Robot (>= 3 offline) + legacy RobotUnit mirror
        $robots = [];
        $unit = RobotUnit::query()->firstOrCreate(
            ['robot_code' => 'AMR-T1-UNIT-01'],
            [
                'warehouse_id' => $whId,
                'kind' => 'amr',
                'battery_pct' => 92,
                'status' => 'idle',
                'current_zone_code' => 'Z-T1-01',
                'max_speed_mm_s' => 1800,
            ],
        );

        for ($i = 1; $i <= 20; $i++) {
            $isOnline = $i <= 16; // 4 offline (>= 3)
            $battery = $isOnline ? (20 + (($i * 7) % 80)) : 10;
            $code = sprintf('AMR-T1-%02d', $i);
            $robots[] = Robot::query()->updateOrCreate(
                ['robot_code' => $code],
                [
                    'warehouse_id' => $whId,
                    'robot_type' => $i % 4 === 0 ? 'reach_amr' : 'amr',
                    'capacity_units' => 100 + (($i % 3) * 25),
                    'battery_pct' => $battery,
                    'is_online' => $isOnline,
                    'position_x' => ($i * 3) % 20,
                    'position_y' => ($i * 5) % 15,
                    'current_zone_code' => sprintf('Z-T1-%02d', (($i - 1) % 6) + 1),
                    'device_mode' => 'simulated_device',
                    'health_score' => $isOnline ? 850 + ($i % 150) : 420,
                ],
            );
        }

        // 2. 6 Zona lalu lintas (>= 2 kapasitas 1, >= 1 diblok staf)
        $zones = [];
        for ($z = 1; $z <= 6; $z++) {
            $capacityK = $z <= 3 ? 1 : 2; // 3 zona kapasitas 1 (>= 2)
            $staffBlocked = $z === 6; // zona 6 diblok staf
            $zones[] = TrafficZone::query()->updateOrCreate(
                ['warehouse_id' => $whId, 'zone_code' => sprintf('Z-T1-%02d', $z)],
                [
                    'zone_kind' => $z <= 2 ? 'narrow_aisle' : 'corridor',
                    'capacity_k' => $capacityK,
                    'staff_blocked' => $staffBlocked,
                    'block_start_min' => $staffBlocked ? 0 : null,
                    'block_end_min' => $staffBlocked ? 60 : null,
                    'block_reason' => $staffBlocked ? 'pemeliharaan_staf_t1' : null,
                ],
            );
        }

        // 3. 120 RobotMission mencakup seluruh 8 status SM (>= 12 fallback_manual)
        $statuses = RobotMissionStateMachine::allStates();
        for ($m = 1; $m <= 120; $m++) {
            $status = $m <= 12
                ? RobotMissionStateMachine::STATUS_FALLBACK_MANUAL
                : $statuses[($m - 1) % count($statuses)];
            $zone = $zones[($m - 1) % 5]; // hindari zona 6 yang diblok staf untuk misi moving
            $robot = $robots[($m - 1) % 16]; // robot online
            $code = sprintf('MIS-T1-%03d', $m);

            $mission = RobotMission::query()->updateOrCreate(
                ['mission_code' => $code],
                [
                    'warehouse_id' => $whId,
                    'robot_unit_id' => $unit->id,
                    'robot_id' => $status === RobotMissionStateMachine::STATUS_QUEUED ? null : $robot->id,
                    'zone_code' => $zone->zone_code,
                    'traffic_zone_id' => $zone->id,
                    'status' => $status,
                    'fallback_manual' => $status === RobotMissionStateMachine::STATUS_FALLBACK_MANUAL,
                    'retry_count' => $status === RobotMissionStateMachine::STATUS_FAILED ? 1 : 0,
                    'priority' => 50 + ($m % 50),
                    'scheduled_start_min' => $m * 10,
                    'duration_min' => 5,
                    'failure_reason' => in_array($status, [RobotMissionStateMachine::STATUS_FAILED, RobotMissionStateMachine::STATUS_FALLBACK_MANUAL], true) ? 'obstacle_timeout_sim' : null,
                    'certificate_hash' => hash('sha256', "T1|{$code}|{$status}"),
                ],
            );

            // Pastikan setiap misi berstatus moving memegang tepat 1 reservasi aktif non-bentrok
            if ($status === RobotMissionStateMachine::STATUS_MOVING) {
                $slotStart = 1000 + ($m * 10);
                ZoneReservation::query()->updateOrCreate(
                    ['mission_id' => $mission->id, 'status' => 'active'],
                    [
                        'warehouse_id' => $whId,
                        'traffic_zone_id' => $zone->id,
                        'start_min' => $slotStart,
                        'end_min' => $slotStart + 5,
                        'reservation_kind' => 'mission',
                    ],
                );
            }

            if (RobotMissionEvent::query()->where('mission_id', $mission->id)->doesntExist()) {
                DB::table('wms_robot_mission_events')->insert([
                    'mission_id' => $mission->id,
                    'from_status' => null,
                    'to_status' => $status,
                    'reason_code' => 'T1_SEED',
                    'reason_note' => 'Inisialisasi seeder deterministik T1',
                    'metadata' => json_encode(['seed' => 'T1'], JSON_THROW_ON_ERROR),
                    'recorded_at' => $now,
                ]);
            }
        }

        // 4. 60 order untuk wave lintas 5 carrier berbeda cutoff (J2)
        $carriers = [
            ['code' => 'FX-CAR-A', 'cutoff' => 1400],
            ['code' => 'FX-CAR-B', 'cutoff' => 1500],
            ['code' => 'FX-CAR-C', 'cutoff' => 1600],
            ['code' => 'FX-CAR-D', 'cutoff' => 1700],
            ['code' => 'FX-CAR-E', 'cutoff' => 1800],
        ];
        $orders = [];
        for ($o = 1; $o <= 60; $o++) {
            $c = $carriers[($o - 1) % 5];
            $orders[] = [
                'id' => sprintf('ORD-T1-%03d', $o),
                'size' => 10 + (($o * 7) % 35),
                'carrier' => $c['code'],
                'cutoff' => $c['cutoff'],
            ];
        }
        $packer = new WaveBinPacker;
        $packed = $packer->pack(70, $orders);
        foreach ($packed['waves'] as $w) {
            RobotWave::query()->updateOrCreate(
                ['wave_code' => sprintf('T1-W%03d', $w['wave_index'])],
                [
                    'warehouse_id' => $whId,
                    'carrier_code' => $w['carrier'],
                    'cutoff_min' => $w['cutoff'],
                    'capacity' => $w['capacity'],
                    'allocated_load' => $w['load'],
                    'is_oversize' => $w['status'] === WaveBinPacker::STATUS_OVERSIZE,
                    'status' => 'released',
                    'order_items' => $w['items'],
                    'certificate_hash' => $packed['certificate']->sha256,
                ],
            );
        }

        // 5. 4 Proposal ROI (>= 1 NPV negatif, berlabel asumsi_simulasi)
        $npvCalc = new AutomationNpvCalculator;
        $proposals = [
            ['PROP-T1-01', 'Armada 10 AMR Zona A', 1_000_000_000, [400_000_000, 400_000_000, 400_000_000, 400_000_000], 1, 10],
            ['PROP-T1-02', 'Conveyor Sortasi 2 Line', 1_000_000_000, [400_000_000, 400_000_000, 400_000_000, 400_000_000], 1, 5],
            ['PROP-T1-03', 'AMR High-Rate Scenario', 1_000_000_000, [400_000_000, 400_000_000, 400_000_000, 400_000_000], 1, 4], // NPV negatif!
            ['PROP-T1-04', 'Retrofit Goods-to-Person', 800_000_000, [350_000_000, 350_000_000, 350_000_000, 350_000_000], 3, 20],
        ];
        foreach ($proposals as [$pCode, $pTitle, $inv, $cfs, $rNum, $rDen]) {
            $eval = $npvCalc->evaluate($inv, $cfs, $rNum, $rDen);
            RoboticsRoiProposal::query()->updateOrCreate(
                ['proposal_code' => $pCode],
                [
                    'warehouse_id' => $whId,
                    'title' => $pTitle,
                    'investment_minor' => $inv,
                    'cash_flows_minor' => $cfs,
                    'discount_rate_num' => $rNum,
                    'discount_rate_den' => $rDen,
                    'npv_minor' => $eval['npv'],
                    'payback_months' => $eval['payback_months'],
                    'recommendation' => $eval['recommendation'],
                    'status' => 'evaluated',
                    'parameter_label' => 'asumsi_simulasi',
                    'certificate_hash' => $eval['certificate']->sha256,
                ],
            );
        }

        // 6. 1 Kejadian insiden keselamatan simulasi awal
        RobotSafetyIncident::query()->firstOrCreate(
            ['incident_code' => 'INC-T1-001'],
            [
                'warehouse_id' => $whId,
                'traffic_zone_id' => $zones[5]->id,
                'severity' => 'medium',
                'incident_type' => 'staff_zone_entry_interlock',
                'description' => 'Staf memasuki zona Z-T1-06; interlock zona aktif (simulasi).',
                'status' => 'reported',
                'simulation_label' => 'asumsi_simulasi',
                'recorded_at' => $now,
            ],
        );
    }
}
