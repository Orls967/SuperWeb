<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Application\Services;

use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Core\Contracts\ApprovalEngineInterface;
use Modules\Manufacturing\Domain\Models\Bom;
use Modules\Manufacturing\Domain\Models\BomLine;
use Modules\Manufacturing\Domain\Models\CostVersion;
use Modules\Manufacturing\Domain\Models\FgReceipt;
use Modules\Manufacturing\Domain\Models\Material;
use Modules\Manufacturing\Domain\Models\MaterialIssue;
use Modules\Manufacturing\Domain\Models\MaterialLot;
use Modules\Manufacturing\Domain\Models\OperationReport;
use Modules\Manufacturing\Domain\Models\OrderCost;
use Modules\Manufacturing\Domain\Models\ProductionOrder;
use Modules\Manufacturing\Domain\Models\ReworkRecord;
use Modules\Manufacturing\Domain\Models\Routing;
use Modules\Manufacturing\Domain\Models\StandardCost;
use Modules\Manufacturing\Domain\Models\SubcontractReceipt;
use Modules\Manufacturing\Domain\Models\Variance;
use Modules\Manufacturing\Domain\Models\WorkCenter;

/**
 * Biaya produksi (Fase 38): standard cost ber-versi, actual per order,
 * jurnal WIP/FG, varians, COGS penjualan.
 *
 * Konvensi ledger (lihat DECISIONS Fase 38): debit positif, kredit negatif.
 * - Issue bahan:      DR inv:wip      / CR inv:materials
 * - Konversi:         DR inv:wip      / CR clearing:external
 * - Scrap:            DR expense:mfg_scrap / CR inv:wip
 * - Penerimaan FG:    DR inv:finished_goods / CR inv:wip
 * - COGS penjualan:   DR expense:mfg_cogs / CR inv:finished_goods
 * - Varians post:     DR expense:mfg_variance / CR clearing:external
 * - Varians capitalize: DR inv:wip / CR clearing:external
 */
class CostingService
{
    public const ACCT_MATERIALS = 'inv:materials:IDR';

    public const ACCT_WIP = 'inv:wip:IDR';

    public const ACCT_FG = 'inv:finished_goods:IDR';

    public const ACCT_SCRAP = 'expense:mfg_scrap:IDR';

    public const ACCT_COGS = 'expense:mfg_cogs:IDR';

    public const ACCT_VARIANCE = 'expense:mfg_variance:IDR';

    public const ACCT_CLEARING = 'clearing:external:IDR';

    public function __construct(
        private readonly Ledger $ledger,
        private readonly ApprovalEngineInterface $approvals,
    ) {}

    // ── 38.1 Standard cost: versi + roll-up ─────────────────────────────

    /**
     * @param  array<string, int>  $baseCosts  Biaya dasar per material_id (biasa untuk bahan baku)
     */
    public function createCostVersion(string $name, array $baseCosts, User $creator, string $notes = ''): CostVersion
    {
        $version = (int) CostVersion::where('name', $name)->max('version') + 1;

        return DB::transaction(function () use ($name, $baseCosts, $creator, $notes, $version) {
            $costVersion = CostVersion::create([
                'name' => $name, 'version' => $version, 'status' => 'draft',
                'notes' => $notes, 'created_by_user_id' => $creator->id,
            ]);

            $this->rollUpStandardCosts($costVersion, $baseCosts);

            return $costVersion->load('standardCosts.material');
        });
    }

