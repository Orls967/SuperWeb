<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Asset\Application\Services\WorkOrderService as AssetWorkOrderService;
use Modules\Asset\Domain\Models\Asset;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Manufacturing\Domain\Models\DowntimeLog;
use Modules\Manufacturing\Domain\Models\EquipmentPart;
use Modules\Manufacturing\Domain\Models\MaintenanceOrder;
use Modules\Manufacturing\Domain\Models\MaintenancePart;
use Modules\Manufacturing\Domain\Models\OeeSummary;
use Modules\Manufacturing\Domain\Models\OperationReport;
use Modules\Manufacturing\Domain\Models\ProductionOrder;
use Modules\Manufacturing\Domain\Models\ResourceUsage;
use Modules\Manufacturing\Domain\Models\SensorReading;
use Modules\Manufacturing\Domain\Models\WorkCenter;

/**
 * Pemeliharaan pabrik, OEE, sensor simulasi, dan energi/lingkungan
 * (Fase 40.1–40.5, 40.7).
 *
 * WO mesin dengan tautan `asset_id` mendelegasikan ke modul Aset (31.5)
 * agar biaya & rantai event aset tetap menjadi satu-satunya pemilik.
 */
class MaintenanceService
{
    public function __construct(
        private readonly DocumentNumberingInterface $numbering,
        private readonly ?AssetWorkOrderService $assetWorkOrders = null,
    ) {}

    // ── 40.2 Pemeliharaan ────────────────────────────────────────────────

    /**
     * @param  array{asset_id?: string, kind?: string, priority?: string, trigger_key?: string,
     *   due_date?: string, labor_cost_idr?: int, parts_cost_idr?: int, notes?: string}  $data
     */
    public function createMaintenanceOrder(WorkCenter $workCenter, string $description, User $creator, array $data = []): MaintenanceOrder
    {
        return DB::transaction(function () use ($workCenter, $description, $creator, $data) {
            // Idempoten: trigger_key unik (alarm sensor harian, jadwal preventif).
            if (($data['trigger_key'] ?? null) !== null) {
                $existing = MaintenanceOrder::where('trigger_key', $data['trigger_key'])->first();
                if ($existing !== null) {
                    return $existing;
                }
            }

            $number = $this->numbering->nextNumber('MFG', 'MWO', false, 'MWO/{ENT}/');

            $order = MaintenanceOrder::create([
                'number' => $number,
                'work_center_id' => $workCenter->id,
                'asset_id' => $data['asset_id'] ?? $workCenter->asset_id,
                'kind' => $data['kind'] ?? 'corrective',
                'status' => 'open',
                'priority' => $data['priority'] ?? 'normal',
                'trigger_key' => $data['trigger_key'] ?? null,
                'description' => $description,
                'due_date' => $data['due_date'] ?? now()->addDays(7)->toDateString(),
                'labor_cost_idr' => (int) ($data['labor_cost_idr'] ?? 0),
                'parts_cost_idr' => (int) ($data['parts_cost_idr'] ?? 0),
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $creator->id,
            ]);

            // 40.2: WO mesin ber-tautan aset memakai modul Aset (31.5).
            if ($order->asset_id !== null && $this->assetWorkOrders !== null) {
                $asset = Asset::find($order->asset_id);
                if ($asset !== null) {
                    $this->assetWorkOrders->schedule($asset, [
                        'type' => $order->kind === 'preventive' ? 'preventive' : 'corrective',
                        'trigger' => 'time',
                        'due_date' => $order->due_date?->toDateString(),
                        'description' => "[{$order->number}] {$description}",
                        'cost_treatment' => 'expense',
                        'parts_cost_idr' => (int) $order->parts_cost_idr,
                        'labor_cost_idr' => (int) $order->labor_cost_idr,
                    ]);
                }
            }

            return $order;
        });
    }

