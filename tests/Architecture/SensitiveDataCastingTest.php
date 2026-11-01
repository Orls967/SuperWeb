<?php

declare(strict_types=1);

use App\Quality\Support\SourceFinder;
use Tests\TestCase;

/*
| PROGRESS R3.2 / B5 (KONSEP §A13, K-22):
| Architecture test enforcing that every Eloquent model declaring sensitive data
| columns (NIK, NPWP, secret, api_key, *_encrypted) must explicitly declare
| an 'encrypted' or 'hashed' cast.
*/

uses(TestCase::class);

/**
 * Identify whether a column name indicates a raw sensitive value requiring encryption/hashing.
 */
function isRawSensitiveColumn(string $column): bool
{
    $col = strtolower(trim($column, " \t\n\r\0\x0B'\""));

    // Exclude hashes, blind indexes, masked representations, IDs, and booleans
    if (str_ends_with($col, '_bidx')
        || str_ends_with($col, '_hash')
        || str_ends_with($col, '_masked')
        || str_ends_with($col, '_id')
        || str_starts_with($col, 'is_')
        || str_starts_with($col, 'has_')) {
        return false;
    }

    // Match raw sensitive identifiers
    return preg_match('/(?:^|_)(nik|npwp)(?:_|$)|(?:^|_)secret(?:_|$)|(?:^|_)api_key(?:_|$)|_encrypted(?:$|_)/i', $col) === 1;
}

/**
 * Scan a PHP source content or file for Eloquent model sensitive casting violations.
 *
 * @return list<array{model: string, column: string, reason: string}>
 */
function scanModelSensitiveCasting(string $content, string $filePath = 'MemoryModel.php'): array
{
    // Must be a class extending Model
    if (! preg_match('/class\s+([A-Za-z0-9_]+)\s+extends\s+([A-Za-z0-9_\\\\]*Model[A-Za-z0-9_\\\\]*)/i', $content, $classMatches)) {
        return [];
    }

    $className = $classMatches[1];

    // Extract fillable columns
    $fillable = [];
    if (preg_match('/(?:protected|public)\s+\$fillable\s*=\s*\[(.*?)\];/s', $content, $fillableMatches)) {
        preg_match_all('/[\'"]([^\'"]+)[\'"]/', $fillableMatches[1], $colMatches);
        $fillable = $colMatches[1] ?? [];
    }

    // Extract casts (from property or casts() method)
    $casts = [];
    if (preg_match('/(?:protected|public)\s+\$casts\s*=\s*\[(.*?)\];/s', $content, $castsPropMatches)) {
        preg_match_all('/[\'"]([^\'"]+)[\'"]\s*=>\s*[\'"]?([^\'",\]\s]+)[\'"]?/', $castsPropMatches[1], $castMatches, PREG_SET_ORDER);
        foreach ($castMatches as $m) {
            $casts[$m[1]] = strtolower(trim($m[2], " \t\n\r\0\x0B'\""));
        }
    }

    if (preg_match('/function\s+casts\s*\(\)\s*:\s*array\s*\{(.*?)\}/s', $content, $castsMethodMatches)) {
        preg_match_all('/[\'"]([^\'"]+)[\'"]\s*=>\s*[\'"]?([^\'",\]\s]+)[\'"]?/', $castsMethodMatches[1], $castMatches, PREG_SET_ORDER);
        foreach ($castMatches as $m) {
            $casts[$m[1]] = strtolower(trim($m[2], " \t\n\r\0\x0B'\""));
        }
    }

    $violations = [];

    foreach ($fillable as $column) {
        if (isRawSensitiveColumn($column)) {
            $cast = $casts[$column] ?? null;
            if ($cast !== 'encrypted' && $cast !== 'hashed') {
                $violations[] = [
                    'model' => $className,
                    'column' => $column,
                    'file' => $filePath,
                    'reason' => "Sensitive column '{$column}' in model {$className} lacks 'encrypted' or 'hashed' cast (found: ".($cast ?? 'none').').',
                ];
            }
        }
    }

    return $violations;
}

it('enforces that every Eloquent model with sensitive columns declares encrypted or hashed cast', function (): void {
    gc_collect_cycles();
    $root = dirname(__DIR__, 2);
    $files = SourceFinder::phpFiles($root, ['app/Models', 'modules'], ['#(?:^|/)modules/[^/]+/tests/.*#']);

    $violations = [];

    foreach ($files as $file) {
        $fileViolations = scanModelSensitiveCasting($file->contents, $file->relativePath);
        if ($fileViolations !== []) {
            $violations = array_merge($violations, $fileViolations);
        }
    }

    unset($files);
    gc_collect_cycles();

    expect($violations)->toBe([], 'Semua model Eloquent dengan kolom sensitif wajib memiliki cast encrypted atau hashed.');
});

it('correctly catches negative fixtures and passes compliant models', function (): void {
    // Compliant model fixture
    $compliantModel = <<<'PHP'
    <?php
    namespace Modules\Test\Domain\Models;

    use Illuminate\Database\Eloquent\Model;

    class CompliantEmployee extends Model
    {
        protected $fillable = [
            'name',
            'nik',
            'nik_bidx',
            'npwp',
            'npwp_bidx',
            'secret_key',
            'formula_payload_encrypted',
        ];

        protected $casts = [
            'nik' => 'encrypted',
            'npwp' => 'encrypted',
            'secret_key' => 'encrypted',
            'formula_payload_encrypted' => 'encrypted',
        ];
    }
    PHP;

    expect(scanModelSensitiveCasting($compliantModel))->toBe([]);

    // Negative fixture 1: Uncast NIK
    $negativeModel1 = <<<'PHP'
    <?php
    namespace Modules\Test\Domain\Models;

    use Illuminate\Database\Eloquent\Model;

    class InsecureEmployee extends Model
    {
        protected $fillable = [
            'name',
            'nik',
        ];

        protected $casts = [
            'name' => 'string',
        ];
    }
    PHP;

    $violations1 = scanModelSensitiveCasting($negativeModel1);
    expect($violations1)->toHaveCount(1)
        ->and($violations1[0]['column'])->toBe('nik');

    // Negative fixture 2: Uncast NPWP and Secret
    $negativeModel2 = <<<'PHP'
    <?php
    namespace Modules\Test\Domain\Models;

    use Illuminate\Database\Eloquent\Model;

    class InsecureTenant extends Model
    {
        protected $fillable = [
            'brand_name',
            'npwp',
            'secret_token',
        ];
    }
    PHP;

    $violations2 = scanModelSensitiveCasting($negativeModel2);
    expect($violations2)->toHaveCount(2);

    // Negative fixture 3: Uncast formula_payload_encrypted
    $negativeModel3 = <<<'PHP'
    <?php
    namespace Modules\Test\Domain\Models;

    use Illuminate\Database\Eloquent\Model;

    class InsecureLabNotebook extends Model
    {
        protected $fillable = [
            'title',
            'formula_payload_encrypted',
        ];

        protected function casts(): array
        {
            return [
                'title' => 'string',
            ];
        }
    }
    PHP;

    $violations3 = scanModelSensitiveCasting($negativeModel3);
    expect($violations3)->toHaveCount(1)
        ->and($violations3[0]['column'])->toBe('formula_payload_encrypted');
});
