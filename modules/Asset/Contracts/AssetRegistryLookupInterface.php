<?php

declare(strict_types=1);

namespace Modules\Asset\Contracts;

interface AssetRegistryLookupInterface
{
    /**
     * @return array{id: string, name: string, status: string, asset_type: string}|null
     */
    public function getAssetDetails(string $assetType, string $assetId): ?array;
}