    public function transitionMaintenance(MaintenanceOrder $order, string $to): MaintenanceOrder
    {
        return DB::transaction(function () use ($order, $to) {
            /** @var MaintenanceOrder $locked */
            $locked = MaintenanceOrder::query()->lockForUpdate()->findOrFail($order->getKey());
            if ($locked->status === $to) {
                return $locked;
            }
            if (! $locked->canTransitionTo($to)) {
                throw new InvalidArgumentException("Transisi pemeliharaan {$locked->status} → {$to} tidak sah.");
            }

            $updates = ['status' => $to];
            if ($to === 'in_progress') {
                $updates['started_at'] = now();
            }
            if ($to === 'completed') {
                $updates['completed_at'] = now();
            }
            $locked->update($updates);

            return $locked->fresh();
        });
    }

    // ── 40.3 Suku cadang ─────────────────────────────────────────────────

    public function addEquipmentPart(WorkCenter $workCenter, array $data): EquipmentPart
    {
        return EquipmentPart::updateOrCreate(
            ['work_center_id' => $workCenter->id, 'part_code' => strtoupper($data['part_code'])],
            [
                'name' => $data['name'],
                'qty_per_equipment' => (int) ($data['qty_per_equipment'] ?? 1),
                'min_stock' => $data['min_stock'] ?? 0,
                'unit_cost_idr' => (int) ($data['unit_cost_idr'] ?? 0),
            ]
        );
    }

