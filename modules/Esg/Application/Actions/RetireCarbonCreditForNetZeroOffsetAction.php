<?php

declare(strict_types=1);

namespace Modules\Esg\Application\Actions;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Esg\Domain\Models\CarbonCredit;
use Modules\Esg\Domain\Models\OffsetRetirement;
use Modules\Esg\Ledger\EsgTransactionTypes;

class RetireCarbonCreditForNetZeroOffsetAction
{
    protected LedgerService $ledgerService;

    public function __construct(?LedgerService $ledgerService = null)
    {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * @return array{
     *     retirement: OffsetRetirement,
     *     offset_cost_idr: int,
     *     remaining_credit_tons: float,
     *     is_fully_retired: bool
     * }
     */
    public function execute(
        CarbonCredit $credit,
        string $entityId,
        float $retiredQuantityTons,
        string $reason,
        ?string $certificateUrl = null
    ): array {
        if ($credit->status !== 'active') {
            throw new InvalidArgumentException("Cannot retire inactive or expired carbon credit certificate {$credit->certificate_number}.");
        }

        $availableTons = (float) $credit->quantity_co2e_tons;
        if ($retiredQuantityTons <= 0.0 || $retiredQuantityTons > $availableTons) {
            throw new InvalidArgumentException("Retirement quantity {$retiredQuantityTons} exceeds available balance {$availableTons} tons.");
        }

        $offsetCostIdr = (int) round($retiredQuantityTons * $credit->cost_per_ton_idr);
        $retirementNumber = 'RET-'.strtoupper(Str::random(8));

        $retirement = OffsetRetirement::create([
            'id' => (string) Str::uuid(),
            'retirement_number' => $retirementNumber,
            'carbon_credit_id' => $credit->id,
            'entity_id' => $entityId,
            'retired_quantity_tons' => $retiredQuantityTons,
            'reason' => $reason,
            'retired_at' => date('Y-m-d'),
            'certificate_url' => $certificateUrl,
        ]);

        $remainingTons = round($availableTons - $retiredQuantityTons, 4);
        $isFullyRetired = $remainingTons <= 0.0001;

        $credit->update([
            'quantity_co2e_tons' => $remainingTons,
            'status' => $isFullyRetired ? 'retired' : 'active',
        ]);

        if ($offsetCostIdr > 0) {
            $this->ledgerService->post(new PostingDTO(
                type: EsgTransactionTypes::ESG_CARBON_RETIREMENT->value,
                description: "Retirement of {$retiredQuantityTons} tCO2e from {$credit->certificate_number} for {$reason}",
                idempotencyKey: "ESG-RETIRE-{$retirement->id}",
                entries: Posting::lines()
                    ->debit('expense:esg:carbon_offset:IDR', $offsetCostIdr)
                    ->credit('inventory:esg:carbon_credit:IDR', $offsetCostIdr)
                    ->toEntries('IDR'),
                referenceType: 'CARBON_OFFSET_RETIREMENT',
                referenceId: (string) $retirement->id,
            ));
        }

        return [
            'retirement' => $retirement,
            'offset_cost_idr' => $offsetCostIdr,
            'remaining_credit_tons' => $remainingTons,
            'is_fully_retired' => $isFullyRetired,
        ];
    }
}
