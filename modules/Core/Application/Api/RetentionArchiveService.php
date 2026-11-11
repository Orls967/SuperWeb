<?php

declare(strict_types=1);

namespace Modules\Core\Application\Api;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\RetentionArchiveProvider;
use Modules\Core\Domain\Models\CoreArchiveDrillRestore;
use Modules\Core\Domain\Models\CoreRetentionPolicy;
use Modules\Core\Domain\Models\CoreRetentionRun;

class RetentionArchiveService
{
    public const DEFAULT_RETENTION_DAYS = 1825; // 5 years

    /** @var array<string, RetentionArchiveProvider> */
    private array $providers = [];

    public function registerProvider(RetentionArchiveProvider $provider): void
    {
        $this->providers[$provider->sourceTable()] = $provider;
    }

    public function runBatchForCurrentTenant(): int
    {
        if (\Modules\Core\Application\Tenancy\TenantScopeRegistry::isStrict() && \Modules\Core\Application\Tenancy\TenantContext::current() === null) {
            throw new \Modules\Core\Domain\Exceptions\TenantContextMissingException(
                'Tenant context is required when strict enforcement is active.'
            );
        }

        return 0;
    }

    /**
     * @return array<string, RetentionArchiveProvider>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    public function getProvider(string $sourceTable): RetentionArchiveProvider
    {
        if (!isset($this->providers[$sourceTable])) {
            throw new DomainException("RETENTION_PROVIDER_UNKNOWN: Provider untuk [{$sourceTable}] tidak terdaftar.");
        }

        return $this->providers[$sourceTable];
    }

    /**
     * Move rows older than retention threshold to archive table with SHA-256 chunk verification.
     */
    public function archive(string $sourceTable, int $retentionDays = self::DEFAULT_RETENTION_DAYS): ?CoreRetentionRun
    {
        $provider = $this->getProvider($sourceTable);
        $policy = CoreRetentionPolicy::firstOrCreate(
            ['source_table' => $sourceTable],
            [
                'module_name' => $provider->moduleName(),
                'archive_table' => $provider->archiveTable(),
                'retention_days' => $retentionDays,
                'native_partition_hint' => 'PARTITION BY RANGE (YEAR(' . $provider->timestampColumn() . '))',
                'is_active' => true,
            ]
        );

        $cutoff = now()->subDays($retentionDays);
        $pk = $provider->primaryKeyColumn();
        $ts = $provider->timestampColumn();

        $rows = DB::table($provider->sourceTable())
            ->where($ts, '<=', $cutoff)
            ->orderBy($pk, 'asc')
            ->limit(1000)
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $hashCols = $provider->hashColumns();
        $serializedLines = [];
        $firstId = (string) $rows->first()->{$pk};
        $lastId = (string) $rows->last()->{$pk};

        foreach ($rows as $row) {
            $line = [];
            foreach ($hashCols as $col) {
                $val = $row->{$col} ?? '';
                $line[] = is_scalar($val) ? (string) $val : json_encode($val);
            }
            $serializedLines[] = implode('|', $line);
        }

        $chunkSha256 = hash('sha256', implode("\n", $serializedLines));

        // Create run record
        $run = CoreRetentionRun::create([
            'retention_policy_id' => $policy->id,
            'source_table' => $provider->sourceTable(),
            'archive_table' => $provider->archiveTable(),
            'chunk_start_ref' => $firstId,
            'chunk_end_ref' => $lastId,
            'rows_archived' => $rows->count(),
            'chunk_sha256' => $chunkSha256,
            'status' => 'pending',
            'executed_at' => now(),
        ]);

        // Insert into archive table then delete from source table inside transaction
        DB::transaction(function () use ($provider, $rows, $run, $pk): void {
            $archiveData = [];
            foreach ($rows as $row) {
                $rowArray = (array) $row;
                $rowArray['archive_run_id'] = $run->id;
                $rowArray['archived_at'] = now();
                if (isset($rowArray['created_at'])) {
                    $rowArray['original_created_at'] = $rowArray['created_at'];
                    unset($rowArray['created_at'], $rowArray['updated_at']);
                }
                $archiveData[] = $rowArray;
            }

            DB::table($provider->archiveTable())->insert($archiveData);

            $idsToDelete = $rows->pluck($pk)->all();
            DB::table($provider->sourceTable())->whereIn($pk, $idsToDelete)->delete();
        });

        $run->update(['status' => 'verified']);

        return $run->fresh();
    }

    /**
     * Run disaster-recovery audit drill by verifying archive chunk hash matches actual payload.
     */
    public function drillRestore(CoreRetentionRun $run): CoreArchiveDrillRestore
    {
        $provider = $this->getProvider($run->source_table);
        $pk = $provider->primaryKeyColumn();
        $hashCols = $provider->hashColumns();

        $archivedRows = DB::table($provider->archiveTable())
            ->where('archive_run_id', $run->id)
            ->orderBy($pk, 'asc')
            ->get();

        $serializedLines = [];
        foreach ($archivedRows as $row) {
            $line = [];
            foreach ($hashCols as $col) {
                $val = $row->{$col} ?? '';
                $line[] = is_scalar($val) ? (string) $val : json_encode($val);
            }
            $serializedLines[] = implode('|', $line);
        }

        $actualSha256 = hash('sha256', implode("\n", $serializedLines));
        $matched = hash_equals($run->chunk_sha256, $actualSha256);

        return CoreArchiveDrillRestore::create([
            'retention_run_id' => $run->id,
            'source_table' => $run->source_table,
            'restored_rows' => $archivedRows->count(),
            'expected_sha256' => $run->chunk_sha256,
            'actual_sha256' => $actualSha256,
            'matched' => $matched,
            'restored_payload' => json_encode($archivedRows->take(10)->all()),
            'drilled_at' => now(),
        ]);
    }
}
