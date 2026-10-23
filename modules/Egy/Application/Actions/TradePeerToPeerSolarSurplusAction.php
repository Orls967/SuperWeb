<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;
use RuntimeException;

class TradePeerToPeerSolarSurplusAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        $sellerPartyId = (string) $params['seller_party_id'];
        $buyerPartyId = (string) $params['buyer_party_id'];
        $surplusKwh = (int) $params['surplus_kwh'];
        $clearingPriceMinor = (int) ($params['clearing_price_minor'] ?? 1450); // e.g. Rp 1.450 / kWh

        if ($surplusKwh <= 0) {
            throw new RuntimeException('Surplus energy must be greater than zero.');
        }

        $tradeTotalMinor = $surplusKwh * $clearingPriceMinor;

        return DB::transaction(function () use ($sellerPartyId, $buyerPartyId, $surplusKwh, $clearingPriceMinor, $tradeTotalMinor) {
            $tradeCode = 'P2P-'.strtoupper(substr(hash('sha256', "{$sellerPartyId}:{$buyerPartyId}:{$surplusKwh}:{$tradeTotalMinor}"), 0, 10));

            $idempotencyKey = hash('sha256', "P2P-TRADE:{$tradeCode}:{$tradeTotalMinor}");

            if ($tradeTotalMinor > 0) {
                $this->ledgerService->post(new PostingDTO(
                    type: 'ENERGY_METER_READING_BILL',
                    description: "P2P microgrid solar trade {$tradeCode} ({$surplusKwh} kWh)",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('egy:utility_receivable:IDR', $tradeTotalMinor)
                        ->credit('egy:grid_electricity_revenue:IDR', $tradeTotalMinor)
                        ->toEntries('IDR'),
                    referenceType: 'EGY_P2P_TRADE',
                    referenceId: $tradeCode,
                ));
            }

            $this->audit(
                action: 'egy_p2p_trade_executed',
                newValues: [
                    'trade_code' => $tradeCode,
                    'seller' => $sellerPartyId,
                    'buyer' => $buyerPartyId,
                    'kwh' => $surplusKwh,
                    'total_minor' => $tradeTotalMinor,
                ],
                impactType: 'p2p_energy_clearing',
            );

            return [
                'trade_code' => $tradeCode,
                'seller_party_id' => $sellerPartyId,
                'buyer_party_id' => $buyerPartyId,
                'surplus_kwh' => $surplusKwh,
                'clearing_price_minor' => $clearingPriceMinor,
                'total_amount_minor' => $tradeTotalMinor,
                'status' => 'CLEARED',
            ];
        });
    }
}
