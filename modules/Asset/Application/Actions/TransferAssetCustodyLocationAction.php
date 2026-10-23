<?php

declare(strict_types=1);

namespace Modules\Asset\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Asset\Domain\Enums\AssetEventType;
use Modules\Asset\Domain\Models\Asset;
use Modules\Asset\Domain\Models\AssetEvent;
use Modules\Shared\Application\BaseAction;

class TransferAssetCustodyLocationAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params) {
            $asset = Asset::findOrFail($params['asset_id']);
            $oldLocationId = $asset->location_id;
            $oldUserId = $asset->responsible_user_id;

            $newLocationId = isset($params['new_location_id']) ? (int) $params['new_location_id'] : $oldLocationId;
            $newUserId = isset($params['new_responsible_user_id']) ? (int) $params['new_responsible_user_id'] : $oldUserId;

            $asset->update([
                'location_id' => $newLocationId,
                'responsible_user_id' => $newUserId,
            ]);

            // Append AssetEvent
            $lastEvent = AssetEvent::where('asset_id', $asset->id)->orderByDesc('sequence')->first();
            $prevHash = $lastEvent?->hash ?? RegisterFixedAssetAction::GENESIS_HASH;
            $nextSeq = ($lastEvent?->sequence ?? 0) + 1;
            $nowIso = Carbon::now()->toIso8601String();

            $payload = [
                'action' => 'custody_location_transferred',
                'old_location_id' => $oldLocationId,
                'new_location_id' => $newLocationId,
                'old_responsible_user_id' => $oldUserId,
                'new_responsible_user_id' => $newUserId,
                'transfer_reason' => (string) ($params['reason'] ?? 'Inter-departmental asset reassignment'),
            ];
            $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR);

            $hash = AssetEvent::calculateHash($prevHash, $nextSeq, AssetEventType::Move->value, $payloadJson, $nowIso);

            AssetEvent::create([
                'id' => (string) Str::uuid(),
                'asset_id' => $asset->id,
                'sequence' => $nextSeq,
                'event_type' => AssetEventType::Move,
                'payload' => $payload,
                'prev_hash' => $prevHash,
                'hash' => $hash,
                'created_by_name' => (string) ($params['authorized_by'] ?? 'Asset Logistics Manager'),
                'occurred_at' => $nowIso,
            ]);

            $this->audit(
                action: 'asset_custody_transferred',
                auditable: $asset,
                newValues: ['new_location' => $newLocationId, 'new_custodian' => $newUserId],
                impactType: 'asset_custody',
            );

            return [
                'asset_id' => $asset->id,
                'asset_number' => $asset->asset_number,
                'old_location_id' => $oldLocationId,
                'new_location_id' => $newLocationId,
                'old_responsible_user_id' => $oldUserId,
                'new_responsible_user_id' => $newUserId,
                'event_hash' => $hash,
            ];
        });
    }
}
