<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Schema;

final class ZeroDowntimeSchemaPlanner
{
    /**
     * Generates a 5-step Expand-and-Contract migration plan for non-trivial schema changes.
     *
     * @return list<array{phase: string, step_number: int, description: string, safe_to_rollback: bool, ddl_snippet: string}>
     */
    public function generateExpandContractPlan(string $tableName, string $oldColumn, string $newColumn, string $columnType): array
    {
        return [
            [
                'phase' => 'EXPAND',
                'step_number' => 1,
                'description' => "Tambahkan kolom baru `{$newColumn}` sebagai NULLABLE pada tabel `{$tableName}`.",
                'safe_to_rollback' => true,
                'ddl_snippet' => "ALTER TABLE `{$tableName}` ADD COLUMN `{$newColumn}` {$columnType} NULL, ALGORITHM=INSTANT;",
            ],
            [
                'phase' => 'DUAL_WRITE',
                'step_number' => 2,
                'description' => "Perbarui kode aplikasi untuk menulis ke kedua kolom (`{$oldColumn}` dan `{$newColumn}`), tetapi tetap membaca dari `{$oldColumn}`.",
                'safe_to_rollback' => true,
                'ddl_snippet' => '-- Dual-write di layer ORM / Eloquent Observer',
            ],
            [
                'phase' => 'BACKFILL',
                'step_number' => 3,
                'description' => "Jalankan background backfill job per chunk untuk menyalin data historis dari `{$oldColumn}` ke `{$newColumn}`.",
                'safe_to_rollback' => true,
                'ddl_snippet' => "UPDATE `{$tableName}` SET `{$newColumn}` = `{$oldColumn}` WHERE `{$newColumn}` IS NULL LIMIT 500;",
            ],
            [
                'phase' => 'SWITCH_READ',
                'step_number' => 4,
                'description' => "Alihkan pembacaan aplikasi sepenuhnya ke kolom baru `{$newColumn}` setelah verifikasi data 100% konsisten.",
                'safe_to_rollback' => true,
                'ddl_snippet' => '-- Switch read path di Query Builder & Repository layer',
            ],
            [
                'phase' => 'CONTRACT',
                'step_number' => 5,
                'description' => "Hapus kolom lama `{$oldColumn}` setelah rilis dinyatakan stabil dan masa tenggang deprecation selesai.",
                'safe_to_rollback' => false,
                'ddl_snippet' => "ALTER TABLE `{$tableName}` DROP COLUMN `{$oldColumn}`, ALGORITHM=INSTANT;",
            ],
        ];
    }
}
