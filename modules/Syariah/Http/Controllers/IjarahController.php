<?php

declare(strict_types=1);

namespace Modules\Syariah\Http\Controllers;

use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Modules\Syariah\Application\Services\IjarahFacilityService;
use Modules\Syariah\Domain\Models\SybIjarahFacility;

final class IjarahController extends Controller
{
    public function __construct(
        private readonly IjarahFacilityService $facilityService
    ) {}

    public function index(Request $request): JsonResponse|Response
    {
        $facilities = SybIjarahFacility::query()
            ->with(['customer', 'schedules'])
            ->orderByDesc('id')
            ->paginate(15);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $facilities->items(),
                'total' => $facilities->total(),
            ]);
        }

        return response()->view('syariah::ijarah.index', [
            'facilities' => $facilities,
        ]);
    }

    public function show(int $id, Request $request): JsonResponse|Response
    {
        $facility = SybIjarahFacility::with(['customer', 'schedules'])->findOrFail($id);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $facility]);
        }

        return response()->view('syariah::ijarah.show', ['facility' => $facility]);
    }

    public function propose(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contract_code' => ['required', 'string', 'max:64'],
            'customer_user_id' => ['required', 'integer', 'exists:users,id'],
            'asset_name' => ['required', 'string', 'max:191'],
            'asset_cost_idr' => ['required', 'integer', 'min:1000'],
            'tenor_months' => ['required', 'integer', 'min:1', 'max:360'],
            'total_ujrah_idr' => ['required', 'integer', 'min:1000'],
            'residual_option_price_idr' => ['nullable', 'integer', 'min:0'],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);

        try {
            $facility = $this->facilityService->proposeFacility($validated, $request->user());

            return response()->json(['success' => true, 'data' => $facility], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function dpsApprove(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'opinion_note' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);

        try {
            $facility = $this->facilityService->reviewDps(
                facilityId: $id,
                dpsReviewer: $request->user(),
                opinionNote: $validated['opinion_note'],
                idempotencyKey: $validated['idempotency_key']
            );

            return response()->json(['success' => true, 'data' => $facility]);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function activate(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);

        try {
            $facility = $this->facilityService->activateFacility(
                facilityId: $id,
                officer: $request->user(),
                idempotencyKey: $validated['idempotency_key']
            );

            return response()->json(['success' => true, 'data' => $facility]);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function payPeriod(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);

        try {
            $schedule = $this->facilityService->payPeriod(
                facilityId: $id,
                period: (int) $validated['period'],
                payer: $request->user(),
                idempotencyKey: $validated['idempotency_key']
            );

            return response()->json(['success' => true, 'data' => $schedule]);
        } catch (DomainException|InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function exerciseImbt(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);

        try {
            $facility = $this->facilityService->exerciseImbtOption($id, $request->user(), $validated['idempotency_key']);

            return response()->json(['success' => true, 'data' => $facility]);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function exportCsv(): Response
    {
        $facilities = SybIjarahFacility::query()->with('customer')->orderBy('id')->get();
        $csv = "ID,Kode Akad,Nasabah,Nama Aset,Biaya Aset IDR,Tenor Bulan,Total Ujrah IDR,Sisa Nilai Buku IDR,Status,Terbayar IDR\n";
        foreach ($facilities as $f) {
            $csv .= sprintf(
                "%d,%s,\"%s\",\"%s\",%d,%d,%d,%d,%s,%d\n",
                $f->id,
                $f->contract_code,
                str_replace('"', '""', $f->customer?->name ?? (string) $f->customer_user_id),
                str_replace('"', '""', $f->asset_name),
                $f->asset_cost_idr,
                $f->tenor_months,
                $f->total_ujrah_idr,
                $f->carrying_book_value_idr,
                $f->status->value,
                $f->total_ujrah_paid_idr
            );
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="ijarah_facilities.csv"',
        ]);
    }
}
