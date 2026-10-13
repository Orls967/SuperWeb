<?php

declare(strict_types=1);

namespace Modules\Procurement\Application\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\ApprovalEngineInterface;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Procurement\Domain\Models\BudgetCenter;
use Modules\Procurement\Domain\Models\BudgetEncumbrance;
use Modules\Procurement\Domain\Models\ImportProfile;
use Modules\Procurement\Domain\Models\PoLine;
use Modules\Procurement\Domain\Models\PoVersion;
use Modules\Procurement\Domain\Models\PurchaseOrder;
use Modules\Procurement\Domain\Models\Quote;
use Modules\Procurement\Domain\Models\Requisition;
use Modules\Procurement\Domain\Models\RequisitionLine;
use Modules\Procurement\Domain\Models\Rfq;
use Modules\Procurement\Domain\Models\RfqInvitation;
use Modules\Procurement\Domain\Models\Tender;
use Modules\Procurement\Domain\Models\TenderBid;
use Modules\Supplier\Domain\Models\Supplier;

/**
 * Procurement (Fase 33): PR → RFQ/Tender → PO, dengan encumbrance
 * anggaran (33.6), PO impor (33.5), blanket/call-off (33.4), dan
 * integrasi ShipmentBooking (33.7).
 *
 * Uang: seluruh nilai integer IDR / decimal untuk kurs; tanpa float.
 * Idempotensi: nomor dokumen gapless (26.8); PO versi & encumbrance punya
 * unique key per sumber agar retry aman.
 */
class ProcurementService
{
    public function __construct(
        private readonly DocumentNumberingInterface $numbering,
        private readonly ApprovalEngineInterface $approvals,
    ) {}

    // ── 33.6 Budget center & encumbrance ───────────────────────────────

    public function createBudgetCenter(string $code, string $name, int $annualBudgetIdr): BudgetCenter
    {
        return BudgetCenter::firstOrCreate(
            ['code' => $code],
            ['name' => $name, 'annual_budget_idr' => $annualBudgetIdr, 'is_active' => true]
        );
    }

