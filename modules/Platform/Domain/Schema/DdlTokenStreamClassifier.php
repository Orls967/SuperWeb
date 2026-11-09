<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Schema;

final class DdlTokenStreamClassifier
{
    /**
     * @return array{
     *     operation_type: string,
     *     target_table: string|null,
     *     risk_tier: string,
     *     requires_table_lock: bool,
     *     estimated_lock_scope: string,
     *     instant_ddl_compatible: bool,
     *     recommendations: list<string>
     * }
     */
    public function classifyDdlStatement(string $sql): array
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $sql) ?? '');
        $upper = strtoupper($normalized);

        $operationType = 'UNKNOWN';
        $targetTable = null;
        $riskTier = 'low';
        $requiresLock = false;
        $lockScope = 'none';
        $instantDdl = true;
        $recommendations = [];

        if (preg_match('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:`?([a-zA-Z0-9_]+)`?\.`?)?`?([a-zA-Z0-9_]+)`?/i', $normalized, $matches)) {
            $operationType = 'CREATE_TABLE';
            $targetTable = $matches[2] ?? $matches[1] ?? 'unknown';
            $riskTier = 'low';
            $requiresLock = false;
            $lockScope = 'metadata_only';
            $instantDdl = true;
            $recommendations[] = 'Ensure table has appropriate engine (InnoDB) and utf8mb4 collation.';
        } elseif (preg_match('/^DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?/i', $normalized, $matches)) {
            $operationType = 'DROP_TABLE';
            $targetTable = $matches[1] ?? 'unknown';
            $riskTier = 'critical';
            $requiresLock = true;
            $lockScope = 'exclusive';
            $instantDdl = false;
            $recommendations[] = 'Destructive operation: Ensure backup and deprecation window before drop.';
        } elseif (preg_match('/^ALTER\s+TABLE\s+`?([a-zA-Z0-9_]+)`?\s+(.*)/i', $normalized, $matches)) {
            $operationType = 'ALTER_TABLE';
            $targetTable = $matches[1] ?? 'unknown';
            $alterBody = strtoupper($matches[2] ?? '');

            if (str_contains($alterBody, 'DROP COLUMN')) {
                $riskTier = 'high';
                $requiresLock = true;
                $lockScope = 'exclusive';
                $instantDdl = false;
                $recommendations[] = 'Dropping column causes downtime if application queries reference it. Deprecate column first.';
            } elseif (str_contains($alterBody, 'ADD INDEX') || str_contains($alterBody, 'ADD KEY')) {
                $riskTier = 'medium';
                $requiresLock = false;
                $lockScope = 'shared';
                $instantDdl = true;
                $recommendations[] = 'Use ALGORITHM=INPLACE, LOCK=NONE if supported on large tables.';
            } elseif (str_contains($alterBody, 'ADD COLUMN')) {
                $riskTier = 'low';
                $requiresLock = false;
                $lockScope = 'metadata_only';
                $instantDdl = true;
                $recommendations[] = 'Adding column at end of table is INSTANT on MySQL 8.0+. Adding in middle (AFTER) requires table copy.';
            } elseif (str_contains($alterBody, 'MODIFY') || str_contains($alterBody, 'CHANGE')) {
                $riskTier = 'high';
                $requiresLock = true;
                $lockScope = 'exclusive';
                $instantDdl = false;
                $recommendations[] = 'Modifying column type typically rewrites table. Verify row count before running.';
            }
        } elseif (preg_match('/^CREATE\s+INDEX\s+`?([a-zA-Z0-9_]+)`?\s+ON\s+`?([a-zA-Z0-9_]+)`?/i', $normalized, $matches)) {
            $operationType = 'CREATE_INDEX';
            $targetTable = $matches[2] ?? 'unknown';
            $riskTier = 'medium';
            $requiresLock = false;
            $lockScope = 'shared';
            $instantDdl = true;
            $recommendations[] = 'Concurrent index creation recommended during off-peak hours.';
        } elseif (preg_match('/^TRUNCATE\s+(?:TABLE\s+)?`?([a-zA-Z0-9_]+)`?/i', $normalized, $matches)) {
            $operationType = 'TRUNCATE_TABLE';
            $targetTable = $matches[1] ?? 'unknown';
            $riskTier = 'critical';
            $requiresLock = true;
            $lockScope = 'exclusive';
            $instantDdl = false;
            $recommendations[] = 'Truncate removes all rows instantly. Disallowed in zero-downtime migrations.';
        }

        return [
            'operation_type' => $operationType,
            'target_table' => $targetTable,
            'risk_tier' => $riskTier,
            'requires_table_lock' => $requiresLock,
            'estimated_lock_scope' => $lockScope,
            'instant_ddl_compatible' => $instantDdl,
            'recommendations' => $recommendations,
        ];
    }
}
