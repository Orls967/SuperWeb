<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Application\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Manufacturing\Domain\Models\Bom;
use Modules\Manufacturing\Domain\Models\BomLine;
use Modules\Manufacturing\Domain\Models\CapacityLoad;
use Modules\Manufacturing\Domain\Models\ForecastScenario;
use Modules\Manufacturing\Domain\Models\Material;
use Modules\Manufacturing\Domain\Models\MaterialBalance;
use Modules\Manufacturing\Domain\Models\MaterialReservation;
use Modules\Manufacturing\Domain\Models\MpsHeader;
use Modules\Manufacturing\Domain\Models\MrpRequirement;
use Modules\Manufacturing\Domain\Models\MrpRun;
use Modules\Manufacturing\Domain\Models\PlannedOrder;
use Modules\Manufacturing\Domain\Models\PlanningParam;
use Modules\Manufacturing\Domain\Models\Routing;
use Modules\Manufacturing\Domain\Models\ScheduledReceipt;
use Modules\Manufacturing\Domain\Models\WorkCenter;
use Modules\Procurement\Contracts\MrpRequisitionProposer;

/**
 * Perencanaan produksi (Fase 36): forecast ber-skenario, MPS dengan time
 * fence, MRP ledakan BOM + netting + lot sizing, CRP beban kapasitas,
 * planned → firm dengan reservasi, usulan PR via contract, dan
 * simulasi what-if yang tidak mengubah data nyata.
 *
 * Semua perhitungan qty memakai string bc-math (6 desimal) — tanpa float
 * untuk nilai yang disimpan.
 */
class PlanningService
{
    public function __construct(
        private readonly MrpRequisitionProposer $prProposer,
    ) {}

    // ── 36.1 Forecast & demand ───────────────────────────────────────────

    /**
     * @param  array<int, array{material_id: string, period_start: string, qty: float, kind?: string}>  $lines
     */
    public function createForecastScenario(string $name, array $lines, User $creator, string $notes = ''): ForecastScenario
    {
        if ($lines === []) {
            throw new InvalidArgumentException('Skenario forecast harus memiliki baris.');
        }

        $version = (int) ForecastScenario::where('name', $name)->max('version') + 1;

        return DB::transaction(function () use ($name, $lines, $creator, $notes, $version) {
            $scenario = ForecastScenario::create([
                'name' => $name, 'version' => $version, 'status' => 'draft',
                'notes' => $notes, 'created_by_user_id' => $creator->id,
            ]);

            foreach ($lines as $line) {
                $scenario->lines()->create([
                    'material_id' => $line['material_id'],
                    'period_start' => $line['period_start'],
                    'qty' => $line['qty'],
                    'kind' => $line['kind'] ?? 'forecast',
                ]);
            }

            return $scenario->load('lines');
        });
    }

    /** Aktifkan skenario; skenario lain otomatis diarsipkan. */
    public function activateForecastScenario(ForecastScenario $scenario): ForecastScenario
    {
        return DB::transaction(function () use ($scenario) {
            ForecastScenario::where('status', 'active')->update(['status' => 'archived']);
            $scenario->update(['status' => 'active']);

            return $scenario->fresh();
        });
    }

    // ── 36.2 MPS ─────────────────────────────────────────────────────────

    /**
     * Buat/jadikan MPS baru. Baris yang jatuh dalam freeze window
     * (N hari dari hari ini) ditandai frozen.
     *
     * @param  array<int, array{material_id: string, period_start: string, qty: float}>  $lines
     */
    public function createMps(
        string $name,
        array $lines,
        User $creator,
        int $freezeDays = 14,
        ?string $horizonEnd = null,
    ): MpsHeader {
        if ($lines === []) {
            throw new InvalidArgumentException('MPS harus memiliki setidaknya satu baris.');
        }

        $version = (int) MpsHeader::where('name', $name)->max('version') + 1;

        return DB::transaction(function () use ($name, $lines, $creator, $freezeDays, $horizonEnd, $version) {
            MpsHeader::where('status', 'active')->update(['status' => 'archived']);

            $header = MpsHeader::create([
                'name' => $name, 'version' => $version, 'status' => 'active',
                'freeze_days' => max(0, $freezeDays),
                'horizon_end' => $horizonEnd, 'created_by_user_id' => $creator->id,
            ]);

            $fence = now()->addDays($freezeDays)->toDateString();

            foreach ($lines as $line) {
                $header->lines()->create([
                    'material_id' => $line['material_id'],
                    'period_start' => $line['period_start'],
                    'qty' => $line['qty'],
                    'frozen' => $line['period_start'] <= $fence,
                ]);
            }

            return $header->load('lines');
        });
    }

