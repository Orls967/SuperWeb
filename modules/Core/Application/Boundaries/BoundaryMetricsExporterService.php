<?php

declare(strict_types=1);

namespace Modules\Core\Application\Boundaries;

use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Contracts\ClockInterface;
use Modules\Core\Domain\Models\CoreBoundaryViolation;

/**
 * Service for deterministic export of architecture boundary metrics (DR4-3, DR4-8).
 *
 * Produces structured CSV and JSON streams without timestamps in data rows
 * to guarantee reproducible byte-identical verification.
 */
final class BoundaryMetricsExporterService
{
    public function __construct(
        private readonly ?ClockInterface $clock = null,
    ) {}

    /**
     * Export violations to CSV format.
     */
    public function exportViolationsCsv(): string
    {
        $rows = ['ID,RuleID,Category,ClientModule,OwnerModule,FilePath,LineNumber,Status,ContractName'];
        foreach ($this->orderedRecords() as $v) {
            $status = $v->status instanceof \BackedEnum ? $v->status->value : (string) $v->status;
            $rows[] = $this->formatCsvLine([
                $v->id,
                $v->rule_id,
                $v->category,
                $v->client_module,
                $v->owner_module,
                $v->file_path,
                $v->line_number,
                $status,
                (string) $v->contract_name,
            ]);
        }

        return implode("\n", $rows)."\n";
    }

    /**
     * Export waivers to CSV format.
     */
    public function exportWaiversCsv(): string
    {
        $now = ($this->clock ?? app(ClockInterface::class))->now();
        $rows = ['ID,RuleID,ClientModule,OwnerModule,AdrAnchor,WaivedBy,ExpiresAt,DaysRemaining,Reason'];

        foreach ($this->orderedRecords('waived') as $w) {
            $daysRemaining = $w->expires_at ? max(0, (int) $now->diffInDays($w->expires_at, false)) : 0;
            $rows[] = $this->formatCsvLine([
                $w->id,
                $w->rule_id,
                $w->client_module,
                $w->owner_module,
                $w->adr_anchor ?? '',
                $w->waived_by ?? '',
                $w->expires_at?->format('Y-m-d') ?? '',
                $daysRemaining,
                (string) $w->waiver_reason,
            ]);
        }

        return implode("\n", $rows)."\n";
    }

    /**
     * @return Collection<int, CoreBoundaryViolation>
     */
    private function orderedRecords(?string $status = null): Collection
    {
        $query = CoreBoundaryViolation::query();
        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->orderBy('rule_id')->orderBy('client_module')->orderBy('id')->get();
    }

    /**
     * Export contract coverage scores to CSV.
     *
     * @param  array{perModuleScores: array<string, int>, globalScore: int, totalContracts: int, totalViolations: int}  $coverageData
     */
    public function exportCoverageCsv(array $coverageData): string
    {
        $rows = ['Module,CoveragePermille,Percentage,Status'];
        $perModule = $coverageData['perModuleScores'];
        ksort($perModule);

        foreach ($perModule as $module => $score) {
            $percent = number_format($score / 10, 1, '.', '');
            $status = $score >= 800 ? 'EXCELLENT' : ($score >= 500 ? 'MODERATE' : 'CRITICAL');
            $rows[] = $this->formatCsvLine([$module, $score, "{$percent}%", $status]);
        }

        $globalPercent = number_format($coverageData['globalScore'] / 10, 1, '.', '');
        $rows[] = $this->formatCsvLine(['_GLOBAL_TOTAL', $coverageData['globalScore'], "{$globalPercent}%", 'SUMMARY']);

        return implode("\n", $rows)."\n";
    }

    /**
     * Export table ownership mapping to CSV format.
     *
     * @param  array<string, string>  $prefixes
     * @param  array<string, string>  $legacyTables
     * @param  array<string, list<string>>  $sharedTables
     */
    public function exportTableOwnershipCsv(array $prefixes, array $legacyTables, array $sharedTables): string
    {
        $rows = ['Type,PatternOrTable,OwnerModule,ExceptionKind'];

        ksort($prefixes);
        foreach ($prefixes as $prefix => $owner) {
            $rows[] = $this->formatCsvLine(['PREFIX', $prefix, $owner, 'STANDARD']);
        }

        ksort($legacyTables);
        foreach ($legacyTables as $table => $owner) {
            $rows[] = $this->formatCsvLine(['LEGACY', $table, $owner, 'LEGACY_ALLOWLIST']);
        }

        ksort($sharedTables);
        foreach ($sharedTables as $consumer => $tables) {
            sort($tables);
            foreach ($tables as $t) {
                $rows[] = $this->formatCsvLine(['SHARED', $t, $consumer, 'WAIVER_SHARED_ACCESS']);
            }
        }

        return implode("\n", $rows)."\n";
    }

    /**
     * @param  list<mixed>  $fields
     */
    private function formatCsvLine(array $fields): string
    {
        $escaped = array_map(static function (mixed $value): string {
            if (is_int($value)) {
                return (string) $value;
            }

            return '"'.str_replace('"', '""', (string) ($value instanceof \BackedEnum ? $value->value : $value)).'"';
        }, $fields);

        return implode(',', $escaped);
    }
}
