<?php

declare(strict_types=1);

namespace Modules\Procurement\Application\Services;

use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Core\Contracts\ApprovalEngineInterface;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Procurement\Domain\Models\ApEntry;
use Modules\Procurement\Domain\Models\CreditMemo;
use Modules\Procurement\Domain\Models\Inspection;
use Modules\Procurement\Domain\Models\LandedCost;
use Modules\Procurement\Domain\Models\PaymentBatch;
use Modules\Procurement\Domain\Models\PaymentItem;
use Modules\Procurement\Domain\Models\PoLine;
use Modules\Procurement\Domain\Models\PurchaseOrder;
use Modules\Procurement\Domain\Models\ReceivingLine;
use Modules\Procurement\Domain\Models\ReceivingReport;
use Modules\Procurement\Domain\Models\SupplierAdvance;
use Modules\Procurement\Domain\Models\SupplierInvoice;
use Modules\Procurement\Domain\Models\SupplierReturn;
use Modules\Procurement\Domain\Models\ThreeWayMatch;
use Modules\Supplier\Domain\Models\Supplier;

/**
 * Penerimaan barang, hutang usaha, dan pembayaran pemasok (Fase 34).
 *
 * Semua mutasi DB dan posting ledger berada dalam transaksi yang sama.
 * Nilai IDR integer; pajak PPN/PPh dan diskon dini merupakan simulasi.
 */
class ReceivingService
{
    public const GRIR = 'inv:grir';

    public const PPN_INPUT = 'ap:ppn_input:IDR';

    public const PPH23_WITHHELD = 'ap:pph23_withheld:IDR';

    public const PURCHASE_PRICE_VARIANCE = 'expense:purchase_price_variance:IDR';

    public const SUPPLIER_AP_PREFIX = 'ap:supplier:';

    public function __construct(
        private readonly DocumentNumberingInterface $numbering,
        private readonly Ledger $ledger,
        private readonly InventoryService $inventory,
        private readonly ApprovalEngineInterface $approvals,
    ) {}

    // ── 34.1 Goods Receipt Note ──────────────────────────────────────────