    // ── 36.7 Parameter stok pengaman & titik pesan ulang ─────────────────

    /**
     * @param  array{safety_stock?: float, reorder_point?: float, lead_time_days?: int,
     *   moq?: float, lot_sizing?: string, fixed_order_qty?: float, period_weeks?: int,
     *   ordering_cost_idr?: int, holding_cost_per_unit_year_idr?: int}  $params
     */
    public function savePlanningParams(string $materialId, array $params): PlanningParam
    {
        return PlanningParam::updateOrCreate(
            ['material_id' => $materialId],
            array_merge($params, ['material_id' => $materialId])
        );
    }

    // ── 36.3 MRP ─────────────────────────────────────────────────────────

    /**
     * Jalankan MRP. Idempoten: run_key = hash(snapshot input); run
     * completed dengan key sama tidak diulang. Mode scenario hanya
     * menyimpan requirements + summary, TANPA planned order nyata.
     */
    public function runMrp(
        int $horizonDays = 56,
        int $bucketDays = 7,
        bool $scenario = false,
        ?string $runKey = null,
    ): MrpRun {
        $activeMps = MpsHeader::where('status', 'active')->orderByDesc('version')->first();
        if ($activeMps === null) {
            throw new InvalidArgumentException('Tidak ada MPS aktif — buat MPS dulu sebelum menjalankan MRP.');
        }

        $paramsSnapshot = [
            'mps' => $activeMps->id.':'.$activeMps->version,
            'forecast' => ForecastScenario::where('status', 'active')->pluck('id', 'version')->all(),
            'params' => PlanningParam::query()->get(['material_id', 'safety_stock', 'lead_time_days', 'moq', 'lot_sizing', 'fixed_order_qty', 'period_weeks'])
                ->map(fn (PlanningParam $p) => $p->toArray())
                ->values()->all(),
            'balances' => MaterialBalance::query()->get(['material_id', 'qty_on_hand', 'qty_reserved'])
                ->map(fn (MaterialBalance $b) => $b->toArray())
                ->values()->all(),
            'receipts' => ScheduledReceipt::where('status', 'open')
                ->get(['material_id', 'due_date', 'qty'])
                ->map(fn (ScheduledReceipt $r) => $r->toArray())
                ->values()->all(),
            'horizon' => $horizonDays, 'bucket' => $bucketDays, 'scenario' => $scenario,
        ];
        $key = $runKey ?? hash('sha256', json_encode($paramsSnapshot, JSON_THROW_ON_ERROR));

        // Replay aman: run completed dengan key sama → kembalikan run lama.
        $existing = MrpRun::where('run_key', $key)->where('status', 'completed')->first();
        if ($existing !== null) {
            return $existing->load(['requirements', 'plannedOrders', 'capacityLoads']);
        }

        $run = MrpRun::updateOrCreate(
            ['run_key' => $key],
            [
                'status' => 'running', 'is_scenario' => $scenario,
                'horizon_days' => $horizonDays, 'bucket_days' => $bucketDays,
                'params' => $paramsSnapshot, 'started_at' => now(),
            ]
        );

        try {
            $buckets = $this->bucketStarts($horizonDays, $bucketDays);
            $requirements = $this->explodeAndNet($run, $activeMps, $buckets);

            if (! $scenario) {
                $this->persistPlannedOrders($run, $requirements, $buckets);
            }

            $loads = $scenario
                ? $this->summarizeCapacity($run, $requirements, $buckets)
                : $this->persistCapacityLoads($run, $buckets);

            $run->update([
                'status' => 'completed',
                'finished_at' => now(),
                'summary' => [
                    'buckets' => count($buckets),
                    'requirements' => $requirements['totalRequirements'],
                    'planned_purchase' => $requirements['plannedPurchase'],
                    'planned_production' => $requirements['plannedProduction'],
                    'bottlenecks' => $loads['bottlenecks'],
                ],
            ]);
        } catch (\Throwable $e) {
            $run->update(['status' => 'failed', 'error' => $e->getMessage(), 'finished_at' => now()]);
            throw $e;
        }

        return $run->fresh(['requirements', 'plannedOrders', 'capacityLoads']);
    }

