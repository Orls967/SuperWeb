<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Hotel\Application\Actions\AccrueLoyaltyStayNightsAction;
use Modules\Hotel\Application\Actions\ScoreGuestChurnRiskAction;
use Modules\Hotel\Domain\Services\HotelLoyaltyPassportProfileService;

/**
 * Controller: Paspor Loyalitas Tamu & Pemantauan Risiko Atrisi (Churn).
 */
final class HotelLoyaltyPassportController extends Controller
{
    public function profile(
        string $guestId,
        HotelLoyaltyPassportProfileService $service
    ): View {
        $profile = $service->getGuestPassportProfile($guestId);

        return view('hotel::guest_loyalty_passport', [
            'profile' => $profile,
        ]);
    }

    public function accrue(
        Request $request,
        string $guestId,
        AccrueLoyaltyStayNightsAction $action
    ): JsonResponse {
        $validated = $request->validate([
            'prior_nights' => 'required|integer|min:0',
            'stay_nights' => 'required|integer|min:1',
            'eligible_spend_idr' => 'required|integer|min:0',
            'existing_points_balance' => 'required|integer|min:0',
        ]);

        $cert = $action->execute(
            guestId: $guestId,
            priorNights: (int) $validated['prior_nights'],
            stayNights: (int) $validated['stay_nights'],
            eligibleSpendIdr: (int) $validated['eligible_spend_idr'],
            existingPointsBalance: (int) $validated['existing_points_balance']
        );

        return response()->json([
            'status' => 'success',
            'data' => $cert,
        ]);
    }

    public function scoreChurn(
        Request $request,
        string $guestId,
        ScoreGuestChurnRiskAction $action
    ): JsonResponse {
        $validated = $request->validate([
            'days_since_last_stay' => 'required|integer|min:0',
            'total_stays_past_year' => 'required|integer|min:0',
            'average_spend_per_stay_idr' => 'required|integer|min:0',
            'unresolved_complaints_count' => 'required|integer|min:0',
            'average_review_rating' => 'required|numeric|min:1.0|max:5.0',
        ]);

        $cert = $action->execute(
            guestId: $guestId,
            daysSinceLastStay: (int) $validated['days_since_last_stay'],
            totalStaysPastYear: (int) $validated['total_stays_past_year'],
            averageSpendPerStayIdr: (int) $validated['average_spend_per_stay_idr'],
            unresolvedComplaintsCount: (int) $validated['unresolved_complaints_count'],
            averageReviewRating: (float) $validated['average_review_rating']
        );

        return response()->json([
            'status' => 'success',
            'data' => $cert,
        ]);
    }
}
