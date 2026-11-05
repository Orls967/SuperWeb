<?php

declare(strict_types=1);

namespace Modules\Syariah\Http\Controllers;

use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Modules\Syariah\Application\Services\SukukFacilityService;
use Modules\Syariah\Domain\Models\SybSukukFacility;

final class SukukController extends Controller
{
    public function __construct(
        private readonly SukukFacilityService $facilityService
    ) {}

    public function index(Request $request): JsonResponse|Response
    {
        $facilities = SybSukukFacility::query()
            ->with(['distributions', 'dpsReviewer'])
            ->orderByDesc('id')
            ->paginate(15);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $facilities->items(),
                'total' => $facilities->total(),
            ]);
        }

        return response()->view('syariah::sukuk.index', [
            'facilities' => $facilities,
        ]);
    }

    public function show(int $id, Request $request): JsonResponse|Response
    {
        $facility = SybSukukFacility::with(['distributions', 'dpsReviewer'])->findOrFail($id);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $facility]);
        }

        return response()->view('syariah::sukuk.show', ['facility' => $facility]);
    }

    public function propose(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sukuk_code' => ['required', 'string', 'max:64'],
            'facility_name' => ['required', 'string', 'max:191'],
            'asset_code' => ['required', 'string', 'max:64'],
            'face_value_idr' => ['required', 'integer', 'min:1000'],
            'total_units' => ['required', 'integer', 'min:1'],
            'maturity_date' => ['required', 'date'],
            'annual_coupon_bps' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'spv_party_name' => ['nullable', 'string', 'max:191'],
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

    public function issue(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);

        try {
            $facility = $this->facilityService->issueToken(
                facilityId: $id,
                officer: $request->user(),
                idempotencyKey: $validated['idempotency_key']
            );

            return response()->json(['success' => true, 'data' => $facility]);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function distributeCoupon(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_code' => ['required', 'string', 'max:32'],
            'coupon_pool_idr' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);

        try {
            $distribution = $this->facilityService->distributeCoupon(
                facilityId: $id,
                periodCode: $validated['period_code'],
                couponPoolIdr: (int) $validated['coupon_pool_idr'],
                idempotencyKey: $validated['idempotency_key'],
                officer: $request->user()
            );

            return response()->json(['success' => true, 'data' => $distribution]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function redeem(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);

        $facility = $this->facilityService->redeemFacility($id, $request->user(), $validated['idempotency_key']);

        return response()->json(['success' => true, 'data' => $facility]);
    }

    public function exportCsv(): Response
    {
        $facilities = SybSukukFacility::query()->orderBy('id')->get();
        $csv = "ID,Kode Sukuk,Nama Fasilitas,Aset Dasar,Nilai Nominal IDR,Total Unit,Jatuh Tempo,Kupon BPS,Status,Total Terdistribusi IDR\n";
        foreach ($facilities as $f) {
            $csv .= sprintf(
                "%d,%s,\"%s\",%s,%d,%d,%s,%d,%s,%d\n",
                $f->id,
                $f->sukuk_code,
                str_replace('"', '""', $f->facility_name),
                $f->asset_code,
                $f->face_value_idr,
                $f->total_units,
                $f->maturity_date->toDateString(),
                $f->annual_coupon_bps,
                $f->status->value,
                $f->total_coupon_distributed_idr
            );
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="sukuk_facilities.csv"',
        ]);
    }
}