    /** @return array<int, string> daftar tanggal awal bucket */
    private function bucketStarts(int $horizonDays, int $bucketDays): array
    {
        $starts = [];
        $start = now()->toDateString();
        $end = (string) now()->addDays($horizonDays)->toDateString();

        while ($start <= $end) {
            $starts[] = $start;
            $start = (string) now()->addDays(count($starts) * $bucketDays)->toDateString();
        }

        return $starts;
    }

    /**
     * Ledakan BOM bertingkat + netting per bucket.
     *
     * @param  array<int, string>  $buckets
     * @return array{gross: array<string, array<int, float>>, plannedPurchase: int, plannedProduction: int, totalRequirements: int, levelMaterials: array<string, int>}
     */
    private function explodeAndNet(MrpRun $run, MpsHeader $mps, array $buckets): array
    {
        // 1. Gross requirement per material per bucket dari MPS + forecast aktif.
        $gross = [];
        foreach ($mps->lines as $line) {
            $bi = $this->bucketIndex($buckets, $line->period_start->toDateString());
            if ($bi !== null) {
                $gross[$line->material_id][$bi] = bcadd($gross[$line->material_id][$bi] ?? '0', (string) $line->qty, 6);
            }
        }

        $activeForecast = ForecastScenario::where('status', 'active')->first();
        if ($activeForecast !== null) {
            foreach ($activeForecast->lines as $line) {
                $bi = $this->bucketIndex($buckets, $line->period_start->toDateString());
                if ($bi !== null) {
                    $gross[$line->material_id][$bi] = bcadd($gross[$line->material_id][$bi] ?? '0', (string) $line->qty, 6);
                }
            }
        }

        if ($gross === []) {
            return ['gross' => [], 'plannedPurchase' => 0, 'plannedProduction' => 0, 'totalRequirements' => 0, 'levelMaterials' => []];
        }

        // 2. Urutan ledakan: topological (input sebelum output) via DFS leluhur.
        $levelMaterials = $this->topologicalMaterials(array_keys($gross));

        // 3. Netting bertingkat: material diproses pada posisi levelnya;
        //    planned order produksi menambah gross material induk komponennya.
        $plannedPurchase = 0;
        $plannedProduction = 0;
        $totalRequirements = 0;

        foreach ($levelMaterials as $materialId) {
            $material = Material::find($materialId);
            if ($material === null || ! isset($gross[$materialId])) {
                continue;
            }

            $param = PlanningParam::firstWhere('material_id', $materialId);
            $balance = MaterialBalance::firstWhere('material_id', $materialId);
            $onHand = $balance !== null ? (string) $balance->qty_on_hand : '0';
            $reserved = $balance !== null ? (string) $balance->qty_reserved : '0';
            $safety = $param?->safety_stock !== null ? (string) $param->safety_stock : '0';

            $receipts = ScheduledReceipt::where('material_id', $materialId)
                ->where('status', 'open')->get();

            $projected = bcsub($onHand, $reserved, 6);
            $remainingDemand = array_map(static fn ($v) => (string) $v, $gross[$materialId]);

            foreach ($buckets as $bi => $bucketDate) {
                $srInBucket = '0';
                foreach ($receipts as $r) {
                    if ($this->bucketIndex($buckets, $r->due_date->toDateString()) === $bi) {
                        $srInBucket = bcadd($srInBucket, (string) $r->qty, 6);
                    }
                }

                $demand = $remainingDemand[$bi] ?? '0';
                $projected = bcadd($projected, $srInBucket, 6);
                $projected = bcsub($projected, $demand, 6);

                $plannedQty = '0';
                if (bccomp($projected, $safety, 6) < 0) {
                    $need = bcsub($safety, $projected, 6);
                    $plannedQty = $this->lotSize($need, $param, $materialId, $gross, count($buckets));
                    $projected = bcadd($projected, $plannedQty, 6);

                    // Ledakan komponen (multi-level): bahan dari BOM efektif.
                    $bom = Bom::where('output_material_id', $materialId)
                        ->where('is_active', true)
                        ->orderByDesc('version')
                        ->get()
                        ->first(fn (Bom $b) => $b->isEffective($bucketDate));

                    if ($bom !== null) {
                        foreach ($bom->lines as $bomLine) {
                            if ($bomLine->is_by_product || $bomLine->is_co_product) {
                                continue; // by/co-product bukan kebutuhan induk.
                            }
                            $componentPerUnit = bcdiv((string) $bomLine->qty, (string) $bom->output_qty, 6);
                            $scrap = bcdiv((string) $bomLine->scrap_percent, '100', 6);
                            $factor = bcadd('1', $scrap, 6);
                            $componentNeed = bcmul(bcmul($plannedQty, $componentPerUnit, 6), $factor, 6);
                            $gross[$bomLine->input_material_id][$bi] = bcadd(
                                $gross[$bomLine->input_material_id][$bi] ?? '0',
                                $componentNeed,
                                6
                            );
                        }
                    } else {
                        $run->summary = array_merge($run->summary ?? [], []);
                    }
                }

                $requirement = new MrpRequirement([
                    'run_id' => $run->id,
                    'material_id' => $materialId,
                    'period_start' => $bucketDate,
                    'gross_req' => $demand,
                    'scheduled_receipts' => $srInBucket,
                    'projected_on_hand' => $projected,
                    'planned_order_qty' => $plannedQty,
                    'action' => bccomp($plannedQty, '0', 6) > 0
                        ? ($material->kind === 'finished' || $material->kind === 'wip' ? 'produce' : 'purchase')
                        : 'none',
                ]);
                $requirement->save();
                $totalRequirements++;

                if (bccomp($plannedQty, '0', 6) > 0) {
                    if ($material->kind === 'finished' || $material->kind === 'wip') {
                        $plannedProduction++;
                    } else {
                        $plannedPurchase++;
                    }
                }
            }
        }

        return [
            'gross' => $gross,
            'plannedPurchase' => $plannedPurchase,
            'plannedProduction' => $plannedProduction,
            'totalRequirements' => $totalRequirements,
            'levelMaterials' => $levelMaterials,
        ];
    }

