<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Modules\Mining\Domain\Enums\TradingContractStatus;
use Modules\Mining\Domain\Models\MiningMetalsTradingContract;
use Modules\Mining\Domain\Models\MiningMetalsTradingPosition;
use Modules\Mining\Domain\StateMachines\MetalsTradingContractStateMachine;

final class TriggerMetalsTradingMarginCallAction
{
    public function __construct(
        private readonly MetalsTradingContractStateMachine $stateMachine
    ) {}

    /**
     * @return array{
     *     contract_number: string,
     *     margin_call_active: bool,
     *     variation_margin_required_idr: int,
     *     alert_message: string
     * }
     */
    public function execute(MiningMetalsTradingContract $contract): array
    {
        $latestPos = MiningMetalsTradingPosition::where('contract_id', $contract->id)
            ->latest('valuation_date')
            ->first();

        $marginRequired = $latestPos ? (int) $latestPos->variationMarginRequiredIdr : 0;
        $isTriggered = $latestPos ? (bool) $latestPos->margin_call_triggered : false;

        if ($isTriggered) {
            $this->stateMachine->transition($contract, TradingContractStatus::MARGIN_CALLED);
            $message = "PERINGATAN MARGIN CALL: Kontrak {$contract->contract_number} memerlukan top-up margin sebesar Rp ".number_format($marginRequired, 0, ',', '.').'.';
        } else {
            $message = "Kondisi ekuitas kontrak {$contract->contract_number} aman di atas batas margin pemeliharaan.";
        }

        return [
            'contract_number' => $contract->contract_number,
            'margin_call_active' => $isTriggered,
            'variation_margin_required_idr' => $marginRequired,
            'alert_message' => $message,
        ];
    }
}
