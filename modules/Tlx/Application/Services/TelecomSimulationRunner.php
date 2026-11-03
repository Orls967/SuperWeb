<?php

declare(strict_types=1);

namespace Modules\Tlx\Application\Services;

use Carbon\CarbonImmutable;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Shared\Domain\Numerics\CanonicalHashChain;
use Modules\Shared\Domain\Numerics\CanonicalHashChainVerifier;
use Modules\Tlx\Domain\Models\ColocationContract;
use Modules\Tlx\Domain\Models\NetworkElement;
use Modules\Tlx\Domain\Models\NocTicket;
use Modules\Tlx\Domain\Models\TelecomDataCenter;
use RuntimeException;

/**
 * L14.4: 31-Day Deterministic Simulation Runner for Telecom & Data Center (Lini 14).
 * Scale: 31 days, 3 entities, 5,000 devices, 200,000 CDRs, 2 DCs, 500 topology elements.
 * Adheres strictly to T4 (hash covers module tables + tlx: ledger entries only).
 */
class TelecomSimulationRunner
{
    public function __construct(
        protected IotDeviceRegistryService $registryService,
        protected CdrRatingService $ratingService,
        protected ConnectivityBillingService $connectivityBillingService,
        protected ColocationBillingService $colocationBillingService,
        protected NocCorrelationService $nocCorrelationService
    ) {}

