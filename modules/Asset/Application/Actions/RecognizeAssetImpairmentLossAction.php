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

class RecognizeAssetImpairmentLossAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): AssetRevaluation
    {
        return DB::transaction(function () use ($params) {
            $asset = Asset::findOrFail($params['asset_id']);
            $recoverableAmount = (int) $params['recoverable_amount_idr'];
            $oldVal = $asset->book_value_idr;

            if ($recoverableAmount >= $oldVal) {
                throw new \InvalidArgumentException('Nilai terpulihkan harus lebih kecil dari nilai buku saat ini untuk impairment.');
            }

            $impairmentLoss = $oldVal - $recoverableAmount;

            $revaluation = AssetRevaluation::create([
                'asset_id' => $asset->id,
                'kind' => 'impairment',
                'old_value_idr' => $oldVal,
                'new_value_idr' => $recoverableAmount,
                'difference_idr' => -$impairmentLoss,
                'reason' => (string) ($params['reason'] ?? 'Asset impairment test recognition'),
                'approval_status' => 'approved',
                'requested_by_user_id' => isset($params['requested_by_user_id']) ? (int) $params['requested_by_user_id'] : null,
            ]);

            $asset->book_value_idr = $recoverableAmount;
            $asset->save();

            // Append AssetEvent
            $lastEvent = AssetEvent::where('asset_id', $asset->id)->orderByDesc('sequence')->first();
            $prevHash = $lastEvent?->hash ?? RegisterFixedAssetAction::GENESIS_HASH;
            $nextSeq = ($lastEvent?->sequence ?? 0) + 1;
            $nowIso = Carbon::now()->toIso8601String();

            $payload = [
                'action' => 'impairment_loss_recognized',
                'impairment_loss_idr' => $impairmentLoss,
                'carrying_value_after_idr' => $recoverableAmount,
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
                'created_by_name' => (string) ($params['assessor_name'] ?? 'Financial Risk Auditor'),
                'occurred_at' => $nowIso,
            ]);

            $this->audit(
                action: 'asset_impairment_loss_recognized',
                auditable: $revaluation,
                newValues: ['loss_idr' => $impairmentLoss, 'new_book_value' => $recoverableAmount],
                impactType: 'asset_impairment',
            );

            return $revaluation;
        });
    }
}