    /**
     * Penerimaan parsial/berkali terhadap PO. Lot/batch + kedaluwarsa tersimpan
     * pada ReceivingLine; stok masuk lewat InventoryService (kontrak, tanpa
     * import Domain). Over-delivery diizinkan sampai toleransi.
     *
     * @param array<int, array{po_line_id: int, received_qty: int, accepted_qty?: int,
     *   rejected_qty?: int, lot_number?: string, expiry_date?: string,
     *   product_id?: int, warehouse_code?: string}> $lines
     */
    public function receive(
        PurchaseOrder $po,
        array $lines,
        User $receiver,
        int $overDeliveryTolerancePct = 5
    ): ReceivingReport {
        if ($lines === []) {
            throw new InvalidArgumentException('GRN minimal memiliki satu baris penerimaan.');
        }

        return DB::transaction(function () use ($po, $lines, $receiver, $overDeliveryTolerancePct) {
            /** @var PurchaseOrder $lockedPo */
            $lockedPo = PurchaseOrder::query()->lockForUpdate()->findOrFail($po->getKey());

            if (in_array($lockedPo->status, ['cancelled', 'closed'], true)) {
                throw new InvalidArgumentException('PO tertutup/batal tidak menerima barang.');
            }

            $grn = ReceivingReport::create([
                'number' => $this->numbering->nextNumber('PRC', 'GRN', false, 'GRN/{ENT}/'),
                'po_id' => $lockedPo->id,
                'status' => 'open',
                'received_at' => now()->toDateString(),
                'received_by_user_id' => $receiver->id,
            ]);

            $acceptedTotal = 0;
            $grirTotal = 0;

            foreach ($lines as $lineData) {
                /** @var PoLine $poLine */
                $poLine = PoLine::query()
                    ->where('po_id', $lockedPo->id)
                    ->lockForUpdate()
                    ->findOrFail((int) $lineData['po_line_id']);

                $received = max(0, (int) $lineData['received_qty']);
                if ($received === 0) {
                    continue;
                }

                $accepted = max(0, min($received, (int) ($lineData['accepted_qty'] ?? $received)));
                $rejected = max(0, $received - $accepted);

                $ordered = (int) $poLine->qty;
                $cumulative = (int) $poLine->received_qty + $received;
                $maxAllowed = (int) ceil($ordered * (100 + $overDeliveryTolerancePct) / 100);

                if ($cumulative > $maxAllowed) {
                    throw new InvalidArgumentException(
                        "Penerimaan baris PO melebihi toleransi {$overDeliveryTolerancePct}% (maks {$maxAllowed}, diterima kumulatif {$cumulative})."
                    );
                }

                $grnLine = ReceivingLine::create([
                    'grn_id' => $grn->id,
                    'po_line_id' => $poLine->id,
                    'description' => $poLine->description,
                    'ordered_qty' => $ordered,
                    'received_qty' => $received,
                    'accepted_qty' => $accepted,
                    'rejected_qty' => $rejected,
                    'lot_number' => $lineData['lot_number'] ?? null,
                    'expiry_date' => $lineData['expiry_date'] ?? null,
                    'warehouse_code' => $lineData['warehouse_code'] ?? null,
                    'product_id' => $lineData['product_id'] ?? null,
                ]);

                $poLine->received_qty = $cumulative;
                $poLine->save();

                $lineValue = $accepted * (int) $poLine->unit_price;
                $acceptedTotal += $lineValue;
                $grirTotal += $lineValue;

                // 34.2 Inspeksi (hook QMS Fase 39): unit ditolak → karantina.
                Inspection::create([
                    'grn_id' => $grn->id,
                    'receiving_line_id' => $grnLine->id,
                    'result' => $rejected > 0 ? 'failed' : 'passed',
                    'quarantine' => $rejected > 0,
                    'findings' => $rejected > 0 ? "{$rejected} unit ditolak/karantina (hook QMS Fase 39)." : null,
                    'inspected_by_user_id' => $receiver->id,
                ]);

                // Inventory contract only — tanpa import Domain Inventory.
                if ($accepted > 0 && ($lineData['product_id'] ?? null) !== null) {
                    $this->inventory->adjust(
                        productId: (int) $lineData['product_id'],
                        qty: $accepted,
                        reason: StockMovementReason::PURCHASE,
                        sourceType: ReceivingReport::class,
                        sourceId: (int) $grn->getKey(),
                        note: "GRN {$grn->number}; lot ".($lineData['lot_number'] ?? '-'),
                        userId: $receiver->id,
                    );
                }

                // 34.2 Retur ke pemasok (debit note).
                if ($rejected > 0) {
                    $returnAmount = $rejected * (int) $poLine->unit_price;
                    $return = SupplierReturn::create([
                        'grn_id' => $grn->id,
                        'number' => $this->numbering->nextNumber('PRC', 'RTN', false, 'RTN/{ENT}/'),
                        'qty' => $rejected,
                        'amount_idr' => $returnAmount,
                        'reason' => "Barang ditolak pada GRN {$grn->number}",
                        'status' => 'issued',
                    ]);

                    $this->recordApEntry(
                        invoiceId: null,
                        supplierId: $lockedPo->supplier_id,
                        kind: 'debit_note',
                        amountIdr: $returnAmount,
                        direction: 'debit',
                        reference: $return->number,
                    );
                }
            }

            if ($grirTotal > 0) {
                $this->ensureAccounts($lockedPo->supplier_id);
                $grirAccount = $this->account(self::GRIR, 'GR/IR Clearing', AccountKind::LIABILITY);
                $inventoryAccount = $this->account('inventory:procurement:IDR', 'Persediaan Pembelian', AccountKind::INVENTORY);

                $this->ledger->post(new PostingDTO(
                    type: TransactionType::SUPPLIER_PAYABLE->value,
                    description: "GRN {$grn->number} PO {$lockedPo->number}",
                    idempotencyKey: 'proc:grn:'.$grn->id,
                    entries: [
                        PostingEntryDTO::forAccount($inventoryAccount->id, 'IDR', BigDecimal::of($grirTotal)),
                        PostingEntryDTO::forAccount($grirAccount->id, 'IDR', BigDecimal::of($grirTotal)->negated()),
                    ],
                    referenceType: ReceivingReport::class,
                    referenceId: $grn->getKey(),
                    meta: ['po_id' => $lockedPo->id, 'grn_id' => $grn->id, 'accepted_value_idr' => $grirTotal],
                    createdBy: $receiver->id,
                    postedAt: now(),
                ));

                $this->recordApEntry(null, $lockedPo->supplier_id, 'gr_ir', $grirTotal, 'credit', $grn->number);
            }

            $lockedPo->received_amount = (int) $lockedPo->received_amount + $acceptedTotal;
            $allReceived = $lockedPo->lines()->whereColumn('received_qty', '<', 'qty')->doesntExist();
            $lockedPo->status = $allReceived ? 'received' : 'partially_received';
            $lockedPo->save();

            $grn->status = 'accepted';
            $grn->save();

            return $grn->fresh(['lines', 'inspections', 'returns']);
        });
    }

    // ── 34.3 Vendor invoice + 3-way match ───────────────────────────────

