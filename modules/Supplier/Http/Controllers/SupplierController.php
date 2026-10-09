<?php

declare(strict_types=1);

namespace Modules\Supplier\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Supplier\Application\Services\SupplierService;
use Modules\Supplier\Domain\Enums\SupplierStatus;
use Modules\Supplier\Domain\Models\Supplier;
use Modules\Supplier\Domain\Models\SupplierPriceTier;

/**
 * Pemasok (32.1–32.7): direktori, kualifikasi, harga, skor, risiko.
 */
class SupplierController extends Controller
{
    public function __construct(
        private readonly SupplierService $service,
    ) {}

    public function index(Request $request): View
    {
        $query = Supplier::query()->orderByDesc('created_at');

        if ($search = $request->get('q')) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%"));
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        return view('supplier::index', [
            'suppliers' => $query->paginate(20)->withQueryString(),
            'statuses' => SupplierStatus::cases(),
        ]);
    }

    public function show(Supplier $supplier): View
    {
        $supplier->load(['party', 'certifications', 'qualifications', 'items.priceTiers', 'scorecards', 'riskFlags', 'asns']);

        return view('supplier::show', [
            'supplier' => $supplier,
            'statuses' => SupplierStatus::cases(),
        ]);
    }

    public function create(): View
    {
        return view('supplier::create');
    }

    /** 32.1 Registrasi. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:200',
            'party_id' => 'nullable|uuid|exists:pty_parties,id',
            'kind' => 'required|in:producer,supplier,distributor,agent',
            'lead_time_days' => 'nullable|integer|min:1|max:365',
            'payment_terms_days' => 'nullable|integer|min:0|max:365',
            'owner_user_id' => 'nullable|integer|exists:users,id',
            'capabilities' => 'nullable|array',
            'capabilities.*' => 'string|max:80',
            'notes' => 'nullable|string|max:1000',
        ]);

        $supplier = $this->service->register($data, $request->user());

        return redirect()
            ->route('supplier.show', $supplier)
            ->with('success', "Pemasok [{$supplier->code}] terdaftar dengan status Kandidat.");
    }

    /** 32.2 Kualifikasi / audit lokasi (kirim ke approval). */
    public function submitQualification(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate([
            'type' => 'required|in:questionnaire,site_audit',
            'total_score' => 'required|integer|min:0|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $score = (int) $data['total_score'];

        $this->service->submitQualification(
            supplier: $supplier,
            type: $data['type'],
            answers: ['total' => $score],
            scores: ['overall' => $score],
            assessor: $request->user(),
            notes: $data['notes'] ?? null,
        );

        return back()->with('success', 'Kualifikasi diajukan (skor '.$score.') dan menunggu persetujuan.');
    }

    /** Setujui kualifikasi terakhir → status Approved. */
    public function approveQualification(Request $request, Supplier $supplier): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin() ?? false, 403);

        $qualification = $supplier->qualifications()->where('approval_status', 'pending')->firstOrFail();
        $this->service->approveQualification($qualification, $request->user());

        return back()->with('success', 'Kualifikasi disetujui — pemasok naik ke status Disetujui.');
    }

    /** Transisi status onboarding (alasan wajib). */
    public function transition(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'required|in:candidate,approved,preferred,probation,disqualified',
            'reason' => 'required|string|max:500',
        ]);

        $this->service->transition(
            supplier: $supplier,
            next: SupplierStatus::from($data['status']),
            reason: $data['reason'],
            actor: $request->user(),
        );

        return back()->with('success', 'Status pemasok diperbarui dengan riwayat.');
    }

    /** 32.1/32.5 Sertifikasi ber-masa berlaku. */
    public function storeCertification(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate([
            'type' => 'required|in:iso9001,iso22000,sni,halal,bpom,gmp,haccp,other',
            'number' => 'nullable|string|max:100',
            'issuer' => 'nullable|string|max:160',
            'issued_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
        ]);

        $supplier->certifications()->create($data);

        return back()->with('success', 'Sertifikasi terdaftar.');
    }

    /** 32.3/32.4 Harga bertingkat item pemasok. */
    public function storeItem(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate([
            'supplier_sku' => 'required|string|max:80',
            'name' => 'required|string|max:200',
            'unit' => 'nullable|string|max:32',
            'moq' => 'nullable|integer|min:1',
            'lead_time_days' => 'nullable|integer|min:1',
            'currency' => 'nullable|string|size:3',
            'internal_product_id' => 'nullable|integer',
            'unit_price' => 'required|numeric|min:0',
            'min_qty' => 'nullable|integer|min:1',
            'valid_from' => 'required|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
        ]);

        $item = $supplier->items()->firstOrCreate(
            ['supplier_sku' => $data['supplier_sku']],
            [
                'name' => $data['name'],
                'unit' => $data['unit'] ?? 'pcs',
                'moq' => $data['moq'] ?? 1,
                'lead_time_days' => $data['lead_time_days'] ?? 7,
                'currency' => strtoupper($data['currency'] ?? 'IDR'),
                'internal_product_id' => $data['internal_product_id'] ?? null,
                'is_active' => true,
            ]
        );

        $tier = SupplierPriceTier::create([
            'item_id' => $item->id,
            'min_qty' => $data['min_qty'] ?? 1,
            'unit_price' => number_format((float) $data['unit_price'], 4, '.', ''),
            'currency' => strtoupper($data['currency'] ?? 'IDR'),
            'valid_from' => $data['valid_from'],
            'valid_to' => $data['valid_to'] ?? null,
            'is_active' => true,
        ]);

        $updated = $this->service->applyReferenceCost($tier);

        return back()->with(
            'success',
            'Item & harga terdaftar.'.($updated ? ' Harga terakhir diterapkan ke MAC referensi (32.8).' : '')
        );
    }

    /** 32.6 Simpan skor periodik. */
    public function storeScorecard(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'otd_percent' => 'nullable|numeric|min:0|max:100',
            'reject_percent' => 'nullable|numeric|min:0|max:100',
            'price_index' => 'nullable|numeric|min:0|max:10',
            'response_days' => 'nullable|numeric|min:0|max:365',
            'notes' => 'nullable|string|max:500',
        ]);

        $this->service->saveScorecard($supplier, $data['period'], [
            'otd_percent' => (float) ($data['otd_percent'] ?? 100),
            'reject_percent' => (float) ($data['reject_percent'] ?? 0),
            'price_index' => (float) ($data['price_index'] ?? 1),
            'response_days' => (float) ($data['response_days'] ?? 0),
        ], $data['notes'] ?? null);

        return back()->with('success', 'Skor periodik pemasok tersimpan.');
    }

    /** 32.7 Pindai risiko (sertifikat, skor, sanksi, konsentrasi). */
    public function scanRisks(Request $request): RedirectResponse
    {
        $opened = $this->service->scanRisks();

        return back()->with(
            'success',
            count($opened).' flag risiko dibuka (sertifikat, skor rendah, sanksi, single-source).'
        );
    }
}
