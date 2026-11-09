<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Change;

final class ChangeConflictDetector
{
    /**
     * Checks for overlaps between pending change requests within the same release window.
     *
     * @param  array{id: int, files: list<string>, tables: list<string>, routes: list<string>}  $primaryChange
     * @param  list<array{id: int, files: list<string>, tables: list<string>, routes: list<string>}>  $otherChanges
     * @return array{has_conflicts: bool, conflicted_changes: list<int>, overlapping_files: list<string>, overlapping_tables: list<string>, severity: string}
     */
    public function detectConflicts(array $primaryChange, array $otherChanges): array
    {
        $conflictedChanges = [];
        $overlappingFiles = [];
        $overlappingTables = [];

        $pFiles = $primaryChange['files'] ?? [];
        $pTables = $primaryChange['tables'] ?? [];
        $pRoutes = $primaryChange['routes'] ?? [];

        foreach ($otherChanges as $other) {
            if (($other['id'] ?? 0) === ($primaryChange['id'] ?? 0)) {
                continue;
            }

            $oFiles = $other['files'] ?? [];
            $oTables = $other['tables'] ?? [];
            $oRoutes = $other['routes'] ?? [];

            $fileIntersect = array_intersect($pFiles, $oFiles);
            $tableIntersect = array_intersect($pTables, $oTables);
            $routeIntersect = array_intersect($pRoutes, $oRoutes);

            if ($fileIntersect !== [] || $tableIntersect !== [] || $routeIntersect !== []) {
                $conflictedChanges[] = $other['id'];
                $overlappingFiles = array_merge($overlappingFiles, $fileIntersect);
                $overlappingTables = array_merge($overlappingTables, $tableIntersect);
            }
        }

        $overlappingFiles = array_values(array_unique($overlappingFiles));
        $overlappingTables = array_values(array_unique($overlappingTables));

        $hasConflicts = $conflictedChanges !== [];
        $severity = 'NONE';

        if ($hasConflicts) {
            if ($overlappingTables !== []) {
                $severity = 'CRITICAL_SCHEMA_COLLISION';
            } elseif (count($overlappingFiles) > 3) {
                $severity = 'HIGH_CODE_COLLISION';
            } else {
                $severity = 'MEDIUM_COORDINATION_WARNING';
            }
        }

        return [
            'has_conflicts' => $hasConflicts,
            'conflicted_changes' => $conflictedChanges,
            'overlapping_files' => $overlappingFiles,
            'overlapping_tables' => $overlappingTables,
            'severity' => $severity,
        ];
    }
}