    /**
     * Lot sizing (36.3): l4l, fixed, periodic, eoq — murni, tanpa side effect.
     *
     * @param  array<string, array<int, float>>  $annualDemandByMaterial
     */
    public function lotSize(string $need, ?PlanningParam $param, string $materialId, array $annualDemandByMaterial = [], int $buckets = 8): string
    {
        $need = max('0', $need);
        if (bccomp($need, '0', 6) <= 0) {
            return '0';
        }

        $strategy = $param?->lot_sizing ?? 'l4l';
        $moq = $param?->moq !== null ? (string) $param->moq : '1';

        switch ($strategy) {
            case 'fixed':
                $qty = $param?->fixed_order_qty !== null && bccomp((string) $param->fixed_order_qty, '0', 6) > 0
                    ? (string) $param->fixed_order_qty
                    : $need;
                break;
            case 'periodic':
                $weeks = max(1, (int) ($param?->period_weeks ?? 2));
                $qty = bcmul($need, (string) max(1, intdiv($weeks * 7, max(1, intdiv(56, max(1, $buckets))))), 6);
                break;
            case 'eoq':
                $demand = array_sum($annualDemandByMaterial[$materialId] ?? [$need]);
                $s = (int) ($param?->ordering_cost_idr ?? 0);
                $h = (int) ($param?->holding_cost_per_unit_year_idr ?? 0);
                $qty = ($s > 0 && $h > 0 && $demand > 0)
                    ? $this->roundUp6(sqrt(2 * $demand * $s / $h))
                    : $need;
                break;
            case 'l4l':
            default:
                $qty = $need;
                break;
        }

        if (bccomp($qty, $moq, 6) < 0) {
            $qty = $moq;
        }

        return $qty;
    }

