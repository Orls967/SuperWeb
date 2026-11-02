<?php

declare(strict_types=1);

namespace Modules\Asset\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Asset\Contracts\AssetRegistryLookupInterface;

class AssetRegistryLookupService implements AssetRegistryLookupInterface
{
    public function getAssetDetails(string $assetType, string $assetId): ?array
    {
        $asset = DB::table('ast_assets')
            ->where(function ($q) use ($assetId) {
                $q->where('id', $assetId)
                    ->orWhere('asset_code', $assetId)
                    ->orWhere('asset_number', $assetId);
            })
            ->first();

        if (! $asset) {
            return null;
        }

        return [
            'id' => (string) $asset->id,
            'name' => (string) ($asset->name ?? $asset->asset_name ?? $assetId),
            'status' => (string) ($asset->status ?? 'active'),
            'asset_type' => (string) ($asset->asset_type ?? $assetType),
        ];
    }
}