    /**
     * Roll-up: bahan → Σ(bahan × std) + konversi routing × biaya/jam WC.
     * Level: bahan baku memakai $baseCosts, barang jadi/WIP dihitung setelah induknya.
     */
    public function rollUpStandardCosts(CostVersion $version, array $baseCosts = []): CostVersion
    {
        $materials = Material::where('is_active', true)->get()->keyBy('id');
        $unitCosts = [];

        // Bahan baku/packaging: biaya dasar diberikan.
        foreach ($materials as $id => $material) {
            if (in_array($material->kind, ['raw', 'packaging'], true)) {
                $base = (int) ($baseCosts[$id] ?? $baseCosts[$material->code] ?? 0);
                $unitCosts[$id] = [
                    'unit' => $base,
                    'breakdown' => ['material' => $base, 'conversion' => 0, 'overhead' => 0],
                ];
            }
        }

        // Level: output BOM setelah inputnya (relaksasi level, sama seperti MRP).
        $edges = BomLine::join('mfg_boms', 'mfg_boms.id', '=', 'mfg_bom_lines.bom_id')
            ->get(['mfg_boms.output_material_id as out', 'mfg_bom_lines.input_material_id as in']);
        $level = array_fill_keys($materials->keys()->all(), 0);
        for ($pass = 0, $n = max(1, $materials->count()); $pass < $n; $pass++) {
            $changed = false;
            foreach ($edges as $e) {
                if (($level[$e->out] ?? 0) + 1 > ($level[$e->in] ?? 0)) {
                    $level[$e->in] = ($level[$e->out] ?? 0) + 1;
                    $changed = true;
                }
            }
            if (! $changed) {
                break;
            }
        }
        asort($level);

        $computed = 0;
        foreach ($level as $id => $lvl) {
            $material = $materials[$id] ?? null;
            if ($material === null) {
                continue;
            }

            if (! isset($unitCosts[$id])) {
                $unitCosts[$id] = $this->rollUpOne($material, $unitCosts, $version);
            }

            $unit = $unitCosts[$id];
            $breakdown = $unit['breakdown'];
            StandardCost::updateOrCreate(
                ['version_id' => $version->id, 'material_id' => $id],
                [
                    'material_cost_idr' => $breakdown['material'],
                    'conversion_cost_idr' => $breakdown['conversion'],
                    'overhead_cost_idr' => $breakdown['overhead'],
                    'unit_cost_idr' => $unit['unit'],
                ]
            );
            $computed++;
        }

        return $version->refresh();
    }

    /**
     * @param  array<string, int|array{unit: int, breakdown: array{material: int, conversion: int, overhead: int}}>  $unitCosts
     * @return array{unit: int, breakdown: array{material: int, conversion: int, overhead: int}}
     */
    private function rollUpOne($material, array $unitCosts, CostVersion $version): array
    {
        $materialCost = 0;
        $conversion = 0;
        $overhead = 0;

        $bom = Bom::where('output_material_id', $material->id)
            ->where('is_active', true)->orderByDesc('version')->get()
            ->first(fn ($b) => $b->isEffective());

        if ($bom !== null) {
            foreach ($bom->lines as $line) {
                if ($line->is_by_product || $line->is_co_product) {
                    continue;
                }
                $input = $unitCosts[$line->input_material_id] ?? 0;
                $inputCost = is_array($input) ? (int) ($input['unit'] ?? 0) : (int) $input;
                $perUnit = (float) $line->qty / max(0.000001, (float) $bom->output_qty);
                $scrap = 1 + ((float) $line->scrap_percent / 100);
                $materialCost += (int) round($perUnit * $scrap * (int) $inputCost);
            }
        }

        // Konversi dari routing: Σ menit siklus × biaya per jam WC (ceil).
        $routing = Routing::where('output_material_id', $material->id)
            ->where('is_active', true)->orderByDesc('version')->get()
            ->first(fn ($r) => $r->isEffective());

        if ($routing !== null) {
            foreach ($routing->operations as $op) {
                if ($op->work_center_id === null) {
                    continue;
                }
                $wc = WorkCenter::find($op->work_center_id);
                if ($wc === null) {
                    continue;
                }
                $minutes = $op->setup_minutes + $op->run_minutes_per_unit;
                $hourly = $wc->totalCostPerHour();
                $machine = (int) ceil($minutes * (int) $wc->machine_cost_per_hour_idr / 60);
                $labor = (int) ceil($minutes * (int) $wc->labor_cost_per_hour_idr / 60);
                $oh = (int) ceil($minutes * (int) $wc->overhead_per_hour_idr / 60);
                $conversion += $machine + $labor;
                $overhead += $oh;
            }
        }

        return ['unit' => $materialCost + $conversion + $overhead, 'breakdown' => [
            'material' => $materialCost, 'conversion' => $conversion, 'overhead' => $overhead,
        ]];
    }