    /**
     * Amankan (encumber) anggaran; melebihi batas → warning (bukan error) sesuai 33.6.
     *
     * @return array{encumbered_idr: int, remaining_idr: int, warning: bool}
     */
    public function encumber(BudgetCenter $center, string $sourceType, string|int $sourceId, int $amountIdr): array
    {
        if ($amountIdr <= 0) {
            throw new InvalidArgumentException('Nilai encumbrance harus lebih besar dari 0.');
        }

        return DB::transaction(function () use ($center, $sourceType, $sourceId, $amountIdr) {
            /** @var BudgetCenter $locked */
            $locked = BudgetCenter::query()->lockForUpdate()->findOrFail($center->getKey());

            // Replay-safe: encumbrance per sumber unik.
            $existing = BudgetEncumbrance::query()
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null && $existing->status === 'active') {
                return [
                    'encumbered_idr' => (int) $existing->amount_idr,
                    'remaining_idr' => $locked->remainingBudget(),
                    'warning' => $locked->remainingBudget() < 0,
                ];
            }

            $used = (int) BudgetEncumbrance::query()
                ->where('budget_center_id', $locked->id)
                ->where('status', 'active')
                ->sum('amount_idr');

            BudgetEncumbrance::create([
                'budget_center_id' => $locked->id,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'amount_idr' => $amountIdr,
                'status' => 'active',
            ]);

            $remaining = (int) $locked->annual_budget_idr - ($used + $amountIdr);

            return ['encumbered_idr' => $amountIdr, 'remaining_idr' => $remaining, 'warning' => $remaining < 0];
        });
    }

    /** Lepaskan encumbrance (PO cancelled / PR rejected). */
    public function releaseEncumbrance(string $sourceType, string|int $sourceId): bool
    {
        return DB::transaction(function () use ($sourceType, $sourceId) {
            $enc = BudgetEncumbrance::query()
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->lockForUpdate()
                ->first();

            if ($enc === null || $enc->status !== 'active') {
                return false;
            }

            $enc->status = 'released';
            $enc->save();

            return true;
        });
    }

    // ── 33.1 Purchase Requisition ──────────────────────────────────────

    /**
     * Buat PR dari kebutuhan manual/MRP/reorder-point + approval berjenjang.
     *
     * @param array{title: string, notes?: string, source?: string, budget_center_id?: int,
     *   legal_entity_id?: string, lines: array<int, array{description: string, qty?: int, unit?: string,
     *   estimated_unit_price_idr?: int, supplier_item_id?: int, currency?: string}>} $data
     */
    public function createRequisition(array $data, User $creator, bool $submitForApproval = true): Requisition
    {
        return DB::transaction(function () use ($data, $creator, $submitForApproval) {
            $number = $this->numbering->nextNumber('PRC', 'PR', false, 'PR/{ENT}/');

            $requisition = Requisition::create([
                'number' => $number,
                'title' => $data['title'],
                'notes' => $data['notes'] ?? null,
                'source' => $data['source'] ?? 'manual',
                'budget_center_id' => $data['budget_center_id'] ?? null,
                'legal_entity_id' => $data['legal_entity_id'] ?? null,
                'status' => 'draft',
                'requested_by_user_id' => $creator->id,
            ]);

            $total = 0;
            foreach ($data['lines'] ?? [] as $line) {
                $qty = max(1, (int) ($line['qty'] ?? 1));
                $unitPrice = max(0, (int) ($line['estimated_unit_price_idr'] ?? 0));
                $lineTotal = $qty * $unitPrice;

                RequisitionLine::create([
                    'requisition_id' => $requisition->id,
                    'description' => $line['description'],
                    'supplier_item_id' => $line['supplier_item_id'] ?? null,
                    'qty' => $qty,
                    'unit' => $line['unit'] ?? 'pcs',
                    'estimated_unit_price_idr' => $unitPrice,
                    'currency' => strtoupper($line['currency'] ?? 'IDR'),
                ]);

                $total += $lineTotal;
            }

            $requisition->update(['total_estimated_idr' => $total]);

            if ($submitForApproval) {
                $this->submitRequisitionForApproval($requisition, $creator);
            }

            return $requisition->fresh();
        });
    }

    /**
     * Approval berjenjang berdasar nilai & pusat biaya (33.1).
     */
    public function submitRequisitionForApproval(Requisition $requisition, User $creator): object
    {
        $steps = [['role' => 'procurement']];
        if ((int) $requisition->total_estimated_idr >= 50_000_000) {
            $steps[] = ['role' => 'admin'];
        }

        $approval = $this->approvals->submit(
            approvalType: 'PURCHASE_REQUISITION',
            title: "PR {$requisition->number}: {$requisition->title}",
            creator: $creator,
            approvable: $requisition,
            amount: (float) (int) $requisition->total_estimated_idr,
            steps: $steps,
            slaHours: 72,
            metadata: [
                'pr_number' => $requisition->number,
                'budget_center_id' => $requisition->budget_center_id,
                'source' => $requisition->source,
            ]
        );

        $requisition->update(['status' => 'pending_approval', 'approval_id' => $approval->id]);

        return $approval;
    }

    /** Setujui PR → status approved + encumber anggaran. */
    public function approveRequisition(Requisition $requisition): Requisition
    {
        return DB::transaction(function () use ($requisition) {
            /** @var Requisition $locked */
            $locked = Requisition::query()->lockForUpdate()->findOrFail($requisition->getKey());

            if ($locked->status !== 'pending_approval') {
                return $locked;
            }

            $locked->status = 'approved';
            $locked->approved_at = now();
            $locked->save();

            if ($locked->budget_center_id !== null && (int) $locked->total_estimated_idr > 0) {
                $center = BudgetCenter::find($locked->budget_center_id);
                if ($center !== null) {
                    $this->encumber($center, 'pr', $locked->id, (int) $locked->total_estimated_idr);
                }
            }

            return $locked->fresh();
        });
    }

    // ── 33.2 RFQ multi-pemasok ─────────────────────────────────────────

    /**
     * Buat RFQ & undang sejumlah pemasok.
     *
     * @param  array<int, string>  $supplierIds
     */
    public function createRfq(array $data, array $supplierIds, User $creator): Rfq
    {
        if ($supplierIds === []) {
            throw new InvalidArgumentException('RFQ minimal mengundang satu pemasok.');
        }

        return DB::transaction(function () use ($data, $supplierIds, $creator) {
            $rfq = Rfq::create([
                'number' => $this->numbering->nextNumber('PRC', 'RFQ', false, 'RFQ/{ENT}/'),
                'requisition_id' => $data['requisition_id'] ?? null,
                'title' => $data['title'],
                'status' => 'open',
                'opens_at' => $data['opens_at'] ?? now(),
                'closes_at' => $data['closes_at'] ?? now()->addDays(7),
                'terms' => $data['terms'] ?? null,
                'created_by_user_id' => $creator->id,
            ]);

            foreach (array_unique($supplierIds) as $supplierId) {
                RfqInvitation::firstOrCreate(
                    ['rfq_id' => $rfq->id, 'supplier_id' => $supplierId],
                    ['status' => 'invited']
                );
            }

            return $rfq;
        });
    }

    /** Pemasok mengirim penawaran (idempoten per pemasok). */
    public function submitQuote(Rfq $rfq, Supplier $supplier, array $data): Quote
    {
        if ($rfq->status !== 'open') {
            throw new InvalidArgumentException('RFQ tidak terbuka untuk penawaran.');
        }

        return DB::transaction(function () use ($rfq, $supplier, $data) {
            $invitation = RfqInvitation::query()
                ->where('rfq_id', $rfq->id)
                ->where('supplier_id', $supplier->id)
                ->lockForUpdate()
                ->first();

            if ($invitation === null) {
                throw new InvalidArgumentException('Pemasok tidak diundang pada RFQ ini.');
            }

            $quote = Quote::updateOrCreate(
                ['rfq_id' => $rfq->id, 'supplier_id' => $supplier->id],
                [
                    'total_price_idr' => max(0, (int) ($data['total_price_idr'] ?? 0)),
                    'currency' => strtoupper($data['currency'] ?? 'IDR'),
                    'lead_time_days' => max(1, (int) ($data['lead_time_days'] ?? 7)),
                    'payment_terms' => $data['payment_terms'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]
            );

            $invitation->update(['status' => 'quoted']);

            return $quote;
        });
    }

    /**
     * Matriks perbandingan penawaran: harga, lead time, skor pemasok (33.2).
     *
     * @return array<int, array{supplier_id: string, supplier_name: string, price_idr: int,
     *   lead_time_days: int, supplier_score: int, composite_score: float}>
     */
    public function compareQuotes(Rfq $rfq): array
    {
        $quotes = Quote::where('rfq_id', $rfq->id)->with('supplier')->get();

        if ($quotes->isEmpty()) {
            return [];
        }

        $minPrice = max(1, (int) $quotes->min('total_price_idr'));
        $minLead = max(1, (int) $quotes->min('lead_time_days'));

        return $quotes->map(function (Quote $quote) use ($minPrice, $minLead) {
            $priceIdr = (int) $quote->total_price_idr;
            $lead = max(1, (int) $quote->lead_time_days);

            // Skor pemasok terakhir (32.6) — fallback 80 bila belum ada.
            $supplierScore = (int) ($quote->supplier?->scorecards()->orderByDesc('period')->value('overall_score') ?? 80);

            // Skor komposit: harga 50%, lead time 20%, pemasok 30% (lebih tinggi lebih baik).
            $priceScore = (100 * $minPrice) / max(1, $priceIdr);
            $leadScore = (100 * $minLead) / $lead;
            $composite = ($priceScore * 0.5) + ($leadScore * 0.2) + ($supplierScore * 0.3);

            return [
                'supplier_id' => (string) $quote->supplier_id,
                'supplier_name' => (string) ($quote->supplier?->name ?? '-'),
                'price_idr' => $priceIdr,
                'lead_time_days' => $lead,
                'supplier_score' => $supplierScore,
                'composite_score' => round($composite, 2),
            ];
        })->sortByDesc('composite_score')->values()->all();
    }

    /** Pilih pemenang RFQ dengan alasan tercatat (33.2). */
    public function awardQuote(Rfq $rfq, Quote $quote, string $reason, User $actor): Quote
    {
        if (! in_array($rfq->status, ['open', 'evaluating'], true)) {
            throw new InvalidArgumentException('RFQ harus terbuka/evaluasi untuk penetapan pemenang.');
        }

        if ($rfq->id !== $quote->rfq_id) {
            throw new InvalidArgumentException('Penawaran bukan milik RFQ ini.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('Alasan penetapan wajib dicatat (jejak audit).');
        }

        return DB::transaction(function () use ($rfq, $quote, $reason) {
            Quote::where('rfq_id', $rfq->id)->where('id', '!=', $quote->id)
                ->update(['is_selected' => false]);

            $quote->update(['is_selected' => true, 'selection_reason' => $reason]);
            $rfq->update(['status' => 'awarded']);

            return $quote->fresh();
        });
    }

    // ── 33.3 Tender: segel, evaluasi berbobot, penetapan ───────────────

    /**
     * Buat tender (tertutup/terbuka) dengan periode bidding & kriteria berbobot.
     *
     * @param array{title: string, type?: string, bids_open_at?: string, bids_close_at: string,
     *   criteria?: array<string, int>} $data
     */
    public function createTender(array $data, User $creator): Tender
    {
        $criteria = $data['criteria'] ?? ['price' => 60, 'lead_time' => 25, 'supplier' => 15];

        if (array_sum($criteria) !== 100) {
            throw new InvalidArgumentException('Total bobot kriteria evaluasi harus 100%.');
        }

        return Tender::create([
            'number' => $this->numbering->nextNumber('PRC', 'TDR', false, 'TDR/{ENT}/'),
            'title' => $data['title'],
            'type' => $data['type'] ?? 'closed',
            'status' => 'bidding',
            'bids_open_at' => Carbon::parse($data['bids_open_at'] ?? now()),
            'bids_close_at' => Carbon::parse($data['bids_close_at']),
            'criteria' => $criteria,
            'created_by_user_id' => $creator->id,
        ]);
    }

    /**
     * Peserta mengirim segel penawaran (hash SHA-256) sebelum tenggat (blind).
     * Isi hanya terbaca setelah tender dibuka.
     */
    public function sealBid(Tender $tender, Supplier $supplier, array $offer): TenderBid
    {
        if ($tender->status !== 'bidding') {
            throw new InvalidArgumentException('Tender tidak dalam masa penawaran.');
        }

        if (now()->greaterThan($tender->bids_close_at)) {
            throw new InvalidArgumentException('Tenggat penawaran telah lewat.');
        }

        return DB::transaction(function () use ($tender, $supplier, $offer) {
            $existing = TenderBid::query()
                ->where('tender_id', $tender->id)
                ->where('supplier_id', $supplier->id)
                ->lockForUpdate()
                ->first();

            $sealedAt = now();
            $hash = hash('sha256', json_encode($offer, JSON_UNESCAPED_SLASHES).'|'.$sealedAt->toIso8601String());

            if ($existing !== null) {
                // Segel hanya boleh diubah sebelum tender dibuka (addendum/replace).
                if ($tender->status !== 'bidding') {
                    throw new InvalidArgumentException('Segel sudah terkunci.');
                }

                $existing->update(['seal_hash' => $hash, 'sealed_at' => $sealedAt, 'offer' => $offer]);

                return $existing;
            }

            return TenderBid::create([
                'tender_id' => $tender->id,
                'supplier_id' => $supplier->id,
                'seal_hash' => $hash,
                'sealed_at' => $sealedAt,
                'offer' => $offer,
            ]);
        });
    }

    /** Buka semua segel bersamaan (setelah tenggat) — 33.3. */
    public function openBids(Tender $tender): int
    {
        if (now()->lessThan($tender->bids_close_at)) {
            throw new InvalidArgumentException('Tenggat penawaran belum tiba; segel belum dapat dibuka.');
        }

        return DB::transaction(function () use ($tender) {
            /** @var Tender $locked */
            $locked = Tender::query()->lockForUpdate()->findOrFail($tender->getKey());

            if (! in_array($locked->status, ['bidding', 'opened'], true)) {
                return 0;
            }

            $opened = 0;
            foreach (TenderBid::where('tender_id', $locked->id)->whereNull('opened_at')->get() as $bid) {
                $bid->update(['opened_at' => now()]);
                $opened++;
            }

            $locked->status = 'opened';
            $locked->save();

            return $opened;
        });
    }

    /**
     * Evaluasi berbobot: harga (rendah menang), lead time, skor pemasok.
     * Hanya setelah semua segel dibuka.
     *
     * @return array<int, array{bid_id: int, supplier_name: string, total_score: float}>
     */
    public function evaluateTender(Tender $tender): array
    {
        $bids = TenderBid::where('tender_id', $tender->id)->with('supplier')->get();

        $unopened = $bids->filter(fn (TenderBid $bid) => $bid->opened_at === null);
        if ($unopened->isNotEmpty()) {
            throw new InvalidArgumentException('Masih ada segel yang belum dibuka — evaluasi butuh buka bersamaan.');
        }

        if ($bids->isEmpty()) {
            return [];
        }

        $criteria = $tender->criteria ?? [];
        $weightPrice = (int) ($criteria['price'] ?? 60);
        $weightLead = (int) ($criteria['lead_time'] ?? 25);
        $weightSupplier = (int) ($criteria['supplier'] ?? 15);

        $minPrice = max(1, (int) collect($bids->map(fn (TenderBid $bid) => (int) ($bid->offer['total_price_idr'] ?? 0)))->min());
        $minLead = max(1, (int) collect($bids->map(fn (TenderBid $bid) => (int) ($bid->offer['lead_time_days'] ?? 7)))->min());

        $results = [];

        foreach ($bids as $bid) {
            $price = max(1, (int) ($bid->offer['total_price_idr'] ?? 0));
            $lead = max(1, (int) ($bid->offer['lead_time_days'] ?? 7));
            $supplierScore = (int) ($bid->supplier?->scorecards()->orderByDesc('period')->value('overall_score') ?? 80);

            $priceScore = (100 * $minPrice) / $price;
            $leadScore = (100 * $minLead) / $lead;
            $total = round(
                ($priceScore * $weightPrice / 100)
                + ($leadScore * $weightLead / 100)
                + ($supplierScore * $weightSupplier / 100),
                4
            );

            $bid->update(['total_score' => $total]);
            $results[] = [
                'bid_id' => (int) $bid->id,
                'supplier_name' => (string) ($bid->supplier?->name ?? '-'),
                'total_score' => $total,
            ];
        }

        usort($results, fn ($a, $b) => $b['total_score'] <=> $a['total_score']);

        TenderBid::where('tender_id', $tender->id)->update(['is_winner' => false]);
        $winner = TenderBid::find($results[0]['bid_id'] ?? 0);
        if ($winner !== null) {
            $winner->update(['is_winner' => true, 'total_score' => $results[0]['total_score']]);
        }

        $tender->update(['status' => 'evaluated']);

        return $results;
    }

    /**
     * Tetapkan pemenang tender via approval (four-eyes) lalu tandai bid.
     */
    public function awardTender(Tender $tender, TenderBid $bid, User $actor, string $reason = ''): TenderBid
    {
        if ($tender->status !== 'evaluated') {
            throw new InvalidArgumentException('Tender harus dievaluasi sebelum penetapan pemenang.');
        }

        if ($bid->tender_id !== $tender->id) {
            throw new InvalidArgumentException('Bid bukan milik tender ini.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('Alasan penetapan pemenang wajib dicatat.');
        }

        return DB::transaction(function () use ($tender, $bid, $actor, $reason) {
            $approval = $this->approvals->submit(
                approvalType: 'TENDER_AWARD',
                title: "Penetapan pemenang {$tender->number}: {$bid->supplier?->name}",
                creator: $actor,
                approvable: $tender,
                amount: (float) (int) ($bid->offer['total_price_idr'] ?? 0),
                steps: [['role' => 'procurement'], ['role' => 'admin']],
                slaHours: 72,
                metadata: [
                    'tender_number' => $tender->number,
                    'bid_id' => $bid->id,
                    'reason' => $reason,
                    'total_score' => $bid->total_score,
                ]
            );

            $bid->update(['approval_id' => $approval->id, 'notes' => $reason]);
            $tender->update(['status' => 'awarded']);

            return $bid->fresh();
        });
    }

    // ── 33.4 Purchase Order (standard, blanket, call-off, versi) ────────

    /**
     * Buat PO dari PR/RFQ/tender/kontrak, lalu encumber anggaran.
     *
     * @param array{supplier_id: string, title: string, kind?: string, requisition_id?: string,
     *   rfq_id?: string, tender_id?: string, contract_id?: string, blanket_po_id?: string,
     *   budget_center_id?: int, currency?: string, expected_date?: string, notes?: string,
     *   lines: array<int, array{description: string, qty?: int, unit?: string, unit_price?: int}>} $data
     */
    public function createPurchaseOrder(array $data, User $creator): PurchaseOrder
    {
        if (empty($data['lines'])) {
            throw new InvalidArgumentException('PO minimal memiliki satu baris.');
        }

        return DB::transaction(function () use ($data, $creator) {
            $kind = $data['kind'] ?? 'standard';

            $po = PurchaseOrder::create([
                'number' => $this->numbering->nextNumber('PRC', 'PO', false, 'PO/{ENT}/'),
                'supplier_id' => $data['supplier_id'],
                'requisition_id' => $data['requisition_id'] ?? null,
                'rfq_id' => $data['rfq_id'] ?? null,
                'tender_id' => $data['tender_id'] ?? null,
                'contract_id' => $data['contract_id'] ?? null,
                'blanket_po_id' => $data['blanket_po_id'] ?? null,
                'title' => $data['title'],
                'kind' => $kind,
                'status' => 'draft',
                'currency' => strtoupper($data['currency'] ?? 'IDR'),
                'expected_date' => $data['expected_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'budget_center_id' => $data['budget_center_id'] ?? null,
                'created_by_user_id' => $creator->id,
            ]);

            $total = 0;
            foreach ($data['lines'] as $line) {
                $qty = max(1, (int) ($line['qty'] ?? 1));
                $unitPrice = max(0, (int) ($line['unit_price'] ?? 0));
                $lineTotal = $qty * $unitPrice;

                PoLine::create([
                    'po_id' => $po->id,
                    'description' => $line['description'],
                    'qty' => $qty,
                    'unit' => $line['unit'] ?? 'pcs',
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ]);

                $total += $lineTotal;
            }

            $po->update(['total_amount' => $total]);

            // 33.6 encumbrance — PO mengunci anggaran (warning bila melebihi).
            if ($po->budget_center_id !== null && $total > 0) {
                $center = BudgetCenter::find($po->budget_center_id);
                if ($center !== null) {
                    $enc = $this->encumber($center, 'po', $po->id, $total);
                    $po->update(['encumbrance_id' => $enc['encumbered_idr']]);
                }
            }

            // 33.4 rekam versi awal.
            $this->recordPoVersion($po, 'Pembuatan PO', 'recorded');

            return $po->fresh(['lines']);
        });
    }

    /**
     * Rekam snapshot PO (versi riwayat).
     *
     * `prc_purchase_orders.version` adalah revisi bisnis (naik saat revisi),
     * sedangkan nomor snapshot di sini adalah ordinal baris riwayat (max+1)
     * sehingga close/cancel tanpa revisi tetap tidak melanggar
     * unique(po_id, version).
     */
    public function recordPoVersion(PurchaseOrder $po, string $changeSummary, string $status = 'recorded', ?User $actor = null): PoVersion
    {
        $next = ((int) $po->versions()->max('version')) + 1;

        return PoVersion::create([
            'po_id' => $po->id,
            'version' => $next,
            'change_summary' => $changeSummary,
            'snapshot' => $po->getAttributes(),
            'status' => $status,
            'created_by_user_id' => $actor?->id,
        ]);
    }

    /**
     * Ubah PO → versi baru via approval (33.4).
     *
     * @param  array{title?: string, expected_date?: string, notes?: string, lines?: array}  $changes
     */
    public function revisePurchaseOrder(PurchaseOrder $po, array $changes, User $actor, string $reason): object
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Alasan revisi PO wajib dicatat.');
        }

        if (in_array($po->status, ['received', 'closed', 'cancelled'], true)) {
            throw new InvalidArgumentException('PO tidak dapat direvisi dalam status ini.');
        }

        return DB::transaction(function () use ($po, $changes, $actor, $reason) {
            $approval = $this->approvals->submit(
                approvalType: 'PO_REVISION',
                title: "Revisi PO {$po->number}: {$reason}",
                creator: $actor,
                approvable: $po,
                amount: (float) (int) $po->total_amount,
                steps: [['role' => 'procurement'], ['role' => 'admin']],
                slaHours: 48,
                metadata: ['po_number' => $po->number, 'reason' => $reason, 'changes' => array_keys($changes)],
            );

            $po->update([
                'version' => (int) $po->version + 1,
                'title' => $changes['title'] ?? $po->title,
                'expected_date' => $changes['expected_date'] ?? $po->expected_date,
                'notes' => $changes['notes'] ?? $po->notes,
            ]);

            $this->recordPoVersion($po, $reason, 'pending', $actor);

            return $approval;
        });
    }

    /** Tutup PO (33.4) dan lepas sisa encumbrance. */
    public function closePurchaseOrder(PurchaseOrder $po, string $reason): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $reason) {
            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($po->getKey());

            if (in_array($locked->status, ['closed', 'cancelled'], true)) {
                return $locked;
            }

            $locked->status = 'closed';
            $locked->closed_at = now();
            $locked->notes = trim((string) $locked->notes."\n[Closed] {$reason}");
            $locked->save();

            $this->recordPoVersion($locked, "Close: {$reason}");

            if ($locked->encumbrance_id !== null) {
                $this->releaseEncumbrance('po', $locked->id);
            }

            return $locked->fresh();
        });
    }

    /** Batalkan PO dan lepas encumbrance. */
    public function cancelPurchaseOrder(PurchaseOrder $po, string $reason): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $reason) {
            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($po->getKey());

            if ($locked->status === 'cancelled') {
                return $locked;
            }

            $locked->status = 'cancelled';
            $locked->notes = trim((string) $locked->notes."\n[Cancelled] {$reason}");
            $locked->save();

            $this->recordPoVersion($locked, "Cancel: {$reason}");

            $this->releaseEncumbrance('po', $locked->id);

            return $locked->fresh();
        });
    }

    /**
     * Buat blanket PO lalu call-off (jenis berbeda, rujuk induk).
     */
    public function createBlanketAndCallOff(
        array $blanketData,
        array $callOffData,
        User $creator
    ): array {
        $blanketData['kind'] = 'blanket';
        $blanket = $this->createPurchaseOrder($blanketData, $creator);

        $callOffData['kind'] = 'call_off';
        $callOffData['blanket_po_id'] = $blanket->id;
        $callOffData['supplier_id'] = $blanketData['supplier_id'];
        $callOff = $this->createPurchaseOrder($callOffData, $creator);

        return [$blanket, $callOff];
    }

    // ── 33.5 PO impor (Incoterm, kurs, landed cost — simulasi) ──────────

    /**
     * Profil impor untuk PO: Incoterm, pelabuhan, estimasi landed cost.
     *
     * @param array{currency?: string, incoterm?: string, fx_rate?: float, origin_port?: string,
     *   destination_port?: string, freight_estimate_idr?: int, insurance_estimate_idr?: int,
     *   duty_estimate_idr?: int, notes?: string} $data
     */
    public function createImportProfile(PurchaseOrder $po, array $data): ImportProfile
    {
        $po->update(['kind' => 'import']);

        $freight = max(0, (int) ($data['freight_estimate_idr'] ?? 0));
        $insurance = max(0, (int) ($data['insurance_estimate_idr'] ?? 0));
        $duty = max(0, (int) ($data['duty_estimate_idr'] ?? 0));

        $landed = (int) $po->total_amount + $freight + $insurance + $duty;

        return ImportProfile::firstOrCreate(
            ['po_id' => $po->id],
            [
                'currency' => strtoupper($data['currency'] ?? 'USD'),
                'incoterm' => strtoupper($data['incoterm'] ?? 'FOB'),
                'fx_rate' => $data['fx_rate'] ?? 1,
                'origin_port' => $data['origin_port'] ?? null,
                'destination_port' => $data['destination_port'] ?? null,
                'freight_estimate_idr' => $freight,
                'insurance_estimate_idr' => $insurance,
                'duty_estimate_idr' => $duty,
                'landed_cost_estimate_idr' => $landed,
                'notes' => $data['notes'] ?? null,
            ]
        );
    }

    // ── 33.8 Dashboard procurement ──────────────────────────────────────

    /**
     * @return array{open_pr: int, open_po: int, overdue_po: int, rfq_open: int,
     *   spend_idr: int, encumbered_idr: int, budget_warning: bool}
     */
    public function dashboard(): array
    {
        $openPr = Requisition::whereIn('status', ['draft', 'pending_approval', 'approved'])->count();
        $openPo = PurchaseOrder::whereIn('status', ['draft', 'sent', 'acknowledged', 'partially_received'])->count();
        $overduePo = PurchaseOrder::whereIn('status', ['sent', 'acknowledged', 'partially_received'])
            ->whereDate('expected_date', '<', now()->toDateString())
            ->count();
        $rfqOpen = Rfq::where('status', 'open')->count();
        $spend = (int) PurchaseOrder::whereIn('status', ['sent', 'acknowledged', 'partially_received', 'received', 'closed'])->sum('total_amount');
        $encumbered = (int) BudgetEncumbrance::where('status', 'active')->sum('amount_idr');

        $budgetWarning = false;
        foreach (BudgetCenter::where('is_active', true)->get() as $center) {
            if ($center->remainingBudget() < 0) {
                $budgetWarning = true;
                break;
            }
        }

        return [
            'open_pr' => $openPr,
            'open_po' => $openPo,
            'overdue_po' => $overduePo,
            'rfq_open' => $rfqOpen,
            'spend_idr' => $spend,
            'encumbered_idr' => $encumbered,
            'budget_warning' => $budgetWarning,
        ];
    }
}
