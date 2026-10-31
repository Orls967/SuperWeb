<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Services;

use Carbon\CarbonImmutable;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Egy\Application\Actions\IngestSmartGridMeterTelemetryAction;
use Modules\Egy\Application\Actions\SettlePowerPurchaseAgreementBillingAction;
use Modules\Egy\Domain\Models\PpaContract;
use Modules\Egy\Domain\Models\SmartMeter;
use Modules\Shared\Domain\Numerics\CanonicalHashChain;
use Modules\Shared\Domain\Numerics\CanonicalHashChainVerifier;
use Modules\Shared\Domain\Numerics\PriorityKnapsack;
use Modules\Shared\Domain\Numerics\PriorityKnapsackVerifier;
use Modules\Shared\Domain\Numerics\UnitCommitment;
use Modules\Shared\Domain\Numerics\UnitCommitmentVerifier;
use RuntimeException;

class EnergySimulationRunner
{
    public function __construct(
        protected IngestSmartGridMeterTelemetryAction $ingestAction,
        protected SettlePowerPurchaseAgreementBillingAction $ppaAction,
        protected ConsolidatedUtilityBillingService $billingService,
        protected EnergyGridAndMeteringService $gridService,
        protected MicrogridAndResilienceService $resilienceService
    ) {}

    /**
     * Run deterministic 31-day simulation of Energy & Utilities (Lini 13).
     *
     * @return array{
     *     seed: int,
     *     days: int,
     *     properties_count: int,
     *     meters_count: int,
     *     total_readings_ingested: int,
     *     duplicate_attempts: int,
     *     out_of_order_queue_deliveries: int,
     *     missing_intervals_detected: int,
     *     rollover_events: int,
     *     reset_events: int,
     *     ppa_total_minor: int,
     *     uc_plans_count: int,
     *     islanding_events_count: int,
     *     bess_runs_count: int,
     *     total_invoiced_minor: int,
     *     invoices_count: int,
     *     audit_exit_code: int,
     *     group_audit_exit_code: int,
     *     bank_reconcile_exit_code: int,
     *     state_hash: string,
     *     chain_valid: bool
     * }
     */
    public function run(int $days = 31, int $metersPerProperty = 10, int $seed = 12345): array
    {
        // Deterministic PRNG initialization (PR3)
        mt_srand($seed);

        if (LedgerAccount::count() === 0) {
            $seeder = new LedgerAccountsSeeder;
            $seeder->run();
        }

        // Setup 5 Properties across 2 different conglomerate entities
        // Entitas 1: ENT-HOLDCO-01 (Mall 1, Hotel 1, Hospital 1)
        // Entitas 2: ENT-INDUSTRI-02 (Factory 1, Factory 2)
        $properties = [
            ['id' => 'PROP-SIM-MALL-01', 'entity_id' => 'ENT-HOLDCO-01', 'type' => 'MALL'],
            ['id' => 'PROP-SIM-HOTEL-01', 'entity_id' => 'ENT-HOLDCO-01', 'type' => 'HOTEL'],
            ['id' => 'PROP-SIM-HOSP-01', 'entity_id' => 'ENT-HOLDCO-01', 'type' => 'HOSPITAL'],
            ['id' => 'PROP-SIM-FACT-01', 'entity_id' => 'ENT-INDUSTRI-02', 'type' => 'FACTORY'],
            ['id' => 'PROP-SIM-FACT-02', 'entity_id' => 'ENT-INDUSTRI-02', 'type' => 'FACTORY'],
        ];

        // 50 Smart Meters (10 per property)
        $meterList = [];
        $registers = [];
        $meterIndex = 1;

        foreach ($properties as $prop) {
            for ($m = 1; $m <= $metersPerProperty; $m++) {
                $serial = sprintf('MTR-SIM-%03d', $meterIndex);
                // Assign rollover modulus to 2 specific meters (Meter 7 and Meter 18)
                $modulus = in_array($meterIndex, [7, 18], true) ? 1000000000 : null;

                $smartMeter = SmartMeter::firstOrCreate(
                    ['meter_serial_number' => $serial],
                    [
                        'id' => (string) Str::uuid(),
                        'consumer_property_type' => $prop['type'],
                        'consumer_property_id' => $prop['id'],
                        'tariff_category' => 'BUSINESS_TOU',
                        'status' => 'ACTIVE',
                        'modulus' => $modulus,
                    ]
                );

                $meterList[] = [
                    'model' => $smartMeter,
                    'serial' => $serial,
                    'prop_id' => $prop['id'],
                    'index' => $meterIndex,
                    'modulus' => $modulus,
                ];

                $registers[$serial] = 100000 + ($meterIndex * 5000);
                $meterIndex++;
            }
        }

        // Setup 2 PPA Contracts
        $ppaSolar = PpaContract::firstOrCreate(
            ['contract_number' => 'PPA-SIM-SOLAR-01'],
            [
                'id' => (string) Str::uuid(),
                'producer_entity_id' => 'ENT-SOLAR-FARMS-01',
                'grid_offtaker_id' => 'ENT-EGY-01',
                'feed_in_tariff_per_kwh_minor' => 1250,
                'status' => 'ACTIVE',
            ]
        );

        $ppaWind = PpaContract::firstOrCreate(
            ['contract_number' => 'PPA-SIM-WIND-02'],
            [
                'id' => (string) Str::uuid(),
                'producer_entity_id' => 'ENT-WIND-FARMS-02',
                'grid_offtaker_id' => 'ENT-EGY-01',
                'feed_in_tariff_per_kwh_minor' => 1100,
                'status' => 'ACTIVE',
            ]
        );

        // 6 Generation Units for Daily Unit Commitment (B0".1)
        $generators = [
            ['code' => 'GEN-COAL-01', 'min_mw' => 100, 'max_mw' => 500, 'marginal_cost' => 350000, 'startup_cost' => 5000000],
            ['code' => 'GEN-COAL-02', 'min_mw' => 80, 'max_mw' => 400, 'marginal_cost' => 380000, 'startup_cost' => 4500000],
            ['code' => 'GEN-GAS-01', 'min_mw' => 50, 'max_mw' => 300, 'marginal_cost' => 600000, 'startup_cost' => 2000000],
            ['code' => 'GEN-GAS-02', 'min_mw' => 40, 'max_mw' => 250, 'marginal_cost' => 650000, 'startup_cost' => 1800000],
            ['code' => 'GEN-PEAK-01', 'min_mw' => 10, 'max_mw' => 100, 'marginal_cost' => 1200000, 'startup_cost' => 500000],
            ['code' => 'GEN-PEAK-02', 'min_mw' => 10, 'max_mw' => 80, 'marginal_cost' => 1300000, 'startup_cost' => 400000],
        ];

        // Microgrid islanding critical loads (B0".2)
        $priorityGroups = [
            [
                'group' => 'TIER1_RS',
                'loads' => [
                    ['id' => 'LOAD-HOSPITAL-ICU', 'kw' => 90],
                    ['id' => 'LOAD-HOSPITAL-WARD', 'kw' => 70],
                ],
            ],
            [
                'group' => 'TIER2_FACTORY',
                'loads' => [
                    ['id' => 'LOAD-FACTORY-LINE-A', 'kw' => 150],
                    ['id' => 'LOAD-FACTORY-LINE-B', 'kw' => 110],
                ],
            ],
            [
                'group' => 'TIER3_COMMERCIAL',
                'loads' => [
                    ['id' => 'LOAD-HOTEL-ESSENTIAL', 'kw' => 80],
                    ['id' => 'LOAD-MALL-LIGHTING', 'kw' => 120],
                ],
            ],
        ];

        // 1 Battery BESS params (B0".3)
        $bessParams = [
            'capacity_kwh' => 200,
            'max_power_kw' => 50,
            'efficiency_bps' => 9000,
            'initial_soc_kwh' => 100,
        ];

        $prevHash = CanonicalHashChain::GENESIS_PREV_HASH;
        $chainRecords = [];

        $totalReadingsIngested = 0;
        $duplicateCount = 0;
        $outOfOrderQueueCount = 0;
        $missingCount = 0;
        $rolloverCount = 0;
        $resetCount = 0;
        $ppaTotalMinor = 0;
        $ucPlansCount = 0;
        $islandingCount = 0;
        $bessRunsCount = 0;

        // 4 representative daily reading points covering both LWBP and WBP windows
        $readingSlots = [
            ['time' => '04:00:00', 'is_wbp' => false, 'pct' => 0.20],
            ['time' => '10:00:00', 'is_wbp' => false, 'pct' => 0.30],
            ['time' => '18:00:00', 'is_wbp' => true, 'pct' => 0.35],
            ['time' => '21:00:00', 'is_wbp' => true, 'pct' => 0.15],
        ];

        // 31 Daily Simulation Cycles
        for ($day = 1; $day <= $days; $day++) {
            $dayStr = sprintf('2026-10-%02d', $day);
            $dailyWh = 0;

            foreach ($readingSlots as $slotIdx => $slot) {
                $queueForSlot = [];

                foreach ($meterList as $mItem) {
                    $serial = $mItem['serial'];
                    $idx = $mItem['index'];
                    $ts = CarbonImmutable::parse("{$dayStr} {$slot['time']}", 'Asia/Jakarta');
                    $deltaWh = (int) (1000 + ($idx * 15) + (mt_rand(0, 100)));

                    // 1. Rollover event handling (Meter 7 on Day 12, Meter 18 on Day 25)
                    $isRollover = false;
                    if (($idx === 7 && $day === 12 && $slotIdx === 2) || ($idx === 18 && $day === 25 && $slotIdx === 1)) {
                        $registers[$serial] = 25000;
                        $rolloverCount++;
                        $isRollover = true;
                    }
                    // 2. Meter Replacement / Reset event (Meter 25 on Day 20)
                    elseif ($idx === 25 && $day === 20 && $slotIdx === 1) {
                        $registers[$serial] = 10000;
                        $queueForSlot[] = [
                            'serial' => $serial,
                            'ts' => $ts,
                            'cumul' => $registers[$serial],
                            'is_reset' => true,
                            'idx' => $idx,
                        ];
                        $resetCount++;

                        continue;
                    }
                    // 3. Missing Interval simulation (0.3% gap: Meter 10 on Day 5 slot 2 skipped)
                    elseif ($idx === 10 && $day === 5 && $slotIdx === 2) {
                        $missingCount++;

                        continue; // Interval missing, reading not recorded, not estimated silently
                    } else {
                        $registers[$serial] += $deltaWh;
                    }

                    $queueForSlot[] = [
                        'serial' => $serial,
                        'ts' => $ts,
                        'cumul' => $registers[$serial],
                        'is_reset' => false,
                        'idx' => $idx,
                    ];
                }

                // 0.5% Out-of-Order Queue Delivery across the fleet in ingestion stream
                if ($slotIdx === 1 && count($queueForSlot) > 4) {
                    // Swap items in delivery queue to simulate out-of-order network arrival across devices
                    $temp = $queueForSlot[1];
                    $queueForSlot[1] = $queueForSlot[3];
                    $queueForSlot[3] = $temp;
                    $outOfOrderQueueCount++;
                }

                // Process ingestion queue
                foreach ($queueForSlot as $packet) {
                    $serial = $packet['serial'];
                    $ts = $packet['ts'];
                    $cumul = $packet['cumul'];
                    $isReset = $packet['is_reset'];
                    $idx = $packet['idx'];

                    $reading = $this->ingestAction->execute([
                        'meter_number' => $serial,
                        'reading_at' => $ts->toIso8601String(),
                        'cumulative_wh' => $cumul,
                        'idempotency_key' => "egy:sim:{$serial}:{$dayStr}:{$slot['time']}".($isReset ? ':reset' : ''),
                        'is_meter_reset' => $isReset,
                    ]);

                    $totalReadingsIngested++;
                    $dailyWh += $reading->delta_wh;

                    // 4. Duplicate reading simulation (1% duplicate idempotency verification)
                    if ($slotIdx === 0 && ($idx % 5 === 0)) {
                        $dup = $this->ingestAction->execute([
                            'meter_number' => $serial,
                            'reading_at' => $ts->toIso8601String(),
                            'cumulative_wh' => $cumul,
                            'idempotency_key' => "egy:sim:{$serial}:{$dayStr}:{$slot['time']}",
                        ]);
                        $duplicateCount++;
                        // Must return identical reading without incrementing total
                        if ($dup->id !== $reading->id) {
                            throw new RuntimeException("Idempotency violation on duplicate ingest for {$serial}");
                        }
                    }
                }
            }

            // Daily PPA Settlement (2 PPA contracts)
            $solarKwh = 600 + (mt_rand(0, 50));
            $windKwh = 750 + (mt_rand(0, 60));

            $solRes = $this->ppaAction->executeWithIntercompany(
                (string) $ppaSolar->id,
                $solarKwh,
                "egy:sim:ppa:solar:{$dayStr}"
            );
            $windRes = $this->ppaAction->executeWithIntercompany(
                (string) $ppaWind->id,
                $windKwh,
                "egy:sim:ppa:wind:{$dayStr}"
            );

            $dailyPpaMinor = $solRes['total_minor'] + $windRes['total_minor'];
            $ppaTotalMinor += $dailyPpaMinor;

            // Daily 24h Unit Commitment & Merit Order Dispatch (B0".1)
            $demands24h = [];
            for ($h = 0; $h < 24; $h++) {
                $base = ($h >= 17 && $h <= 21) ? 550 : 350;
                $demands24h[] = $base + (mt_rand(0, 50));
            }
            $ucResult = UnitCommitment::solve($generators, $demands24h, []);
            $ucPlansCount++;

            // Verify Unit Commitment Certificate (PR2)
            $isCertValid = UnitCommitmentVerifier::verify(
                $generators,
                $demands24h,
                [],
                $ucResult
            );
            if (! $isCertValid) {
                throw new RuntimeException("Unit Commitment certificate verification failed on day {$day}!");
            }

            // 3 Islanding events (on Day 10, Day 20, Day 30) (B0".2)
            $islandServedKw = 0;
            if (in_array($day, [10, 20, 30], true)) {
                $islandCap = 380; // Limited microgrid capacity
                $islandRawResult = PriorityKnapsack::solve($islandCap, $priorityGroups);
                $islandingCount++;
                $islandServedKw = $islandRawResult['served_kw'];

                // Verify Islanding Certificate
                $isIslandCertValid = PriorityKnapsackVerifier::verify(
                    $islandCap,
                    $priorityGroups,
                    $islandRawResult
                );
                if (! $isIslandCertValid) {
                    throw new RuntimeException("Microgrid Islanding certificate verification failed on day {$day}!");
                }
            }

            // Daily BESS Battery Arbitrage Schedule (B0".3)
            $prices24h = [];
            for ($h = 0; $h < 24; $h++) {
                $prices24h[] = ($h >= 17 && $h < 22) ? 2100 : 1450;
            }
            $bessResult = $this->resilienceService->planBatteryArbitrage($bessParams, $prices24h);
            $bessRunsCount++;

            // Daily Snapshot Payload for Canonical Hash Chain
            $dailyPayload = [
                'day' => $day,
                'date' => $dayStr,
                'daily_wh' => $dailyWh,
                'ppa_minor' => $dailyPpaMinor,
                'uc_cost' => $ucResult['total_cost'],
                'island_served_kw' => $islandServedKw,
                'bess_profit_minor' => $bessResult['profit_minor'],
            ];

            $dailyHash = CanonicalHashChain::computeHash($prevHash, $dailyPayload);
            $chainRecords[] = [
                'prev_hash' => $prevHash,
                'payload' => $dailyPayload,
                'hash' => $dailyHash,
            ];
            $prevHash = $dailyHash;
        }

        // End of Month: Consolidated Utility Invoices for all 5 properties (Period 2026-10)
        $billingPeriod = '2026-10';
        $totalInvoicedMinor = 0;
        $invoices = [];

        foreach ($properties as $prop) {
            $invoice = $this->billingService->generateMonthlyInvoice($prop['id'], $billingPeriod);
            $this->billingService->issueInvoice((string) $invoice->id);
            $invoices[] = $invoice;
            $totalInvoicedMinor += $invoice->total_consolidated_minor;
        }

        // Execute Audits
        $auditExitCode = Artisan::call('egy:audit');
        $groupAuditExitCode = Artisan::call('group:audit');
        $bankReconcileExitCode = Artisan::call('bank:reconcile');

        $finalPayload = [
            'period' => $billingPeriod,
            'properties_count' => count($properties),
            'meters_count' => count($meterList),
            'total_readings' => $totalReadingsIngested,
            'ppa_total_settled_minor' => $ppaTotalMinor,
            'total_invoiced_minor' => $totalInvoicedMinor,
            'audit_exit_code' => $auditExitCode,
            'group_audit_exit_code' => $groupAuditExitCode,
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
            throw new RuntimeException("Canonical hash chain broken at record index: {$brokenIndex}");
        }

        return [
            'seed' => $seed,
            'days' => $days,
            'properties_count' => count($properties),
            'meters_count' => count($meterList),
            'total_readings_ingested' => $totalReadingsIngested,
            'duplicate_attempts' => $duplicateCount,
            'out_of_order_queue_deliveries' => $outOfOrderQueueCount,
            'missing_intervals_detected' => $missingCount,
            'rollover_events' => $rolloverCount,
            'reset_events' => $resetCount,
            'ppa_total_minor' => $ppaTotalMinor,
            'uc_plans_count' => $ucPlansCount,
            'islanding_events_count' => $islandingCount,
            'bess_runs_count' => $bessRunsCount,
            'total_invoiced_minor' => $totalInvoicedMinor,
            'invoices_count' => count($invoices),
            'audit_exit_code' => $auditExitCode,
            'group_audit_exit_code' => $groupAuditExitCode,
            'bank_reconcile_exit_code' => $bankReconcileExitCode,
            'state_hash' => $finalHash,
            'chain_valid' => $chainValid,
        ];
    }
}
