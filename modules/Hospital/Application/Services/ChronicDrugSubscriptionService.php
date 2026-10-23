<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hospital\Domain\Models\ChronicSubscription;
use Modules\Hospital\Domain\Models\EPharmacyOrder;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\PharmacyBranch;

class ChronicDrugSubscriptionService
{
    public function __construct(
        private readonly FefoInventoryService $fefoService,
        private readonly ?LedgerService $ledgerService = null,
    ) {}

    /**
     * Register a new recurring chronic medication subscription
     */
    public function registerSubscription(
        Patient $patient,
        PharmacyBranch $branch,
        string $drugCode,
        string $drugName,
        int $monthlyQty,
        int $unitPriceIdr
    ): ChronicSubscription {
        return ChronicSubscription::create([
            'subscription_code' => 'SUB-'.strtoupper(bin2hex(random_bytes(6))),
            'patient_id' => $patient->id,
            'pharmacy_branch_id' => $branch->id,
            'drug_code' => $drugCode,
            'drug_name' => $drugName,
            'monthly_quantity' => $monthlyQty,
            'unit_price_idr' => $unitPriceIdr,
            'next_refill_date' => now()->toDateString(),
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * Process all recurring refills due on or before target date
     *
     * @return array<int, EPharmacyOrder>
     */
    public function processMonthlyRefills(string $targetDate): array
    {
        $dueSubscriptions = ChronicSubscription::where('status', 'ACTIVE')
            ->whereDate('next_refill_date', '<=', $targetDate)
            ->get();

        $processedOrders = [];
        $ledger = $this->ledgerService ?? app(LedgerService::class);

        foreach ($dueSubscriptions as $sub) {
            $order = DB::transaction(function () use ($sub, $ledger, $targetDate) {
                // 1. Pick FEFO stock
                $this->fefoService->pickStock(
                    branchId: $sub->pharmacy_branch_id,
                    drugCode: $sub->drug_code,
                    requestedQty: $sub->monthly_quantity
                );

                $totalAmountIdr = $sub->monthly_quantity * $sub->unit_price_idr;

                // 2. Generate ePharmacy order
                $order = EPharmacyOrder::create([
                    'order_code' => 'CHRONIC-'.strtoupper(bin2hex(random_bytes(6))),
                    'patient_id' => $sub->patient_id,
                    'pharmacy_branch_id' => $sub->pharmacy_branch_id,
                    'drug_code' => $sub->drug_code,
                    'drug_name' => $sub->drug_name,
                    'quantity' => $sub->monthly_quantity,
                    'total_price_idr' => $totalAmountIdr,
                    'drug_classification' => 'REGULAR',
                    'doctor_signature_hash' => hash('sha256', "CHRONIC-REFILL:{$sub->subscription_code}:{$targetDate}"),
                    'is_chronic_subscription' => true,
                    'delivery_status' => 'DISPATCHED',
                    'status' => 'CONFIRMED',
                ]);

                // 3. Post balanced double-entry ledger entry
                $ledger->post(new PostingDTO(
                    type: 'CHRONIC_MED_SUBSCRIPTION',
                    description: "Chronic subscription fulfillment for {$sub->subscription_code}",
                    idempotencyKey: "HSP-CHRONIC-{$sub->subscription_code}-{$targetDate}",
                    entries: Posting::lines()
                        ->debit('hsp:epharmacy_receivable:IDR', $totalAmountIdr)
                        ->credit('hsp:epharmacy_revenue:IDR', $totalAmountIdr)
                        ->toEntries('IDR'),
                    referenceType: 'CHRONIC_SUBSCRIPTION',
                    referenceId: (string) $sub->id,
                ));

                // 4. Advance next refill date by 30 days
                $sub->update([
                    'next_refill_date' => Carbon::parse($targetDate)->addDays(30)->toDateString(),
                ]);

                return $order;
            });

            $processedOrders[] = $order;
        }

        return $processedOrders;
    }
}
