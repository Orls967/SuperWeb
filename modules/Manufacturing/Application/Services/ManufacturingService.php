<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\ApprovalEngineInterface;
use Modules\Manufacturing\Domain\Models\Bom;
use Modules\Manufacturing\Domain\Models\BomLine;
use Modules\Manufacturing\Domain\Models\Formula;
use Modules\Manufacturing\Domain\Models\Material;
use Modules\Manufacturing\Domain\Models\Plant;
use Modules\Manufacturing\Domain\Models\RestoAdapter;
use Modules\Manufacturing\Domain\Models\Routing;
use Modules\Manufacturing\Domain\Models\Worker;

/**
 * Master data manufaktur (Fase 35): plant, material, BOM ber-versi,
 * routing, formula (approval + hash-chain), adapter CK-01, tenaga kerja.
 */
class ManufacturingService
{
    public const GENESIS = 'GENESIS_MFG_0000000000000000000000000000000000000000000000000000';

    public function __construct(
        private readonly ApprovalEngineInterface $approvals,
    ) {}

    // ── 35.1 Plant ───────────────────────────────────────────────────────

    public function createPlant(array $data): Plant
    {
        return DB::transaction(function () use ($data) {
            $code = strtoupper($data['code']);
            if (Plant::where('code', $code)->exists()) {
                throw new InvalidArgumentException("Kode pabrik {$code} sudah dipakai.");
            }

            return Plant::create([
                'code' => $code,
                'name' => $data['name'],
                'legal_entity_id' => $data['legal_entity_id'] ?? null,
                'type' => $data['type'] ?? 'factory',
                'timezone' => $data['timezone'] ?? 'Asia/Jakarta',
                'nominal_capacity_per_day' => (int) ($data['nominal_capacity_per_day'] ?? 0),
                'capacity_uom' => $data['capacity_uom'] ?? 'unit',
                'address' => $data['address'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    // ── 35.3 Master material ─────────────────────────────────────────────

    public function createMaterial(array $data): Material
    {
        return DB::transaction(function () use ($data) {
            $code = strtoupper($data['code']);
            if (Material::where('code', $code)->exists()) {
                throw new InvalidArgumentException("Kode material {$code} sudah dipakai.");
            }

            $material = Material::create([
                'code' => $code,
                'name' => $data['name'],
                'kind' => $data['kind'] ?? 'raw',
                'base_uom' => $data['base_uom'] ?? 'pcs',
                'lot_tracked' => $data['lot_tracked'] ?? true,
                'expiry_tracked' => $data['expiry_tracked'] ?? false,
                'serial_tracked' => $data['serial_tracked'] ?? false,
                'description' => $data['description'] ?? null,
            ]);

            // Konversi identitas: 1 base → 1 base, agar validasi UoM selalu punya jalur.
            $material->uomConversions()->create([
                'from_uom' => $material->base_uom,
                'to_uom' => $material->base_uom,
                'factor' => 1,
            ]);

            return $material;
        });
    }

    // ── 35.4 BOM multi-level ber-versi ───────────────────────────────────

    /**
     * Buat versi BOM baru untuk material output. Validasi 35.9:
     * kuantitas nol/negatif, UoM tak kompatibel, siklus multi-level.
     *
     * @param  array{output_material_id: string, name: string, output_qty?: float,
     *   output_uom?: string, effective_from: string, effective_to?: string|null,
     *   change_reason?: string, lines: array<int, array<string, mixed>>}  $data
     */
    public function createBom(array $data, User $creator): Bom
    {
        return DB::transaction(function () use ($data) {
            /** @var Material $output */
            $output = Material::query()->lockForUpdate()->findOrFail($data['output_material_id']);

            $lines = $data['lines'] ?? [];
            if ($lines === []) {
                throw new InvalidArgumentException('BOM harus memiliki setidaknya satu baris bahan.');
            }

            $this->validateBomLines($output, $lines);

            $version = (int) Bom::where('output_material_id', $output->id)->max('version') + 1;

            $bom = Bom::create([
                'output_material_id' => $output->id,
                'version' => $version,
                'name' => $data['name'],
                'output_qty' => $data['output_qty'] ?? 1,
                'output_uom' => $data['output_uom'] ?? $output->base_uom,
                'effective_from' => $data['effective_from'],
                'effective_to' => $data['effective_to'] ?? null,
                'is_active' => true,
                'change_reason' => $data['change_reason'] ?? null,
            ]);

            $sequence = 1;
            foreach ($lines as $line) {
                /** @var Material $input */
                $input = Material::findOrFail($line['input_material_id']);
                BomLine::create([
                    'bom_id' => $bom->id,
                    'input_material_id' => $input->id,
                    'qty' => $line['qty'],
                    'uom' => $line['uom'] ?? $input->base_uom,
                    'scrap_percent' => $line['scrap_percent'] ?? 0,
                    'is_alternative' => $line['is_alternative'] ?? false,
                    'substitution_group' => $line['substitution_group'] ?? null,
                    'is_by_product' => $line['is_by_product'] ?? false,
                    'is_co_product' => $line['is_co_product'] ?? false,
                    'allocation_percent' => $line['allocation_percent'] ?? null,
                    'sequence' => $line['sequence'] ?? $sequence,
                ]);
                $sequence++;
            }

            return $bom->load('lines');
        });
    }

    /**
     * Validasi baris BOM: kuantitas > 0, UoM material input kompatibel,
     * dan tidak membentuk siklus multi-level (A → B → A).
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function validateBomLines(Material $output, array $lines): void
    {
        $seenInputs = [];

        foreach ($lines as $line) {
            $qty = (float) ($line['qty'] ?? 0);
            if ($qty <= 0) {
                throw new InvalidArgumentException('Kuantitas baris BOM harus lebih besar dari nol.');
            }

            $inputId = (string) ($line['input_material_id'] ?? '');
            $input = Material::find($inputId);
            if ($input === null) {
                throw new InvalidArgumentException("Material input {$inputId} tidak ditemukan.");
            }

            if ($input->id === $output->id) {
                throw new InvalidArgumentException("BOM siklus: material {$output->code} merujuk dirinya sendiri.");
            }

            $uom = (string) ($line['uom'] ?? $input->base_uom);
            if (! $this->isUomCompatible($input, $uom, $input->base_uom)) {
                throw new InvalidArgumentException(
                    "UoM {$uom} tidak kompatibel dengan basis {$input->base_uom} untuk material {$input->code}."
                );
            }

            // Siklus berjenjang: telusuri leluhur material input; bila salah
            // satunya adalah output saat ini, BOM akan membentuk lingkaran.
            if ($this->reachesMaterial($input, $output->id, [])) {
                throw new InvalidArgumentException(
                    "BOM siklus multi-level: {$input->code} (atau turunannya) menuju {$output->code}."
                );
            }

            $seenInputs[$inputId] = true;
        }

        // Alokasi co-product total 100% bila ada baris co-product.
        $coProducts = array_filter($lines, static fn (array $l): bool => (bool) ($l['is_co_product'] ?? false));
        if ($coProducts !== []) {
            $total = array_sum(array_map(static fn (array $l): float => (float) ($l['allocation_percent'] ?? 0), $coProducts));
            if (abs($total - 100.0) > 0.01) {
                throw new InvalidArgumentException('Alokasi biaya co-product harus berjumlah 100%.');
            }
        }
    }

    /** UoM kompatibel bila sama dengan basis, atau punya jalur konversi (1 langkah). */
    public function isUomCompatible(Material $material, string $fromUom, string $toUom): bool
    {
        if ($fromUom === $toUom) {
            return true;
        }

        return $material->uomConversions()
            ->where('from_uom', $fromUom)
            ->where('to_uom', $toUom)
            ->exists()
            || $material->uomConversions()
                ->where('from_uom', $toUom)
                ->where('to_uom', $fromUom)
                ->exists();
    }

    /** DFS: apakah dari $material (atau leluhur BOM-nya) dapat mencapai $targetId? */
    private function reachesMaterial(Material $material, string $targetId, array $visited): bool
    {
        if ($material->id === $targetId) {
            return true;
        }

        if (isset($visited[$material->id])) {
            return false; // sudah diproses — hentikan agar tidak infinite.
        }
        $visited[$material->id] = true;

        $parentIds = Bom::where('output_material_id', $material->id)
            ->pluck('id');

        if ($parentIds->isEmpty()) {
            return false;
        }

        $inputIds = BomLine::whereIn('bom_id', $parentIds)
            ->whereNotNull('input_material_id')
            ->pluck('input_material_id')
            ->unique();

        foreach ($inputIds as $inputId) {
            $parent = Material::find($inputId);
            if ($parent === null) {
                continue;
            }
            if ($parent->id === $targetId) {
                return true;
            }
            if ($this->reachesMaterial($parent, $targetId, $visited)) {
                return true;
            }
        }

        return false;
    }

    // ── 35.5 Routing ─────────────────────────────────────────────────────

    /**
     * @param  array{output_material_id: string, name: string, effective_from: string,
     *   effective_to?: string|null, change_reason?: string,
     *   operations: array<int, array<string, mixed>>}  $data
     */
    public function createRouting(array $data): Routing
    {
        return DB::transaction(function () use ($data) {
            $output = Material::findOrFail($data['output_material_id']);
            $operations = $data['operations'] ?? [];
            if ($operations === []) {
                throw new InvalidArgumentException('Routing harus memiliki setidaknya satu operasi.');
            }

            $version = (int) Routing::where('output_material_id', $output->id)->max('version') + 1;

            $routing = Routing::create([
                'output_material_id' => $output->id,
                'version' => $version,
                'name' => $data['name'],
                'effective_from' => $data['effective_from'],
                'effective_to' => $data['effective_to'] ?? null,
                'is_active' => true,
                'change_reason' => $data['change_reason'] ?? null,
            ]);

            $sequence = 1;
            foreach ($operations as $op) {
                $routing->operations()->create([
                    'work_center_id' => $op['work_center_id'] ?? null,
                    'sequence' => $op['sequence'] ?? $sequence,
                    'name' => $op['name'],
                    'setup_minutes' => (int) ($op['setup_minutes'] ?? 0),
                    'run_minutes_per_unit' => (int) ($op['run_minutes_per_unit'] ?? 0),
                    'work_instructions' => $op['work_instructions'] ?? null,
                    'inspection_point' => (bool) ($op['inspection_point'] ?? false),
                ]);
                $sequence++;
            }

            return $routing->load('operations');
        });
    }

    // ── 35.6 Formula: draft → approval → approved + hash-chain ───────────

    /**
     * @param  array{output_material_id: string, name: string, standard_yield_percent?: float,
     *   yield_tolerance_percent?: float, active_ingredients?: array,
     *   effective_from: string, effective_to?: string|null, change_reason?: string}  $data
     */
    public function createFormula(array $data, User $creator): Formula
    {
        return DB::transaction(function () use ($data) {
            $output = Material::findOrFail($data['output_material_id']);
            $version = (int) Formula::where('output_material_id', $output->id)->max('version') + 1;

            $prev = Formula::where('output_material_id', $output->id)
                ->orderByDesc('version')
                ->first();

            $formula = Formula::create([
                'output_material_id' => $output->id,
                'version' => $version,
                'name' => $data['name'],
                'standard_yield_percent' => $data['standard_yield_percent'] ?? 100,
                'yield_tolerance_percent' => $data['yield_tolerance_percent'] ?? 5,
                'active_ingredients' => $data['active_ingredients'] ?? null,
                'effective_from' => $data['effective_from'],
                'effective_to' => $data['effective_to'] ?? null,
                'status' => 'draft',
                'change_reason' => $data['change_reason'] ?? null,
                'prev_hash' => $prev?->hash ?? self::GENESIS,
                'hash' => $this->calculateFormulaHash(
                    $prev?->hash ?? self::GENESIS,
                    $version,
                    $data,
                ),
            ]);

            return $formula;
        });
    }

    /** Ajukan formula draft ke approval (four-eyes: planner submit, admin approve). */
    public function submitFormula(Formula $formula, User $creator): Formula
    {
        return DB::transaction(function () use ($formula, $creator) {
            /** @var Formula $locked */
            $locked = Formula::query()->lockForUpdate()->findOrFail($formula->getKey());

            if ($locked->status !== 'draft') {
                throw new InvalidArgumentException("Formula v{$locked->version} hanya dapat diajukan dari status draft (saat ini {$locked->status}).");
            }

            $approval = $this->approvals->submit(
                approvalType: 'MFG_FORMULA_CHANGE',
                title: "Formula {$locked->name} v{$locked->version}",
                creator: $creator,
                approvable: $locked,
                steps: [['role' => 'admin']],
                slaHours: 72,
                metadata: ['formula_id' => $locked->id, 'version' => $locked->version],
            );

            $locked->status = 'pending_approval';
            // Kolom mfg_formulas.approval_id bertipe integer (PK ApprovalRequest).
            $locked->approval_id = (int) $approval->id;
            $locked->save();

            return $locked;
        });
    }

    /** Setujui formula → status approved; versi lama tetap ada (append-only). */
    public function approveFormula(Formula $formula, User $approver): Formula
    {
        return DB::transaction(function () use ($formula, $approver) {
            /** @var Formula $locked */
            $locked = Formula::query()->lockForUpdate()->findOrFail($formula->getKey());

            if ($locked->status !== 'pending_approval') {
                throw new InvalidArgumentException("Formula v{$locked->version} tidak dalam antrian approval (status {$locked->status}).");
            }

            if ($locked->approval_id !== null) {
                $this->approvals->approve((int) $locked->approval_id, $approver, 'Formula disetujui');
            }

            $locked->status = 'approved';
            $locked->save();

            return $locked;
        });
    }

    public function calculateFormulaHash(string $prevHash, int $version, array $body): string
    {
        $canonical = $prevHash.'|'.$version.'|'.hash('sha256', json_encode($body, JSON_THROW_ON_ERROR));

        return hash('sha256', $canonical);
    }

    /** Verifikasi rantai hash seluruh versi formula suatu material. */
    public function verifyFormulaChain(Material $output): bool
    {
        $expectedPrev = self::GENESIS;

        foreach (Formula::where('output_material_id', $output->id)->orderBy('version')->get() as $f) {
            if ($f->prev_hash !== $expectedPrev) {
                return false;
            }
            $expectedPrev = (string) $f->hash;
        }

        return true;
    }

    // ── 35.7 Adapter CK-01 ───────────────────────────────────────────────

    /**
     * Sinkronisasi plant central_kitchen ↔ outlet Resto. Idempoten per
     * sync_key; data Resto tidak disalin — hanya pointer + stempel waktu.
     */
    public function syncRestoAdapter(RestoAdapter $adapter, ?string $syncKey = null): RestoAdapter
    {
        return DB::transaction(function () use ($adapter, $syncKey) {
            /** @var RestoAdapter $locked */
            $locked = RestoAdapter::query()->lockForUpdate()->findOrFail($adapter->getKey());

            if ($locked->status !== 'active') {
                throw new InvalidArgumentException("Adapter tidak aktif (status {$locked->status}).");
            }

            if ($syncKey !== null && $syncKey === $locked->last_sync_key) {
                return $locked; // replay aman
            }

            $locked->last_synced_at = now();
            $locked->last_sync_key = $syncKey ?? $locked->last_sync_key;
            $locked->save();

            return $locked;
        });
    }

    /** Pasang adapter outlet ke plant (CK-01). */
    public function attachRestoAdapter(Plant $plant, ?int $outletId): RestoAdapter
    {
        if ($plant->type !== 'central_kitchen') {
            throw new InvalidArgumentException('Adapter Resto hanya untuk plant bertipe central_kitchen.');
        }

        return RestoAdapter::firstOrCreate(
            ['plant_id' => $plant->id, 'outlet_id' => $outletId],
            ['adapter_type' => 'central_kitchen', 'status' => 'active']
        );
    }

    // ── 35.8 Tenaga kerja ────────────────────────────────────────────────

    public function createWorker(array $data): Worker
    {
        return DB::transaction(function () use ($data) {
            if (Worker::where('employee_code', $data['employee_code'])->exists()) {
                throw new InvalidArgumentException("Kode karyawan {$data['employee_code']} sudah dipakai.");
            }

            return Worker::create([
                'user_id' => $data['user_id'] ?? null,
                'employee_code' => $data['employee_code'],
                'name' => $data['name'],
                'status' => $data['status'] ?? 'active',
                'skills' => $data['skills'] ?? null,
                'certifications' => $data['certifications'] ?? null,
            ]);
        });
    }
}