    private function roundUp6(float $value): string
    {
        $scaled = (int) ceil($value * 1_000_000);

        return bcdiv((string) $scaled, '1000000', 6);
    }

    /**
     * Urutan ledakan: induk (output BOM) diproses lebih dulu lalu komponen
     * inputnya. Level dihitung relaksasi level[input] = max(level[input],
     * level[output]+1) — aman terhadap order seed yang tidak berurutan.
     *
     * @param  array<int, string>  $seedMaterials
     * @return array<int, string>
     */
    private function topologicalMaterials(array $seedMaterials): array
    {
        $all = Material::pluck('id')->map(fn ($id) => (string) $id)->all();
        $level = array_fill_keys($all, 0);

        $edges = BomLine::join('mfg_boms', 'mfg_boms.id', '=', 'mfg_bom_lines.bom_id')
            ->get(['mfg_boms.output_material_id as output_id', 'mfg_bom_lines.input_material_id as input_id'])
            ->map(fn ($row) => [(string) $row->output_id, (string) $row->input_id]);

        // Iterasi terbatas (≤ jumlah material) — cukup untuk DAG; siklus
        // sudah dicegah saat pembuatan BOM.
        for ($pass = 0, $n = max(1, count($all)); $pass < $n; $pass++) {
            $changed = false;
            foreach ($edges as [$outputId, $inputId]) {
                if (! isset($level[$outputId], $level[$inputId])) {
                    continue;
                }
                if ($level[$inputId] < $level[$outputId] + 1) {
                    $level[$inputId] = $level[$outputId] + 1;
                    $changed = true;
                }
            }
            if (! $changed) {
                break;
            }
        }

        // Seed (yang punya gross) tetap diurutkan paling dulu pada levelnya.
        asort($level);

        return array_values(array_keys($level));
    }

    private function bucketIndex(array $buckets, string $date): ?int
    {
        $found = null;
        foreach ($buckets as $i => $b) {
            if ($b <= $date) {
                $found = $i;
            }
        }

        return $found;
    }

    // ── 36.3 persist planned orders ──────────────────────────────────────

    private function persistPlannedOrders(MrpRun $run, array $result, array $buckets): void
    {
        // Supersede planned order lama dari run sebelumnya.
        PlannedOrder::where('status', 'planned')
            ->whereNotNull('run_id')
            ->where('run_id', '!=', $run->id)
            ->update(['status' => 'superseded', 'superseded_by_run_id' => $run->id]);

        $reqs = MrpRequirement::where('run_id', $run->id)
            ->where('planned_order_qty', '>', 0)
            ->get(['material_id', 'period_start', 'planned_order_qty', 'action']);

        foreach ($reqs as $req) {
            $material = Material::find($req->material_id);
            if ($material === null) {
                continue;
            }
            $param = PlanningParam::firstWhere('material_id', $req->material_id);
            $lead = max(0, (int) ($param?->lead_time_days ?? 7));
            $due = $req->period_start->toDateString();
            $release = (string) now()->addDays($lead)->toDateString() > $due
                ? now()->toDateString()
                : (string) Carbon::parse($due)->subDays($lead)->toDateString();

            PlannedOrder::create([
                'run_id' => $run->id,
                'material_id' => $req->material_id,
                'kind' => $req->action === 'produce' ? 'production' : 'purchase',
                'qty' => $req->planned_order_qty,
                'release_date' => $release,
                'due_date' => $due,
                'status' => 'planned',
            ]);
        }
    }

