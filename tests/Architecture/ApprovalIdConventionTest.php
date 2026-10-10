<?php

declare(strict_types=1);

use Tests\TestCase;

/*
| PROGRESS R0.3.b (K-B03, BLOCKERS B-03, KNOWLEDGE K-40):
| Pagar permanen yang menolak penulisan ->uuid ke atribut approval_id.
| Kolom approval_id menyimpan core_approvals.id (bigint), bukan UUID.
| Baseline aturan baru = 0.
*/

uses(TestCase::class);

/**
 * Scan source string or file for prohibited writing of ->uuid to approval_id.
 *
 * @return list<array{line: int, snippet: string}>
 */
function scanApprovalIdUuidViolations(string $content): array
{
    $lines = explode("\n", $content);
    $violations = [];

    // Pattern matches:
    // 1) 'approval_id' => ...->uuid
    // 2) "approval_id" => ...->uuid
    // 3) approval_id = ...->uuid
    // 4) $obj->approval_id = ...->uuid
    $pattern = '/(?:[\'"]?approval_id[\'"]?\s*(?:=>|=)\s*[^;\n]*->uuid\b)|(?:\$[a-zA-Z0-9_]+->approval_id\s*=\s*[^;\n]*->uuid\b)/i';

    foreach ($lines as $index => $line) {
        $trimmed = trim($line);
        // Skip pure comments
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '#') || str_starts_with($trimmed, '*')) {
            continue;
        }

        if (preg_match($pattern, $line, $matches)) {
            $violations[] = [
                'line' => $index + 1,
                'snippet' => $trimmed,
            ];
        }
    }

    return $violations;
}

it('enforces that codebase never assigns ->uuid to approval_id', function (): void {
    $directories = [
        app_path(),
        base_path('modules'),
    ];

    $violations = [];

    foreach ($directories as $dir) {
        if (! is_dir($dir)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                $fileViolations = scanApprovalIdUuidViolations($content);

                foreach ($fileViolations as $v) {
                    $violations[] = [
                        'file' => str_replace(base_path().'/', '', $file->getPathname()),
                        'line' => $v['line'],
                        'snippet' => $v['snippet'],
                    ];
                }
            }
        }
    }

    // Baseline aturan baru = 0
    expect($violations)->toBe([], 'Dilarang menulis ->uuid ke atribut approval_id. approval_id wajib menyimpan core_approvals.id (bigint).');
});

it('correctly flags negative fixtures and passes positive fixtures', function (): void {
    // Fixture positif (harus lolos/0 pelanggaran)
    $positiveFixture = <<<'PHP'
    <?php
    $contract->update([
        'approval_id' => $approval->id,
        'status' => 'approved',
    ]);
    $contract->approval_id = $approval->id;
    $record['approval_id'] = 12345;
    $table->foreignId('approval_id')->nullable();
    // 'approval_id' => $approval->uuid, (in comment)
    PHP;

    expect(scanApprovalIdUuidViolations($positiveFixture))->toBe([]);

    // Fixture negatif (harus tertangkap)
    $negativeFixture1 = <<<'PHP'
    <?php
    $contract->update([
        'approval_id' => $approval->uuid,
    ]);
    PHP;

    $negativeFixture2 = <<<'PHP'
    <?php
    $model->approval_id = $approval->uuid;
    PHP;

    $negativeFixture3 = <<<'PHP'
    <?php
    $data = ['approval_id' => $someObject->uuid];
    PHP;

    expect(scanApprovalIdUuidViolations($negativeFixture1))->toHaveCount(1)
        ->and(scanApprovalIdUuidViolations($negativeFixture2))->toHaveCount(1)
        ->and(scanApprovalIdUuidViolations($negativeFixture3))->toHaveCount(1);
});
