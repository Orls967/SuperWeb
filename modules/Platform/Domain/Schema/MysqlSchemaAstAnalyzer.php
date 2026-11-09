<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Schema;

final class MysqlSchemaAstAnalyzer
{
    /**
     * Inspects column definitions for MySQL 8.4 instant DDL compatibility.
     *
     * @return array{is_instant_compatible: bool, requires_table_rebuild: bool, lock_type: string, algorithm: string, risk_notes: list<string>}
     */
    public function analyzeColumnOperation(string $operationType, string $columnType, array $options = []): array
    {
        $isInstant = false;
        $requiresRebuild = false;
        $lockType = 'NONE';
        $algorithm = 'INPLACE';
        $riskNotes = [];

        switch (strtolower($operationType)) {
            case 'add':
                // MySQL 8.0.29+ / 8.4 supports ALGORITHM=INSTANT for adding columns at the end, or with DEFAULT
                $hasDefault = isset($options['default']);
                $isNullable = $options['nullable'] ?? true;

                if ($isNullable || $hasDefault) {
                    $isInstant = true;
                    $algorithm = 'INSTANT';
                    $lockType = 'NONE';
                } else {
                    $isInstant = false;
                    $requiresRebuild = true;
                    $lockType = 'SHARED';
                    $algorithm = 'COPY';
                    $riskNotes[] = 'Penambahan kolom NOT NULL tanpa DEFAULT membutuhkan evaluasi baris tabel penuh (full rebuild).';
                }
                break;

            case 'drop':
                // MySQL 8.4 allows INSTANT drop column if metadata slot is available
                $isInstant = true;
                $algorithm = 'INSTANT';
                $lockType = 'NONE';
                $riskNotes[] = 'Drop column aman di level engine tapi berisiko aplikasi bila masih ada query yang membaca kolom tersebut.';
                break;

            case 'modify':
                // Expanding varchar length without exceeding 255 bytes is INSTANT/INPLACE
                if ($columnType === 'varchar' && ($options['old_length'] ?? 0) < 255 && ($options['new_length'] ?? 0) <= 255) {
                    $isInstant = true;
                    $algorithm = 'INPLACE';
                    $lockType = 'NONE';
                } else {
                    $requiresRebuild = true;
                    $lockType = 'SHARED';
                    $algorithm = 'COPY';
                    $riskNotes[] = 'Perubahan tipe kolom atau pelebaran varchar melintasi batas 255 byte memicu full table copy.';
                }
                break;

            case 'add_index':
                $isUnique = $options['unique'] ?? false;
                $algorithm = 'INPLACE';
                $lockType = $isUnique ? 'SHARED' : 'NONE';
                if ($isUnique) {
                    $riskNotes[] = 'Index UNIQUE membutuhkan pengecekan duplikasi yang dapat memicu temporary shared lock.';
                }
                break;

            default:
                $requiresRebuild = true;
                $lockType = 'EXCLUSIVE';
                $algorithm = 'COPY';
                $riskNotes[] = "Operasi {$operationType} tidak tergolong zero-downtime DDL standar.";
        }

        return [
            'is_instant_compatible' => $isInstant,
            'requires_table_rebuild' => $requiresRebuild,
            'lock_type' => $lockType,
            'algorithm' => $algorithm,
            'risk_notes' => $riskNotes,
        ];
    }
}