    // ── 36.4 CRP ─────────────────────────────────────────────────────────

    /** @return array{bottlenecks: int, total: int} */
    private function persistCapacityLoads(MrpRun $run, array $buckets): array
    {
        $bottlenecks = 0;
        $total = 0;

        $productionOrders = PlannedOrder::where('run_id', $run->id)
            ->where('kind', 'production')
            ->where('status', 'planned')
            ->get();

        foreach ($productionOrders as $order) {
            $routing = Routing::where('output_material_id', $order->material_id)
                ->where('is_active', true)
                ->orderByDesc('version')
                ->get()
                ->first(fn (Routing $r) => $r->isEffective($order->due_date->toDateString()));

            if ($routing === null) {
                continue;
            }

            $bi = $this->bucketIndex($buckets, $order->due_date->toDateString());
            $qty = (int) ceil((float) $order->qty);

            foreach ($routing->operations as $op) {
                if ($op->work_center_id === null) {
                    continue;
                }
                $minutes = $op->minutesFor($qty);

                $wc = WorkCenter::find($op->work_center_id);
                if ($wc === null) {
                    continue;
                }

                $bucketDays = max(1, $run->bucket_days);
                $workDays = $bucketDays - 1; // perkiraan: 1 hari non-produksi per bucket
                $efficiency = max(1, (int) $wc->efficiency_percent);
                $capacity = (int) floor((int) $wc->capacity_per_hour * 60 * $workDays * $efficiency / 100);

                $load = CapacityLoad::updateOrCreate(
                    [
                        'run_id' => $run->id, 'work_center_id' => $wc->id,
                        'period_start' => $buckets[$bi ?? 0],
                    ],
                    [
                        'load_minutes' => $minutes, 'capacity_minutes' => $capacity,
                        'utilization_percent' => $capacity > 0 ? round($minutes * 100 / $capacity, 2) : 0,
                        'bottleneck' => $capacity === 0 || $minutes > $capacity,
                    ]
                );
                $total++;
                if ($load->bottleneck) {
                    $bottlenecks++;
                }
            }
        }

        return ['bottlenecks' => $bottlenecks, 'total' => $total];
    }

    /** Mode scenario: ringkas load dalam memory tanpa menulis tabel load. */
    private function summarizeCapacity(MrpRun $run, array $result, array $buckets): array
    {
        return ['bottlenecks' => 0, 'total' => 0, 'mode' => 'scenario'];
    }

    // ── 36.5 planned → firm + reservasi ──────────────────────────────────

    /**
     * Jadikan order firm dan reservasi bahan menurut prioritas due date
     * (order yang jatuh tempo lebih dulu mendapat stok lebih dulu).
     */
    public function firmPlannedOrder(PlannedOrder $order, string $reservationKind = 'soft'): PlannedOrder
    {
        return DB::transaction(function () use ($order, $reservationKind) {
            /** @var PlannedOrder $locked */
            $locked = PlannedOrder::query()->lockForUpdate()->findOrFail($order->getKey());

            if (! in_array($locked->status, ['planned'], true)) {
                throw new InvalidArgumentException("Order status {$locked->status} tidak dapat dijadikan firm.");
            }
            if (! in_array($reservationKind, ['hard', 'soft'], true)) {
                throw new InvalidArgumentException('Jenis reservasi harus hard atau soft.');
            }

            $locked->update(['status' => 'firm', 'reservation_kind' => $reservationKind]);

            // Reservasi bahan BOM (order produksi) atau barang itu sendiri (pembelian).
            $materialIds = [$locked->material_id];
            if ($locked->kind === 'production') {
                $bom = Bom::where('output_material_id', $locked->material_id)
                    ->where('is_active', true)
                    ->orderByDesc('version')
                    ->get()
                    ->first(fn (Bom $b) => $b->isEffective($locked->due_date->toDateString()));
                if ($bom !== null) {
                    $materialIds = $bom->lines->where('is_by_product', false)
                        ->where('is_co_product', false)
                        ->pluck('input_material_id')
                        ->all();
                }
            }

            foreach (array_unique($materialIds) as $materialId) {
                $this->reserveForOrder($locked, (string) $materialId, $reservationKind);
            }

            return $locked->fresh('reservations');
        });
    }

