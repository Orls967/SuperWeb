<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Hotel\Domain\Models\CorporateTravelRequest;
use Modules\Hotel\Domain\Models\TravelBundle;
use Modules\Hotel\Domain\Models\TravelItinerary;
use RuntimeException;

class TravelPlatformAndItineraryService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * 113.1 Book Multi-vendor Travel Bundle with Escrow Deposit
     */
    public function bookTravelBundle(
        int $userId,
        string $city,
        int $flightShareIdr,
        int $hotelShareIdr,
        int $transportShareIdr,
        int $eventShareIdr
    ): TravelBundle {
        $totalPriceIdr = $flightShareIdr + $hotelShareIdr + $transportShareIdr + $eventShareIdr;

        return DB::transaction(function () use (
            $userId,
            $city,
            $flightShareIdr,
            $hotelShareIdr,
            $transportShareIdr,
            $eventShareIdr,
            $totalPriceIdr
        ) {
            $bundle = TravelBundle::create([
                'id' => (string) Str::uuid(),
                'bundle_code' => 'TRV-'.strtoupper(bin2hex(random_bytes(4))),
                'user_id' => $userId,
                'destination_city' => $city,
                'total_price_idr' => $totalPriceIdr,
                'flight_vendor_share_idr' => $flightShareIdr,
                'hotel_vendor_share_idr' => $hotelShareIdr,
                'transport_vendor_share_idr' => $transportShareIdr,
                'event_vendor_share_idr' => $eventShareIdr,
                'status' => 'ESCROWED',
            ]);

            // Post escrow deposit
            $this->ledgerService->post(new PostingDTO(
                type: 'TRAVEL_BUNDLE_ESCROW_DEPOSIT',
                description: "Escrow payment for travel bundle {$bundle->bundle_code}",
                idempotencyKey: "TRV-DEP-{$bundle->bundle_code}",
                entries: [
                    PostingEntryDTO::forCode('htl:travel_escrow_deposit:IDR', 'IDR', $totalPriceIdr),
                    PostingEntryDTO::forCode('htl:travel_unearned_escrow:IDR', 'IDR', -$totalPriceIdr),
                ],
                referenceType: 'TRAVEL_BUNDLE',
                referenceId: $bundle->id,
            ));

            return $bundle;
        });
    }

    /**
     * 113.1 & 113.6 (a) Settle Travel Bundle: Sum of Vendor Shares Equals Total Payment
     */
    public function settleTravelBundle(TravelBundle $bundle): TravelBundle
    {
        return DB::transaction(function () use ($bundle) {
            $bundle->update(['status' => 'SETTLED']);

            $this->ledgerService->post(new PostingDTO(
                type: 'TRAVEL_BUNDLE_FINAL_SETTLEMENT',
                description: "Multi-vendor settlement for bundle {$bundle->bundle_code}",
                idempotencyKey: "TRV-SETTLE-{$bundle->bundle_code}",
                entries: [
                    PostingEntryDTO::forCode('htl:travel_unearned_escrow:IDR', 'IDR', $bundle->total_price_idr),
                    PostingEntryDTO::forCode('air:flight_partner_payable:IDR', 'IDR', -$bundle->flight_vendor_share_idr),
                    PostingEntryDTO::forCode('htl:hotel_vendor_payable:IDR', 'IDR', -$bundle->hotel_vendor_share_idr),
                    PostingEntryDTO::forCode('lgx:transport_vendor_payable:IDR', 'IDR', -$bundle->transport_vendor_share_idr),
                    PostingEntryDTO::forCode('ven:event_vendor_payable:IDR', 'IDR', -$bundle->event_vendor_share_idr),
                ],
                referenceType: 'TRAVEL_BUNDLE',
                referenceId: $bundle->id,
            ));

            return $bundle;
        });
    }

    /**
     * 113.2 Add Activity Itinerary
     */
    public function addItineraryItem(
        TravelBundle $bundle,
        int $dayNumber,
        string $activity,
        string $venueType,
        int $penaltyFeeIdr = 100_000
    ): TravelItinerary {
        return TravelItinerary::create([
            'id' => (string) Str::uuid(),
            'bundle_id' => $bundle->id,
            'day_number' => $dayNumber,
            'activity_name' => $activity,
            'venue_type' => $venueType,
            'cancellation_penalty_fee_idr' => $penaltyFeeIdr,
            'status' => 'BOOKED',
        ]);
    }

    /**
     * 113.4 & 113.6 (c) Corporate Travel Desk: Policy Limit Verification
     */
    public function submitCorporateTravel(
        string $corporatePartyId,
        int $employeeId,
        string $grade,
        int $requestedAmountIdr,
        int $policyBudgetLimitIdr
    ): CorporateTravelRequest {
        if ($requestedAmountIdr > $policyBudgetLimitIdr) {
            throw new RuntimeException("Corporate travel request rejected: Requested amount {$requestedAmountIdr} IDR exceeds policy budget limit of {$policyBudgetLimitIdr} IDR for grade {$grade}");
        }

        return CorporateTravelRequest::create([
            'id' => (string) Str::uuid(),
            'request_code' => 'CTR-'.strtoupper(bin2hex(random_bytes(4))),
            'corporate_party_id' => $corporatePartyId,
            'employee_id' => $employeeId,
            'employee_grade' => $grade,
            'policy_budget_limit_idr' => $policyBudgetLimitIdr,
            'requested_amount_idr' => $requestedAmountIdr,
            'approved_by_manager' => true,
            'status' => 'APPROVED',
        ]);
    }
}