    /**
     * Pakai suatu cadangan pada WO: qty × harga → total parts WO.
     * Biaya masuk `parts_cost_idr` WO (TCO aset menangkap via 31.5 schedule).
     */
    public function consumePart(MaintenanceOrder $order, EquipmentPart $part, float $qty): MaintenancePart
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Qty suku cadang harus lebih besar dari nol.');
        }

        return DB::transaction(function () use ($order, $part, $qty) {
            $cost = (int) ceil($qty * (int) $part->unit_cost_idr);
            $usage = MaintenancePart::create([
                'maintenance_order_id' => $order->id,
                'equipment_part_id' => $part->id,
                'qty' => $qty,
                'cost_idr' => $cost,
            ]);

            $order->parts_cost_idr = (int) $order->parts_cost_idr + $cost;
            $order->save();

            return $usage;
        });
    }

    /** Part di bawah stok minimum (stok = total pemakaian kontras — stok gudang tidak dimodelkan di Fase 40). */
    public function lowStockParts(): array
    {
        $rows = [];
        foreach (EquipmentPart::all() as $part) {
            $consumed = (float) MaintenancePart::where('equipment_part_id', $part->id)->sum('qty');
            // Stok awal tidak dimodelkan: pemakaian ≥ min → beri peringatan berbasis pemakaian.
            if ($part->isBelowMinimum($consumed) && $consumed > 0) {
                $rows[] = ['part' => $part, 'consumed' => $consumed];
            }
        }

        return $rows;
    }

    // ── 40.4 Sensor simulasi ─────────────────────────────────────────────

    /**
     * Catat bacaan sensor; pelanggaran ambang membentuk WO korektif
     * idempoten per (work center, metric, tanggal).
     *
     * @return array{reading: SensorReading, alarm: bool, maintenance: ?MaintenanceOrder}
     */
    public function recordSensorReading(
        WorkCenter $workCenter,
        string $sensorCode,
        string $metric,
        float $value,
        User $recorder,
        ?float $thresholdHigh = null,
        ?float $thresholdLow = null,
    ): array {
        if (! in_array($metric, SensorReading::METRICS, true)) {
            throw new InvalidArgumentException('Metrik sensor tidak dikenal: '.$metric);
        }

        return DB::transaction(function () use ($workCenter, $sensorCode, $metric, $value, $recorder, $thresholdHigh, $thresholdLow) {
            $unit = match ($metric) {
                'temperature' => '°C', 'vibration' => 'mm/s', default => 'A',
            };

            $reading = SensorReading::create([
                'work_center_id' => $workCenter->id,
                'sensor_code' => $sensorCode,
                'metric' => $metric,
                'value' => $value,
                'unit' => $unit,
                'threshold_high' => $thresholdHigh,
                'threshold_low' => $thresholdLow,
                'alarm' => false,
                'recorded_at' => now(),
            ]);

            $reading->alarm = $reading->breachesThreshold();
            $reading->save();

            $maintenance = null;
            if ($reading->alarm) {
                $trigger = sprintf('sensor:%s:%s:%s', $workCenter->id, $metric, now()->toDateString());
                $maintenance = $this->createMaintenanceOrder(
                    $workCenter,
                    sprintf('Alarm sensor %s %s: %.3f%s (ambang %s)', $sensorCode, $metric, $value, $unit, $thresholdHigh !== null ? "≤ {$thresholdHigh}" : "≥ {$thresholdLow}"),
                    $recorder,
                    ['kind' => 'predictive', 'priority' => 'high', 'trigger_key' => $trigger]
                );
                $reading->maintenance_order_id = $maintenance->id;
                $reading->save();
            }

            return ['reading' => $reading, 'alarm' => (bool) $reading->alarm, 'maintenance' => $maintenance];
        });
    }

    // ── 40.1 OEE ─────────────────────────────────────────────────────────

    /**
     * Hitung & simpan OEE per work center per tanggal (opsional shift).
     *
     * Availability = (planned − downtime) / planned
     * Performance   = (ideal cycle × total) / run minutes
     * Quality      = good / total
     * OEE          = A × P × Q
     *
     * @return array{summary: OeeSummary, availability: float, performance: float, quality: float, oee: float}
     */
    public function computeOee(WorkCenter $workCenter, string $date, int $plannedMinutes = 480, ?string $shiftCode = null): array
    {
        $downtime = (int) DowntimeLog::where('work_center_id', $workCenter->id)
            ->whereDate('started_at', $date)
            ->sum('minutes');

        $runMinutes = (int) OperationReport::where('work_center_id', $workCenter->id)
            ->whereDate('started_at', $date)
            ->whereNotNull('finished_at')
            ->sum('duration_minutes');

        $totals = OperationReport::where('work_center_id', $workCenter->id)
            ->whereDate('started_at', $date)
            ->selectRaw('COALESCE(SUM(qty_good),0) as good, COALESCE(SUM(qty_good + qty_scrap + qty_rework),0) as total')
            ->first();

        $good = (float) ($totals->good ?? 0);
        $total = (float) ($totals->total ?? 0);

        $planned = max(1, $plannedMinutes);
        $operating = max(0, $planned - $downtime);
        $idealCycle = max(1, (int) ceil(3600 / max(1, (int) $workCenter->capacity_per_hour))); // detik/unit

        $availability = $planned > 0 ? ($operating / $planned) * 100 : 0.0;
        $performance = ($operating > 0 && $total > 0)
            ? (($idealCycle * $total) / 60 / $operating) * 100
            : 0.0;
        $quality = $total > 0 ? ($good / $total) * 100 : 0.0;
        $oee = ($availability / 100) * ($performance / 100) * ($quality / 100) * 100;

        // MTBF/MTTR dari downtime pada tanggal.
        $breakdowns = DowntimeLog::where('work_center_id', $workCenter->id)
            ->whereDate('started_at', $date)->count();
        $mtbf = $breakdowns > 0 ? round($operating / $breakdowns / 60, 2) : 0.0;
        $mttr = $breakdowns > 0 ? round($downtime / $breakdowns, 2) : 0.0;

        // Lookup manual: cast `date` pada model membuat where() baku tidak
        // cocok dengan format tersimpan SQLite ('Y-m-d H:i:s') → pakai whereDate.
        $summary = OeeSummary::where('work_center_id', $workCenter->id)
            ->whereDate('period_date', $date)
            ->where('shift_code', $shiftCode ?? '')
            ->first() ?? new OeeSummary([
                'work_center_id' => $workCenter->id,
                'period_date' => $date,
                'shift_code' => $shiftCode ?? '',
            ]);
        $summary->forceFill([
            'planned_minutes' => $planned,
            'run_minutes' => $runMinutes,
            'ideal_cycle_seconds' => $idealCycle,
            'qty_total' => $total,
            'qty_good' => $good,
            'availability_percent' => round($availability, 4),
            'performance_percent' => round($performance, 4),
            'quality_percent' => round($quality, 4),
            'oee_percent' => round($oee, 4),
            'mtbf_hours' => $mtbf,
            'mttr_minutes' => $mttr,
        ]);
        $summary->save();

        return [
            'summary' => $summary,
            'availability' => round($availability, 2),
            'performance' => round($performance, 2),
            'quality' => round($quality, 2),
            'oee' => round($oee, 2),
        ];
    }

    // ── 40.5 Pareto downtime, MTBF/MTTR, backlog ─────────────────────────

    /**
     * Pareto alasan downtime: menit, %, kumulatif %.
     *
     * @return array<int, array{reason: string, minutes: int, share_percent: float, cumulative_percent: float}>
     */
    public function downtimePareto(?string $fromDate = null, ?string $toDate = null): array
    {
        $query = DowntimeLog::query()->selectRaw('reason_code, SUM(minutes) as total_minutes, COUNT(*) as occurrences')
            ->groupBy('reason_code')
            ->orderByDesc('total_minutes');

        if ($fromDate !== null) {
            $query->whereDate('started_at', '>=', $fromDate);
        }
        if ($toDate !== null) {
            $query->whereDate('started_at', '<=', $toDate);
        }

        $rows = $query->get();
        $grand = max(1, (int) $rows->sum('total_minutes'));

        $cumulative = 0;
        $result = [];
        foreach ($rows as $row) {
            $share = ($row->total_minutes * 100) / $grand;
            $cumulative += $share;
            $result[] = [
                'reason' => (string) $row->reason_code,
                'minutes' => (int) $row->total_minutes,
                'share_percent' => round($share, 2),
                'cumulative_percent' => round($cumulative, 2),
                'occurrences' => (int) $row->occurrences,
            ];
        }

        return $result;
    }

    /**
     * Backlog pemeliharaan: WO terbuka menurut prioritas.
     *
     * @return array<string, array{count: int, overdue: int}>
     */
    public function maintenanceBacklog(): array
    {
        $backlog = [];
        foreach (['critical', 'high', 'normal', 'low'] as $priority) {
            $backlog[$priority] = [
                'count' => MaintenanceOrder::whereIn('status', ['open', 'in_progress'])
                    ->where('priority', $priority)->count(),
                'overdue' => MaintenanceOrder::whereIn('status', ['open', 'in_progress'])
                    ->where('priority', $priority)
                    ->whereNotNull('due_date')
                    ->where('due_date', '<', now()->toDateString())->count(),
            ];
        }

        return $backlog;
    }

    // ── 40.7 Energi & lingkungan ─────────────────────────────────────────

    /**
     * @param  array{work_center_id?: string, cost_idr?: int, recorded_at?: string}  $data
     */
    public function recordResourceUsage(ProductionOrder $order, string $resource, float $qty, User $recorder, array $data = []): ResourceUsage
    {
        if (! in_array($resource, ResourceUsage::RESOURCES, true)) {
            throw new InvalidArgumentException('Sumber daya tidak dikenal: '.$resource);
        }
        if ($qty <= 0) {
            throw new InvalidArgumentException('Qty pemakaian harus lebih besar dari nol.');
        }

        $unit = match ($resource) {
            'electricity_kwh' => 'kWh', 'water_liter' => 'liter', default => 'kg',
        };

        return ResourceUsage::create([
            'production_order_id' => $order->id,
            'work_center_id' => $data['work_center_id'] ?? null,
            'resource' => $resource,
            'qty' => $qty,
            'unit' => $unit,
            'cost_idr' => (int) ($data['cost_idr'] ?? 0),
            'recorded_at' => $data['recorded_at'] ?? now(),
            'recorded_by_user_id' => $recorder->id,
        ]);
    }

    /**
     * Intensitas per unit output: total resource / qty_completed (ESG).
     *
     * @return array<int, array{resource: string, total: float, per_unit: float}>
     */
    public function resourceIntensity(ProductionOrder $order): array
    {
        $qty = max(1.0, (float) $order->qty_completed);

        return ResourceUsage::where('production_order_id', $order->id)
            ->selectRaw('resource, SUM(qty) as total')
            ->groupBy('resource')
            ->get()
            ->map(fn ($row) => [
                'resource' => (string) $row->resource,
                'total' => (float) $row->total,
                'per_unit' => round((float) $row->total / $qty, 6),
            ])->all();
    }
}