    /**
     * Invoice pemasok + match PO–GRN–Invoice.
     * Variance melewati toleransi → held + approval.
     *
     * @return array{invoice: SupplierInvoice, held: bool, price_variance_pct: float, qty_variance_pct: float}
     */
    public function matchInvoice(
        Supplier $supplier,
        PurchaseOrder $po,
        ReceivingReport $grn,
        string $invoiceNumber,
        int $invoiceAmountIdr,
        User $creator,
        int $priceTolerancePct = 2,
        int $qtyTolerancePct = 5
    ): array {
        if ($po->supplier_id !== $supplier->id || $grn->po_id !== $po->id) {
            throw new InvalidArgumentException('Supplier/PO/GRN tidak cocok.');
        }

        if ($invoiceAmountIdr < 0) {
            throw new InvalidArgumentException('Nominal invoice tidak boleh negatif.');
        }

        return DB::transaction(function () use ($supplier, $po, $grn, $invoiceNumber, $invoiceAmountIdr, $creator, $priceTolerancePct, $qtyTolerancePct) {
            /** @var PurchaseOrder $lockedPo */
            $lockedPo = PurchaseOrder::query()->lockForUpdate()->findOrFail($po->getKey());
            /** @var ReceivingReport $lockedGrn */
            $lockedGrn = ReceivingReport::query()->lockForUpdate()->findOrFail($grn->getKey());

            $poAmount = (int) $lockedPo->total_amount;
            $grnAmount = (int) $lockedPo->received_amount;
            $priceVariance = $invoiceAmountIdr - $poAmount;
            $priceVariancePct = $poAmount > 0 ? abs($priceVariance) * 100 / $poAmount : 0;
            $qtyVariancePct = $poAmount > 0 ? abs($poAmount - $grnAmount) * 100 / $poAmount : 0;

            $priceHold = $priceVariancePct > $priceTolerancePct;
            $qtyHold = $qtyVariancePct > $qtyTolerancePct;
            $held = $priceHold || $qtyHold;

            $invoice = SupplierInvoice::create([
                'number' => $invoiceNumber,
                'supplier_id' => $supplier->id,
                'po_id' => $lockedPo->id,
                'status' => $held ? 'held' : 'matched',
                'invoice_amount_idr' => $invoiceAmountIdr,
                'matched_amount_idr' => $grnAmount,
                'variance_idr' => $priceVariance,
                'price_tolerance_pct' => $priceTolerancePct,
                'qty_tolerance_pct' => $qtyTolerancePct,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays((int) $supplier->payment_terms_days)->toDateString(),
                'currency' => 'IDR',
                'created_by_user_id' => $creator->id,
                'notes' => $held ? 'Hold otomatis 3-way match; butuh review & approval.' : null,
            ]);

            ThreeWayMatch::create([
                'invoice_id' => $invoice->id,
                'grn_id' => $lockedGrn->id,
                'po_id' => $lockedPo->id,
                'result' => $held ? ($priceHold ? 'price_hold' : 'qty_hold') : 'matched',
                'po_amount_idr' => $poAmount,
                'grn_amount_idr' => $grnAmount,
                'invoice_amount_idr' => $invoiceAmountIdr,
                'price_variance_idr' => $priceVariance,
                'qty_variance_pct' => (int) round($qtyVariancePct),
                'notes' => $held ? "price variance {$priceVariancePct}% / qty variance {$qtyVariancePct}%" : null,
            ]);

            if ($held) {
                $approval = $this->approvals->submit(
                    approvalType: 'SUPPLIER_INVOICE_VARIANCE',
                    title: "3-way match hold invoice {$invoiceNumber}",
                    creator: $creator,
                    approvable: $invoice,
                    amount: $invoiceAmountIdr,
                    steps: [['role' => 'procurement'], ['role' => 'admin']],
                    slaHours: 48,
                    metadata: [
                        'invoice_id' => $invoice->id,
                        'po_id' => $lockedPo->id,
                        'grn_id' => $lockedGrn->id,
                        'price_variance_idr' => $priceVariance,
                        'qty_variance_pct' => $qtyVariancePct,
                    ],
                );
                $invoice->update(['notes' => ($invoice->notes ?? '')." approval={$approval->uuid}"]);
            } else {
                $this->postInvoiceAp($invoice, $supplier, $poAmount, $invoiceAmountIdr, (int) $creator->id);
            }

            return ['invoice' => $invoice->fresh(), 'held' => $held, 'price_variance_pct' => $priceVariancePct, 'qty_variance_pct' => $qtyVariancePct];
        });
    }

    /** Finalisasi invoice yang sebelumnya held, sesudah approval. */
    public function approveHeldInvoice(SupplierInvoice $invoice, User $approver): SupplierInvoice
    {
        return DB::transaction(function () use ($invoice, $approver) {
            /** @var SupplierInvoice $locked */
            $locked = SupplierInvoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

            if ($locked->status !== 'held') {
                return $locked;
            }

            $supplier = Supplier::findOrFail($locked->supplier_id);
            $po = PurchaseOrder::findOrFail($locked->po_id);
            $this->postInvoiceAp($locked, $supplier, (int) $po->total_amount, (int) $locked->invoice_amount_idr, (int) $approver->id);
            $locked->status = 'approved';
            $locked->save();

            return $locked;
        });
    }

