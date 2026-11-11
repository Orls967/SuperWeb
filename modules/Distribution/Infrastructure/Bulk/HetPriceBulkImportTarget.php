<?php

declare(strict_types=1);

namespace Modules\Distribution\Infrastructure\Bulk;

use Modules\Core\Contracts\BulkImportTarget;
use Modules\Distribution\Domain\Models\HetPrice;

class HetPriceBulkImportTarget implements BulkImportTarget
{
    public function targetKey(): string
    {
        return 'het-prices';
    }

    public function moduleName(): string
    {
        return 'Distribution';
    }

    public function label(): string
    {
        return 'Harga Eceran Tertinggi (HET)';
    }

    public function requiredColumns(): array
    {
        return ['sku', 'het_idr', 'valid_from', 'valid_until'];
    }

    public function validateRow(array $row, int $lineNumber): array
    {
        $sku = trim($row['sku'] ?? '');
        $het = (int) ($row['het_idr'] ?? 0);
        $from = trim($row['valid_from'] ?? '');
        $until = trim($row['valid_until'] ?? '');

        if ($sku === '') {
            return ['valid' => false, 'reason' => "Baris {$lineNumber}: SKU wajib diisi.", 'normalized' => []];
        }

        if ($het <= 0) {
            return ['valid' => false, 'reason' => "Baris {$lineNumber}: Nilai HET harus lebih besar dari 0.", 'normalized' => []];
        }

        if ($from === '') {
            return ['valid' => false, 'reason' => "Baris {$lineNumber}: Tanggal berlaku mulai wajib diisi.", 'normalized' => []];
        }

        return [
            'valid' => true,
            'reason' => null,
            'normalized' => [
                'sku' => $sku,
                'het_idr' => $het,
                'valid_from' => $from,
                'valid_until' => $until !== '' ? $until : null,
            ],
        ];
    }

    public function applyBatch(array $normalizedRows, string $idempotencyKey, string $tenantId): int
    {
        $count = 0;
        foreach ($normalizedRows as $row) {
            HetPrice::updateOrCreate(
                ['sku' => $row['sku'], 'valid_from' => $row['valid_from']],
                $row
            );
            $count++;
        }

        return $count;
    }

    public function exportRows(int $maxRows = 50000): iterable
    {
        $prices = HetPrice::limit($maxRows)->get();
        foreach ($prices as $p) {
            yield [
                'sku' => $p->sku,
                'het_idr' => $p->het_idr,
                'valid_from' => $p->valid_from?->toDateString() ?? '',
                'valid_until' => $p->valid_until?->toDateString() ?? '',
            ];
        }
    }
}
