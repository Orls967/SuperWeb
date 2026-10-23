<?php

declare(strict_types=1);

namespace Modules\Rwa\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Rwa\Domain\Models\RwaAsset;
use Modules\Rwa\Domain\Models\RwaCorporateAction;
use Modules\Rwa\Domain\Models\RwaHolding;

class RwaCorporateActionService
{
    public const GENESIS_PREFIX = 'GENESIS:RWA:CORPO:';

    public function __construct(
        protected SimClockInterface $simClock,
        protected LedgerService $ledgerService
    ) {}

    public function executePartialRedemption(
        RwaAsset $asset,
        int $tokensToRedeem,
        int $redemptionProceedsPoolIdr
    ): RwaCorporateAction {
        return DB::transaction(function () use ($asset, $tokensToRedeem, $redemptionProceedsPoolIdr) {
            if ($tokensToRedeem <= 0 || $tokensToRedeem >= $asset->total_supply_tokens) {
                throw new \InvalidArgumentException('Tokens to redeem must be greater than zero and less than total supply.');
            }

            $payoutPerToken = (int) intdiv($redemptionProceedsPoolIdr, $tokensToRedeem);
            $holdings = RwaHolding::where('rwa_asset_id', $asset->id)
                ->where('token_balance', '>', 0)
                ->lockForUpdate()
                ->get();

            $totalCirculating = $holdings->sum('token_balance');
            $redeemedTotal = 0;
            $distributedProceeds = 0;
            $entries = [];

            foreach ($holdings as $holding) {
                $proRataRedeem = (int) intdiv($tokensToRedeem * $holding->token_balance, $totalCirculating);
                if ($proRataRedeem > 0) {
                    $userPayout = $proRataRedeem * $payoutPerToken;
                    $holding->token_balance -= $proRataRedeem;
                    $holding->save();

                    $redeemedTotal += $proRataRedeem;
                    $distributedProceeds += $userPayout;

                    $entries[] = PostingEntryDTO::forCode("wallet:user:{$holding->user_id}:IDR", 'IDR', $userPayout);
                }
            }

            $asset->total_supply_tokens -= $redeemedTotal;
            $asset->save();

            // Hash-chain record
            $lastAction = RwaCorporateAction::where('rwa_asset_id', $asset->id)
                ->orderByDesc('id')
                ->first();

            $prevHash = $lastAction ? $lastAction->block_hash : hash('sha256', self::GENESIS_PREFIX.$asset->id);
            $executedAt = $this->simClock->now();

            $payload = implode('|', [
                $prevHash,
                $asset->id,
                'redemption',
                $redeemedTotal,
                $payoutPerToken,
                $executedAt->toIso8601String(),
            ]);
            $blockHash = hash('sha256', $payload);

            $idempotencyKey = "rwa:corpo:redemption:{$asset->id}:".$executedAt->timestamp;

            if ($distributedProceeds > 0) {
                foreach ($holdings as $holding) {
                    User::find($holding->user_id)?->walletAccount('IDR');
                }

                $entries[] = PostingEntryDTO::forCode("rwa:pool:{$asset->token_symbol}:IDR", 'IDR', -$distributedProceeds);

                $this->ledgerService->post(new PostingDTO(
                    type: 'rwa_redemption',
                    description: "RWA partial redemption for {$asset->token_symbol}",
                    idempotencyKey: $idempotencyKey,
                    entries: $entries,
                    referenceType: 'rwa_corporate_action',
                    referenceId: $idempotencyKey,
                ));
            }

            return RwaCorporateAction::create([
                'rwa_asset_id' => $asset->id,
                'action_type' => 'redemption',
                'tokens_affected' => $redeemedTotal,
                'redemption_payout_per_token_idr' => $payoutPerToken,
                'prev_hash' => $prevHash,
                'block_hash' => $blockHash,
                'executed_at' => $executedAt,
            ]);
        });
    }
}
