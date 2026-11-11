<?php

declare(strict_types=1);

namespace Modules\Trade\Infrastructure\Bulk;

use Modules\Core\Contracts\BulkImportTarget;
use Modules\Trade\Domain\Models\HsCode;

class HsCodeBulkImportTarget implements BulkImportTarget
{
    public function targetKey(): string
    {
        return 'hs-codes';
    }

    public function moduleName(): string
    {
        return 'Trade';
    }

    public function label(): string
    {
        return 'Harmonized System (HS) Codes';
    }

    public function requiredColumns(): array
    {
        return ['hs_code', 'description', 'base_duty_rate_percent', 'fta_preferential_rate_percent', 'is_lartas'];
    }

    public function validateRow(array $row, int $lineNumber): array
    {
        $code = trim($row['hs_code'] ?? '');
        $desc = trim($row['description'] ?? '');

        if ($code === '') {
            return ['valid' => false, 'reason' => "Baris {$lineNumber}: Kode HS wajib diisi.", 'normalized' => []];
        }

        if ($desc === '') {
            return ['valid' => false, 'reason' => "Baris {$lineNumber}: Deskripsi pos tarif wajib diisi.", 'normalized' => []];
        }

        return [
            'valid' => true,
            'reason' => null,
            'normalized' => [
                'hs_code' => $code,
                'description' => $desc,
                'base_duty_rate_percent' => (float) ($row['base_duty_rate_percent'] ?? 0.0),
                'fta_preferential_rate_percent' => (float) ($row['fta_preferential_rate_percent'] ?? 0.0),
                'is_lartas' => in_array(strtolower(trim($row['is_lartas'] ?? '')), ['1', 'true', 'yes'], true),
                'lartas_permit_required' => null,
            ],
        ];
    }

    public function applyBatch(array $normalizedRows, string $idempotencyKey, string $tenantId): int
    {
        $count = 0;
        foreach ($normalizedRows as $row) {
            HsCode::updateOrCreate(
                ['hs_code' => $row['hs_code']],
                $row
            );
            $count++;
        }

        return $count;
    }

    public function exportRows(int $maxRows = 50000): iterable
    {
        $codes = HsCode::limit($maxRows)->get();
        foreach ($codes as $c) {
            yield [
                'hs_code' => $c->hs_code,
                'description' => $c->description,
                'base_duty_rate_percent' => $c->base_duty_rate_percent,
                'fta_preferential_rate_percent' => $c->fta_preferential_rate_percent,
                'is_lartas' => $c->is_lartas ? '1' : '0',
            ];
        }
    }
}
