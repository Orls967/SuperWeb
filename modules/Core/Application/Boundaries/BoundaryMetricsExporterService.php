<?php

declare(strict_types=1);

namespace Modules\Core\Application\Boundaries;

use Modules\Core\Domain\Models\CoreBoundaryViolation;

/**
 * Service for deterministic export of architecture boundary metrics (DR4-3, DR4-8).
 *
 * Produces structured CSV and JSON streams without timestamps in data rows
 * to guarantee reproducible byte-identical verification.
 */
final class BoundaryMetricsExporterService
{
    /**
     * Export violations to CSV format.
     */
    public function exportViolationsCsv(): string
    {
        $violations = CoreBoundaryViolation::orderBy('rule_id')
            ->orderBy('client_module')
            ->orderBy('id')
            ->get();

        $rows = [];
        $rows[] = 'ID,RuleID,Category,ClientModule,OwnerModule,FilePath,LineNumber,Status,ContractName';

        foreach ($violations as $v) {
            $rows[] = sprintf(
                '%d,"%s","%s","%s","%s","%s",%d,"%s","%s"',
                $v->id,
                $v->rule_id,
                $v->category,
                $v->client_module,
                $v->owner_module,
                str_replace('"', '""', $v->file_path),
                $v->line_number,
                $v->status instanceof \BackedEnum ? $v->status->value : (string) $v->status,
                str_replace('"', '""', (string) $v->contract_name)
            );
        }

        return implode("\n", $rows)."\n";
    }

    /**
     * Export waivers to CSV format.
     */
    public function exportWaiversCsv(): string
    {
        $waivers = CoreBoundaryViolation::where('status', 'waived')
            ->orderBy('rule_id')
            ->orderBy('client_module')
            ->orderBy('id')
            ->get();

        $rows = [];
        $rows[] = 'ID,RuleID,ClientModule,OwnerModule,AdrAnchor,WaivedBy,ExpiresAt,DaysRemaining,Reason';

        foreach ($waivers as $w) {
            $daysRemaining = $w->expires_at ? max(0, (int) now()->diffInDays($w->expires_at, false)) : 0;
            $rows[] = sprintf(
                '%d,"%s","%s","%s","%s","%s","%s",%d,"%s"',
                $w->id,
                $w->rule_id,
                $w->client_module,
                $w->owner_module,
                $w->adr_anchor ?? '',
                $w->waived_by ?? '',
                $w->expires_at?->format('Y-m-d') ?? '',
                $daysRemaining,
                str_replace('"', '""', (string) $w->waiver_reason)
            );
        }

        return implode("\n", $rows)."\n";
    }

    /**
     * Export contract coverage scores to CSV.
     *
     * @param  array{perModuleScores: array<string, int>, globalScore: int, totalContracts: int, totalViolations: int}  $coverageData
     */
    public function exportCoverageCsv(array $coverageData): string
    {
        $rows = [];
        $rows[] = 'Module,CoveragePermille,Percentage,Status';

        $perModule = $coverageData['perModuleScores'];
        ksort($perModule);

        foreach ($perModule as $module => $score) {
            $percent = number_format($score / 10, 1, '.', '');
            $status = $score >= 800 ? 'EXCELLENT' : ($score >= 500 ? 'MODERATE' : 'CRITICAL');
            $rows[] = sprintf('"%s",%d,"%s%%","%s"', $module, $score, $percent, $status);
        }

        $globalPercent = number_format($coverageData['globalScore'] / 10, 1, '.', '');
        $rows[] = sprintf('"_GLOBAL_TOTAL",%d,"%s%%","SUMMARY"', $coverageData['globalScore'], $globalPercent);

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
        $rows = [];
        $rows[] = 'Type,PatternOrTable,OwnerModule,ExceptionKind';

        // 1. Registered prefixes
        ksort($prefixes);
        foreach ($prefixes as $prefix => $owner) {
            $rows[] = sprintf('"PREFIX","%s","%s","STANDARD"', $prefix, $owner);
        }

        // 2. Legacy tables
        ksort($legacyTables);
        foreach ($legacyTables as $table => $owner) {
            $rows[] = sprintf('"LEGACY","%s","%s","LEGACY_ALLOWLIST"', $table, $owner);
        }

        // 3. Shared tables
        ksort($sharedTables);
        foreach ($sharedTables as $consumer => $tables) {
            sort($tables);
            foreach ($tables as $t) {
                $rows[] = sprintf('"SHARED","%s","%s","WAIVER_SHARED_ACCESS"', $t, $consumer);
            }
        }

        return implode("\n", $rows)."\n";
    }
}
