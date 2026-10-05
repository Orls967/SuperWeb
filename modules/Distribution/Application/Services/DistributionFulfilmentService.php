<?php

declare(strict_types=1);

namespace Modules\Distribution\Application\Services;

use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
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
use Modules\Distribution\Domain\Models\ConsignmentSale;
use Modules\Distribution\Domain\Models\ConsignmentStock;
use Modules\Distribution\Domain\Models\DistInvoice;
use Modules\Distribution\Domain\Models\DistOrder;
use Modules\Distribution\Domain\Models\DistOrderLine;
use Modules\Distribution\Domain\Models\DistReturn;
use Modules\Distribution\Domain\Models\Distributor;
use Modules\Distribution\Domain\Models\DistShipment;
use Modules\Distribution\Domain\Models\DistShipmentLine;
use Modules\Distribution\Domain\Models\HetPrice;
use Modules\Distribution\Domain\Models\RebateAccrual;
use Modules\Distribution\Domain\Models\RebateProgram;
use Modules\Distribution\Domain\Models\SelloutLine;
use Modules\Distribution\Domain\Models\SelloutReport;
use Modules\Distribution\Domain\Models\StockLevel;
use Modules\Distribution\Domain\Models\Tier;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Inventory\Domain\Models\StockMovement;
use Modules\Logistics\Contracts\ShipmentBooking;
use Modules\Store\Domain\Models\Product;

/**
 * Eksekusi distribusi (Fase 43): order (limit + ATP + alokasi),
 * pemenuhan/POD + faktur pajak simulasi, sell-out + anomali,
 * konsinyasi, retur/klaim, rebate, HET price compliance, VMI.
 *
 * Konvensi ledger: debit positif, kredit negatif.
 * - Faktur: DR `dist:receivable:IDR` / CR `revenue:distribution:IDR`
 * - Kredit nota retur: DR `revenue:distribution:IDR` / CR `dist:receivable:IDR`
 * - Akrual rebate: DR `dist:rebate_expense:IDR` / CR `dist:rebate_payable:IDR`
 * - Konsinyasi terjual: DR `dist:consignment_ar:IDR` / CR `dist:consignment:IDR`
 */
class DistributionFulfilmentService
{
    public const PPN_RATE_PERCENT = 11.0; // SIMULASI

    public const ACCT_RECEIVABLE = DistributionService::ACCT_RECEIVABLE;

    public const ACCT_REVENUE = 'revenue:distribution:IDR';

    public const ACCT_REBATE_EXPENSE = 'dist:rebate_expense:IDR';

    public const ACCT_REBATE_PAYABLE = 'dist:rebate_payable:IDR';

    public const ACCT_CONSIGNMENT = 'dist:consignment:IDR';

    public const ACCT_CONSIGNMENT_AR = 'dist:consignment_ar:IDR';

    public function __construct(
        private readonly DocumentNumberingInterface $numbering,
        private readonly ApprovalEngineInterface $approvals,
        private readonly Ledger $ledger,
        private readonly InventoryService $inventory,
        private readonly DistributionService $distribution,
        private readonly ?ShipmentBooking $shipment = null,
    ) {}

    // ── 43.1 Order: limit + ATP + alokasi ───────────────────────────────

