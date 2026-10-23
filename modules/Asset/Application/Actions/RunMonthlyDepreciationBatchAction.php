<?php

declare(strict_types=1);

namespace Modules\Asset\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Asset\Domain\Enums\AssetEventType;
use Modules\Asset\Domain\Enums\DepreciationMethod;
use Modules\Asset\Domain\Models\Asset;
use Modules\Asset\Domain\Models\AssetEvent;
use Modules\Asset\Domain\Models\Depreciation;
use Modules\Shared\Application\BaseAction;

class RunMonthlyDepreciationBatchAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): Depreciation
    {
        return DB::transaction(function () use ($params) {
            $asset = Asset::findOrFail($params['asset_id']);
            $period = (string) ($params['period'] ?? Carbon::now()->format('Y-m'));
            $usefulMonths = (int) ($params['useful_life_months'] ?? 60);

            // Compute depreciation amount
            $depreciableBase = max(0, ($asset->acquisition_cost_idr + $asset->landed_cost_idr) - $asset->salvage_value_idr);
            $amountIdr = (int) ($params['amount_idr'] ?? (int) round($depreciableBase / max(1, $usefulMonths)));

            // Cap at remaining book value above salvage
            $maxAllowable = max(0, $asset->book_value_idr - $asset->salvage_value_idr);
            $finalAmountIdr = min($amountIdr, $maxAllowable);

            $newAccumulated = $asset->accumulated_depreciation_idr + $finalAmountIdr;
            $asset->accumulated_depreciation_idr = $newAccumulated;
            $asset->recomputeBookValue();
            $asset->save();

            $depreciation = Depreciation::create([
                'asset_id' => $asset->id,
                'period' => $period,
                'method' => DepreciationMethod::StraightLine,
                'book' => 'commercial',
                'amount_idr' => $finalAmountIdr,
                'accumulated_after_idr' => $newAccumulated,
                'meta' => [
                    'useful_months' => $usefulMonths,
                    'depreciable_base' => $depreciableBase,
                ],
            ]);

            // Append AssetEvent to hash chain
            $lastEvent = AssetEvent::where('asset_id', $asset->id)->orderByDesc('sequence')->first();
            $prevHash = $lastEvent?->hash ?? RegisterFixedAssetAction::GENESIS_HASH;
            $nextSeq = ($lastEvent?->sequence ?? 0) + 1;
            $nowIso = Carbon::now()->toIso8601String();

            $payload = [
                'action' => 'monthly_depreciation',
                'period' => $period,
                'amount_idr' => $finalAmountIdr,
                'book_value_after_idr' => $asset->book_value_idr,
            ];
            $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR);

            $hash = AssetEvent::calculateHash($prevHash, $nextSeq, AssetEventType::Depreciation->value, $payloadJson, $nowIso);

            AssetEvent::create([
                'id' => (string) Str::uuid(),
                'asset_id' => $asset->id,
                'sequence' => $nextSeq,
                'event_type' => AssetEventType::Depreciation,
                'payload' => $payload,
                'prev_hash' => $prevHash,
                'hash' => $hash,
                'created_by_name' => (string) ($params['depreciated_by'] ?? 'Batch Depreciation Engine'),
                'occurred_at' => $nowIso,
            ]);

            $this->audit(
                action: 'asset_monthly_depreciated',
                auditable: $depreciation,
                newValues: ['period' => $period, 'amount_idr' => $finalAmountIdr, 'book_value_idr' => $asset->book_value_idr],
                impactType: 'asset_depreciation',
            );

            return $depreciation;
        });
    }
}
