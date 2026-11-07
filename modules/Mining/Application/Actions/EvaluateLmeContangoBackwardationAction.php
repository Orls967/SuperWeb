<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Modules\Mining\Contracts\MetalsTradingLmeGateway;

final class EvaluateLmeContangoBackwardationAction
{
    public function __construct(
        private readonly MetalsTradingLmeGateway $lmeGateway
    ) {}

    /**
     * @return array{
     *     metal_symbol: string,
     *     cash_price_usd_cents: int,
     *     three_month_price_usd_cents: int,
     *     spread_usd_cents: int,
     *     market_structure: string,
     *     hedging_recommendation: string
     * }
     */
    public function execute(string $metalSymbol = 'LME-NI'): array
    {
        $quote = $this->lmeGateway->fetchCashSettlementPrice($metalSymbol);
        $spread = $quote['three_month_cents_ton'] - $quote['cash_cents_ton'];

        if ($spread >= 0) {
            $structure = 'CONTANGO';
            $recommendation = 'Carry trade forward selling optimal untuk mengunci premi forward.';
        } else {
            $structure = 'BACKWARDATION';
            $recommendation = 'Kekurangan pasokan fisik spot: utamakan penjualan tunai prompt date.';
        }

        return [
            'metal_symbol' => $metalSymbol,
            'cash_price_usd_cents' => $quote['cash_cents_ton'],
            'three_month_price_usd_cents' => $quote['three_month_cents_ton'],
            'spread_usd_cents' => $spread,
            'market_structure' => $structure,
            'hedging_recommendation' => $recommendation,
        ];
    }
}
