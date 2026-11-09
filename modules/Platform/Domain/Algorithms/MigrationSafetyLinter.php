<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Algorithms;

final class MigrationSafetyLinter
{
    /**
     * Lints a migration file path.
     *
     * @return list<MigrationSafetyViolation>
     */
    public function lintFile(string $filePath): array
    {
        if (! file_exists($filePath)) {
            return [];
        }

        $code = (string) file_get_contents($filePath);

        return $this->lintString($code, basename($filePath));
    }

    /**
     * Lints raw PHP migration source code.
     *
     * @return list<MigrationSafetyViolation>
     */
    public function lintString(string $code, string $fileName = 'migration.php'): array
    {
        $violations = [];
        $lines = explode("\n", $code);

        $isAlteringExistingTable = false;
        $inDownMethod = false;

        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;
            $trimmed = trim($line);

            if (str_contains($line, 'function down(')) {
                $inDownMethod = true;
            } elseif (str_contains($line, 'function up(')) {
                $inDownMethod = false;
            }

            // Skip checks inside down() method as reversal drop operations are expected
            if ($inDownMethod) {
                continue;
            }

            if (str_contains($line, 'Schema::table(')) {
                $isAlteringExistingTable = true;
            } elseif (str_contains($line, 'Schema::create(')) {
                $isAlteringExistingTable = false;
            }

            // Check 1: NOT NULL without DEFAULT on existing table
            if ($isAlteringExistingTable && str_contains($line, '$table->')) {
                if (str_contains($line, '->nullable(false)') || (preg_match('/\$table->(string|integer|bigInteger|boolean|text|json)\(/', $line) && ! str_contains($line, '->nullable()') && ! str_contains($line, '->default(') && ! str_contains($line, '->change()'))) {
                    // Check if default is provided on same or chained call
                    if (! str_contains($line, '->default(')) {
                        $violations[] = new MigrationSafetyViolation(
                            'NOT_NULL_WITHOUT_DEFAULT',
                            $fileName,
                            $lineNumber,
                            'Penambahan kolom NOT NULL tanpa DEFAULT pada tabel existing menyebabkan kegagalan DDL dan lock eksklusif.'
                        );
                    }
                }
            }

            // Check 2: Direct dropColumn on existing table without approval tag
            if (str_contains($line, '->dropColumn(') && ! str_contains($line, '#approved-destructive')) {
                $violations[] = new MigrationSafetyViolation(
                    'DIRECT_DROP_COLUMN',
                    $fileName,
                    $lineNumber,
                    'Penghapusan kolom langsung (dropColumn) dilarang tanpa tag #approved-destructive atau fase deprecation bertahap.'
                );
            }

            // Check 3: Raw exclusive table lock keywords in DB statements
            if (preg_match('/LOCK\s+TABLES|EXCLUSIVE\s+MODE/i', $line)) {
                $violations[] = new MigrationSafetyViolation(
                    'EXCLUSIVE_TABLE_LOCK',
                    $fileName,
                    $lineNumber,
                    'Penguncian tabel eksklusif (exclusive table lock) dilarang pada sistem zero-downtime.'
                );
            }
        }

        return $violations;
    }
}
