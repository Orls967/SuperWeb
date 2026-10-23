<?php

declare(strict_types=1);

namespace Modules\Esg\Application\Actions;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Esg\Domain\Models\CarbonCredit;
use Modules\Esg\Ledger\EsgTransactionTypes;

class PurchaseAndInventoryCarbonCreditLedgerAction
{
    protected LedgerService $ledgerService;

    public function __construct(?LedgerService $ledgerService = null)
    {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * @param array{
     *     certificate_number: string,
     *     registry: string, // VERRA, GOLD_STANDARD, IDX_CARBON
     *     project_name: string,
     *     project_type: string,
     *     vintage_year: int,
     *     quantity_co2e_tons: float,
     *     cost_per_ton_idr: int
     * } $data
     * @return array{
     *     carbon_credit: CarbonCredit,
     *     total_cost_idr: int,
     *     ledger_posted: bool
     * }
     */
    public function execute(array $data): array
    {
        if ($data['quantity_co2e_tons'] <= 0.0 || $data['cost_per_ton_idr'] <= 0) {
            throw new InvalidArgumentException('Carbon credit quantity and cost per ton must be greater than zero.');
        }

        $totalCostIdr = (int) round($data['quantity_co2e_tons'] * $data['cost_per_ton_idr']);

        $credit = CarbonCredit::create([
            'id' => (string) Str::uuid(),
            'certificate_number' => $data['certificate_number'],
            'registry' => strtoupper($data['registry']),
            'project_name' => $data['project_name'],
            'project_type' => $data['project_type'],
            'vintage_year' => $data['vintage_year'],
            'quantity_co2e_tons' => $data['quantity_co2e_tons'],
            'cost_per_ton_idr' => $data['cost_per_ton_idr'],
            'total_cost_idr' => $totalCostIdr,
            'status' => 'active',
        ]);

        if ($totalCostIdr > 0) {
            $this->ledgerService->post(new PostingDTO(
                type: EsgTransactionTypes::ESG_CARBON_PURCHASE->value,
                description: "Purchase of Carbon Credits {$credit->certificate_number} ({$credit->quantity_co2e_tons} tCO2e)",
                idempotencyKey: "ESG-BUY-{$credit->id}",
                entries: Posting::lines()
                    ->debit('inventory:esg:carbon_credit:IDR', $totalCostIdr)
                    ->credit('ap:esg:carbon_registry:IDR', $totalCostIdr)
                    ->toEntries('IDR'),
                referenceType: 'CARBON_CREDIT',
                referenceId: (string) $credit->id,
            ));
        }

        return [
            'carbon_credit' => $credit,
            'total_cost_idr' => $totalCostIdr,
            'ledger_posted' => true,
        ];
    }
}
