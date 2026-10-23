<?php

declare(strict_types=1);

namespace Modules\Asset\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Asset\Domain\Enums\AssetEventType;
use Modules\Asset\Domain\Models\Asset;
use Modules\Asset\Domain\Models\AssetEvent;
use Modules\Asset\Domain\Models\AssetRevaluation;
use Modules\Shared\Application\BaseAction;

class RevalueAssetCarryingAmountAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): AssetRevaluation
    {
        return DB::transaction(function () use ($params) {
            $asset = Asset::findOrFail($params['asset_id']);
            $newVal = (int) $params['new_value_idr'];
            $oldVal = $asset->book_value_idr;
            $diff = $newVal - $oldVal;

            $revaluation = AssetRevaluation::create([
                'asset_id' => $asset->id,
                'kind' => $diff >= 0 ? 'upward' : 'downward',
                'old_value_idr' => $oldVal,
                'new_value_idr' => $newVal,
                'difference_idr' => $diff,
                'reason' => (string) ($params['reason'] ?? 'Periodic appraisal revaluation'),
                'approval_status' => 'approved',
                'requested_by_user_id' => isset($params['requested_by_user_id']) ? (int) $params['requested_by_user_id'] : null,
            ]);

            $asset->book_value_idr = $newVal;
            $asset->save();

            // Append AssetEvent
            $lastEvent = AssetEvent::where('asset_id', $asset->id)->orderByDesc('sequence')->first();
            $prevHash = $lastEvent?->hash ?? RegisterFixedAssetAction::GENESIS_HASH;
            $nextSeq = ($lastEvent?->sequence ?? 0) + 1;
            $nowIso = Carbon::now()->toIso8601String();

            $payload = [
                'action' => 'carrying_amount_revalued',
                'old_value_idr' => $oldVal,
                'new_value_idr' => $newVal,
                'difference_idr' => $diff,
            ];
            $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR);

            $hash = AssetEvent::calculateHash($prevHash, $nextSeq, AssetEventType::Revaluation->value, $payloadJson, $nowIso);

            AssetEvent::create([
                'id' => (string) Str::uuid(),
                'asset_id' => $asset->id,
                'sequence' => $nextSeq,
                'event_type' => AssetEventType::Revaluation,
                'payload' => $payload,
                'prev_hash' => $prevHash,
                'hash' => $hash,
                'created_by_name' => (string) ($params['appraiser_name'] ?? 'Certified Asset Appraiser'),
                'occurred_at' => $nowIso,
            ]);

            $this->audit(
                action: 'asset_carrying_value_revalued',
                auditable: $revaluation,
                newValues: ['old_val' => $oldVal, 'new_val' => $newVal, 'diff' => $diff],
                impactType: 'asset_revaluation',
            );

            return $revaluation;
        });
    }
}
