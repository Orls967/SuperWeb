<?php

declare(strict_types=1);

namespace Modules\Syariah\Http\Controllers;

use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Syariah\Application\Services\WaqfPhilanthropyService;
use Modules\Syariah\Domain\Models\SybGaplessCertificate;
use Modules\Syariah\Domain\Models\SybWaqfPrincipal;

final class WaqfController extends Controller
{
    public function __construct(
        private readonly WaqfPhilanthropyService $waqfService
    ) {}

    public function index(Request $request): JsonResponse|Response
    {
        $principals = SybWaqfPrincipal::query()
            ->with(['wakif', 'distributions'])
            ->orderByDesc('id')
            ->paginate(15);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'principals' => $principals->items(),
                'total' => $principals->total(),
            ]);
        }

        return response()->view('syariah::waqf.index', [
            'principals' => $principals,
        ]);
    }

    public function receiveMoney(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'wakif_user_id' => ['required', 'integer', 'exists:users,id'],
            'principal_amount_idr' => ['required', 'integer', 'min:1000'],
            'purpose' => ['required', 'string', 'max:191'],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);

        $record = $this->waqfService->receiveCashWaqf($validated, $request->user());

        return response()->json(['success' => true, 'data' => $record], 201);
    }

    public function withdrawPrincipal(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount_idr' => ['required', 'integer', 'min:1000'],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);

        try {
            $this->waqfService->withdrawPrincipal(
                waqfPrincipalId: $id,
                amountIdr: (int) $validated['amount_idr'],
                idempotencyKey: $validated['idempotency_key'],
                actor: $request->user()
            );

            return response()->json(['success' => true, 'message' => 'Penarikan pokok berhasil diproses']);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'PERPETUAL_WAQF_PRINCIPAL_IMMUTABLE',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function distributeBenefit(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'benefit_amount_idr' => ['required', 'integer', 'min:1000'],
            'recipient_party_name' => ['required', 'string', 'max:191'],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);

        $dist = $this->waqfService->distributeBenefit(
            waqfPrincipalId: $id,
            benefitAmountIdr: (int) $validated['benefit_amount_idr'],
            recipientName: $validated['recipient_party_name'],
            idempotencyKey: $validated['idempotency_key'],
            nazhir: $request->user()
        );

        return response()->json(['success' => true, 'data' => $dist]);
    }

    public function showCertificate(int $id, Request $request): JsonResponse|Response
    {
        $cert = SybGaplessCertificate::findOrFail($id);
        $user = $request->user();

        if (! $user->isAdmin() && ! $user->hasRole('nazhir') && $cert->recipient_user_id !== $user->id) {
            abort(403, 'Akses sertifikat wakif ditolak (IDOR protection).');
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $cert]);
        }

        return response()->view('syariah::waqf.certificate', ['certificate' => $cert]);
    }

    public function exportCsv(): Response
    {
        $principals = SybWaqfPrincipal::query()->with('wakif')->orderBy('id')->get();
        $csv = "ID,Wakif,Tipe Wakaf,Pokok Wakaf IDR,Tujuan,Abadi,Nomor Sertifikat,Status\n";
        foreach ($principals as $w) {
            $csv .= sprintf(
                "%d,\"%s\",%s,%d,\"%s\",%s,%s,%s\n",
                $w->id,
                str_replace('"', '""', $w->wakif?->name ?? (string) $w->wakif_user_id),
                $w->waqf_type->value,
                $w->principal_amount_idr,
                str_replace('"', '""', $w->purpose),
                $w->is_perpetual ? 'YA' : 'TIDAK',
                $w->certificate_number ?? '-',
                $w->status
            );
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="waqf_principals_report.csv"',
        ]);
    }
}