    public function submitCostVersion(CostVersion $version, User $creator): CostVersion
    {
        return DB::transaction(function () use ($version, $creator) {
            /** @var CostVersion $locked */
            $locked = CostVersion::query()->lockForUpdate()->findOrFail($version->getKey());
            if ($locked->status !== 'draft') {
                throw new InvalidArgumentException("Versi biaya hanya diajukan dari draft (saat ini {$locked->status}).");
            }

            $approval = $this->approvals->submit(
                approvalType: 'MFG_COST_VERSION',
                title: "Versi biaya {$locked->name} v{$locked->version}",
                creator: $creator,
                approvable: $locked,
                steps: [['role' => 'admin']],
                slaHours: 72,
                metadata: ['cost_version_id' => $locked->id],
            );

            $locked->update(['status' => 'pending_approval', 'approval_id' => (int) $approval->id]);

            return $locked;
        });
    }

    public function approveCostVersion(CostVersion $version, User $approver): CostVersion
    {
        return DB::transaction(function () use ($version, $approver) {
            /** @var CostVersion $locked */
            $locked = CostVersion::query()->lockForUpdate()->findOrFail($version->getKey());
            if ($locked->status !== 'pending_approval') {
                throw new InvalidArgumentException("Versi biaya tidak dalam antrian approval (status {$locked->status}).");
            }

            if ($locked->approval_id !== null) {
                $this->approvals->approve((int) $locked->approval_id, $approver, 'Versi biaya disetujui');
            }

            CostVersion::where('status', 'approved')->update(['status' => 'retired']);
            $locked->update(['status' => 'approved', 'approved_at' => now()]);

            return $locked->fresh();
        });
    }

    /** Standar unit cost dari versi approved (atau 0). */
    public function standardUnitCost(string $materialId): int
    {
        $approved = CostVersion::approved();
        if ($approved === null) {
            return 0;
        }

        return (int) StandardCost::where('version_id', $approved->id)
            ->where('material_id', $materialId)
            ->value('unit_cost_idr') ?? 0;
    }

    // ── Jurnal: issue / konversi / scrap / FG ───────────────────────────

    /** DR inv:wip / CR inv:materials — biaya bahan masuk WIP. */
    public function postIssue(ProductionOrder $order, array $issues): ?LedgerTransaction
    {
        $total = 0;
        foreach ($issues as $issue) {
            $total += $this->issueCost($issue);
        }
        if ($total <= 0) {
            return null;
        }

        $this->ensureAccounts();

        return $this->ledger->post(new PostingDTO(
            type: TransactionType::PRODUCTION->value,
            description: "Pengeluaran bahan order {$order->number}",
            idempotencyKey: 'mfg:issue:'.collect($issues)->pluck('id')->sort()->implode('-'),
            entries: [
                PostingEntryDTO::forCode(self::ACCT_WIP, 'IDR', BigDecimal::of($total)),
                PostingEntryDTO::forCode(self::ACCT_MATERIALS, 'IDR', BigDecimal::of($total)->negated()),
            ],
            referenceType: ProductionOrder::class,
            referenceId: $order->id,
            meta: ['order_number' => $order->number, 'cost_idr' => $total],
            postedAt: now(),
        ));
    }

    /** Biaya satu issue: alokasi lot × unit cost + sisa tanpa lot × standar. */
    public function issueCost(MaterialIssue $issue): int
    {
        if (in_array($issue->kind, ['issue', 'backflush'], true) === false) {
            return 0;
        }

        $cost = 0;
        foreach ($issue->issueLots as $allocation) {
            $cost += (int) round((float) $allocation->qty * (int) ($allocation->lot?->unit_cost_idr ?? 0));
        }

        $allocated = (float) $issue->issueLots()->sum('qty');
        $unlotted = max(0.0, (float) $issue->qty - $allocated);
        if ($unlotted > 0) {
            $cost += (int) round($unlotted * $this->standardUnitCost((string) $issue->material_id));
        }

        return $cost;
    }

    /** DR inv:wip / CR clearing — konversi (tenaga + mesin + overhead). */
    public function postConversion(ProductionOrder $order, OperationReport $report): ?LedgerTransaction
    {
        $cost = $this->conversionCost($report);
        if ($cost <= 0) {
            return null;
        }

        $this->ensureAccounts();

        return $this->ledger->post(new PostingDTO(
            type: TransactionType::PRODUCTION->value,
            description: "Konversi operasi #{$report->sequence} order {$order->number}",
            idempotencyKey: "mfg:convert:{$order->id}:{$report->id}",
            entries: [
                PostingEntryDTO::forCode(self::ACCT_WIP, 'IDR', BigDecimal::of($cost)),
                PostingEntryDTO::forCode(self::ACCT_CLEARING, 'IDR', BigDecimal::of($cost)->negated()),
            ],
            referenceType: ProductionOrder::class,
            referenceId: $order->id,
            meta: ['minutes' => $report->duration_minutes, 'cost_idr' => $cost],
            postedAt: now(),
        ));
    }

