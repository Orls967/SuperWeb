<?php

declare(strict_types=1);

namespace Modules\Rwa\Application\Actions;

use Modules\Rwa\Domain\Models\RwaAsset;
use Modules\Shared\Application\BaseAction;

class TokenizeRealWorldAssetSupplyAction extends BaseAction
{
    /**
     * Tokenize a real-world physical or yield-generating asset into digital security tokens.
     *
     * @return array{asset: RwaAsset, token_price_idr: int, total_supply: int}
     */
    public function handle(
        string $tokenSymbol,
        string $name,
        string $underlyingType,
        string $underlyingId,
        string $appraisalDocRef,
        int $appraisalValueIdr,
        int $totalSupplyTokens,
        float $maxHolderConcentrationPct = 20.0
    ): array {
        return $this->transaction(function () use (
            $tokenSymbol,
            $name,
            $underlyingType,
            $underlyingId,
            $appraisalDocRef,
            $appraisalValueIdr,
            $totalSupplyTokens,
            $maxHolderConcentrationPct
        ) {
            $tokenPriceIdr = intdiv($appraisalValueIdr, max(1, $totalSupplyTokens));

            $idempotencyKey = hash('sha256', "rwa:tokenize:{$tokenSymbol}:{$underlyingId}:{$appraisalValueIdr}");

            $asset = RwaAsset::create([
                'token_symbol' => strtoupper(trim($tokenSymbol)),
                'name' => $name,
                'underlying_asset_type' => $underlyingType,
                'underlying_asset_id' => $underlyingId,
                'appraisal_document_ref' => $appraisalDocRef,
                'appraisal_value_idr' => $appraisalValueIdr,
                'total_supply_tokens' => $totalSupplyTokens,
                'token_price_idr' => $tokenPriceIdr,
                'max_holder_concentration_pct' => $maxHolderConcentrationPct,
                'status' => 'active',
                'approved_at' => now(),
            ]);

            $this->audit(
                action: 'rwa.asset.tokenized',
                auditable: $asset,
                oldValues: null,
                newValues: [
                    'token_symbol' => $asset->token_symbol,
                    'appraisal_value_idr' => $appraisalValueIdr,
                    'total_supply_tokens' => $totalSupplyTokens,
                    'token_price_idr' => $tokenPriceIdr,
                ],
                correlationId: "rwa_tok_{$tokenSymbol}",
                impactType: 'rwa_issuance'
            );

            $this->outbox(
                eventType: 'RwaAssetTokenized',
                payload: [
                    'asset_id' => $asset->id,
                    'token_symbol' => $asset->token_symbol,
                    'appraisal_value_idr' => $appraisalValueIdr,
                    'total_supply_tokens' => $totalSupplyTokens,
                    'token_price_idr' => $tokenPriceIdr,
                ],
                idempotencyKey: $idempotencyKey
            );

            return [
                'asset' => $asset,
                'token_price_idr' => $tokenPriceIdr,
                'total_supply' => $totalSupplyTokens,
            ];
        });
    }
}