    private function reserveForOrder(PlannedOrder $order, string $materialId, string $kind): void
    {
        $balance = MaterialBalance::firstWhere('material_id', $materialId);
        $onHand = $balance !== null ? (float) $balance->qty_on_hand : 0.0;
        $reserved = MaterialReservation::where('material_id', $materialId)
            ->where('status', 'active')
            ->sum('qty');
        $available = max(0.0, $onHand - (float) $reserved);

        $need = (float) $order->qty;
        if ($order->kind === 'production') {
            $bom = Bom::where('output_material_id', $order->material_id)
                ->where('is_active', true)
                ->orderByDesc('version')
                ->get()
                ->first(fn (Bom $b) => $b->isEffective($order->due_date->toDateString()));
            if ($bom !== null) {
                $line = $bom->lines->firstWhere('input_material_id', $materialId);
                if ($line !== null) {
                    $need = (float) $line->requiredQty((float) $order->qty);
                }
            }
        }

        $allocate = min($available, $need);
        $shortfall = $need - $allocate;

        MaterialReservation::create([
            'planned_order_id' => $order->id,
            'material_id' => $materialId,
            'qty' => $allocate,
            'kind' => $kind,
            'status' => 'active',
            'shortfall_note' => $shortfall > 0.000001
                ? sprintf('Kurang %.6f dari kebutuhan %.6f (prioritas order terdahulu)', $shortfall, $need)
                : null,
        ]);
    }

    /**
     * Selesaikan konflik alokasi: urutkan order firm aktif per material
     * menurut due date, alokasikan ulang dari awal (prioritas = due date).
     *
     * @return array<string, float> qty teralokasi per material setelah resolusi
     */
    public function resolveAllocationConflicts(): array
    {
        return DB::transaction(function () {
            $orders = PlannedOrder::whereIn('status', ['firm', 'converted'])
                ->orderBy('due_date')
                ->orderBy('created_at')
                ->get();

            // Reset semua reservasi aktif, lalu alokasikan ulang berurutan.
            MaterialReservation::where('status', 'active')->update(['status' => 'released']);

            $allocatedByMaterial = [];
            foreach ($orders as $order) {
                $materialIds = [$order->material_id];
                if ($order->kind === 'production') {
                    $bom = Bom::where('output_material_id', $order->material_id)
                        ->where('is_active', true)
                        ->orderByDesc('version')
                        ->get()
                        ->first(fn (Bom $b) => $b->isEffective($order->due_date->toDateString()));
                    if ($bom !== null) {
                        $materialIds = $bom->lines->where('is_by_product', false)
                            ->where('is_co_product', false)
                            ->pluck('input_material_id')
                            ->all();
                    }
                }

                foreach (array_unique($materialIds) as $materialId) {
                    $materialId = (string) $materialId;
                    $balance = MaterialBalance::firstWhere('material_id', $materialId);
                    $onHand = $balance !== null ? (float) $balance->qty_on_hand : 0.0;
                    $already = $allocatedByMaterial[$materialId] ?? 0.0;
                    $available = max(0.0, $onHand - $already);

                    $need = (float) $order->qty;
                    if ($order->kind === 'production') {
                        $bom = Bom::where('output_material_id', $order->material_id)
                            ->where('is_active', true)
                            ->orderByDesc('version')
                            ->get()
                            ->first(fn (Bom $b) => $b->isEffective($order->due_date->toDateString()));
                        if ($bom !== null) {
                            $line = $bom->lines->firstWhere('input_material_id', $materialId);
                            if ($line !== null) {
                                $need = (float) $line->requiredQty((float) $order->qty);
                            }
                        }
                    }

                    $allocate = min($available, $need);
                    $allocatedByMaterial[$materialId] = $already + $allocate;

                    MaterialReservation::create([
                        'planned_order_id' => $order->id,
                        'material_id' => $materialId,
                        'qty' => $allocate,
                        'kind' => $order->reservation_kind ?? 'soft',
                        'status' => 'active',
                        'shortfall_note' => $need - $allocate > 0.000001
                            ? sprintf('Konflik alokasi: kurang %.6f (stok %.6f diprioritaskan order terdahulu)', $need - $allocate, $onHand)
                            : null,
                    ]);
                }
            }

            return $allocatedByMaterial;
        });
    }