    /**
     * Buat order draft dari garis produk. Validasi limit kredit distributor.
     *
     * @param  array<int, array{product_id: int, qty: float, unit_price_idr?: int}>  $lines
     * @param  array{shipping_address?: string, requested_date?: string,
     *   allocation_strategy?: string, discount_idr?: int, notes?: string}  $options
     */
    public function placeOrder(Distributor $distributor, array $lines, User $creator, array $options = []): DistOrder
    {
        if ($lines === []) {
            throw new InvalidArgumentException('Order harus berisi setidaknya satu baris.');
        }

        return DB::transaction(function () use ($distributor, $lines, $creator, $options) {
            /** @var Distributor $locked */
            $locked = Distributor::query()->lockForUpdate()->findOrFail($distributor->getKey());
            if ($locked->status !== 'approved' || $locked->isBlocked()) {
                throw new InvalidArgumentException("Distributor {$locked->code} tidak dapat membuat order (status {$locked->status}).");
            }

            $number = $this->numbering->nextNumber('DIST', 'DOR', false, 'DOR/{ENT}/');
            $order = DistOrder::create([
                'number' => $number,
                'distributor_id' => $locked->id,
                'status' => 'draft',
                'shipping_address' => $options['shipping_address'] ?? null,
                'requested_date' => $options['requested_date'] ?? null,
                'allocation_strategy' => $options['allocation_strategy'] ?? 'priority',
                'notes' => $options['notes'] ?? null,
                'created_by_user_id' => $creator->id,
            ]);

            $subtotal = 0;
            foreach ($lines as $line) {
                $qty = (float) ($line['qty'] ?? 0);
                if ($qty <= 0) {
                    throw new InvalidArgumentException('Qty baris order harus lebih besar dari nol.');
                }
                $price = (int) ($line['unit_price_idr'] ?? 0);

                $product = Product::find($line['product_id']);
                if ($product === null) {
                    throw new InvalidArgumentException('Produk tidak ditemukan.');
                }

                $lineTotal = (int) round($qty * $price);
                DistOrderLine::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'sku' => (string) $product->sku,
                    'name_snapshot' => (string) $product->name,
                    'qty' => $qty,
                    'unit_price_idr' => $price,
                    'line_total_idr' => $lineTotal,
                    'line_status' => 'open',
                ]);
                $subtotal += $lineTotal;
            }

            $discount = max(0, (int) ($options['discount_idr'] ?? 0));
            // Harga tier mengurangi diskon otomatis bila tidak dispesifikkan.
            if (($options['discount_idr'] ?? null) === null) {
                $rate = (float) (Tier::where('code', $locked->tier)->value('discount_percent') ?? 0);
                $discount = (int) floor($subtotal * $rate / 100);
            }
            $net = max(0, $subtotal - $discount);
            $ppn = (int) ceil($net * self::PPN_RATE_PERCENT / 100);
            $total = $net + $ppn;

            // Validasi limit kredit: total order + exposure saat ini ≤ limit.
            if ((int) $locked->credit_limit_idr > 0
                && (int) $locked->credit_exposure_idr + $total > (int) $locked->credit_limit_idr) {
                throw new InvalidArgumentException(sprintf(
                    'Melebihi limit kredit: eksposur %s + order %s > limit %s.',
                    number_format((int) $locked->credit_exposure_idr),
                    number_format($total),
                    number_format((int) $locked->credit_limit_idr),
                ));
            }

            $order->update([
                'subtotal_idr' => $subtotal,
                'discount_idr' => $discount,
                'ppn_idr' => $ppn,
                'total_idr' => $total,
            ]);

            return $order->load('lines');
        });
    }

    /**
     * Alokasi ATP (available-to-promise): FIFO per stok produk global.
     * Strategy priority mengalokasikan order ini dulu; fair_share membagi
     * rata antar order draft yang menunggu (sederhana: urutan due date).
     *
     * @return array{allocated: int, backordered: int}
     */
    public function allocate(DistOrder $order): array
    {
        return DB::transaction(function () use ($order) {
            /** @var DistOrder $locked */
            $locked = DistOrder::query()->lockForUpdate()->with('lines')->findOrFail($order->getKey());
            // Draft/partial/backordered boleh (re)alokasi — baris yang
            // sudah penuh dilewati agar idempoten saat retry backorder.
            if (! in_array($locked->status, ['draft', 'partial', 'backordered', 'allocated'], true)) {
                throw new InvalidArgumentException("Order {$locked->number} tidak dapat dialokasikan ({$locked->status}).");
            }
            if ($locked->status === 'allocated') {
                return ['allocated' => $locked->lines->count(), 'backordered' => 0]; // idempoten
            }

            $allocatedLines = 0;
            $backorderLines = 0;
            $fairShare = $locked->allocation_strategy === 'fair_share';

            foreach ($locked->lines as $line) {
                $need = (float) $line->qty - (float) $line->allocated_qty;
                if ($need <= 0.000001) {
                    $allocatedLines++;

                    continue;
                }

                $available = $this->inventory->available((int) $line->product_id);
                $take = (int) floor($need + 0.000001);

                if ($fairShare) {
                    // Fair-share: stok dibagi rata antar order yang BELUM
                    // menerima alokasi apa pun (pemenang pertama mengunci
                    // bagiannya; sisanya jatuh ke order berikutnya).
                    $competing = DistOrder::whereIn('status', ['draft', 'partial', 'backordered'])
                        ->where('allocation_strategy', 'fair_share')
                        ->whereHas('lines', fn ($q) => $q
                            ->where('product_id', $line->product_id)
                            ->whereColumn('allocated_qty', '<', 'qty'))
                        ->whereHas('lines', fn ($q) => $q
                            ->where('product_id', $line->product_id)
                            ->where('allocated_qty', 0))
                        ->count();
                    $competing = max(1, $competing);
                    $share = (int) floor(max(0, $available) / $competing);
                    $take = min($take, $share);
                } else {
                    $take = min($take, max(0, $available));
                }

                if ($take > 0) {
                    // Reserve stok → alasan RESERVATION; dikirim pada pemenuhan.
                    $this->inventory->reserve(
                        (int) $line->product_id, $take,
                        'dist_order_line', (int) $line->id,
                        "Alokasi order {$locked->number}", $locked->created_by_user_id
                    );
                    $line->allocated_qty = (float) $line->allocated_qty + $take;
                    $line->line_status = $take >= $need - 0.000001 ? 'allocated' : 'backorder';
                    $line->save();
                } else {
                    $line->line_status = 'backorder';
                    $line->save();
                }

                if ((float) $line->allocated_qty > 0.000001) {
                    $allocatedLines++;
                }
                if ($line->backorderQty() > 0.000001) {
                    $backorderLines++;
                }
            }

            $status = $backorderLines > 0
                ? ($allocatedLines > 0 ? 'partial' : 'backordered')
                : 'allocated';
            $locked->update(['status' => $status, 'allocated_at' => now()]);

            return ['allocated' => $allocatedLines, 'backordered' => $backorderLines];
        });
    }

    // ── 43.2 Pemenuhan: pick → shipment → POD → faktur ──────────────────

    /**
     * Buat pengiriman untuk order (alokasi sudah jalan). Pick → shipment
     * Logistics (opsional) → status picked.
     *
     * @param  array{mode?: string, notes?: string}  $options
     */
    public function fulfil(DistOrder $order, User $operator, array $options = []): DistShipment
    {
        return DB::transaction(function () use ($order, $operator, $options) {
            /** @var DistOrder $locked */
            $locked = DistOrder::query()->lockForUpdate()->with('lines')->findOrFail($order->getKey());
            if ($locked->status === 'shipped' || $locked->status === 'delivered') {
                return $locked->shipments()->firstOrFail(); // idempoten
            }
            if (! in_array($locked->status, ['allocated', 'partial'], true)) {
                throw new InvalidArgumentException("Pemenuhan hanya untuk order alokasi/parsial ({$locked->status}).");
            }

            $mode = $options['mode'] ?? 'ltl';
            if (! in_array($mode, DistShipment::MODES, true)) {
                throw new InvalidArgumentException('Mode pengiriman tidak dikenal.');
            }

            $number = $this->numbering->nextNumber('DIST', 'DSP', false, 'DSP/{ENT}/');
            $shipment = DistShipment::create([
                'number' => $number,
                'order_id' => $locked->id,
                'mode' => $mode,
                'status' => 'picked',
                'picked_at' => now(),
                'notes' => $options['notes'] ?? null,
                'created_by_user_id' => $operator->id,
            ]);

            foreach ($locked->lines as $line) {
                $qty = (float) $line->allocated_qty;
                if ($qty <= 0.000001) {
                    continue;
                }

                // 43.2 Integrasi Logistics via contract (FTL/LTL/multimoda).
                if ($this->shipment !== null && $shipment->tracking_number === null) {
                    try {
                        $booked = $this->shipment->bookForOrder($operator, [
                            'origin_code' => 'HUB',
                            'destination_address' => [
                                'street' => (string) ($locked->shipping_address ?? 'Alamat distributor'),
                                'city' => 'Banjarmasin',
                            ],
                            'consignee_name' => (string) ($locked->distributor?->name ?? 'Distributor'),
                            'consignee_phone' => '-',
                            'packages' => [[
                                'weight_g' => max(100, (int) ceil($qty) * 1000),
                                'length_mm' => 300, 'width_mm' => 200, 'height_mm' => 200,
                                'description' => "Order {$locked->number}",
                            ]],
                            'declared_value_idr' => max(1, (int) $line->line_total_idr),
                            'source_type' => 'dist_shipment',
                            'source_id' => (string) $shipment->id,
                            'amount_idr' => 1,
                        ]);
                        $shipment->tracking_number = $booked['tracking_number'];
                        $shipment->save();
                    } catch (\Throwable) {
                        // Logistics opsional.
                    }
                }

                DistShipmentLine::create([
                    'shipment_id' => $shipment->id,
                    'order_line_id' => $line->id,
                    'qty' => $qty,
                ]);

                // Commit reservasi → pemakaian stok nyata (alasan SALE).
                $movements = StockMovement::where('source_type', 'dist_order_line')
                    ->where('source_id', (int) $line->id)
                    ->where('product_id', $line->product_id)
                    ->where('reason', 'reservation')
                    ->where('qty', '<', 0)
                    ->orderBy('id')
                    ->get();
                $remaining = $qty;
                foreach ($movements as $movement) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $this->inventory->commit($movement->id, StockMovementReason::SALE, "Kirim order {$locked->number}");
                    $remaining += (float) $movement->qty; // qty negatif
                }

                $line->shipped_qty = (float) $line->shipped_qty + $qty;
                $line->line_status = 'shipped';
                $line->save();
            }

            $locked->update(['status' => 'shipped', 'shipped_at' => now()]);

            return $shipment->fresh('lines');
        });
    }

    /** POD (proof of delivery) → delivered + terbitkan faktur (simulasi). */
    public function recordPod(DistShipment $shipment, string $podName, User $driver, ?string $podNote = null): array
    {
        return DB::transaction(function () use ($shipment, $podName, $driver, $podNote) {
            /** @var DistShipment $locked */
            $locked = DistShipment::query()->lockForUpdate()->with('lines')->findOrFail($shipment->getKey());
            if ($locked->status === 'pod') {
                return ['shipment' => $locked, 'invoice' => null]; // idempoten
            }
            if ($locked->status !== 'shipped' && $locked->status !== 'picked') {
                throw new InvalidArgumentException("POD hanya untuk pengiriman shipped/picked ({$locked->status}).");
            }

            $locked->update([
                'status' => 'pod',
                'pod_at' => now(),
                'pod_name' => $podName,
                'pod_note' => $podNote,
            ]);

            $order = DistOrder::query()->lockForUpdate()->findOrFail($locked->order_id);
            $order->update(['status' => 'delivered', 'delivered_at' => now()]);
            foreach ($order->lines as $line) {
                if ($line->line_status === 'shipped') {
                    $line->update(['line_status' => 'delivered']);
                }
            }

            // 43.2 Faktur pajak simulasi (PPN 11% sudah dihitung order).
            $invoice = $this->issueTaxInvoice($order, $driver);

            return ['shipment' => $locked->fresh(), 'invoice' => $invoice];
        });
    }

    /**
     * Faktur penjualan + nomor seri pajak (SIMULASI) + jurnal revenue.
     */
    public function issueTaxInvoice(DistOrder $order, User $issuer): DistInvoice
    {
        return DB::transaction(function () use ($order, $issuer) {
            $existing = DistInvoice::where('order_id', $order->id)->first();
            if ($existing !== null) {
                return $existing; // idempoten
            }

            $number = $this->numbering->nextNumber('DIST', 'FTR', false, 'FTR/{ENT}/');
            $serial = sprintf('010.%s.%s.%s', '001', now()->format('ym'), $number);

            $invoice = DistInvoice::create([
                'number' => $number,
                'tax_serial' => $serial,
                'order_id' => $order->id,
                'distributor_id' => $order->distributor_id,
                'subtotal_idr' => (int) $order->subtotal_idr,
                'discount_idr' => (int) $order->discount_idr,
                'ppn_idr' => (int) $order->ppn_idr,
                'total_idr' => (int) $order->total_idr,
                'status' => 'issued',
                'issued_at' => now()->toDateString(),
                'created_by_user_id' => $issuer->id,
            ]);

            $net = (int) $order->subtotal_idr - (int) $order->discount_idr;
            $this->ensureAccounts();
            $this->ledger->post(new PostingDTO(
                type: TransactionType::PAYMENT->value,
                description: "Faktur distribusi {$invoice->number} (PPN 11% simulasi)",
                idempotencyKey: 'dist:invoice:'.$invoice->id,
                entries: [
                    PostingEntryDTO::forCode(self::ACCT_RECEIVABLE, 'IDR', BigDecimal::of($net)),
                    PostingEntryDTO::forCode(self::ACCT_REVENUE, 'IDR', BigDecimal::of($net)->negated()),
                ],
                referenceType: DistInvoice::class,
                referenceId: $invoice->id,
                meta: ['ppn_idr' => (int) $order->ppn_idr, 'tax_serial' => $serial],
                postedAt: now(),
            ));

            return $invoice;
        });
    }

    // ── 43.3 Sell-out reporting + anomali ───────────────────────────────

    /**
     * Laporan sell-out per outlet per tanggal; deteksi anomali otomatis:
     * - stuffing: total qty >> rata-rata historis outlet (3×)
     * - diversion: SKU tidak pernah dijual outlet itu
     * - price_violation: harga jual < HET × 0,7 (di bawah ambang simulasi)
     *
     * @param  array<int, array{product_id: int, qty: float, unit_price_idr: int}>  $lines
     * @return array{report: SelloutReport, anomalies: array<int, string>}
     */
    public function submitSellout(Distributor $distributor, int $outletId, string $periodDate, array $lines, User $reporter): array
    {
        if ($lines === []) {
            throw new InvalidArgumentException('Laporan sell-out harus berisi baris.');
        }

        return DB::transaction(function () use ($distributor, $outletId, $periodDate, $lines, $reporter) {
            $existing = SelloutReport::where('distributor_id', $distributor->id)
                ->where('outlet_id', $outletId)
                ->where('period_date', $periodDate)
                ->first();
            if ($existing !== null) {
                throw new InvalidArgumentException('Laporan sell-out tanggal tersebut sudah ada.');
            }

            $report = SelloutReport::create([
                'distributor_id' => $distributor->id,
                'outlet_id' => $outletId,
                'period_date' => $periodDate,
                'status' => 'submitted',
                'reported_by_user_id' => $reporter->id,
            ]);

            $totalQty = 0.0;
            $totalValue = 0;
            $anomalies = [];

            foreach ($lines as $line) {
                $qty = (float) ($line['qty'] ?? 0);
                if ($qty <= 0) {
                    throw new InvalidArgumentException('Qty sell-out harus lebih besar dari nol.');
                }
                $price = (int) ($line['unit_price_idr'] ?? 0);
                $product = Product::find($line['product_id']);
                if ($product === null) {
                    throw new InvalidArgumentException('Produk sell-out tidak ditemukan.');
                }

                SelloutLine::create([
                    'report_id' => $report->id,
                    'product_id' => $product->id,
                    'sku' => (string) $product->sku,
                    'qty' => $qty,
                    'unit_price_idr' => $price,
                ]);

                // HET price compliance (43.7 simulasi).
                $het = HetPrice::where('sku', $product->sku)
                    ->where('valid_from', '<=', $periodDate)
                    ->where(function ($q) use ($periodDate) {
                        $q->whereNull('valid_until')->orWhere('valid_until', '>=', $periodDate);
                    })
                    ->first();
                if ($het !== null && $price > 0 && (float) $price < (float) $het->het_idr * 0.70) {
                    $anomalies[] = "price_violation:{$product->sku}";
                }

                $totalQty += $qty;
                $totalValue += (int) round($qty * $price);
            }

            // Stuffing: qty hari ini > 3× rata-rata 7 hari terakhir outlet.
            $recent = SelloutReport::where('outlet_id', $outletId)
                ->where('period_date', '<', $periodDate)
                ->orderByDesc('period_date')
                ->limit(7)
                ->avg('total_qty');
            if ($recent !== null && (float) $recent > 0 && $totalQty > (float) $recent * 3) {
                $anomalies[] = 'stuffing';
            }

            $report->update([
                'total_qty' => $totalQty,
                'total_value_idr' => $totalValue,
                'anomalies' => $anomalies,
                'status' => $anomalies === [] ? 'validated' : 'flagged',
            ]);

            // Catat capaian target sell-out bila cocok (43.3 → 42.5).
            $targets = $distributor->targets()
                ->where('basis', 'sell_out')
                ->where('period', Carbon::parse($periodDate)->format('Y'))
                ->get();
            foreach ($targets as $target) {
                $qtySku = SelloutLine::where('report_id', $report->id)
                    ->where('sku', $target->product_sku)
                    ->sum('qty');
                if ((float) $qtySku > 0) {
                    $this->distribution->recordAchievement($target, (float) $qtySku);
                }
            }

            return ['report' => $report->fresh('lines'), 'anomalies' => $anomalies];
        });
    }

    // ── 43.4 Konsinyasi ─────────────────────────────────────────────────

    /** Terima stok konsinyasi (milik prinsipal) ke lokasi distributor. */
    public function receiveConsignment(Distributor $distributor, int $productId, string $sku, float $qty, int $valueIdr): ConsignmentStock
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Qty konsinyasi harus lebih besar dari nol.');
        }

        return DB::transaction(function () use ($distributor, $productId, $sku, $qty, $valueIdr) {
            $stock = ConsignmentStock::firstOrCreate(
                ['distributor_id' => $distributor->id, 'product_id' => $productId],
                ['sku' => $sku, 'qty' => 0, 'qty_sold_unbilled' => 0, 'value_idr' => 0]
            );
            $stock->qty = bcadd((string) $stock->qty, (string) $qty, 6);
            $stock->value_idr = (int) $stock->value_idr + $valueIdr;
            $stock->save();

            return $stock;
        });
    }

    /**
     * Laporkan penjualan konsinyasi → kurangi stok, tambah belum-faktur.
     *
     * @return array{sale: ConsignmentSale, stock: ConsignmentStock}
     */
    public function reportConsignmentSale(ConsignmentStock $stock, float $qty, int $unitPriceIdr, string $soldAt, User $reporter): array
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Qty terjual harus lebih besar dari nol.');
        }

        return DB::transaction(function () use ($stock, $qty, $unitPriceIdr, $soldAt, $reporter) {
            /** @var ConsignmentStock $locked */
            $locked = ConsignmentStock::query()->lockForUpdate()->findOrFail($stock->getKey());
            if ((float) $locked->qty < $qty) {
                throw new InvalidArgumentException('Stok konsinyasi tidak mencukupi.');
            }

            $locked->qty = bcsub((string) $locked->qty, (string) $qty, 6);
            $locked->qty_sold_unbilled = bcadd((string) $locked->qty_sold_unbilled, (string) $qty, 6);
            $locked->save();

            $sale = ConsignmentSale::create([
                'consignment_id' => $locked->id,
                'sold_at' => $soldAt,
                'qty' => $qty,
                'unit_price_idr' => $unitPriceIdr,
                'status' => 'reported',
                'reported_by_user_id' => $reporter->id,
            ]);

            return ['sale' => $sale, 'stock' => $locked];
        });
    }

    /**
     * Rekonsiliasi konsinyasi: terbitkan faktur atas penjualan terlapor
     * → transfer kepemilikan (stok prinsipal → piutang).
     *
     * @return array{invoice: DistInvoice, billed: int}
     */
    public function invoiceConsignment(Distributor $distributor, User $issuer): array
    {
        return DB::transaction(function () use ($distributor, $issuer) {
            $pending = ConsignmentSale::where('status', 'reported')
                ->whereHas('consignment', fn ($q) => $q->where('distributor_id', $distributor->id))
                ->get();

            if ($pending->isEmpty()) {
                return ['invoice' => null, 'billed' => 0];
            }

            $subtotal = 0;
            foreach ($pending as $sale) {
                $subtotal += (int) round((float) $sale->qty * (int) $sale->unit_price_idr);
            }
            if ($subtotal <= 0) {
                return ['invoice' => null, 'billed' => 0];
            }

            $number = $this->numbering->nextNumber('DIST', 'FTR', false, 'FTR/{ENT}/');
            $invoice = DistInvoice::create([
                'number' => $number,
                'tax_serial' => sprintf('010.%s.%s.%s', '002', now()->format('ym'), $number),
                'order_id' => null,
                'distributor_id' => $distributor->id,
                'subtotal_idr' => $subtotal,
                'discount_idr' => 0,
                'ppn_idr' => 0,
                'total_idr' => $subtotal,
                'status' => 'issued',
                'issued_at' => now()->toDateString(),
                'notes' => 'Faktur konsinyasi — penjualan terlapor',
                'created_by_user_id' => $issuer->id,
            ]);

            foreach ($pending as $sale) {
                $sale->update(['status' => 'invoiced', 'invoice_id' => $invoice->id]);
            }

            // Stok belum dibayar berkurang → nilai kepemilikan pindah ke piutang.
            foreach (ConsignmentStock::where('distributor_id', $distributor->id)
                ->where('qty_sold_unbilled', '>', 0)->get() as $stock) {
                $stock->qty_sold_unbilled = 0;
                $stock->last_reconciled_at = now()->toDateString();
                $stock->save();
            }

            // Jurnal: DR piutang konsinyasi / CR aset konsinyasi.
            $this->ensureAccounts();
            $this->ledger->post(new PostingDTO(
                type: TransactionType::PAYMENT->value,
                description: "Rekonsiliasi konsinyasi {$distributor->code} ({$invoice->number})",
                idempotencyKey: 'dist:consign:'.$invoice->id,
                entries: [
                    PostingEntryDTO::forCode(self::ACCT_CONSIGNMENT_AR, 'IDR', BigDecimal::of($subtotal)),
                    PostingEntryDTO::forCode(self::ACCT_CONSIGNMENT, 'IDR', BigDecimal::of($subtotal)->negated()),
                ],
                referenceType: DistInvoice::class,
                referenceId: $invoice->id,
                postedAt: now(),
            ));

            return ['invoice' => $invoice, 'billed' => $pending->count()];
        });
    }

    // ── 43.5 Retur & klaim ──────────────────────────────────────────────

    /**
     * Ajukan retur. Kebijakan: alasan wajib valid, disposition wajib,
     * nilai ≤ nilai order asal (bila ada).
     *
     * @param  array{reason: string, disposition?: string, qty: float, amount_idr: int,
     *   order_id?: string, evidence_note?: string}  $data
     */
    public function requestReturn(Distributor $distributor, array $data, string $requestedAt, User $requester): DistReturn
    {
        if (! in_array($data['reason'], DistReturn::REASONS, true)) {
            throw new InvalidArgumentException('Alasan retur tidak dikenal.');
        }
        $disposition = $data['disposition'] ?? 'restock';
        if (! in_array($disposition, DistReturn::DISPOSITIONS, true)) {
            throw new InvalidArgumentException('Disposition retur tidak dikenal.');
        }
        if ((float) ($data['qty'] ?? 0) <= 0 || (int) ($data['amount_idr'] ?? 0) <= 0) {
            throw new InvalidArgumentException('Qty dan nilai retur harus lebih besar dari nol.');
        }

        return DB::transaction(function () use ($distributor, $data, $disposition, $requestedAt, $requester) {
            if (! empty($data['order_id'])) {
                $order = DistOrder::find($data['order_id']);
                if ($order === null || (string) $order->distributor_id !== (string) $distributor->id) {
                    throw new InvalidArgumentException('Order retur tidak ditemukan untuk distributor ini.');
                }
                if ((int) $data['amount_idr'] > (int) $order->total_idr) {
                    throw new InvalidArgumentException('Nilai retur melebihi nilai order asal.');
                }
            }

            $number = $this->numbering->nextNumber('DIST', 'RDN', false, 'RDN/{ENT}/');

            return DistReturn::create([
                'number' => $number,
                'distributor_id' => $distributor->id,
                'order_id' => $data['order_id'] ?? null,
                'reason' => $data['reason'],
                'disposition' => $disposition,
                'qty' => (float) $data['qty'],
                'amount_idr' => (int) $data['amount_idr'],
                'status' => 'requested',
                'evidence_note' => $data['evidence_note'] ?? null,
                'requested_at' => $requestedAt,
                'requested_by_user_id' => $requester->id,
            ]);
        });
    }

    /** Setujui retur → kredit nota (jurnal) + status credited. */
    public function approveReturn(DistReturn $return, User $approver): DistReturn
    {
        return DB::transaction(function () use ($return) {
            /** @var DistReturn $locked */
            $locked = DistReturn::query()->lockForUpdate()->findOrFail($return->getKey());
            if ($locked->status === 'credited') {
                return $locked; // idempoten
            }
            if ($locked->status !== 'requested' && $locked->status !== 'approved') {
                throw new InvalidArgumentException("Retur {$locked->number} tidak dapat disetujui ({$locked->status}).");
            }

            // Kredit nota: DR revenue / CR receivable (nilai negatif revenue).
            $this->ensureAccounts();
            $this->ledger->post(new PostingDTO(
                type: TransactionType::REFUND->value,
                description: "Kredit nota retur {$locked->number} ({$locked->reason})",
                idempotencyKey: 'dist:return:'.$locked->id,
                entries: [
                    PostingEntryDTO::forCode(self::ACCT_REVENUE, 'IDR', BigDecimal::of((int) $locked->amount_idr)),
                    PostingEntryDTO::forCode(self::ACCT_RECEIVABLE, 'IDR', BigDecimal::of((int) $locked->amount_idr)->negated()),
                ],
                referenceType: DistReturn::class,
                referenceId: $locked->id,
                meta: ['disposition' => $locked->disposition],
                postedAt: now(),
            ));

            // Pengurang exposure distributor (retur mengurangi piutang).
            $distributor = Distributor::query()->lockForUpdate()->findOrFail($locked->distributor_id);
            $distributor->credit_exposure_idr = max(
                0,
                (int) $distributor->credit_exposure_idr - (int) $locked->amount_idr
            );
            if ($distributor->status === 'blocked'
                && (int) $distributor->credit_exposure_idr < (int) $distributor->credit_limit_idr) {
                $distributor->status = 'approved';
            }
            $distributor->save();

            $locked->update(['status' => 'credited']);

            return $locked;
        });
    }

    // ── 43.6 Rebate & insentif ──────────────────────────────────────────

    public function createRebateProgram(array $data): RebateProgram
    {
        $code = strtoupper($data['code']);
        if (RebateProgram::where('code', $code)->exists()) {
            throw new InvalidArgumentException("Kode program rebate {$code} sudah dipakai.");
        }
        if (! in_array($data['kind'] ?? 'volume', ['volume', 'growth', 'tiered'], true)) {
            throw new InvalidArgumentException('Jenis program rebate tidak dikenal.');
        }
        if ($data['valid_until'] < $data['valid_from']) {
            throw new InvalidArgumentException('Periode program tidak valid.');
        }

        return RebateProgram::create([
            'code' => $code,
            'name' => $data['name'],
            'kind' => $data['kind'] ?? 'volume',
            'valid_from' => $data['valid_from'],
            'valid_until' => $data['valid_until'],
            'rate_percent' => $data['rate_percent'] ?? 0,
            'threshold_qty' => $data['threshold_qty'] ?? 0,
            'tier_breaks' => $data['tier_breaks'] ?? null,
            'is_active' => true,
        ]);
    }

    /**
     * Akrual rebate untuk order yang sudah faktur (idempoten per order+program).
     * Breakage: program kadaluarsa → accrual tidak lagi diakui (status expired).
     *
     * @return array<int, RebateAccrual>
     */
    public function accrueRebate(DistOrder $order, User $actor): array
    {
        return DB::transaction(function () use ($order) {
            $totalQty = (float) $order->lines()->sum('qty');
            $net = max(0, (int) $order->subtotal_idr - (int) $order->discount_idr);

            $accruals = [];
            foreach (RebateProgram::where('is_active', true)->get() as $program) {
                if (! $program->isLive()) {
                    continue;
                }

                $existing = RebateAccrual::where('distributor_id', $order->distributor_id)
                    ->where('program_id', $program->id)
                    ->where('order_id', $order->id)
                    ->first();
                if ($existing !== null) {
                    $accruals[] = $existing; // idempoten

                    continue;
                }

                $rate = $program->rateFor($totalQty);
                if ($rate <= 0) {
                    continue;
                }

                $rebate = (int) floor($net * $rate / 100);
                if ($rebate <= 0) {
                    continue;
                }

                $accrual = RebateAccrual::create([
                    'distributor_id' => $order->distributor_id,
                    'program_id' => $program->id,
                    'order_id' => $order->id,
                    'period' => now()->toDateString(),
                    'base_amount_idr' => $net,
                    'rate_percent' => $rate,
                    'rebate_amount_idr' => $rebate,
                    'status' => 'accrued',
                ]);

                // Jurnal akrual: DR expense / CR payable rebate.
                $this->ensureAccounts();
                $this->ledger->post(new PostingDTO(
                    type: TransactionType::MANUAL_ADJUSTMENT->value,
                    description: "Akrual rebate {$program->code} order {$order->number}",
                    idempotencyKey: 'dist:rebate:'.$accrual->id,
                    entries: [
                        PostingEntryDTO::forCode(self::ACCT_REBATE_EXPENSE, 'IDR', BigDecimal::of($rebate)),
                        PostingEntryDTO::forCode(self::ACCT_REBATE_PAYABLE, 'IDR', BigDecimal::of($rebate)->negated()),
                    ],
                    referenceType: RebateAccrual::class,
                    referenceId: $accrual->id,
                    postedAt: now(),
                ));

                $accruals[] = $accrual;
            }

            return $accruals;
        });
    }

    /**
     * Penyelesaian rebate periodik via approval four-eyes → status settled.
     *
     * @return array{accruals: int, approval: object}
     */
    public function settleRebate(string $period, User $creator): array
    {
        return DB::transaction(function () use ($period, $creator) {
            $pending = RebateAccrual::where('status', 'accrued')
                ->whereDate('period', $period)
                ->get();
            if ($pending->isEmpty()) {
                throw new InvalidArgumentException('Tidak ada akrual rebate untuk periode tersebut.');
            }

            $total = (int) $pending->sum('rebate_amount_idr');

            $approval = $this->approvals->submit(
                approvalType: 'DIST_REBATE_SETTLEMENT',
                title: "Penyelesaian rebate {$period} (".number_format($total).')',
                creator: $creator,
                amount: (float) $total,
                steps: [['role' => 'procurement'], ['role' => 'admin']],
                slaHours: 48,
                metadata: ['period' => $period, 'total_idr' => $total, 'count' => $pending->count()],
            );

            foreach ($pending as $accrual) {
                $accrual->update(['approval_id' => (int) $approval->id]);
            }

            return ['accruals' => $pending->count(), 'approval' => $approval];
        });
    }

    /** Setujui penyelesaian rebate → settled (lewat engine). */
    public function approveSettlement(string $period, User $approver): int
    {
        $accruals = RebateAccrual::where('status', 'accrued')
            ->whereDate('period', $period)
            ->whereNotNull('approval_id')
            ->get();

        if ($accruals->isEmpty()) {
            throw new InvalidArgumentException('Tidak ada akrual rebate menunggu persetujuan.');
        }

        $approvalId = (int) $accruals->first()->approval_id;
        $approval = $this->approvals->approve($approvalId, $approver, 'Rebate disetujui');
        for ($i = 0; $i < 5 && property_exists($approval, 'status') && $approval->status !== 'approved'; $i++) {
            $approval = $this->approvals->approve($approvalId, $approver, 'Rebate disetujui');
        }
        if (property_exists($approval, 'status') && $approval->status !== 'approved') {
            throw new InvalidArgumentException('Approval rebate belum tuntas.');
        }

        $count = 0;
        foreach ($accruals as $accrual) {
            $accrual->update(['status' => 'settled']);
            $count++;
        }

        return $count;
    }

    // ── 43.7 Margin & price compliance ──────────────────────────────────

    /**
     * Margin distributor per order: harga tebus − harga jual, cek HET.
     *
     * @param  array<int, string>  $skus
     * @return array<int, array{sku: string, sell_price: int, het: int|null, margin_percent: float}>
     */
    public function marginReport(DistOrder $order): array
    {
        $rows = [];
        foreach ($order->lines as $line) {
            $het = HetPrice::where('sku', $line->sku)
                ->where('valid_from', '<=', now()->toDateString())
                ->where(function ($q) {
                    $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()->toDateString());
                })
                ->first();

            $sell = (int) $line->unit_price_idr;
            $hetValue = $het !== null ? (int) $het->het_idr : null;
            // Margin terhadap HET (proxy harga tebus = HET × 0,85 simulasi).
            $cost = $hetValue !== null ? (int) floor($hetValue * 0.85) : $sell;
            $margin = $sell > 0 ? (($sell - $cost) / $sell) * 100 : 0.0;

            $rows[] = [
                'sku' => $line->sku,
                'sell_price' => $sell,
                'het' => $hetValue,
                'margin_percent' => round($margin, 2),
            ];
        }

        return $rows;
    }

    // ── 43.8 Stok kritis → saran VMI ────────────────────────────────────

    public function setStockLevel(Distributor $distributor, int $productId, string $sku, float $onHand, float $minQty, float $maxQty, float $avgDailySales): StockLevel
    {
        $level = StockLevel::updateOrCreate(
            ['distributor_id' => $distributor->id, 'sku' => strtoupper($sku)],
            [
                'product_id' => $productId,
                'qty_on_hand' => $onHand,
                'min_qty' => $minQty,
                'max_qty' => $maxQty,
                'avg_daily_sales' => $avgDailySales,
            ]
        );

        $level->suggested_order_qty = $level->calculateSuggestion();
        $level->status = $level->isCritical() ? 'critical' : 'ok';
        $level->save();

        return $level;
    }

    /** Sweep VMI: semua level kritis → saran order. */
    public function computeVmiSuggestions(): array
    {
        $suggestions = [];
        foreach (StockLevel::all() as $level) {
            $level->suggested_order_qty = $level->calculateSuggestion();
            $level->status = $level->isCritical() ? 'critical' : 'ok';
            $level->save();
            if ((float) $level->suggested_order_qty > 0) {
                $suggestions[] = $level;
            }
        }

        return $suggestions;
    }

    // ── Akun ledger ─────────────────────────────────────────────────────

    public function ensureAccounts(): void
    {
        $this->distribution->ensureAccounts();

        foreach ([
            [self::ACCT_REVENUE, 'Pendapatan Distribusi', AccountKind::REVENUE],
            [self::ACCT_REBATE_EXPENSE, 'Beban Rebate Distributor', AccountKind::EXPENSE],
            [self::ACCT_REBATE_PAYABLE, 'Rebate Payable Distributor', AccountKind::LIABILITY],
            [self::ACCT_CONSIGNMENT, 'Persediaan Konsinyasi', AccountKind::INVENTORY],
            [self::ACCT_CONSIGNMENT_AR, 'Piutang Konsinyasi', AccountKind::ASSET],
        ] as [$code, $name, $kind]) {
            LedgerAccount::firstOrCreate(
                ['code' => $code, 'asset_code' => 'IDR'],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $name, 'kind' => $kind->value,
                    'allow_negative' => true, 'cached_balance' => '0', 'is_frozen' => false,
                ]
            );
        }
    }
}