    /**
     * Perhitungan 3-way match murni (tanpa side effect) — dipakai test & UI.
     *
     * @return array{within_tolerance: bool, price_variance_pct: float, qty_variance_pct: float}
     */
    public function evaluateThreeWayMatch(int $poAmountIdr, int $grnAmountIdr, int $invoiceAmountIdr, int $priceTolerancePct, int $qtyTolerancePct): array
    {
        $pricePct = $poAmountIdr > 0 ? abs($invoiceAmountIdr - $poAmountIdr) * 100 / $poAmountIdr : 0.0;
        $qtyPct = $poAmountIdr > 0 ? abs($grnAmountIdr - $poAmountIdr) * 100 / $poAmountIdr : 0.0;

        return [
            'within_tolerance' => $pricePct <= $priceTolerancePct && $qtyPct <= $qtyTolerancePct,
            'price_variance_pct' => round($pricePct, 4),
            'qty_variance_pct' => round($qtyPct, 4),
        ];
    }

    // ── 34.4 GR/IR, AP, PPN, PPh 23, PPV ───────────────────────────────

    private function postInvoiceAp(SupplierInvoice $invoice, Supplier $supplier, int $poAmount, int $invoiceAmount, int $actorId): void
    {
        $this->ensureAccounts($supplier->id);

        $grir = $this->account(self::GRIR, 'GR/IR Clearing', AccountKind::LIABILITY);
        $ap = $this->account($this->apCode($supplier->id), "AP Supplier {$supplier->name}", AccountKind::AP);
        $ppn = $this->account(self::PPN_INPUT, 'PPN Masukan (simulasi 11%)', AccountKind::ASSET);
        $pph = $this->account(self::PPH23_WITHHELD, 'PPh 23 Dipotong (simulasi)', AccountKind::LIABILITY);
        $ppv = $this->account(self::PURCHASE_PRICE_VARIANCE, 'Purchase Price Variance', AccountKind::EXPENSE);
        $clearing = $this->account('clearing:external:IDR', 'Rekening Kliring Eksternal IDR', AccountKind::CLEARING);

        $variance = $invoiceAmount - $poAmount;
        $ppnAmount = (int) BigDecimal::of($invoiceAmount)->multipliedBy(11)->dividedBy(100, 0, RoundingMode::HalfUp)->__toString();
        $pphAmount = (int) BigDecimal::of($invoiceAmount)->multipliedBy(2)->dividedBy(100, 0, RoundingMode::HalfUp)->__toString(); // simulasi 2%
        $netAp = $invoiceAmount + $ppnAmount - $pphAmount;

        $entries = [];
        $grirOffset = min($invoiceAmount, $poAmount);
        if ($grirOffset > 0) {
            $entries[] = PostingEntryDTO::forAccount($grir->id, 'IDR', BigDecimal::of($grirOffset));
        }
        if ($variance !== 0) {
            $entries[] = PostingEntryDTO::forAccount($ppv->id, 'IDR', BigDecimal::of($variance));
        }
        if ($ppnAmount > 0) {
            $entries[] = PostingEntryDTO::forAccount($ppn->id, 'IDR', BigDecimal::of($ppnAmount));
        }
        if ($pphAmount > 0) {
            $entries[] = PostingEntryDTO::forAccount($pph->id, 'IDR', BigDecimal::of($pphAmount)->negated());
        }
        $entries[] = PostingEntryDTO::forAccount($ap->id, 'IDR', BigDecimal::of($netAp)->negated());

        // Seimbangkan bila ada pembulatan kecil (AP disesuaikan agar Σ = 0).
        $sum = BigDecimal::zero();
        foreach ($entries as $e) {
            $sum = $sum->plus($e->amount);
        }
        if (! $sum->isZero() && $entries !== []) {
            $last = array_pop($entries);
            $entries[] = PostingEntryDTO::forAccount($ap->id, 'IDR', $last->amount->minus($sum));
        }

        $tx = $this->ledger->post(new PostingDTO(
            type: TransactionType::SUPPLIER_PAYABLE->value,
            description: "3-way match invoice {$invoice->number} — PPN/PPh simulasi",
            idempotencyKey: 'proc:invoice:'.$invoice->id,
            entries: $entries,
            referenceType: SupplierInvoice::class,
            referenceId: $invoice->getKey(),
            meta: [
                'supplier_id' => $supplier->id,
                'po_amount_idr' => $poAmount,
                'invoice_amount_idr' => $invoiceAmount,
                'variance_idr' => $variance,
                'ppn_input_idr' => $ppnAmount,
                'pph23_withheld_idr' => $pphAmount,
                'tax_simulated' => true,
            ],
            createdBy: $actorId,
            postedAt: now(),
        ));

        $invoice->update(['status' => 'approved', 'matched_amount_idr' => $poAmount]);
        $this->recordApEntry($invoice->id, $supplier->id, 'ap', $netAp, 'credit', $invoice->number, $tx->id);
        $this->recordApEntry($invoice->id, $supplier->id, 'ppn_input', $ppnAmount, 'debit', $invoice->number, $tx->id);
        $this->recordApEntry($invoice->id, $supplier->id, 'pph23_withheld', $pphAmount, 'credit', $invoice->number, $tx->id);
        if ($variance !== 0) {
            $this->recordApEntry($invoice->id, $supplier->id, 'ppv', abs($variance), $variance > 0 ? 'debit' : 'credit', $invoice->number, $tx->id);
        }
    }

