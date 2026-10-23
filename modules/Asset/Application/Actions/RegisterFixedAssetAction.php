<?php

declare(strict_types=1);

namespace Modules\Asset\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Asset\Domain\Enums\AssetEventType;
use Modules\Asset\Domain\Enums\AssetStatus;
use Modules\Asset\Domain\Models\Asset;
use Modules\Asset\Domain\Models\AssetEvent;
use Modules\Shared\Application\BaseAction;

class RegisterFixedAssetAction extends BaseAction
{
    public const GENESIS_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): Asset
    {
        return DB::transaction(function () use ($params) {
            $cost = (int) $params['acquisition_cost_idr'];
            $landedCost = (int) ($params['landed_cost_idr'] ?? 0);
            $salvage = (int) ($params['salvage_value_idr'] ?? 0);
            $assetNumber = (string) ($params['asset_number'] ?? 'AST-'.strtoupper(Str::random(8)));
            $assetTag = (string) ($params['asset_tag'] ?? 'TAG-'.strtoupper(Str::random(6)));

            $asset = Asset::create([
                'id' => (string) Str::uuid(),
                'asset_number' => $assetNumber,
                'asset_tag' => $assetTag,
                'name' => (string) $params['name'],
                'description' => (string) ($params['description'] ?? 'Fixed Asset Registration'),
                'category_id' => isset($params['category_id']) ? (int) $params['category_id'] : null,
                'location_id' => isset($params['location_id']) ? (int) $params['location_id'] : null,
                'legal_entity_id' => $params['legal_entity_id'] ?? null,
                'responsible_user_id' => isset($params['responsible_user_id']) ? (int) $params['responsible_user_id'] : null,
                'condition' => (string) ($params['condition'] ?? 'good'),
                'status' => AssetStatus::InUse,
                'brand' => (string) ($params['brand'] ?? 'OEM'),
                'serial_number' => (string) ($params['serial_number'] ?? strtoupper(Str::random(10))),
                'acquired_at' => $params['acquired_at'] ?? Carbon::now()->toDateString(),
                'in_service_at' => $params['in_service_at'] ?? Carbon::now()->toDateString(),
                'acquisition_cost_idr' => $cost,
                'landed_cost_idr' => $landedCost,
                'accumulated_depreciation_idr' => 0,
                'salvage_value_idr' => $salvage,
                'book_value_idr' => $cost + $landedCost,
            ]);

            $nowIso = Carbon::now()->toIso8601String();
            $payload = [
                'action' => 'genesis_registration',
                'asset_number' => $asset->asset_number,
                'cost_idr' => $cost,
                'landed_cost_idr' => $landedCost,
            ];
            $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR);

            $hash = AssetEvent::calculateHash(
                self::GENESIS_HASH,
                1,
                AssetEventType::Acquisition->value,
                $payloadJson,
                $nowIso
            );

            AssetEvent::create([
                'id' => (string) Str::uuid(),
                'asset_id' => $asset->id,
                'sequence' => 1,
                'event_type' => AssetEventType::Acquisition,
                'payload' => $payload,
                'prev_hash' => self::GENESIS_HASH,
                'hash' => $hash,
                'created_by_name' => (string) ($params['registered_by'] ?? 'System Asset Registrar'),
                'occurred_at' => $nowIso,
            ]);

            $this->audit(
                action: 'fixed_asset_registered',
                auditable: $asset,
                newValues: ['asset_number' => $asset->asset_number, 'cost_idr' => $cost],
                impactType: 'asset_management',
            );

            return $asset;
        });
    }
}