    /** Biaya konversi laporan: menit × biaya/jam WC (labor+machine+overhead). */
    public function conversionCost(OperationReport $report): int
    {
        if ($report->work_center_id === null || $report->duration_minutes <= 0) {
            return 0;
        }

        $wc = WorkCenter::find($report->work_center_id);
        if ($wc === null) {
            return 0;
        }

        return (int) ceil($report->duration_minutes * $wc->totalCostPerHour() / 60);
    }

    /** DR expense:mfg_scrap / CR inv:wip — biaya scrap. */
    public function postScrap(ProductionOrder $order, ReworkRecord $record): ?LedgerTransaction
    {
        $cost = (int) $record->cost_idr;
        if ($cost <= 0) {
            return null;
        }

        $this->ensureAccounts();

        return $this->ledger->post(new PostingDTO(
            type: TransactionType::WASTE->value,
            description: "Scrap order {$order->number}: {$record->reason}",
            idempotencyKey: 'mfg:scrap:'.$record->id,
            entries: [
                PostingEntryDTO::forCode(self::ACCT_SCRAP, 'IDR', BigDecimal::of($cost)),
                PostingEntryDTO::forCode(self::ACCT_WIP, 'IDR', BigDecimal::of($cost)->negated()),
            ],
            referenceType: ProductionOrder::class,
            referenceId: $order->id,
            meta: ['record_id' => $record->id, 'qty' => (string) $record->qty],
            postedAt: now(),
        ));
    }

    /**
     * DR inv:finished_goods / CR inv:wip — penerimaan FG.
     *
     * @return int unit cost terpasang
     */
    public function postFgReceipt(ProductionOrder $order, FgReceipt $receipt): int
    {
        if ((bool) $receipt->by_product) {
            // By-product tidak membebani WIP order induk (38.6: nilai relatif
            // dicatat sebagai kredit pada order cost).
            return 0;
        }

        // Biaya inkremental: total cost sejauh ini − yang sudah keluar lewat
        // penerimaan FG sebelumnya (mencegah konversi terposting ganda saat
        // FG diterima bertahap).
        $cost = $this->fgTransferable($order, $receipt);

        $receipt->unit_cost_idr = $cost;
        $receipt->save();

        if ($cost <= 0) {
            return 0;
        }

        $this->ensureAccounts();

        $this->ledger->post(new PostingDTO(
            type: TransactionType::PRODUCTION->value,
            description: "Penerimaan FG order {$order->number}",
            idempotencyKey: 'mfg:fg:'.$receipt->id,
            entries: [
                PostingEntryDTO::forCode(self::ACCT_FG, 'IDR', BigDecimal::of($cost)),
                PostingEntryDTO::forCode(self::ACCT_WIP, 'IDR', BigDecimal::of($cost)->negated()),
            ],
            referenceType: ProductionOrder::class,
            referenceId: $order->id,
            meta: ['receipt_id' => $receipt->id, 'unit_cost_idr' => $cost / max(1, (int) ceil((float) $receipt->qty))],
            postedAt: now(),
        ));

        return $cost;
    }

    /** Biaya aktual per unit (order cost total / qty_completed). */
    public function fgUnitCost(ProductionOrder $order): int
    {
        $cost = $this->computeOrderCost($order);
        $qty = max(1, (int) ceil((float) $order->qty_completed));

        return (int) floor((int) $cost->total_idr / $qty);
    }

