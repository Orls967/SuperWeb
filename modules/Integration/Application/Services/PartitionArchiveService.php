<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * PartitionArchiveService (Fase 192)
 *
 * Implements:
 *  - 192.2 Cold archive store with SHA-256 checksum verification and recall
 *  - 192.3 Materialized summary rollup aggregation per domain
 *  - 192.4 Query budget registry with automated breach detection
 */
class PartitionArchiveService
{
    /**
     * Archive transactional data partition to cold storage.
     */
    public function archivePartition(string $tableSource, string $period, int $recordCount, string $payloadData): object
    {
        $checksum = hash('sha256', $payloadData);
        $key = 'ARC-'.strtoupper(Str::slug($tableSource)).'-'.$period.'-'.strtoupper(Str::random(4));

        $id = DB::table('scl_cold_archives')->insertGetId([
            'archive_key' => $key,
            'table_source' => $tableSource,
            'partition_period' => $period,
            'record_count' => $recordCount,
            'checksum_sha256' => $checksum,
            'storage_location' => 'S3_GLACIER',
            'status' => 'ARCHIVED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('scl_cold_archives')->find($id);
    }

    /**
     * Recall archived partition and verify SHA-256 checksum integrity.
     */
    public function recallPartition(string $archiveKey, string $payloadData): object
    {
        $archive = DB::table('scl_cold_archives')->where('archive_key', $archiveKey)->first();
        if (! $archive) {
            throw new \InvalidArgumentException("Archive {$archiveKey} not found.");
        }

        $currentChecksum = hash('sha256', $payloadData);
        if ($currentChecksum !== $archive->checksum_sha256) {
            throw new \RuntimeException('Archive integrity breach: SHA-256 checksum mismatch on recall.');
        }

        DB::table('scl_cold_archives')->where('archive_key', $archiveKey)->update([
            'status' => 'RECALLED',
            'updated_at' => now(),
        ]);

        return (object) DB::table('scl_cold_archives')->where('archive_key', $archiveKey)->first();
    }

    /**
     * Record materialized domain rollup summary.
     */
    public function recordRollup(string $domainCode, string $date, float $amountIdr, int $eventsCount): object
    {
        $code = 'RLP-'.strtoupper($domainCode).'-'.str_replace('-', '', $date);

        DB::table('scl_domain_rollups')->updateOrInsert(
            ['rollup_code' => $code],
            [
                'domain_code' => strtoupper($domainCode),
                'period_date' => $date,
                'total_amount_idr' => $amountIdr,
                'total_events' => $eventsCount,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('scl_domain_rollups')->where('rollup_code', $code)->first();
    }

    /**
     * Monitor query budget on endpoint. Flags breach if count or latency exceeds limit.
     */
    public function registerAndCheckBudget(string $endpoint, int $maxQueries, float $maxP95Ms, int $actualQueries, float $actualP95Ms): object
    {
        $breached = ($actualQueries > $maxQueries || $actualP95Ms > $maxP95Ms);

        DB::table('scl_query_budgets')->updateOrInsert(
            ['endpoint_name' => $endpoint],
            [
                'max_queries_allowed' => $maxQueries,
                'max_p95_latency_ms' => $maxP95Ms,
                'actual_queries_count' => $actualQueries,
                'actual_p95_latency_ms' => $actualP95Ms,
                'budget_breached' => $breached,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('scl_query_budgets')->where('endpoint_name', $endpoint)->first();
    }

    /**
     * Quality audit gate (`partition:audit`).
     */
    public function audit(): array
    {
        $budgetBreaches = DB::table('scl_query_budgets')
            ->where('budget_breached', true)
            ->count();

        return [
            'status' => $budgetBreaches === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_archives' => DB::table('scl_cold_archives')->count(),
            'total_rollups' => DB::table('scl_domain_rollups')->count(),
            'total_budgets' => DB::table('scl_query_budgets')->count(),
            'discrepancy_count' => $budgetBreaches,
        ];
    }
}
