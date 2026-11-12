<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\DTOs;

/**
 * Data Transfer Object untuk hasil evaluasi multivariate transfer pricing benchmark.
 */
class MultivariateBenchmarkResultDTO
{
    /**
     * @param array<int, array{
     *     comparator_name: string,
     *     operating_margin_bps: int,
     *     adjusted_margin_bps: int,
     *     working_capital_adj_bps: int,
     *     country_risk_adj_bps: int,
     *     is_outlier: bool
     * }> $comparators
     * @param array{
     *     q1_bps: int,
     *     median_bps: int,
     *     q3_bps: int,
     *     iqr_bps: int,
     *     mean_bps: int,
     *     std_dev_bps: int,
     *     min_bps: int,
     *     max_bps: int
     * } $interquartileRange
     * @param array{
     *     actual_margin_bps: int,
     *     is_within_range: bool,
     *     target_median_bps: int,
     *     variance_bps: int,
     *     requires_true_up: bool,
     *     recommended_adjustment_minor: int,
     *     adjustment_direction: string
     * } $testedPartyEvaluation
     * @param array<int, array{
     *     voucher_type: string,
     *     debit_account: string,
     *     credit_account: string,
     *     amount_minor: int,
     *     memo: string
     * }> $trueUpJournalVouchers
     */
    public function __construct(
        public readonly string $ruleCode,
        public readonly string $testedEntityCode,
        public readonly string $benchmarkMethod,
        public readonly string $testedPeriod,
        public readonly array $comparators,
        public readonly array $interquartileRange,
        public readonly array $testedPartyEvaluation,
        public readonly array $trueUpJournalVouchers,
        public readonly string $evaluatedAt,
        public readonly string $auditChecksum
    ) {}

    /**
     * Konversi ke array untuk serialisasi JSON / representasi respons API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rule_code' => $this->ruleCode,
            'tested_entity_code' => $this->testedEntityCode,
            'benchmark_method' => $this->benchmarkMethod,
            'tested_period' => $this->testedPeriod,
            'comparators_count' => count($this->comparators),
            'comparators' => $this->comparators,
            'interquartile_range' => $this->interquartileRange,
            'tested_party_evaluation' => $this->testedPartyEvaluation,
            'true_up_journal_vouchers' => $this->trueUpJournalVouchers,
            'evaluated_at' => $this->evaluatedAt,
            'audit_checksum' => $this->auditChecksum,
        ];
    }
}
