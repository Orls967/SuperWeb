<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions\Reverse;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Core\Contracts\ClockInterface;
use Modules\Logistics\Domain\Models\ShipmentLeg;
use Modules\Logistics\Ledger\LogisticsTransactionTypes;
use Modules\Logistics\Ledger\SubjectAccounts;

class AccrueCarrierCostAction
{
    public function __construct(
        protected LedgerService $ledgerService,
        protected ?ClockInterface $clock = null
    ) {
        $this->clock = $clock ?? app(ClockInterface::class);
    }

    public function execute(ShipmentLeg $leg, int $amountIdr, ?string $idempotencyKey = null): void
    {
        if ($amountIdr <= 0) {
            return;
        }

        DB::transaction(function () use ($leg, $amountIdr, $idempotencyKey) {
            $carrierId = $leg->carrier_id;
            if (! $carrierId) {
                return;
            }

            $now = $this->clock ? $this->clock->now() : CarbonImmutable::now();
            $key = $idempotencyKey ?? "lgx:carrier_cost:leg:{$leg->id}";

            // D79-3: Debit lgx:carrier_cost:IDR / Kredit ap:carrier:{id}:IDR
            $apCode = SubjectAccounts::carrierPayable((int) $carrierId);
            $this->ledgerService->post(new PostingDTO(
                type: LogisticsTransactionTypes::CARRIER_COST_ACCRUAL->value,
                description: "Akru biaya carrier {$carrierId} leg {$leg->id}",
                idempotencyKey: $key,
                entries: Posting::lines()
                    ->debit('lgx:carrier_cost:IDR', $amountIdr)
                    ->credit($apCode, $amountIdr)
                    ->toEntries('IDR'),
                referenceType: 'SHIPMENT_LEG',
                referenceId: (string) $leg->id,
            ));

            $leg->update([
                'carrier_cost_idr' => $amountIdr,
                'cost_accrued_at' => $now,
            ]);
        });
    }
}