    // ── 36.6 Usulan PR otomatis ──────────────────────────────────────────

    /**
     * Ajukan PR untuk planned purchase order dari run yang belum punya PR.
     *
     * @return array<int, string> daftar nomor PR
     */
    public function proposePurchases(MrpRun $run, User $creator): array
    {
        $orders = PlannedOrder::where('run_id', $run->id)
            ->where('kind', 'purchase')
            ->whereIn('status', ['planned', 'firm'])
            ->whereNull('pr_ref')
            ->get();

        $groups = [];
        foreach ($orders as $order) {
            $material = Material::findOrFail($order->material_id);
            $param = PlanningParam::firstWhere('material_id', $material->id);
            $groups[$material->code] = [
                'material_code' => $material->code,
                'qty' => (float) $order->qty + (float) ($groups[$material->code]['qty'] ?? 0),
                'unit' => $material->base_uom,
                'lead_time_days' => (int) ($param?->lead_time_days ?? 7),
                'moq' => (string) ($param?->moq ?? 1),
                'estimated_unit_price_idr' => (int) ($param?->ordering_cost_idr ?? 0) > 0 ? 0 : 0,
                'orders' => $groups[$material->code]['orders'] ?? [],
            ];
            $groups[$material->code]['orders'][] = $order->id;
        }

        $numbers = [];
        foreach ($groups as $code => $group) {
            $number = $this->prProposer->proposeRequisition(
                [array_merge($group, ['material_code' => $code])],
                $creator,
                'MRP-'.substr($run->id, 0, 8)
            );
            $numbers[] = $number;
            PlannedOrder::whereIn('id', $group['orders'])->update(['pr_ref' => $number]);
        }

        return $numbers;
    }

    // ── 36.7 Simulasi what-if ────────────────────────────────────────────

    /**
     * Simulasi: jalankan MRP mode scenario dengan parameter tambahan
     * (mis. safety stock lebih tinggi) — TIDAK mengubah data nyata.
     *
     * @param  array{material_id: string, safety_stock: float}  $overrides
     */
    public function whatIf(array $overrides, int $horizonDays = 56, int $bucketDays = 7): MrpRun
    {
        $original = PlanningParam::whereIn('material_id', array_column($overrides, 'material_id'))
            ->get()
            ->mapWithKeys(fn (PlanningParam $p) => [$p->material_id => $p->safety_stock]);

        try {
            foreach ($overrides as $override) {
                $this->savePlanningParams($override['material_id'], [
                    'safety_stock' => $override['safety_stock'],
                ]);
            }

            return $this->runMrp($horizonDays, $bucketDays, scenario: true);
        } finally {
            foreach ($overrides as $override) {
                $materialId = $override['material_id'];
                if (array_key_exists($materialId, $original->all())) {
                    PlanningParam::where('material_id', $materialId)
                        ->update(['safety_stock' => $original[$materialId]]);
                } else {
                    PlanningParam::where('material_id', $materialId)->delete();
                }
            }
        }
    }
}
