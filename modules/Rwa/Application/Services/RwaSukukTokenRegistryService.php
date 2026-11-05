<?php

declare(strict_types=1);

namespace Modules\Rwa\Application\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\Rwa\Contracts\SukukTokenRegistry;
use Modules\Rwa\Domain\Models\RwaAsset;

final class RwaSukukTokenRegistryService implements SukukTokenRegistry
{
    public function issue(string $assetCode, int $faceValueIdr, int $units, CarbonImmutable $maturity, string $idempotencyKey): int
    {
        $code = trim($assetCode);
        if ($code === '') {
            throw new InvalidArgumentException('Kode aset sukuk tidak boleh kosong.');
        }
        if ($faceValueIdr <= 0 || $units <= 0) {
            throw new InvalidArgumentException('Nilai nominal dan unit sukuk harus bernilai positif.');
        }

        $existing = RwaAsset::query()->where('token_symbol', $code)->first();
        if ($existing !== null) {
            return (int) $existing->id;
        }

        $unitPrice = (int) intdiv($faceValueIdr, $units);
        $asset = RwaAsset::create([
            'token_symbol' => $code,
            'name' => "Sukuk Ijarah RWA {$code}",
            'underlying_asset_type' => 'SUKUK_IJARAH',
            'underlying_asset_id' => 1,
            'appraisal_document_ref' => "SUKUK-DOC-{$code}",
            'appraisal_value_idr' => $faceValueIdr,
            'total_supply_tokens' => $units,
            'token_price_idr' => $unitPrice,
            'status' => 'active',
            'approved_at' => now(),
        ]);

        return (int) $asset->id;
    }

    public function redeem(string $assetCode, CarbonImmutable $at, string $idempotencyKey): void
    {
        $code = trim($assetCode);
        if ($code === '') {
            throw new InvalidArgumentException('Kode aset sukuk tidak boleh kosong.');
        }

        $asset = RwaAsset::query()->where('token_symbol', $code)->firstOrFail();
        $asset->forceFill([
            'status' => 'redeemed',
            'token_price_idr' => 0,
        ])->save();
    }
}