    /**
     * Sisa biaya yang belum dipindahkan ke FG: total cost order
     * + varians yang dicapitalize − yang sudah tercatat pada FG receipt.
     */
    public function fgTransferable(ProductionOrder $order, ?FgReceipt $pending = null): int
    {
        $cost = $this->computeOrderCost($order);
        $varianceCapitalize = (int) Variance::where('order_id', $order->id)
            ->where('policy', 'capitalize')->where('posted', true)->sum('amount_idr');
        $total = max(0, (int) $cost->total_idr + $varianceCapitalize);

        $movedOut = (int) FgReceipt::where('production_order_id', $order->id)
            ->where('by_product', false)
            ->when($pending !== null, fn ($q) => $q->where('id', '!=', $pending->id))
            ->sum('unit_cost_idr');

        $completed = max(1.0, (float) $order->qty_completed);
        $previousReceived = (float) FgReceipt::where('production_order_id', $order->id)
            ->where('by_product', false)
            ->when($pending !== null, fn ($q) => $q->where('id', '!=', $pending->id))
            ->sum('qty');
        $pendingQty = $pending !== null ? (float) $pending->qty : 0.0;
        $cumulativeReceived = $previousReceived + $pendingQty;

        // Target kumulatif proporsional terhadap hasil lapangan; receipt
        // terakhir menyerap pembulatan sisa.
        $targetMoved = $total <= 0
            ? 0
            : (int) floor($total * min(1.0, $cumulativeReceived / $completed));

        $remaining = max(0, $total - $movedOut);
        $transfer = $targetMoved - $movedOut;

        if ($transfer < 0) {
            $transfer = 0;
        }
        if ($transfer > $remaining) {
            $transfer = $remaining;
        }

        return $transfer;
    }

    // ── 38.2 Actual costing per order ────────────────────────────────────

    public function computeOrderCost(ProductionOrder $order): OrderCost
    {
        $material = 0;
        foreach ($order->issues as $issue) {
            $material += $this->issueCost($issue);
        }

        $labor = $machine = $overhead = 0;
        foreach ($order->operationReports as $report) {
            if ($report->work_center_id === null || $report->duration_minutes <= 0) {
                continue;
            }
            $wc = WorkCenter::find($report->work_center_id);
            if ($wc === null) {
                continue;
            }
            $labor += (int) ceil($report->duration_minutes * (int) $wc->labor_cost_per_hour_idr / 60);
            $machine += (int) ceil($report->duration_minutes * (int) $wc->machine_cost_per_hour_idr / 60);
            $overhead += (int) ceil($report->duration_minutes * (int) $wc->overhead_per_hour_idr / 60);
        }

        $subcontract = (int) SubcontractReceipt::where('production_order_id', $order->id)->sum('service_cost_idr');

        // By-product/co-product: kredit nilai relatif (38.6) = standar unit × qty.
        $byproduct = 0;
        foreach ($order->fgReceipts()->where('by_product', true)->get() as $receipt) {
            $byproduct += (int) ceil((float) $receipt->qty * $this->standardUnitCost((string) $receipt->material_id));
        }

        $total = $material + $labor + $machine + $overhead + $subcontract - $byproduct;
        if ($total < 0) {
            $total = 0;
        }

        $qty = max(1, (int) ceil((float) $order->qty_completed));

        return OrderCost::updateOrCreate(
            ['order_id' => $order->id],
            [
                'material_idr' => $material, 'labor_idr' => $labor, 'machine_idr' => $machine,
                'overhead_idr' => $overhead, 'subcontract_idr' => $subcontract,
                'byproduct_credit_idr' => $byproduct, 'total_idr' => $total,
                'unit_cost_idr' => (int) floor($total / $qty), 'computed_at' => now(),
            ]
        );
    }

    // ── 38.4 Varians ─────────────────────────────────────────────────────