    private function ensureAccounts(string $supplierId): void
    {
        $this->account(self::GRIR, 'GR/IR Clearing', AccountKind::LIABILITY);
        $this->account(self::PPN_INPUT, 'PPN Masukan (simulasi 11%)', AccountKind::ASSET);
        $this->account(self::PPH23_WITHHELD, 'PPh 23 Dipotong (simulasi)', AccountKind::LIABILITY);
        $this->account(self::PURCHASE_PRICE_VARIANCE, 'Purchase Price Variance', AccountKind::EXPENSE);
        $this->account($this->apCode($supplierId), "AP Supplier {$supplierId}", AccountKind::AP);
        $this->account('inventory:procurement:IDR', 'Persediaan Pembelian', AccountKind::INVENTORY);
        $this->account('clearing:external:IDR', 'Rekening Kliring Eksternal IDR', AccountKind::CLEARING);
    }

    private function apCode(string $supplierId): string
    {
        return self::SUPPLIER_AP_PREFIX.$supplierId.':IDR';
    }

    private function account(string $code, string $name, AccountKind $kind): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => $code, 'asset_code' => 'IDR'],
            [
                'uuid' => (string) Str::uuid(), 'name' => $name, 'kind' => $kind->value,
                'allow_negative' => true, 'cached_balance' => '0', 'is_frozen' => false,
            ]
        );
    }

    private function recordApEntry(?string $invoiceId, string $supplierId, string $kind, int $amountIdr, string $direction, ?string $reference = null, ?int $txId = null): void
    {
        if ($amountIdr === 0) {
            return;
        }

        ApEntry::create([
            'invoice_id' => $invoiceId,
            'supplier_id' => $supplierId,
            'kind' => $kind,
            'amount_idr' => abs($amountIdr),
            'direction' => $direction,
            'reference' => $reference,
            'ledger_transaction_id' => $txId,
        ]);
    }

    // ── 34.5 Payment schedule + batch payment run ───────────────────────

    /**
     * Batch payment run dari invoice approved/overdue, dengan diskon pembayaran dini.
     *
     * @param  array<int, array{invoice_id: string, amount_idr?: int, early_discount_idr?: int}>  $items
     */
    public function createPaymentBatch(array $items, User $creator, ?string $scheduledDate = null): PaymentBatch
    {
        if ($items === []) {
            throw new InvalidArgumentException('Payment batch harus berisi setidaknya satu invoice.');
        }

        return DB::transaction(function () use ($items, $creator, $scheduledDate) {
            $batch = PaymentBatch::create([
                'number' => $this->numbering->nextNumber('PRC', 'PAY', false, 'PAY/{ENT}/'),
                'status' => 'draft',
                'scheduled_date' => $scheduledDate,
                'created_by_user_id' => $creator->id,
            ]);

            $total = 0;
            foreach ($items as $row) {
                /** @var SupplierInvoice $invoice */
                $invoice = SupplierInvoice::query()->lockForUpdate()->findOrFail($row['invoice_id']);
                if (! in_array($invoice->status, ['approved', 'held'], true)) {
                    throw new InvalidArgumentException("Invoice {$invoice->number} tidak bisa dimasukkan payment run (status {$invoice->status}).");
                }

                $amount = min((int) ($row['amount_idr'] ?? $invoice->invoice_amount_idr), (int) $invoice->invoice_amount_idr);
                $discount = max(0, min((int) ($row['early_discount_idr'] ?? 0), $amount));
                $total += $amount - $discount;

                PaymentItem::create([
                    'batch_id' => $batch->id,
                    'invoice_id' => $invoice->id,
                    'amount_idr' => $amount,
                    'early_discount_idr' => $discount,
                    'status' => 'pending',
                ]);
            }

            $batch->total_amount_idr = $total;
            $batch->item_count = count($items);
            $batch->save();

            $approval = $this->approvals->submit(
                approvalType: 'SUPPLIER_PAYMENT_BATCH',
                title: "Payment run {$batch->number} (".count($items).' invoice)',
                creator: $creator,
                approvable: $batch,
                amount: $total,
                steps: [['role' => 'procurement'], ['role' => 'admin']],
                slaHours: 48,
                metadata: ['batch_id' => $batch->id, 'total_amount_idr' => $total, 'item_count' => count($items)],
            );

            $batch->status = 'pending_approval';
            $batch->approval_id = $approval->uuid;
            $batch->save();

            return $batch->fresh(['items']);
        });
    }

    /**
     * Eksekusi batch setelah approval — posting per invoice (key deterministik).
     */
    public function executePaymentBatch(PaymentBatch $batch, User $payer): PaymentBatch
    {
        return DB::transaction(function () use ($batch, $payer) {
            /** @var PaymentBatch $locked */
            $locked = PaymentBatch::query()->lockForUpdate()->with('items')->findOrFail($batch->getKey());

            if ($locked->status === 'completed') {
                return $locked;
            }
            if ($locked->status !== 'approved') {
                throw new InvalidArgumentException('Payment batch harus approved sebelum eksekusi.');
            }

            $locked->status = 'processing';
            $locked->save();

            foreach ($locked->items as $item) {
                if ($item->status === 'paid') {
                    continue;
                }

                /** @var SupplierInvoice $invoice */
                $invoice = SupplierInvoice::query()->lockForUpdate()->findOrFail($item->invoice_id);
                $supplier = Supplier::findOrFail($invoice->supplier_id);
                $ap = $this->account($this->apCode($supplier->id), "AP Supplier {$supplier->name}", AccountKind::AP);
                $clearing = $this->account('clearing:external:IDR', 'Rekening Kliring Eksternal IDR', AccountKind::CLEARING);
                $discountRevenue = $this->account('revenue:early_payment_discount:IDR', 'Diskon Pembayaran Dini (simulasi)', AccountKind::REVENUE);

                $amount = (int) $item->amount_idr;
                $discount = (int) $item->early_discount_idr;
                $payment = max(0, $amount - $discount);
                $entries = [
                    PostingEntryDTO::forAccount($ap->id, 'IDR', BigDecimal::of($amount)),
                    PostingEntryDTO::forAccount($clearing->id, 'IDR', BigDecimal::of($payment)->negated()),
                ];
                if ($discount > 0) {
                    // Diskon pembayaran dini: AP dibayar penuh, kas hanya keluar
                    // sebesar payment → selisih (discount) DIKREDIT sebagai
                    // pendapatan diskon sehingga Σ entri tetap 0.
                    $entries[] = PostingEntryDTO::forAccount($discountRevenue->id, 'IDR', BigDecimal::of($discount)->negated());
                }

                $tx = $this->ledger->post(new PostingDTO(
                    type: TransactionType::SUPPLIER_PAYMENT->value,
                    description: "Pembayaran invoice {$invoice->number} via batch {$locked->number}",
                    idempotencyKey: 'proc:payment:'.$locked->id.':'.$invoice->id,
                    entries: $entries,
                    referenceType: SupplierInvoice::class,
                    referenceId: $invoice->getKey(),
                    meta: ['batch_id' => $locked->id, 'invoice_id' => $invoice->id, 'amount' => $payment, 'discount' => $discount],
                    createdBy: $payer->id,
                    postedAt: now(),
                ));

                $item->status = 'paid';
                $item->ledger_transaction_id = $tx->id;
                $item->save();

                $invoice->status = 'paid';
                $invoice->save();

                $this->recordApEntry($invoice->id, $supplier->id, 'payment', $payment, 'debit', $locked->number, $tx->id);
            }

            $locked->status = 'completed';
            $locked->proof = $locked->proof ?? 'SIMULATED-PAYMENT-'.$locked->number;
            $locked->save();

            return $locked->fresh(['items']);
        });
    }

    // ── 34.6 Uang muka pemasok & kredit memo ────────────────────────────

    /** Uang muka pemasok: debit aset uang muka, kredit kas (key wajib & idempoten). */
    public function createSupplierAdvance(Supplier $supplier, int $amountIdr, string $idempotencyKey, User $actor): SupplierAdvance
    {
        if ($amountIdr <= 0) {
            throw new InvalidArgumentException('Uang muka harus lebih besar dari nol.');
        }

        return DB::transaction(function () use ($supplier, $amountIdr, $idempotencyKey, $actor) {
            $this->ensureAccounts($supplier->id);
            $cash = $this->account('clearing:external:IDR', 'Rekening Kliring Eksternal IDR', AccountKind::CLEARING);
            $advance = $this->account('asset:supplier_advance:'.$supplier->id.':IDR', "Uang Muka Pemasok {$supplier->name}", AccountKind::ASSET);

            $this->ledger->post(new PostingDTO(
                type: TransactionType::SUPPLIER_PAYABLE->value,
                description: "Uang muka pemasok {$supplier->name}",
                idempotencyKey: 'proc:advance:'.$idempotencyKey,
                entries: [
                    PostingEntryDTO::forAccount($advance->id, 'IDR', BigDecimal::of($amountIdr)),
                    PostingEntryDTO::forAccount($cash->id, 'IDR', BigDecimal::of($amountIdr)->negated()),
                ],
                referenceType: Supplier::class,
                referenceId: $supplier->getKey(),
                meta: ['supplier_id' => $supplier->id, 'advance' => true, 'amount' => $amountIdr],
                createdBy: $actor->id,
                postedAt: now(),
            ));

            return SupplierAdvance::firstOrCreate(
                ['reference' => $idempotencyKey],
                [
                    'supplier_id' => $supplier->id,
                    'amount_idr' => $amountIdr,
                    'used_amount_idr' => 0,
                    'status' => 'open',
                ]
            );
        });
    }

    /**
     * Uang muka dikompensasikan ke tagihan: konsumsi sebagian penuh.
     *
     * @return array{consumed_idr: int, advance_remaining_idr: int}
     */
    public function consumeSupplierAdvance(SupplierAdvance $advance, SupplierInvoice $invoice, User $actor): array
    {
        return DB::transaction(function () use ($advance, $invoice, $actor) {
            /** @var SupplierAdvance $locked */
            $locked = SupplierAdvance::query()->lockForUpdate()->findOrFail($advance->getKey());

            if ($locked->status !== 'open' || $locked->remaining() <= 0) {
                throw new InvalidArgumentException('Uang muka tidak tersedia untuk kompensasi.');
            }

            $invoice = SupplierInvoice::query()->lockForUpdate()->findOrFail($invoice->getKey());
            $applied = min($locked->remaining(), (int) $invoice->invoice_amount_idr);

            $locked->used_amount_idr = (int) $locked->used_amount_idr + $applied;
            $locked->status = $locked->remaining() > 0 ? 'open' : 'consumed';
            $locked->save();

            $this->ensureAccounts($invoice->supplier_id);
            $ap = $this->account($this->apCode($invoice->supplier_id), 'AP Supplier', AccountKind::AP);
            $advanceAccount = $this->account('asset:supplier_advance:'.$invoice->supplier_id.':IDR', 'Uang Muka Pemasok', AccountKind::ASSET);

            $tx = $this->ledger->post(new PostingDTO(
                type: TransactionType::SUPPLIER_PAYMENT->value,
                description: "Kompensasi uang muka ke invoice {$invoice->number}",
                idempotencyKey: 'proc:advance-apply:'.$invoice->id,
                entries: [
                    PostingEntryDTO::forAccount($ap->id, 'IDR', BigDecimal::of($applied)),
                    PostingEntryDTO::forAccount($advanceAccount->id, 'IDR', BigDecimal::of($applied)->negated()),
                ],
                referenceType: SupplierInvoice::class,
                referenceId: $invoice->getKey(),
                meta: ['advance_id' => $locked->id, 'invoice_id' => $invoice->id, 'applied' => $applied],
                createdBy: $actor->id,
                postedAt: now(),
            ));

            $this->recordApEntry($invoice->id, $invoice->supplier_id, 'advance', $applied, 'debit', $invoice->number, $tx->id);

            return ['consumed_idr' => $applied, 'advance_remaining_idr' => $locked->remaining()];
        });
    }

    /** Kredit memo (debit note supplier): debit AP, kredit PPV — key wajib. */
    public function createCreditMemo(Supplier $supplier, ?SupplierInvoice $invoice, int $amountIdr, string $reason, string $idempotencyKey, User $actor): CreditMemo
    {
        if ($amountIdr <= 0) {
            throw new InvalidArgumentException('Kredit memo harus bernilai positif.');
        }

        return DB::transaction(function () use ($supplier, $invoice, $amountIdr, $reason, $idempotencyKey, $actor) {
            $this->ensureAccounts($supplier->id);
            $ap = $this->account($this->apCode($supplier->id), "AP Supplier {$supplier->name}", AccountKind::AP);
            $ppv = $this->account(self::PURCHASE_PRICE_VARIANCE, 'Purchase Price Variance', AccountKind::EXPENSE);

            $tx = $this->ledger->post(new PostingDTO(
                type: TransactionType::SUPPLIER_PAYMENT->value,
                description: "Credit memo pemasok {$supplier->name}: {$reason}",
                idempotencyKey: 'proc:cm:'.$idempotencyKey,
                entries: [
                    PostingEntryDTO::forAccount($ap->id, 'IDR', BigDecimal::of($amountIdr)),
                    PostingEntryDTO::forAccount($ppv->id, 'IDR', BigDecimal::of($amountIdr)->negated()),
                ],
                referenceType: Supplier::class,
                referenceId: $supplier->getKey(),
                meta: ['supplier_id' => $supplier->id, 'invoice_id' => $invoice?->id, 'credit_memo' => true, 'reason' => $reason],
                createdBy: $actor->id,
                postedAt: now(),
            ));

            return CreditMemo::create([
                'supplier_id' => $supplier->id,
                'invoice_id' => $invoice?->id,
                'number' => $this->numbering->nextNumber('PRC', 'CM', false, 'CM/{ENT}/'),
                'amount_idr' => $amountIdr,
                'reason' => $reason,
                'status' => 'issued',
                'ledger_transaction_id' => $tx->id,
            ]);
        });
    }

    // ── 34.7 Landed cost: alokasi ke nilai persediaan ─────────────────────

    /**
     * Alokasi biaya angkut/bea/asuransi ke baris PO (value|weight|qty).
     * Baris terakhir menyerap selisih pembulatan sehingga Σ = total persis.
     *
     * @return array{total_idr: int, allocations: array<int, array{line_id: int, allocation_idr: int}>}
     */
    public function allocateLandedCost(PurchaseOrder $po, int $amountIdr, string $kind, string $method = 'value'): array
    {
        if ($amountIdr <= 0 || ! in_array($method, ['value', 'weight', 'qty'], true)) {
            throw new InvalidArgumentException('Nominal/method alokasi landed cost tidak valid.');
        }

        return DB::transaction(function () use ($po, $amountIdr, $kind, $method) {
            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()->lockForUpdate()->with('lines')->findOrFail($po->getKey());
            $lines = $locked->lines;

            if ($lines->isEmpty()) {
                throw new InvalidArgumentException('PO tidak memiliki baris untuk alokasi landed cost.');
            }

            LandedCost::create([
                'po_id' => $locked->id,
                'kind' => $kind,
                'amount_idr' => $amountIdr,
                'allocation_method' => $method,
                'status' => 'allocated',
            ]);

            $weights = $lines->mapWithKeys(fn ($line) => [
                $line->id => match ($method) {
                    'qty' => max(1, (int) $line->qty),
                    'weight' => max(1, (int) $line->qty),
                    default => max(1, (int) $line->line_total),
                },
            ]);
            $sumWeight = max(1, (int) $weights->sum());

            $allocated = 0;
            $rows = [];
            $lastIndex = $lines->count() - 1;

            foreach ($lines->values() as $i => $line) {
                $share = $i === $lastIndex
                    ? $amountIdr - $allocated
                    : (int) BigDecimal::of($amountIdr)
                        ->multipliedBy($weights[$line->id])
                        ->dividedBy($sumWeight, 0, RoundingMode::HalfUp)
                        ->__toString();

                $allocated += $share;
                $rows[] = ['line_id' => $line->id, 'allocation_idr' => $share];

                $this->recordApEntry(null, $locked->supplier_id, 'landed_cost_'.$kind, $share, 'debit', 'PO '.$locked->number.' line '.$line->id);
            }

            // Jurnal koreksi: nilai persediaan bertambah sebesar biaya landed.
            $inventory = $this->account('inventory:procurement:IDR', 'Persediaan Pembelian', AccountKind::INVENTORY);
            $clearing = $this->account('clearing:external:IDR', 'Rekening Kliring Eksternal IDR', AccountKind::CLEARING);

            $this->ledger->post(new PostingDTO(
                type: TransactionType::SUPPLIER_PAYABLE->value,
                description: "Alokasi landed cost {$kind} PO {$locked->number}",
                idempotencyKey: 'proc:landed:'.$locked->id.':'.$kind.':'.$method.':'.$amountIdr,
                entries: [
                    PostingEntryDTO::forAccount($inventory->id, 'IDR', BigDecimal::of($amountIdr)),
                    PostingEntryDTO::forAccount($clearing->id, 'IDR', BigDecimal::of($amountIdr)->negated()),
                ],
                referenceType: PurchaseOrder::class,
                referenceId: $locked->getKey(),
                meta: ['po_id' => $locked->id, 'kind' => $kind, 'allocations' => $rows],
                postedAt: now(),
            ));

            return ['total_idr' => $amountIdr, 'allocations' => $rows];
        });
    }

    /**
     * 34.8 Subledger AP & GR/IR (audit `proc:audit` menghitung agregat SQL).
     */
    public function accountBalances(string $supplierId): array
    {
        return [
            'ap_idr' => (int) $this->account($this->apCode($supplierId), 'AP Supplier', AccountKind::AP)->cached_balance,
            'grir_idr' => (int) $this->account(self::GRIR, 'GR/IR Clearing', AccountKind::LIABILITY)->cached_balance,
        ];
    }
}
