<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Hotel\Application\Actions\ReconcileCityLedgerCorporateAgingAction;
use Modules\Hotel\Domain\Models\CityLedgerAccount;

/**
 * Controller: Buku Besar Piutang Kota (City Ledger) & Penagihan Korporat.
 */
final class HotelCityLedgerBillingController extends Controller
{
    public function index(): View
    {
        $accounts = CityLedgerAccount::where('is_active', true)->get();

        return view('hotel::corporate_city_ledger_portal', [
            'accounts' => $accounts,
        ]);
    }

    public function agingSchedule(
        string $accountId,
        ReconcileCityLedgerCorporateAgingAction $action
    ): JsonResponse {
        $res = $action->execute($accountId);

        return response()->json([
            'status' => 'success',
            'data' => $res,
        ]);
    }
}
