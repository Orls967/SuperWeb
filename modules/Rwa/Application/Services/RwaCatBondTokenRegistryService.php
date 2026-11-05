<?php

declare(strict_types=1);

namespace Modules\Rwa\Application\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\Rwa\Contracts\CatBondTokenRegistry;
use Modules\Rwa\Domain\Models\RwaAsset;

final class RwaCatBondTokenRegistryService implements CatBondTokenRegistry
{
    public function registerBond(string $assetCode, int $principalIdr, int $units, string $idempotencyKey): int
    {
        if (trim($assetCode) === '') {
            throw new InvalidArgumentException('Kode aset cat bond tidak boleh kosong.');
        }
        if ($principalIdr <= 0 || $units <= 0) {
            throw new InvalidArgumentException('Pokok dan unit cat bond harus bernilai positif.');
        }

        $existing = RwaAsset::query()->where('token_symbol', $assetCode)->first();
        if ($existing !== null) {
            return (int) $existing->id;
        }

        $price = (int) intdiv($principalIdr, $units);
        $asset = RwaAsset::create([
            'token_symbol' => $assetCode,
            'name' => "Catastrophe Bond {$assetCode}",
            'underlying_asset_type' => 'INSURANCE_CAT_BOND',
            'underlying_asset_id' => 1,
            'appraisal_document_ref' => "CATBOND-DOC-{$assetCode}",
            'appraisal_value_idr' => $principalIdr,
            'total_supply_tokens' => $units,
            'token_price_idr' => $price,
            'status' => 'active',
            'approved_at' => now(),
        ]);

        return (int) $asset->id;
    }

    public function writeDown(string $assetCode, int $principalReductionIdr, CarbonImmutable $at, string $idempotencyKey): void
    {
        if ($principalReductionIdr <= 0) {
            return;
        }

        $asset = RwaAsset::query()->where('token_symbol', $assetCode)->firstOrFail();
        $newAppraisal = max(0, (int) $asset->appraisal_value_idr - $principalReductionIdr);
        $newPrice = (int) intdiv($newAppraisal, max(1, (int) $asset->total_supply_tokens));

        $asset->forceFill([
            'appraisal_value_idr' => $newAppraisal,
            'token_price_idr' => $newPrice,
        ])->save();
    }
}