    /**
     * Hitung varians vs standar (murni, tanpa posting).
     *
     * @return array<int, array{kind: string, amount_idr: int}>
     */
    public function computeVariances(ProductionOrder $order): array
    {
        $cost = $this->computeOrderCost($order);
        $approved = CostVersion::approved();
        if ($approved === null) {
            return [];
        }

        $std = StandardCost::where('version_id', $approved->id)
            ->where('material_id', $order->material_id)
            ->first();
        if ($std === null) {
            return [];
        }

        $qty = max(0.0, (float) $order->qty_completed);
        $variances = [];

        // Price: bahan dibeli di atas standar.
        $price = 0;
        foreach ($order->issues as $issue) {
            foreach ($issue->issueLots as $allocation) {
                $lotCost = (int) ($allocation->lot?->unit_cost_idr ?? 0);
                $stdUnit = $this->standardUnitCost((string) $issue->material_id);
                $price += (int) round((float) $allocation->qty * ($lotCost - $stdUnit));
            }
        }
        $variances[] = ['kind' => 'price', 'amount_idr' => $price];

        // Usage: (qty bahan aktual − qty std untuk hasil aktual) × harga std.
        // Terpisah dari price agar tidak menumpuk efek harga ganda.
        $usage = 0;
        $stdQtyByMaterial = [];
        $bom = Bom::where('output_material_id', $order->material_id)
            ->where('is_active', true)->orderByDesc('version')->get()
            ->first(fn ($b) => $b->isEffective($order->due_date?->toDateString() ?? now()->toDateString()));
        if ($bom !== null) {
            foreach ($bom->lines as $line) {
                if ($line->is_by_product || $line->is_co_product) {
                    continue;
                }
                $stdQtyByMaterial[(string) $line->input_material_id] =
                    ($stdQtyByMaterial[(string) $line->input_material_id] ?? 0.0)
                    + $line->requiredQty($qty);
            }
        }
        $issuedByMaterial = $order->issues()
            ->whereIn('kind', ['issue', 'backflush'])
            ->selectRaw('material_id, SUM(qty) as total_qty')
            ->groupBy('material_id')
            ->get();
        foreach ($issuedByMaterial as $row) {
            $mid = (string) $row->material_id;
            $stdUnit = $this->standardUnitCost($mid);
            $issued = (float) $row->total_qty;
            $required = $stdQtyByMaterial[$mid] ?? 0.0;
            $usage += (int) round(($issued - $required) * $stdUnit);
        }
        $variances[] = ['kind' => 'usage', 'amount_idr' => $usage];

        // Labor efficiency: tenaga aktual vs standar konversi (tenaga+mesin).
        $stdConversion = (int) ceil($std->conversion_cost_idr * $qty);
        $variances[] = ['kind' => 'labor_efficiency', 'amount_idr' => ((int) $cost->labor_idr + (int) $cost->machine_idr) - $stdConversion];

        // Overhead volume: overhead aktual vs diserap standar.
        $stdOverhead = (int) ceil($std->overhead_cost_idr * $qty);
        $variances[] = ['kind' => 'overhead_volume', 'amount_idr' => (int) $cost->overhead_idr - $stdOverhead];

        // Yield: hasil di bawah target membebani (std unit × selisih).
        $yield = (int) ceil($std->unit_cost_idr * max(0.0, (float) $order->qty - $qty));
        $variances[] = ['kind' => 'yield', 'amount_idr' => $yield];

        return array_values(array_filter($variances, fn (array $v) => $v['amount_idr'] !== 0));
    }

    /**
     * Simpan + posting varians. `post` → jurnal beban; `capitalize` → masuk WIP.
     *
     * @return array<int, Variance>
     */
    public function postVariances(ProductionOrder $order, string $policy = 'post', bool $postToLedger = true): array
    {
        if (! in_array($policy, ['post', 'capitalize'], true)) {
            throw new InvalidArgumentException('Kebijakan varians harus post atau capitalize.');
        }

        $created = [];
        foreach ($this->computeVariances($order) as $row) {
            $variance = Variance::updateOrCreate(
                ['order_id' => $order->id, 'kind' => $row['kind']],
                ['amount_idr' => $row['amount_idr'], 'policy' => $policy, 'notes' => null]
            );

            if ($postToLedger && ! $variance->posted && $variance->amount_idr !== 0) {
                $this->ensureAccounts();
                $debit = $policy === 'capitalize' ? self::ACCT_WIP : self::ACCT_VARIANCE;
                $tx = $this->ledger->post(new PostingDTO(
                    type: TransactionType::MANUAL_ADJUSTMENT->value,
                    description: "Varians {$variance->kind} order {$order->number} ({$policy})",
                    idempotencyKey: "mfg:variance:{$order->id}:{$variance->kind}",
                    entries: [
                        PostingEntryDTO::forCode($debit, 'IDR', BigDecimal::of($variance->amount_idr)),
                        PostingEntryDTO::forCode(self::ACCT_CLEARING, 'IDR', BigDecimal::of($variance->amount_idr)->negated()),
                    ],
                    referenceType: ProductionOrder::class,
                    referenceId: $order->id,
                    meta: ['kind' => $variance->kind, 'policy' => $policy],
                    postedAt: now(),
                ));
                $variance->update(['posted' => true, 'ledger_transaction_id' => $tx->id]);
            }

            $created[] = $variance;
        }

        return $created;
    }

