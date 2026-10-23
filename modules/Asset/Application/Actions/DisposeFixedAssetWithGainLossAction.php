<?php

declare(strict_types=1);

namespace Modules\Asset\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Asset\Domain\Enums\AssetEventType;
use Modules\Asset\Domain\Enums\AssetStatus;
use Modules\Asset\Domain\Models\Asset;
use Modules\Asset\Domain\Models\AssetDisposal;
use Modules\Asset\Domain\Models\AssetEvent;
use Modules\Shared\Application\BaseAction;

class DisposeFixedAssetWithGainLossAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): AssetDisposal
    {
        return DB::transaction(function () use ($params) {
            $asset = Asset::findOrFail($params['asset_id']);
            $proceeds = (int) ($params['proceeds_idr'] ?? 0);
            $bookValueAtDisposal = $asset->book_value_idr;
            $gainLoss = $proceeds - $bookValueAtDisposal;
            $method = (string) ($params['method'] ?? 'sale');

            $disposal = AssetDisposal::create([
                'asset_id' => $asset->id,
                'method' => $method,
                'proceeds_idr' => $proceeds,
                'book_value_at_disposal_idr' => $bookValueAtDisposal,
                'gain_loss_idr' => $gainLoss,
                'reason' => (string) ($params['reason'] ?? 'End of asset lifecycle disposal'),
                'approval_status' => 'approved',
                'disposed_at' => Carbon::now(),
                'requested_by_user_id' => isset($params['requested_by_user_id']) ? (int) $params['requested_by_user_id'] : null,
            ]);

            $asset->update([
                'status' => AssetStatus::Disposed,
                'book_value_idr' => 0,
            ]);

            // Append AssetEvent
            $lastEvent = AssetEvent::where('asset_id', $asset->id)->orderByDesc('sequence')->first();
            $prevHash = $lastEvent?->hash ?? RegisterFixedAssetAction::GENESIS_HASH;
            $nextSeq = ($lastEvent?->sequence ?? 0) + 1;
            $nowIso = Carbon::now()->toIso8601String();

            $payload = [
                'action' => 'asset_disposed',
                'method' => $method,
                'proceeds_idr' => $proceeds,
                'book_value_at_disposal_idr' => $bookValueAtDisposal,
                'gain_loss_idr' => $gainLoss,
            ];
            $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR);

            $hash = AssetEvent::calculateHash($prevHash, $nextSeq, AssetEventType::Disposal->value, $payloadJson, $nowIso);

            AssetEvent::create([
                'id' => (string) Str::uuid(),
                'asset_id' => $asset->id,
                'sequence' => $nextSeq,
                'event_type' => AssetEventType::Disposal,
                'payload' => $payload,
                'prev_hash' => $prevHash,
                'hash' => $hash,
                'created_by_name' => (string) ($params['disposed_by'] ?? 'Asset Disposal Officer'),
                'occurred_at' => $nowIso,
            ]);

            $this->audit(
                action: 'asset_disposed_with_gain_loss',
                auditable: $disposal,
                newValues: ['proceeds' => $proceeds, 'book_value' => $bookValueAtDisposal, 'gain_loss' => $gainLoss],
                impactType: 'asset_disposal',
            );

            return $disposal;
        });
    }
}
