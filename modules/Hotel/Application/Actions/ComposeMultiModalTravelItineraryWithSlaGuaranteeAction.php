<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hotel\Domain\Models\CorporateTravelRequest;
use Modules\Hotel\Domain\Models\TravelBundle;
use Modules\Shared\Application\BaseAction;
use RuntimeException;

class ComposeMultiModalTravelItineraryWithSlaGuaranteeAction extends BaseAction
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * Compose multi-modal travel bundle, enforce corporate policy limits, and settle multi-vendor escrow.
     *
     * @return array{bundle: TravelBundle, corporate_request: ?CorporateTravelRequest, total_price_idr: int, status: string}
     */
    public function handle(
        int $userId,
        string $destinationCity,
        int $flightShareIdr,
        int $hotelShareIdr,
        int $transportShareIdr,
        int $eventShareIdr,
        ?string $corporateEmployeeGrade = null,
        int $corporatePolicyBudgetLimitIdr = 0,
        ?string $settleBundleId = null
    ): array {
        $totalPriceIdr = $flightShareIdr + $hotelShareIdr + $transportShareIdr + $eventShareIdr;

        return DB::transaction(function () use (
            $userId,
            $destinationCity,
            $flightShareIdr,
            $hotelShareIdr,
            $transportShareIdr,
            $eventShareIdr,
            $totalPriceIdr,
            $corporateEmployeeGrade,
            $corporatePolicyBudgetLimitIdr,
            $settleBundleId
        ) {
            // 113.4 Corporate travel policy compliance
            $corporateRequest = null;
            if ($corporateEmployeeGrade !== null) {
                if ($corporatePolicyBudgetLimitIdr > 0 && $totalPriceIdr > $corporatePolicyBudgetLimitIdr && $corporateEmployeeGrade !== 'EXECUTIVE') {
                    throw new RuntimeException("Corporate travel policy breach: Bundle price {$totalPriceIdr} IDR exceeds grade {$corporateEmployeeGrade} limit of {$corporatePolicyBudgetLimitIdr} IDR");
                }

                $corporateRequest = CorporateTravelRequest::create([
                    'id' => (string) Str::uuid(),
                    'request_code' => 'CTR-'.strtoupper(bin2hex(random_bytes(4))),
                    'corporate_party_id' => 'CORP-PT-001',
                    'employee_id' => (string) $userId,
                    'employee_grade' => $corporateEmployeeGrade,
                    'policy_budget_limit_idr' => $corporatePolicyBudgetLimitIdr,
                    'requested_amount_idr' => $totalPriceIdr,
                    'approved_by_manager' => true,
                    'status' => 'APPROVED',
                ]);
            }

            // If settling existing bundle
            if ($settleBundleId !== null) {
                $bundle = TravelBundle::lockForUpdate()->findOrFail($settleBundleId);
                $bundle->update(['status' => 'SETTLED']);

                // 113.1 & 113.6 (a) Settle multi-vendor escrow: sum of credits = total payment
                $this->ledgerService->post(new PostingDTO(
                    type: 'TRAVEL_BUNDLE_FINAL_SETTLEMENT',
                    description: "Multi-vendor settlement for bundle {$bundle->bundle_code}",
                    idempotencyKey: "TRV-SETTLE-{$bundle->bundle_code}",
                    entries: Posting::lines()
                        ->debit('htl:travel_unearned_escrow:IDR', $bundle->total_price_idr)
                        ->credit('air:flight_partner_payable:IDR', $bundle->flight_vendor_share_idr)
                        ->credit('htl:hotel_vendor_payable:IDR', $bundle->hotel_vendor_share_idr)
                        ->credit('lgx:transport_vendor_payable:IDR', $bundle->transport_vendor_share_idr)
                        ->credit('ven:event_vendor_payable:IDR', $bundle->event_vendor_share_idr)
                        ->toEntries('IDR'),
                    referenceType: 'TRAVEL_BUNDLE',
                    referenceId: $bundle->id,
                ));

                return [
                    'bundle' => $bundle,
                    'corporate_request' => $corporateRequest,
                    'total_price_idr' => $bundle->total_price_idr,
                    'status' => 'SETTLED',
                ];
            }

            // 113.1 Book new multi-vendor bundle
            $bundleCount = TravelBundle::count() + 1;
            $bundleCode = sprintf('TRV-%s-%04d', strtoupper($destinationCity), $bundleCount);

            $bundle = TravelBundle::create([
                'id' => (string) Str::uuid(),
                'bundle_code' => $bundleCode,
                'user_id' => $userId,
                'destination_city' => $destinationCity,
                'total_price_idr' => $totalPriceIdr,
                'flight_vendor_share_idr' => $flightShareIdr,
                'hotel_vendor_share_idr' => $hotelShareIdr,
                'transport_vendor_share_idr' => $transportShareIdr,
                'event_vendor_share_idr' => $eventShareIdr,
                'status' => 'ESCROWED',
            ]);

            // Post escrow deposit into ledger
            $this->ledgerService->post(new PostingDTO(
                type: 'TRAVEL_BUNDLE_ESCROW_DEPOSIT',
                description: "Escrow payment for travel bundle {$bundle->bundle_code}",
                idempotencyKey: "TRV-DEP-{$bundle->bundle_code}",
                entries: Posting::lines()
                    ->debit('htl:travel_escrow_deposit:IDR', $totalPriceIdr)
                    ->credit('htl:travel_unearned_escrow:IDR', $totalPriceIdr)
                    ->toEntries('IDR'),
                referenceType: 'TRAVEL_BUNDLE',
                referenceId: $bundle->id,
            ));

            $this->audit(
                action: 'hotel.travel_bundle.composed',
                auditable: $bundle,
                oldValues: null,
                newValues: [
                    'bundle_code' => $bundleCode,
                    'destination_city' => $destinationCity,
                    'total_price_idr' => $totalPriceIdr,
                    'corporate_grade' => $corporateEmployeeGrade,
                ],
                correlationId: "htl_bundle_{$bundleCode}",
                impactType: 'travel_bundle_escrow'
            );

            return [
                'bundle' => $bundle,
                'corporate_request' => $corporateRequest,
                'total_price_idr' => $totalPriceIdr,
                'status' => 'ESCROWED',
            ];
        });
    }
}
