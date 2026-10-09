<?php

declare(strict_types=1);

namespace Modules\Supplier\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Core\Contracts\DocumentStoreInterface;
use Modules\Supplier\Domain\Models\Supplier;
use Modules\Supplier\Domain\Models\SupplierAsn;
use Modules\Supplier\Domain\Models\SupplierDocument;

/**
 * Portal Pemasok (32.5): lihat PO, konfirmasi, kirim ASN, unggah COA.
 *
 * Akses dibatasi supplier yang dimiliki user (berdasarkan party/email),
 * sehingga pemasok tidak dapat melihat data pemasok lain (IDOR-safe).
 */
class SupplierPortalController extends Controller
{
    public function __construct(
        private readonly DocumentStoreInterface $documents,
        private readonly DocumentNumberingInterface $numbering,
    ) {}

    /** Temukan supplier yang diwakili user ini. */
    private function ownSupplier(Request $request): Supplier
    {
        /** @var User $user */
        $user = $request->user();

        // Admin boleh melihat pemasok mana pun (portal internal).
        if ($user->isAdmin()) {
            return Supplier::query()->where('is_active', true)->firstOrFail();
        }

        // Sumber kebenaran isolasi portal: supplier.owner_user_id (FK users).
        $supplier = Supplier::where('owner_user_id', $user->id)
            ->where('is_active', true)
            ->first();

        abort_if($supplier === null, 403, 'Akun Anda belum ditetapkan sebagai pemilik portal pemasok mana pun.');

        return $supplier;
    }

    public function home(Request $request): View
    {
        $supplier = $this->ownSupplier($request);

        return view('supplier::portal', [
            'supplier' => $supplier,
            'asns' => $supplier->asns()->get(),
            'purchaseOrders' => DB::table('resto_purchase_orders')
                ->where('supplier_id', DB::table('resto_suppliers')->where('party_id', $supplier->party_id)->value('id'))
                ->orderByDesc('id')
                ->limit(20)
                ->get(['id', 'number', 'status', 'grand_total', 'expected_at']),
        ]);
    }

    /** Buat ASN baru (32.5). */
    public function storeAsn(Request $request): RedirectResponse
    {
        $supplier = $this->ownSupplier($request);

        $data = $request->validate([
            'ship_date' => 'required|date',
            'expected_arrival' => 'required|date|after_or_equal:ship_date',
            'tracking_ref' => 'nullable|string|max:80',
            'lines_json' => 'required|json|max:6000',
        ]);

        $lines = json_decode($data['lines_json'], true);
        if (! is_array($lines) || $lines === []) {
            return back()->withInput()->with('error', 'Baris ASN tidak boleh kosong.');
        }

        $asnNumber = $this->numbering->nextNumber(
            entityCode: 'SUP',
            documentType: 'ASN',
            resetMonthly: true,
            customPrefix: 'ASN/{ENT}/',
        );

        SupplierAsn::create([
            'supplier_id' => $supplier->id,
            'asn_number' => $asnNumber,
            'status' => 'draft',
            'ship_date' => $data['ship_date'],
            'expected_arrival' => $data['expected_arrival'],
            'tracking_ref' => $data['tracking_ref'] ?? null,
            'lines' => $lines,
            'created_by_user_id' => $request->user()->id,
        ]);

        return back()->with('success', "ASN {$asnNumber} dibuat (draft).");
    }

    /** Tandai ASN sudah dikirim. */
    public function markShipped(Request $request, SupplierAsn $asn): RedirectResponse
    {
        $supplier = $this->ownSupplier($request);
        abort_unless($asn->supplier_id === $supplier->id, 404);
        abort_unless($asn->status === 'draft', 409, 'ASN tidak dalam status draft.');

        $asn->update(['status' => 'shipped']);

        return back()->with('success', 'ASN ditandai terkirim.');
    }

    /** Unggah sertifikat/COA via DocumentStore (32.5). */
    public function uploadDocument(Request $request): RedirectResponse
    {
        $supplier = $this->ownSupplier($request);

        $data = $request->validate([
            'file' => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png',
            'kind' => 'required|in:coa,certificate,spec,invoice,other',
            'label' => 'required|string|max:120',
        ]);

        $document = $this->documents->store(
            file: $request->file('file'),
            filename: $request->file('file')->getClientOriginalName(),
            documentType: 'SUPPLIER_'.strtoupper($data['kind']),
            documentable: $supplier,
            uploadedBy: $request->user(),
            metadata: ['supplier_code' => $supplier->code, 'kind' => $data['kind']],
            retentionYears: 7,
        );

        SupplierDocument::create([
            'supplier_id' => $supplier->id,
            'document_id' => (int) $document->id,
            'kind' => $data['kind'],
            'label' => $data['label'],
        ]);

        return back()->with('success', 'Dokumen berhasil diunggah.');
    }
}