    /**
     * Run deterministic 31-day simulation.
     *
     * @return array{
     *     seed: int,
     *     days: int,
     *     entities_count: int,
     *     devices_count: int,
     *     active_devices_count: int,
     *     sim_profiles_count: int,
     *     cdrs_count: int,
     *     total_rated_bytes: int,
     *     data_centers_count: int,
     *     colocation_contracts_count: int,
     *     power_readings_count: int,
     *     topology_elements_count: int,
     *     noc_alarms_count: int,
     *     suppressed_alarms_count: int,
     *     noc_tickets_count: int,
     *     connectivity_invoiced_minor: int,
     *     colocation_invoiced_minor: int,
     *     audit_exit_code: int,
     *     bank_reconcile_exit_code: int,
     *     chain_valid: bool,
     *     state_hash: string
     * }
     */
    public function run(
        int $days = 31,
        int $devicesCount = 5000,
        int $cdrsCount = 200000,
        int $seed = 141414
    ): array {
        mt_srand($seed);

        if (LedgerAccount::count() === 0) {
            $seeder = new LedgerAccountsSeeder;
            $seeder->run();
        }

        $startDate = CarbonImmutable::parse('2026-10-01 00:00:00');
        $billingPeriod = '2026-10';

        // 1. Entities Definition
        $entities = [
            'ENT-LOGISTICS-01' => ['device_share' => 0.50, 'module' => 'Telematics'],
            'ENT-MINING-01' => ['device_share' => 0.30, 'module' => 'Mining'],
            'ENT-AGRO-01' => ['device_share' => 0.20, 'module' => 'Agro'],
        ];

        // 2. Data Centers Setup (2 DCs)
        $dc1 = TelecomDataCenter::firstOrCreate(
            ['dc_code' => 'DC-JKT-01'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Equinix JK1 Mega Campus',
                'city' => 'Jakarta',
                'total_facility_power_kw' => 1350.0,
                'it_load_power_kw' => 1000.0,
                'total_racks_capacity' => 120,
                'occupied_racks' => 50,
                'measured_pue' => 1.35,
            ]
        );

        $dc2 = TelecomDataCenter::firstOrCreate(
            ['dc_code' => 'DC-SBY-01'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Surabaya Hub Tier III',
                'city' => 'Surabaya',
                'total_facility_power_kw' => 725.0,
                'it_load_power_kw' => 500.0,
                'total_racks_capacity' => 80,
                'occupied_racks' => 30,
                'measured_pue' => 1.45,
            ]
        );

        // 3. Colocation Contracts
        $contract1 = ColocationContract::firstOrCreate(
            ['contract_number' => 'COLO-CONTRACT-001'],
            [
                'id' => (string) Str::uuid(),
                'dc_id' => $dc1->id,
                'tenant_party_id' => 'PARTY-BANK-MEGA',
                'rack_units_allocated' => 50,
                'power_commit_kw' => 100,
                'power_rate_per_kwh_minor' => 1500,
                'monthly_rack_fee_minor' => 150_000_000,
                'excess_power_rate_per_kwh_minor' => 1_500_000,
                'port_status' => 'UP',
                'is_overdue' => false,
            ]
        );

        $contract2 = ColocationContract::firstOrCreate(
            ['contract_number' => 'COLO-CONTRACT-002'],
            [
                'id' => (string) Str::uuid(),
                'dc_id' => $dc2->id,
                'tenant_party_id' => 'PARTY-FINTECH-PLUS',
                'rack_units_allocated' => 30,
                'power_commit_kw' => 60,
                'power_rate_per_kwh_minor' => 1500,
                'monthly_rack_fee_minor' => 90_000_000,
                'excess_power_rate_per_kwh_minor' => 1_500_000,
                'port_status' => 'UP',
                'is_overdue' => false,
            ]
        );

        // Ensure DC occupied racks match exactly for tlx:audit
        $dc1->update(['occupied_racks' => 50]);
        $dc2->update(['occupied_racks' => 30]);

        // 4. Provision 5,000 IoT Devices and SIM Profiles (Chunked Bulk Insert for Speed)
        $deviceRows = [];
        $simRows = [];
        $deviceIds = [];
        $activeCount = 0;

        $nowStr = $startDate->toDateTimeString();

        for ($i = 1; $i <= $devicesCount; $i++) {
            $uuid = sprintf('00000000-0000-4000-8000-%012d', $i);
            $deviceCode = sprintf('DEV-SIM-%05d', $i);
            $iccid = sprintf('89620100%012d', $i);
            $imsi = sprintf('510100%09d', $i);
            $msisdn = sprintf('+6281200%07d', $i);

            if ($i <= (int) ($devicesCount * 0.50)) {
                $ownerEntity = 'ENT-LOGISTICS-01';
                $module = 'Telematics';
            } elseif ($i <= (int) ($devicesCount * 0.80)) {
                $ownerEntity = 'ENT-MINING-01';
                $module = 'Mining';
            } else {
                $ownerEntity = 'ENT-AGRO-01';
                $module = 'Agro';
            }

            // 98% Active, 2% Suspended
            $status = ($i % 50 === 0) ? 'SUSPENDED' : 'ACTIVE';
            if ($status === 'ACTIVE') {
                $activeCount++;
            }

            $deviceIds[] = $uuid;

            $deviceRows[] = [
                'id' => $uuid,
                'device_uuid' => $uuid,
                'device_code' => $deviceCode,
                'device_type' => 'TELEMATICS',
                'owner_entity_id' => $ownerEntity,
                'using_module' => $module,
                'active_sim_iccid' => $iccid,
                'monthly_fee_minor' => 30000,
                'status' => $status,
                'activated_at' => $nowStr,
                'suspended_at' => $status === 'SUSPENDED' ? $nowStr : null,
                'retired_at' => null,
                'created_at' => $nowStr,
                'updated_at' => $nowStr,
            ];

            $simRows[] = [
                'id' => (string) Str::uuid(),
                'iccid' => $iccid,
                'imsi' => $imsi,
                'msisdn' => $msisdn,
                'assigned_device_id' => $uuid,
                'plan_code' => 'IOT-UNLIMITED-5GB',
                'monthly_quota_mb' => 5000,
                'status' => 'ALLOCATED',
                'created_at' => $nowStr,
                'updated_at' => $nowStr,
            ];

            if (count($deviceRows) >= 1000) {
                DB::table('tlx_iot_devices')->insert($deviceRows);
                DB::table('tlx_sim_profiles')->insert($simRows);
                $deviceRows = [];
                $simRows = [];
            }
        }

        if (! empty($deviceRows)) {
            DB::table('tlx_iot_devices')->insert($deviceRows);
            DB::table('tlx_sim_profiles')->insert($simRows);
            unset($deviceRows, $simRows);
        }

        // 5. Network Topology (500 elements forming a valid DAG)
        $elementRows = [];
        $elementIds = [];
        $elementsCount = 500;

        // Root core router
        $rootId = '00000000-0001-4000-8000-000000000001';
        $elementIds[1] = $rootId;
        $elementRows[] = [
            'id' => $rootId,
            'element_code' => 'ELEM-CORE-001',
            'element_type' => 'CORE_ROUTER',
            'parent_element_id' => null,
            'status' => 'OPERATIONAL',
            'created_at' => $nowStr,
            'updated_at' => $nowStr,
        ];

        // 10 Distribution Routers (parent is root)
        for ($i = 2; $i <= 11; $i++) {
            $uuid = sprintf('00000000-0001-4000-8000-%012d', $i);
            $elementIds[$i] = $uuid;
            $elementRows[] = [
                'id' => $uuid,
                'element_code' => sprintf('ELEM-DIST-%03d', $i - 1),
                'element_type' => 'AGGREGATION_SWITCH',
                'parent_element_id' => $rootId,
                'status' => 'OPERATIONAL',
                'created_at' => $nowStr,
                'updated_at' => $nowStr,
            ];
        }

        // 40 Aggregation Switches (parents among dist routers)
        for ($i = 12; $i <= 51; $i++) {
            $uuid = sprintf('00000000-0001-4000-8000-%012d', $i);
            $elementIds[$i] = $uuid;
            $parentIdx = 2 + (($i - 12) % 10);
            $elementRows[] = [
                'id' => $uuid,
                'element_code' => sprintf('ELEM-AGG-%03d', $i - 11),
                'element_type' => 'AGGREGATION_SWITCH',
                'parent_element_id' => $elementIds[$parentIdx],
                'status' => 'OPERATIONAL',
                'created_at' => $nowStr,
                'updated_at' => $nowStr,
            ];
        }

        // 449 Access Points / Cell Sites (parents among agg switches)
        for ($i = 52; $i <= $elementsCount; $i++) {
            $uuid = sprintf('00000000-0001-4000-8000-%012d', $i);
            $elementIds[$i] = $uuid;
            $parentIdx = 12 + (($i - 52) % 40);
            $elementRows[] = [
                'id' => $uuid,
                'element_code' => sprintf('ELEM-SITE-%03d', $i - 51),
                'element_type' => 'CELL_TOWER',
                'parent_element_id' => $elementIds[$parentIdx],
                'status' => 'OPERATIONAL',
                'created_at' => $nowStr,
                'updated_at' => $nowStr,
            ];
        }

        DB::table('tlx_network_elements')->insert($elementRows);
        unset($elementRows);

        // 6. NOC Alarm Incidents and Correlation
        $alarmRows = [];
        $totalAlarms = 60;
        $suppressedAlarmsCount = 0;

        for ($a = 1; $a <= $totalAlarms; $a++) {
            $alarmCode = sprintf('ALM-202610-%04d', $a);
            $raisedAt = $startDate->addDays(($a % $days))->toDateTimeString();

            // Root incident on a dist router
            if ($a % 6 === 1) {
                $distIdx = 2 + (($a - 1) % 10);
                $elemId = $elementIds[$distIdx];
                $isSuppressed = false;
                $suppressedBy = null;
            } else {
                // Child alarm on an access site belonging to the dist router
                $siteIdx = 52 + ($a % 400);
                $elemId = $elementIds[$siteIdx];
                $parentElem = NetworkElement::find($elemId);
                $isSuppressed = true;
                $suppressedBy = $parentElem ? $parentElem->parent_element_id : $rootId;
                $suppressedAlarmsCount++;
            }

            $alarmRows[] = [
                'id' => (string) Str::uuid(),
                'alarm_code' => $alarmCode,
                'element_id' => $elemId,
                'alarm_type' => 'LINK_DOWN',
                'severity' => $isSuppressed ? 'MINOR' : 'CRITICAL',
                'is_suppressed' => $isSuppressed,
                'suppressed_by_element_id' => $suppressedBy,
                'ticket_id' => null,
                'raised_at' => $raisedAt,
                'created_at' => $raisedAt,
                'updated_at' => $raisedAt,
            ];
        }

        DB::table('tlx_noc_alarms')->insert($alarmRows);
        unset($alarmRows);

        // Run correlation command/service to create tickets for root alarms
        $this->nocCorrelationService->correlateActiveAlarms();
        $nocTicketsCount = NocTicket::count();

        // 7. 31-Day Simulation Loop: CDRs, Power Readings, Canonical Hash Chain
        $cdrsPerDay = (int) ceil($cdrsCount / $days);
        $totalCdrsIngested = 0;
        $totalRatedBytes = 0;

        $chainRecords = [];
        $prevHash = CanonicalHashChain::GENESIS_PREV_HASH;

        $totalFacilityEnergy1 = 0;
        $totalItEnergy1 = 0;
        $totalFacilityEnergy2 = 0;
        $totalItEnergy2 = 0;

        for ($d = 1; $d <= $days; $d++) {
            $dayDate = $startDate->addDays($d - 1);
            $dayStr = $dayDate->format('Y-m-d');

            // Daily DC Power Readings
            $fac1 = 135000 + (mt_rand(0, 3000));
            $it1 = 100000 + (mt_rand(0, 2000));
            $peak1 = 95 + (mt_rand(0, 10));
            $totalFacilityEnergy1 += $fac1;
            $totalItEnergy1 += $it1;

            $fac2 = 72500 + (mt_rand(0, 2000));
            $it2 = 50000 + (mt_rand(0, 1500));
            $peak2 = 55 + (mt_rand(0, 8));
            $totalFacilityEnergy2 += $fac2;
            $totalItEnergy2 += $it2;

            $readingRows = [
                [
                    'id' => (string) Str::uuid(),
                    'dc_id' => $dc1->id,
                    'reading_at' => "{$dayStr} 12:00:00",
                    'total_facility_energy_kwh' => $fac1,
                    'it_equipment_energy_kwh' => $it1,
                    'peak_power_kw' => $peak1,
                    'created_at' => "{$dayStr} 12:00:00",
                    'updated_at' => "{$dayStr} 12:00:00",
                ],
                [
                    'id' => (string) Str::uuid(),
                    'dc_id' => $dc2->id,
                    'reading_at' => "{$dayStr} 12:00:00",
                    'total_facility_energy_kwh' => $fac2,
                    'it_equipment_energy_kwh' => $it2,
                    'peak_power_kw' => $peak2,
                    'created_at' => "{$dayStr} 12:00:00",
                    'updated_at' => "{$dayStr} 12:00:00",
                ],
            ];
            DB::table('tlx_dc_power_readings')->insert($readingRows);

            // Daily CDR Batch (Bulk Generation)
            $dayCdrRows = [];
            $dayRatedBytes = 0;

            for ($c = 1; $c <= $cdrsPerDay && $totalCdrsIngested < $cdrsCount; $c++) {
                $totalCdrsIngested++;
                $devIdx = ($totalCdrsIngested * 17) % $devicesCount;
                $devId = $deviceIds[$devIdx];

                $rawBytes = mt_rand(500, 50000);
                $rated = ($rawBytes === 0) ? 0 : (int) (ceil($rawBytes / 1024) * 1024);
                $duration = mt_rand(10, 240);

                $dayRatedBytes += $rated;

                $dayCdrRows[] = [
                    'id' => (string) Str::uuid(),
                    'device_id' => $devId,
                    'session_id' => sprintf('SESS-%02d-%06d', $d, $c),
                    'started_at' => "{$dayStr} 08:00:00",
                    'ended_at' => "{$dayStr} 08:05:00",
                    'bytes_transferred' => $rawBytes,
                    'rated_bytes' => $rated,
                    'duration_seconds' => $duration,
                    'created_at' => "{$dayStr} 08:05:00",
                    'updated_at' => "{$dayStr} 08:05:00",
                ];

                if (count($dayCdrRows) >= 2000) {
                    DB::table('tlx_cdrs')->insert($dayCdrRows);
                    $dayCdrRows = [];
                }
            }

            if (! empty($dayCdrRows)) {
                DB::table('tlx_cdrs')->insert($dayCdrRows);
                unset($dayCdrRows);
            }

            $totalRatedBytes += $dayRatedBytes;

            // Daily Hash Payload
            $dailyPayload = [
                'day' => $d,
                'date' => $dayStr,
                'cdrs_ingested' => $totalCdrsIngested,
                'daily_rated_bytes' => $dayRatedBytes,
                'dc1_facility_kwh' => $fac1,
                'dc2_facility_kwh' => $fac2,
            ];

            $dailyHash = CanonicalHashChain::computeHash($prevHash, $dailyPayload);
            $chainRecords[] = [
                'prev_hash' => $prevHash,
                'payload' => $dailyPayload,
                'hash' => $dailyHash,
            ];
            $prevHash = $dailyHash;
        }

        // Update DC measured PUE for exact match with dynamic facts in tlx:audit
        $pue1 = (float) round($totalFacilityEnergy1 / $totalItEnergy1, 2);
        $pue2 = (float) round($totalFacilityEnergy2 / $totalItEnergy2, 2);
        $dc1->update(['measured_pue' => $pue1]);
        $dc2->update(['measured_pue' => $pue2]);

        // 8. Month-End Billing (Day 31)
        // Connectivity Invoices for 3 Entities
        $connectivityInvoicedMinor = 0;
        foreach (array_keys($entities) as $entId) {
            $inv = $this->connectivityBillingService->billEntityConnectivity([
                'owner_entity_id' => $entId,
                'billing_period' => $billingPeriod,
                'idempotency_key' => "BILL:TLX:CONN:{$entId}:{$billingPeriod}",
            ]);
            $connectivityInvoicedMinor += $inv->total_charge_minor;
        }

        // Colocation Invoices for 2 Contracts
        $colocationInvoicedMinor = 0;
        $colo1 = $this->colocationBillingService->billColocationContract([
            'contract_id' => $contract1->id,
            'billing_period' => $billingPeriod,
            'measured_peak_kw' => 105,
            'idempotency_key' => "BILL:TLX:COLO:{$contract1->id}:{$billingPeriod}",
        ]);
        $colocationInvoicedMinor += $colo1->total_minor;

        $colo2 = $this->colocationBillingService->billColocationContract([
            'contract_id' => $contract2->id,
            'billing_period' => $billingPeriod,
            'measured_peak_kw' => 62,
            'idempotency_key' => "BILL:TLX:COLO:{$contract2->id}:{$billingPeriod}",
        ]);
        $colocationInvoicedMinor += $colo2->total_minor;

        // 9. Execute Audits (tlx:audit and bank:reconcile)
        $auditExitCode = Artisan::call('tlx:audit');
        $bankReconcileExitCode = Artisan::call('bank:reconcile');

        // 10. Ledger and Module Table Integrity for T4 Hash
        $tlxLedgerRows = DB::select("
            SELECT COUNT(*) as entry_count, COALESCE(SUM(e.amount), 0) as balance_sum
            FROM bank_ledger_entries e
            JOIN bank_ledger_accounts a ON e.account_id = a.id
            WHERE a.code LIKE 'tlx:%'
        ");
        $tlxLedgerSummary = $tlxLedgerRows[0] ?? (object) ['entry_count' => 0, 'balance_sum' => 0];

        $finalPayload = [
            'period' => $billingPeriod,
            'entities_count' => count($entities),
            'devices_count' => $devicesCount,
            'active_devices_count' => $activeCount,
            'cdrs_count' => $totalCdrsIngested,
            'total_rated_bytes' => $totalRatedBytes,
            'data_centers_count' => 2,
            'colocation_contracts_count' => 2,
            'power_readings_count' => 2 * $days,
            'topology_elements_count' => $elementsCount,
            'noc_alarms_count' => $totalAlarms,
            'suppressed_alarms_count' => $suppressedAlarmsCount,
            'noc_tickets_count' => $nocTicketsCount,
            'connectivity_invoiced_minor' => $connectivityInvoicedMinor,
            'colocation_invoiced_minor' => $colocationInvoicedMinor,
            'tlx_ledger_entries_count' => (int) ($tlxLedgerSummary->entry_count ?? 0),
            'tlx_ledger_balance_sum' => (int) ($tlxLedgerSummary->balance_sum ?? 0),
            'audit_exit_code' => $auditExitCode,
            'bank_reconcile_exit_code' => $bankReconcileExitCode,
        ];

        $finalHash = CanonicalHashChain::computeHash($prevHash, $finalPayload);
        $chainRecords[] = [
            'prev_hash' => $prevHash,
            'payload' => $finalPayload,
            'hash' => $finalHash,
        ];

        // Verify entire canonical hash chain
        $brokenIndex = CanonicalHashChainVerifier::verifyChain($chainRecords);
        $chainValid = ($brokenIndex === null);

        if (! $chainValid) {
            throw new RuntimeException("Canonical hash chain broken at day index: {$brokenIndex}");
        }

        return [
            'seed' => $seed,
            'days' => $days,
            'entities_count' => count($entities),
            'devices_count' => $devicesCount,
            'active_devices_count' => $activeCount,
            'sim_profiles_count' => $devicesCount,
            'cdrs_count' => $totalCdrsIngested,
            'total_rated_bytes' => $totalRatedBytes,
            'data_centers_count' => 2,
            'colocation_contracts_count' => 2,
            'power_readings_count' => 2 * $days,
            'topology_elements_count' => $elementsCount,
            'noc_alarms_count' => $totalAlarms,
            'suppressed_alarms_count' => $suppressedAlarmsCount,
            'noc_tickets_count' => $nocTicketsCount,
            'connectivity_invoiced_minor' => $connectivityInvoicedMinor,
            'colocation_invoiced_minor' => $colocationInvoicedMinor,
            'audit_exit_code' => $auditExitCode,
            'bank_reconcile_exit_code' => $bankReconcileExitCode,
            'chain_valid' => $chainValid,
            'state_hash' => $finalHash,
        ];
    }
}
