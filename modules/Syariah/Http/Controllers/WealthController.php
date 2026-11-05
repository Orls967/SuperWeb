<?php

declare(strict_types=1);

namespace Modules\Syariah\Http\Controllers;

use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Syariah\Application\Services\WealthSyariahService;
use Modules\Syariah\Domain\Models\SybWealthInstrument;
use Modules\Syariah\Domain\Models\SybWealthPortfolio;

final class WealthController extends Controller
{
    public function __construct(
        private readonly WealthSyariahService $wealthService
    ) {}

    public function index(Request $request): JsonResponse|Response
    {
        $instruments = SybWealthInstrument::query()->orderBy('ticker_symbol')->get();

        $portfolio = null;
        if ($request->user()) {
            $portfolio = SybWealthPortfolio::query()
                ->where('user_id', $request->user()->id)
                ->with('orders.instrument')
                ->first();
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'instruments' => $instruments,
                'portfolio' => $portfolio,
            ]);
        }

        return response()->view('syariah::wealth.index', [
            'instruments' => $instruments,
            'portfolio' => $portfolio,
        ]);
    }

    public function registerInstrument(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ticker_symbol' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:191'],
            'instrument_type' => ['nullable', 'string', 'max:32'],
            'interest_bearing_debt_idr' => ['required', 'integer', 'min:0'],
            'total_assets_idr' => ['required', 'integer', 'min:1'],
            'non_halal_revenue_idr' => ['required', 'integer', 'min:0'],
            'total_revenue_idr' => ['required', 'integer', 'min:1'],
            'sector_code' => ['nullable', 'string', 'max:64'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);

        $instrument = $this->wealthService->registerInstrument($validated, $request->user());

        return response()->json(['success' => true, 'data' => $instrument], 201);
    }

    public function createPortfolio(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'risk_profile' => ['required', 'string', 'in:CONSERVATIVE,MODERATE,AGGRESSIVE'],
            'initial_deposit_idr' => ['nullable', 'integer', 'min:0'],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);

        $portfolio = $this->wealthService->createPortfolio($validated, $request->user());

        return response()->json(['success' => true, 'data' => $portfolio], 201);
    }

    public function showPortfolio(int $id, Request $request): JsonResponse|Response
    {
        $portfolio = SybWealthPortfolio::with('orders.instrument')->findOrFail($id);
        $user = $request->user();

        // Proteksi IDOR ketat: hanya pemilik, advisor, atau admin yang berhak
        if (! $user->isAdmin() && ! $user->hasRole('wealth_advisor') && $portfolio->user_id !== $user->id) {
            abort(403, 'Akses portofolio nasabah ditolak (IDOR protection).');
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $portfolio]);
        }

        return response()->view('syariah::wealth.portfolio_show', ['portfolio' => $portfolio]);
    }

    public function placeOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'instrument_id' => ['required', 'integer', 'exists:syb_wealth_instruments,id'],
            'units' => ['required', 'integer', 'min:1'],
            'amount_idr' => ['required', 'integer', 'min:1000'],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);

        try {
            $order = $this->wealthService->placeOrder($validated, $request->user());

            return response()->json(['success' => true, 'data' => $order], 201);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'NON_SHARIAH_COMPLIANT_INSTRUMENT',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function purifyDividend(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'portfolio_id' => ['required', 'integer', 'exists:syb_wealth_portfolios,id'],
            'instrument_id' => ['required', 'integer', 'exists:syb_wealth_instruments,id'],
            'dividend_amount_idr' => ['required', 'integer', 'min:1000'],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);

        $res = $this->wealthService->purifyDividend(
            portfolioId: (int) $validated['portfolio_id'],
            instrumentId: (int) $validated['instrument_id'],
            dividendAmountIdr: (int) $validated['dividend_amount_idr'],
            idempotencyKey: $validated['idempotency_key'],
            investor: $request->user()
        );

        return response()->json(['success' => true, 'data' => $res]);
    }

    public function exportCsv(): Response
    {
        $instruments = SybWealthInstrument::query()->orderBy('ticker_symbol')->get();
        $csv = "Ticker,Nama,Tipe,Utang Bunga IDR,Total Aset IDR,Rasio Utang BPS,Non Halal IDR,Total Pendapatan IDR,Rasio Non Halal BPS,Sektor,Halal Compliant\n";
        foreach ($instruments as $inst) {
            $csv .= sprintf(
                "%s,\"%s\",%s,%d,%d,%d,%d,%d,%d,%s,%s\n",
                $inst->ticker_symbol,
                str_replace('"', '""', $inst->name),
                $inst->instrument_type,
                $inst->interest_bearing_debt_idr,
                $inst->total_assets_idr,
                $inst->debt_ratio_bps,
                $inst->non_halal_revenue_idr,
                $inst->total_revenue_idr,
                $inst->non_halal_revenue_ratio_bps,
                $inst->sector_code,
                $inst->is_shariah_compliant ? 'YA' : 'TIDAK'
            );
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="shariah_instruments_screening.csv"',
        ]);
    }
}
