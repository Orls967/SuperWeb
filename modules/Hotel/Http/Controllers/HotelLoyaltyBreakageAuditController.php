<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hotel\Application\Actions\ReconcileMultiLineLoyaltyLiabilityAction;
use Modules\Hotel\Domain\Models\HtlTravelPassPointBatch;

class HotelLoyaltyBreakageAuditController extends Controller
{
    public function index(Request $request): View
    {
        $batches = HtlTravelPassPointBatch::latest()->paginate(15);
        $reconciliation = app(ReconcileMultiLineLoyaltyLiabilityAction::class)->execute();

        return view('hotel::loyalty_liability_breakage_control_center', [
            'batches' => $batches,
            'reconciliation' => $reconciliation,
        ]);
    }
}