    // ── 38.8 Settle order closed: WIP sisa → FG ─────────────────────────

    /**
     * Saat order ditutup: sisa WIP (total cost − yang sudah keluar lewat
     * penerimaan FG) diserap ke FG agar WIP order = 0.
     *
     * @return int sisa yang diserap (positif: DR FG; negatif: CR FG)
     */
    public function settleClosedOrder(ProductionOrder $order): int
    {
        $cost = $this->computeOrderCost($order);
        $fgOut = (int) FgReceipt::where('production_order_id', $order->id)
            ->where('by_product', false)->sum('unit_cost_idr');
        $varianceIn = (int) Variance::where('order_id', $order->id)
            ->where('policy', 'capitalize')->where('posted', true)->sum('amount_idr');

        $remaining = ((int) $cost->total_idr) + $varianceIn - $fgOut;
        if ($remaining === 0) {
            return 0;
        }

        $this->ensureAccounts();

        // Sisa positif (belum terima FG penuh) → tetap di WIP: biarkan agar
        // audit menandainya; tetapkan FG unit cost penyesuaian bila order closed.
        $tx = $this->ledger->post(new PostingDTO(
            type: TransactionType::PRODUCTION->value,
            description: "Penyelesaian order tertutup {$order->number}",
            idempotencyKey: 'mfg:settle:'.$order->id,
            entries: [
                PostingEntryDTO::forCode(self::ACCT_FG, 'IDR', BigDecimal::of($remaining)),
                PostingEntryDTO::forCode(self::ACCT_WIP, 'IDR', BigDecimal::of($remaining)->negated()),
            ],
            referenceType: ProductionOrder::class,
            referenceId: $order->id,
            meta: ['remaining_idr' => $remaining],
            postedAt: now(),
        ));

        // Catat sebagai penyesuaian FG receipt sintetis agar audit konsisten.
        FgReceipt::create([
            'production_order_id' => $order->id,
            'material_id' => $order->material_id,
            'qty' => 0,
            'unit_cost_idr' => $remaining,
            'by_product' => false,
            'notes' => 'Penyelesaian WIP order closed',
            'received_by_user_id' => $order->created_by_user_id,
        ]);

        unset($tx);

        return $remaining;
    }

    // ── 38.5 COGS penjualan ─────────────────────────────────────────────

    /**
     * HPP penjualan: cocokkan item order Store dengan material pabrik
     * (SKU = kode material), FIFO dari lot, DR expense:mfg_cogs / CR inv:fg.
     *
     * @return int total HPP yang diposting
     */
    public function postCogs(int $storeOrderId, int $storeOrderItemId, string $materialCode, int $qty): int
    {
        $material = Material::where('code', $materialCode)->first();
        if ($material === null || $qty <= 0) {
            return 0;
        }

        // FIFO dari lot aktif; sisa pakai standar.
        $lots = MaterialLot::where('material_id', $material->id)
            ->where('status', 'active')->where('qty', '>', 0)
            ->orderBy('produced_at')->get();

        $remaining = $qty;
        $cost = 0;
        foreach ($lots as $lot) {
            if ($remaining <= 0) {
                break;
            }
            $take = min((int) ceil((float) $lot->qty), $remaining);
            $cost += $take * (int) $lot->unit_cost_idr;
            $remaining -= $take;
        }
        if ($remaining > 0) {
            $cost += $remaining * $this->standardUnitCost($material->id);
        }

        if ($cost <= 0) {
            return 0;
        }

        $this->ensureAccounts();

        $this->ledger->post(new PostingDTO(
            type: TransactionType::PRODUCTION->value,
            description: "HPP penjualan material {$materialCode} (order Store #{$storeOrderId})",
            idempotencyKey: "mfg:cogs:{$storeOrderId}:{$storeOrderItemId}",
            entries: [
                PostingEntryDTO::forCode(self::ACCT_COGS, 'IDR', BigDecimal::of($cost)),
                PostingEntryDTO::forCode(self::ACCT_FG, 'IDR', BigDecimal::of($cost)->negated()),
            ],
            referenceType: 'store_order',
            referenceId: $storeOrderId,
            meta: ['material_code' => $materialCode, 'qty' => $qty, 'cost_idr' => $cost],
            postedAt: now(),
        ));

        return $cost;
    }

