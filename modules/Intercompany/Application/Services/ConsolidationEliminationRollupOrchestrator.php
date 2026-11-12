<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Intercompany\Domain\Algorithms\ConsolidationRollupTreeOptimizer;
use Modules\Intercompany\Domain\Events\IntercompanyConsolidationLocked;
use Modules\Intercompany\Domain\Models\EliminationEntry;
use Psr\Clock\ClockInterface;

/**
 * Orkestrator Eksekusi dan Penguncian Konsolidasi Keuangan Grup (PSAK 65 / IFRS 10).
 *
 * Mengoordinasikan penarikan neraca lajur seluruh entitas, pembentukan jurnal eliminasi,
 * penyimpanan jurnal ke basis data, kalkulasi NCI, serta pemancaran event domain penguncian periode (lock event).
 * Seluruh kalkulasi beroperasi pada bilangan bulat (integer minor units sen / basis points) tanpa float.
 */
class ConsolidationEliminationRollupOrchestrator
{
    public function __construct(
        protected readonly ConsolidationRollupTreeOptimizer $treeOptimizer,
        protected readonly ?ClockInterface $clock = null
    ) {}

    /**
     * Jalankan proses konsolidasi grup dan kunci periode fiskal.
     *
     * @param array{
     *     fiscal_period: string,
     *     holding_entity_code: string,
     *     operator_user_id: int,
     *     presentation_currency?: string,
     *     persist_eliminations?: bool
     * } $config
     * @param  array<string, array<string, mixed>>  $trialBalances
     * @param array<int, array{
     *     selling_entity: string,
     *     buying_entity: string,
     *     unrealized_profit_minor: int,
     *     sale_type: string
     * }> $unrealizedProfits
     * @return array{
     *     consolidation_run_id: string,
     *     fiscal_period: string,
     *     holding_entity_code: string,
     *     locked_by_user_id: int,
     *     locked_at: string,
     *     total_eliminated_minor: int,
     *     persisted_entries_count: int,
     *     consolidated_financials: array<string, mixed>,
     *     nci_attributions: array<string, mixed>,
     *     audit_hash: string
     * }
     */
    public function runAndLock(
        array $config,
        array $trialBalances,
        array $unrealizedProfits = []
    ): array {
        $period = $config['fiscal_period'];
        $holding = strtoupper($config['holding_entity_code']);
        $userId = (int) $config['operator_user_id'];
        $currency = strtoupper($config['presentation_currency'] ?? 'IDR');
        $persist = (bool) ($config['persist_eliminations'] ?? true);

        $now = $this->clock !== null ? CarbonImmutable::instance($this->clock->now()) : CarbonImmutable::now();
        $runId = sprintf('CONS-RUN-%s-%s', $period, $now->format('His'));

        // 1. Eksekusi Roll-Up Konsolidasi
        $optConfig = [
            'run_id' => $runId,
            'fiscal_period' => $period,
            'presentation_currency' => $currency,
            'holding_entity_code' => $holding,
        ];

        $resultDTO = $this->treeOptimizer->rollup($optConfig, $trialBalances, $unrealizedProfits);
        $resArray = $resultDTO->toArray();

        // 2. Hitung Total Eliminasi Bruto
        $totalEliminatedMinor = 0;
        foreach ($resArray['elimination_journals'] as $j) {
            $totalEliminatedMinor += (int) $j['amount_minor'];
        }

        // 3. Simpan Jurnal Eliminasi ke Basis Data jika Diaktifkan
        $persistedCount = 0;
        if ($persist) {
            foreach ($resArray['elimination_journals'] as $j) {
                EliminationEntry::create([
                    'period' => $period,
                    'elimination_code' => $j['journal_code'],
                    'elimination_type' => $j['elimination_type'],
                    'debit_account' => $j['debit_account'],
                    'credit_account' => $j['credit_account'],
                    'amount_idr' => $j['amount_minor'],
                    'amount_minor' => $j['amount_minor'],
                    'rationale' => $j['rationale'],
                    'created_by' => $userId,
                ]);
                $persistedCount++;
            }
        }

        $isoNow = $now->toIso8601String();

        // 4. Pancarkan Domain Event Penguncian Periode Konsolidasi
        event(new IntercompanyConsolidationLocked(
            fiscalPeriod: $period,
            lockedByUserId: $userId,
            totalEliminatedMinor: $totalEliminatedMinor,
            lockedAt: $isoNow
        ));

        return [
            'consolidation_run_id' => $runId,
            'fiscal_period' => $period,
            'holding_entity_code' => $holding,
            'locked_by_user_id' => $userId,
            'locked_at' => $isoNow,
            'total_eliminated_minor' => $totalEliminatedMinor,
            'persisted_entries_count' => $persistedCount,
            'consolidated_financials' => $resArray['consolidated_financials'],
            'nci_attributions' => $resArray['nci_attributions'],
            'audit_hash' => $resArray['integrity_hash'],
        ];
    }
}
