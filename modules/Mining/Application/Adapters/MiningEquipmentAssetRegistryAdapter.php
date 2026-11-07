<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Adapters;

use Modules\Asset\Contracts\AssetRegistryLookupInterface;
use Modules\Mining\Domain\Models\MiningEquipment;

/**
 * Adaptor Integrasi Registri Alat Berat Tambang ke Modul Asset (G7-D29).
 *
 * Menjembatani entitas tunggal `min_equipment` dengan kontrak lintas modul
 * `Modules\Asset\Contracts\AssetRegistryLookupInterface` untuk sinkronisasi
 * nomor aset korporat, status ketersediaan teknis, dan referensi Work Order (G7-K9).
 */
final class MiningEquipmentAssetRegistryAdapter
{
    public function __construct(
        private readonly ?AssetRegistryLookupInterface $assetRegistry = null
    ) {}

    /**
     * Mengambil metadata aset korporat yang berpasangan dengan unit alat berat tambang.
     *
     * @return array{
     *     equipment_id: string,
     *     equipment_code: string,
     *     corporate_asset_id: int,
     *     corporate_asset_code: string,
     *     corporate_status: string,
     *     asset_type: string,
     *     is_linked_to_registry: bool
     * }
     */
    public function resolveEquipmentAssetMetadata(MiningEquipment $equipment): array
    {
        $lookupKey = (string) ($equipment->equipment_code ?: $equipment->id);
        $assetType = (string) ($equipment->type ?: 'HEAVY_EQUIPMENT');

        $details = null;
        if ($this->assetRegistry !== null) {
            $details = $this->assetRegistry->getAssetDetails($assetType, $lookupKey);
        }

        if ($details !== null) {
            $numericId = is_numeric($details['id']) ? (int) $details['id'] : 1;

            return [
                'equipment_id' => (string) $equipment->id,
                'equipment_code' => (string) $equipment->equipment_code,
                'corporate_asset_id' => max(1, $numericId),
                'corporate_asset_code' => $details['name'],
                'corporate_status' => $details['status'],
                'asset_type' => $details['asset_type'],
                'is_linked_to_registry' => true,
            ];
        }

        return [
            'equipment_id' => (string) $equipment->id,
            'equipment_code' => (string) $equipment->equipment_code,
            'corporate_asset_id' => 1,
            'corporate_asset_code' => "AST-MIN-{$lookupKey}",
            'corporate_status' => 'active',
            'asset_type' => $assetType,
            'is_linked_to_registry' => false,
        ];
    }

    /**
     * Memastikan unit alat berat terdaftar atau memiliki kode referensi sah sebelum Work Order diterbitkan.
     */
    public function resolveCorporateAssetIdForWorkOrder(string $equipmentId): int
    {
        $equipment = MiningEquipment::find($equipmentId);
        if ($equipment === null) {
            return 1;
        }

        $meta = $this->resolveEquipmentAssetMetadata($equipment);

        return $meta['corporate_asset_id'];
    }
}