    /**
     * 38.5 dari event `OrderPaid`: posting HPP semua baris item order
     * yang SKU-nya cocok kode material. Query mentah — tanpa import
     * Domain Store (pola seeder/manajer lintas modul).
     */
    public function postOrderCogs(int $storeOrderId): int
    {
        $items = DB::table('store_order_items as oi')
            ->join('store_products as p', 'p.id', '=', 'oi.product_id')
            ->where('oi.order_id', $storeOrderId)
            ->get(['oi.id as item_id', 'p.sku', 'oi.qty']);

        $total = 0;
        foreach ($items as $item) {
            if ($item->sku === null || $item->sku === '') {
                continue;
            }
            $total += $this->postCogs(
                $storeOrderId,
                (int) $item->item_id,
                (string) $item->sku,
                (int) $item->qty,
            );
        }

        return $total;
    }

    // ── 38.7 Laporan margin ──────────────────────────────────────────────

    /**
     * Laporan per material: harga jual (harga produk Store dengan SKU sama),
     * unit cost aktual rata-rata, margin.
     *
     * @return array<int, array{material: string, code: string, sold_qty: int, revenue_idr: int, cogs_idr: int, margin_idr: int}>
     */
    public function marginReport(): array
    {
        $rows = [];
        $materials = Material::where('kind', 'finished')->get();
        foreach ($materials as $material) {
            $fgReceipts = FgReceipt::where('material_id', $material->id)->where('by_product', false)->get();
            $produced = (int) ceil((float) $fgReceipts->sum('qty'));
            $producedCost = (int) $fgReceipts->sum('unit_cost_idr');
            $unit = $produced > 0 ? (int) floor($producedCost / $produced) : $this->standardUnitCost($material->id);

            $price = (int) DB::table('store_products')
                ->where('sku', $material->code)->value('price') ?? 0;

            $orders = DB::table('store_order_items')
                ->join('store_products', 'store_products.id', '=', 'store_order_items.product_id')
                ->where('store_products.sku', $material->code)
                ->selectRaw('COALESCE(SUM(qty),0) as sold, COALESCE(SUM(line_total),0) as revenue')
                ->first();

            $soldQty = (int) ($orders->sold ?? 0);
            $revenue = (int) ($orders->revenue ?? 0);
            $cogs = $soldQty * $unit;

            if ($soldQty === 0 && $produced === 0) {
                continue;
            }

            $rows[] = [
                'material' => $material->name, 'code' => $material->code,
                'sold_qty' => $soldQty, 'revenue_idr' => $revenue,
                'cogs_idr' => $cogs, 'margin_idr' => $revenue - $cogs,
                'price_idr' => $price, 'unit_cost_idr' => $unit,
            ];
        }

        return $rows;
    }

    // ── Akun ledger ──────────────────────────────────────────────────────

    public function ensureAccounts(): void
    {
        foreach ([
            [self::ACCT_MATERIALS, 'Persediaan Bahan Pabrik', AccountKind::INVENTORY],
            [self::ACCT_WIP, 'Persediaan Dalam Proses (WIP)', AccountKind::INVENTORY],
            [self::ACCT_FG, 'Persediaan Barang Jadi Pabrik', AccountKind::INVENTORY],
            [self::ACCT_SCRAP, 'Beban Scrap Produksi', AccountKind::EXPENSE],
            [self::ACCT_COGS, 'Beban Pokok Penjualan Manufaktur', AccountKind::EXPENSE],
            [self::ACCT_VARIANCE, 'Varians Biaya Produksi', AccountKind::EXPENSE],
            [self::ACCT_CLEARING, 'Rekening Kliring Eksternal IDR', AccountKind::CLEARING],
        ] as [$code, $name, $kind]) {
            LedgerAccount::firstOrCreate(
                ['code' => $code, 'asset_code' => 'IDR'],
                [
                    'uuid' => (string) Str::uuid(), 'name' => $name, 'kind' => $kind->value,
                    'allow_negative' => true, 'cached_balance' => '0', 'is_frozen' => false,
                ]
            );
        }
    }
}
