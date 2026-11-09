<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Schema;

use Modules\Platform\Domain\Algorithms\MigrationSafetyViolation;

final class MigrationSafetyAstVisitor
{
    /**
     * @var list<string> Unsafe blueprint method patterns in Laravel
     */
    private const UNSAFE_CALLS = [
        'dropColumn',
        'dropTable',
        'dropTableIfExists',
        'renameColumn',
        'renameTable',
    ];

    /**
     * @return list<MigrationSafetyViolation>
     */
    public function visitFileContent(string $filePath, string $content): array
    {
        $violations = [];
        $lines = explode("\n", $content);

        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;
            $trimmed = trim($line);

            // Skip comments
            if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '#') || str_starts_with($trimmed, '*')) {
                continue;
            }

            foreach (self::UNSAFE_CALLS as $call) {
                if (preg_match('/->\s*'.preg_quote($call, '/').'\s*\(/', $line)) {
                    $violations[] = new MigrationSafetyViolation(
                        ruleName: 'DISALLOW_'.strtoupper($call),
                        severity: 'high',
                        message: "Destructive schema method ->{$call}() detected at {$filePath}:{$lineNumber}",
                        file: $filePath,
                        line: $lineNumber
                    );
                }
            }

            // Raw SQL DDL check
            if (preg_match('/DB::statement\s*\(\s*[\'"]\s*(DROP|TRUNCATE|ALTER\s+TABLE.*DROP)/i', $line, $matches)) {
                $violations[] = new MigrationSafetyViolation(
                    ruleName: 'UNSAFE_RAW_DDL',
                    severity: 'critical',
                    message: "Destructive raw DDL statement '{$matches[1]}' detected at {$filePath}:{$lineNumber}",
                    file: $filePath,
                    line: $lineNumber
                );
            }

            // Check non-nullable addColumn without default
            if (preg_match('/->(?:string|integer|bigInteger|boolean|text|json)\s*\(\s*[\'"][^\'"]+[\'"]\s*\)(?!.*->nullable\(\))(?!.*->default\()/i', $line)) {
                // If it's modifying an existing table (not create)
                if (str_contains($content, 'Schema::table(') && ! str_contains($line, 'nullable') && ! str_contains($line, 'default')) {
                    $violations[] = new MigrationSafetyViolation(
                        ruleName: 'NON_NULLABLE_COLUMN_WITHOUT_DEFAULT',
                        severity: 'medium',
                        message: "Column added without nullable() or default() in Schema::table at {$filePath}:{$lineNumber}",
                        file: $filePath,
                        line: $lineNumber
                    );
                }
            }
        }

        return $violations;
    }
}
