<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Hotel\Application\Actions\SettleSplitFolioPayerAction;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelFolioLine;

/**
 * Controller: Pemisahan Tagihan Sub-Folio Multi-Payer (Corporate vs Personal).
 */
final class HotelFolioSplitController extends Controller
{
    public function show(string $folioId): View
    {
        $folio = HotelFolio::with('reservation')->findOrFail($folioId);
        $lines = HotelFolioLine::where('folio_id', $folio->id)->get();

        return view('hotel::folio_split_workbench', [
            'folio' => $folio,
            'lines' => $lines,
        ]);
    }

    public function settle(
        Request $request,
        string $folioId,
        SettleSplitFolioPayerAction $action
    ): JsonResponse {
        $validated = $request->validate([
            'corporate_id' => 'required|string',
            'corporate_billing_enabled' => 'required|boolean',
            'corporate_credit_limit_idr' => 'required|integer|min:0',
            'personal_payment_amount_idr' => 'required|integer|min:0',
            'idempotency_key' => 'required|string',
        ]);

        $res = $action->execute(
            folioId: $folioId,
            corporateId: $validated['corporate_id'],
            corporateBillingEnabled: (bool) $validated['corporate_billing_enabled'],
            corporateCreditLimitIdr: (int) $validated['corporate_credit_limit_idr'],
            personalPaymentAmountIdr: (int) $validated['personal_payment_amount_idr'],
            idempotencyKey: $validated['idempotency_key']
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Folio split settlement berhasil diproses.',
            'data' => $res,
        ]);
    }
}
