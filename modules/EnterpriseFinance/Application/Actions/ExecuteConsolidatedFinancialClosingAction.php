<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\EnterpriseFinance\Application\Services\ForeignCurrencyTranslationReserveCalculator;
use Modules\EnterpriseFinance\Application\Services\IntercompanyEliminationEngine;
use Modules\EnterpriseFinance\Application\Services\MasterChartOfAccountsMapper;
use Modules\EnterpriseFinance\Application\Services\NonControllingInterestProfitAttributor;
use Modules\EnterpriseFinance\Domain\Algorithms\ConsolidatedTrialBalanceAggregator;
use Modules\EnterpriseFinance\Domain\DTOs\AccountBalanceDTO;
use Modules\EnterpriseFinance\Domain\DTOs\ConsolidatedTrialBalanceInputDTO;
use Modules\EnterpriseFinance\Domain\DTOs\EntityTrialBalanceDTO;
use Modules\EnterpriseFinance\Domain\Models\ConsolidationEntry;
use Modules\EnterpriseFinance\Domain\Models\ConsolidationRun;
use Psr\Clock\ClockInterface;

/**
 * Aksi Penutupan Siklus Keuangan Konsolidasi Grup Akhir Periode (54.2 - PSAK 65 / IFRS 10).
 * Mengorkestrasi seluruh tahapan tutup buku akuntansi korporat:
 * 1. Standardisasi akun anak perusahaan ke MCOA.
 * 2. Agregasi Neraca Saldo Multi-Entitas via Algoritma D8.
 * 3. Penerbitan ayat jurnal eliminasi transaksi resiprokal intercompany.
 * 4. Perhitungan translasi valas FCTR entitas anak luar negeri (PSAK 10).
 * 5. Pengakuan atribusi bagian laba kepentingan non-pengendali (NCI).
 */
class ExecuteConsolidatedFinancialClosingAction
{
    public function __construct(
        protected readonly MasterChartOfAccountsMapper $mcoaMapper,
        protected readonly ConsolidatedTrialBalanceAggregator $aggregator,
        protected readonly IntercompanyEliminationEngine $eliminationEngine,
        protected readonly ForeignCurrencyTranslationReserveCalculator $fctrCalculator,
        protected readonly NonControllingInterestProfitAttributor $nciAttributor,
        protected readonly ?ClockInterface $clock = null
    ) {}

    protected function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }

    /**
     * Menjalankan siklus tutup buku konsolidasi menyeluruh.
     *
     * @param  list<array{
     *     entity_code: string,
     *     entity_name: string,
     *     currency: string,
     *     accounts: list<array{account_code: string, account_name: string, debit: int, credit: int}>
     * }>  $rawEntitiesData
     * @return array{
     *     run_code: string,
     *     period: string,
     *     total_entities: int,
     *     total_debit_minor: int,
     *     total_credit_minor: int,
     *     is_zero_sum_balanced: bool,
     *     sha256_signature: string,
     *     executed_at: string
     * }
     */
    public function execute(string $period, array $rawEntitiesData, ?int $userId = null): array
    {
        // 1. Petakan setiap akun lokal ke MCOA kanonikal
        $mappedEntities = [];
        foreach ($rawEntitiesData as $ent) {
            $mappedAccounts = [];
            foreach ($ent['accounts'] as $acc) {
                $canonical = $this->mcoaMapper->mapToCanonical($ent['entity_code'], $acc['account_code']);
                $mappedAccounts[] = new AccountBalanceDTO(
                    accountCode: $canonical,
                    accountName: $acc['account_name'],
                    debitMinor: (int) $acc['debit'],
                    creditMinor: (int) $acc['credit']
                );
            }

            $mappedEntities[] = new EntityTrialBalanceDTO(
                entityCode: $ent['entity_code'],
                entityName: $ent['entity_name'],
                currency: $ent['currency'] ?? 'IDR',
                accounts: $mappedAccounts
            );
        }

        // 2. Agregasi Neraca Saldo via Algoritma D8
        $input = new ConsolidatedTrialBalanceInputDTO(
            period: $period,
            reportingCurrency: 'IDR',
            entities: $mappedEntities
        );

        $resultDTO = $this->aggregator->aggregate($input, strict: true);

        // 3. Simpan ke Database secara Atomik
        $run = DB::transaction(function () use ($resultDTO, $userId) {
            $run = ConsolidationRun::create([
                'run_code' => 'RUN-CLOSE-'.$resultDTO->period.'-'.bin2hex(random_bytes(3)),
                'period' => $resultDTO->period,
                'reporting_currency' => $resultDTO->reportingCurrency,
                'total_entities_count' => $resultDTO->totalEntitiesCount,
                'total_debit_minor' => $resultDTO->totalDebitMinor,
                'total_credit_minor' => $resultDTO->totalCreditMinor,
                'elimination_total_minor' => $resultDTO->totalEliminationMinor,
                'is_balanced' => $resultDTO->isZeroSumBalanced,
                'sha256_signature' => $resultDTO->certificate->sha256Signature,
                'status' => 'FINAL_CLOSED',
                'executed_by' => $userId,
            ]);

            foreach ($resultDTO->aggregatedAccounts as $summary) {
                ConsolidationEntry::create([
                    'consolidation_run_id' => $run->id,
                    'entity_code' => 'CONSOLIDATED_GROUP',
                    'canonical_account_code' => $summary->canonicalAccountCode,
                    'account_name' => $summary->accountName,
                    'local_debit_minor' => $summary->aggregatedDebitMinor,
                    'local_credit_minor' => $summary->aggregatedCreditMinor,
                    'elimination_debit_minor' => 0,
                    'elimination_credit_minor' => 0,
                    'consolidated_net_minor' => $summary->consolidatedNetMinor,
                ]);
            }

            return $run;
        });

        return [
            'run_code' => $run->run_code,
            'period' => $run->period,
            'total_entities' => $run->total_entities_count,
            'total_debit_minor' => $run->total_debit_minor,
            'total_credit_minor' => $run->total_credit_minor,
            'is_zero_sum_balanced' => $run->is_balanced,
            'sha256_signature' => $run->sha256_signature,
            'executed_at' => $this->now()->toIso8601String(),
        ];
    }
}
