<?php

declare(strict_types=1);

namespace Modules\Syariah\Http\Controllers;

use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Shared\Domain\Algorithms\Islamic\ZakatAssessment;
use Modules\Syariah\Application\Services\ZakatEngineService;
use Modules\Syariah\Domain\Models\SybGaplessCertificate;
use Modules\Syariah\Domain\Models\SybParameter;
use Modules\Syariah\Domain\Models\SybZakatAssessment;
use Modules\Syariah\Domain\Models\SybZakatDistribution;

final class ZakatController extends Controller
{
    public function __construct(
        private readonly ZakatEngineService $zakatService,
        private readonly ZakatAssessment $zakatAssessment = new ZakatAssessment
    ) {}

    public function index(Request $request): JsonResponse|Response
    {
        $assessments = SybZakatAssessment::query()
            ->with('customer')
            ->orderByDesc('haul_year')
            ->paginate(15);

        $distributions = SybZakatDistribution::query()
            ->orderByDesc('haul_year')
            ->get();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'assessments' => $assessments->items(),
                'distributions' => $distributions,
            ]);
        }

        return response()->view('syariah::zakat.index', [
            'assessments' => $assessments,
            'distributions' => $distributions,
        ]);
    }

    public function assess(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_user_id' => ['required', 'integer', 'exists:users,id'],
            'haul_year' => ['required', 'integer', 'min:1400', 'max:1600'],
            'qualifying_wealth_idr' => ['required', 'integer', 'min:0'],
            'due_debts_idr' => ['nullable', 'integer', 'min:0'],
            'holding_days' => ['nullable', 'integer', 'min:0'],
            'auto_debit' => ['nullable', 'boolean'],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);

        try {
            $record = $this->zakatService->assessHaul($validated, $request->user());

            return response()->json(['success' => true, 'data' => $record], 201);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'DUPLICATE_HAUL_ZAKAT_ASSESSMENT',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function distribute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'haul_year' => ['required', 'integer'],
            'total_amount_idr' => ['required', 'integer', 'min:1000'],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);

        $records = $this->zakatService->distributeAsnaf(
            haulYear: (int) $validated['haul_year'],
            totalAmountIdr: (int) $validated['total_amount_idr'],
            idempotencyKey: $validated['idempotency_key'],
            amil: $request->user()
        );

        return response()->json(['success' => true, 'data' => $records]);
    }

    /**
     * Kalkulator zakat publik/nasabah (DAFTAR PERLUASAN WAJIB).
     */
    public function calculate(Request $request): JsonResponse
    {
        $goldParam = SybParameter::getLatestValue('GOLD_PRICE_PER_GRAM_IDR', ['price_idr' => 1_500_000]);
        $goldPrice = (int) ($goldParam['price_idr'] ?? 1_500_000);

        $wealth = (int) $request->input('wealth_idr', 0);
        $debts = (int) $request->input('debts_idr', 0);
        $days = (int) $request->input('holding_days', 354);

        $result = $this->zakatAssessment->assess(
            goldPricePerGramIdr: $goldPrice,
            qualifyingWealthIdr: $wealth,
            dueDebtsIdr: $debts,
            holdingDays: $days
        );

        return response()->json([
            'success' => true,
            'nisab_gold_grams' => 85,
            'gold_price_per_gram_idr' => $goldPrice,
            'nisab_idr' => $result->nisabIdr,
            'net_zakat_base_idr' => $result->netZakatBaseIdr,
            'is_obligated' => $result->isObligated,
            'zakat_amount_due_idr' => $result->zakatAmountIdr,
        ]);
    }

    public function showCertificate(int $id, Request $request): JsonResponse|Response
    {
        $cert = SybGaplessCertificate::findOrFail($id);
        $user = $request->user();

        if (! $user->isAdmin() && ! $user->hasRole('amil') && $cert->recipient_user_id !== $user->id) {
            abort(403, 'Akses sertifikat zakat ditolak (IDOR protection).');
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $cert]);
        }

        return response()->view('syariah::zakat.certificate', ['certificate' => $cert]);
    }

    public function exportCsv(): Response
    {
        $distributions = SybZakatDistribution::query()->orderBy('haul_year')->get();
        $csv = "ID,Haul Hijriah,Golongan Asnaf,Nominal Alokasi IDR,Tersalurkan IDR,Ref Mustahik Party\n";
        foreach ($distributions as $d) {
            $csv .= sprintf(
                "%d,%d,%s,%d,%d,%s\n",
                $d->id,
                $d->haul_year,
                strtoupper($d->asnaf),
                $d->allocated_amount_idr,
                $d->distributed_amount_idr,
                $d->mustahik_party_ref ?? '-'
            );
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="zakat_asnaf_distributions.csv"',
        ]);
    }
}
