<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Application\Services;

use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Core\Contracts\ApprovalEngineInterface;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Manufacturing\Domain\Models\Capa;
use Modules\Manufacturing\Domain\Models\Certificate;
use Modules\Manufacturing\Domain\Models\Gauge;
use Modules\Manufacturing\Domain\Models\Inspection;
use Modules\Manufacturing\Domain\Models\InspectionPlan;
use Modules\Manufacturing\Domain\Models\LotSale;
use Modules\Manufacturing\Domain\Models\Material;
use Modules\Manufacturing\Domain\Models\MaterialIssue;
use Modules\Manufacturing\Domain\Models\MaterialLot;
use Modules\Manufacturing\Domain\Models\Ncr;
use Modules\Manufacturing\Domain\Models\Recall;
use Modules\Manufacturing\Domain\Models\RecallRecipient;
use Modules\Manufacturing\Domain\Models\SpcSample;

/**
 * Mutu & ketertelusuran (Fase 39): rencana inspeksi (AQL simulasi),
 * inspeksi berstage + dispensasi approval, SPC X-bar/R + Cp/Cpk,
 * NCR → CAPA (+ SCAR pemasok query mentah), ketertelusuran maju-mundur,
 * recall lot, sertifikat per lot, dan blokir kalibrasi alat ukur.
 */
class QualityService
{
    public function __construct(
        private readonly DocumentNumberingInterface $numbering,
        private readonly ApprovalEngineInterface $approvals,
        private readonly CostingService $costing,
    ) {}

    // ── 39.1 Rencana inspeksi ────────────────────────────────────────────

    /**
     * @param  array<int, array{name: string, type?: string, unit?: string}>  $characteristics
     */
    public function createInspectionPlan(array $data, array $characteristics): InspectionPlan
    {
        if ($characteristics === []) {
            throw new InvalidArgumentException('Rencana inspeksi harus punya karakteristik.');
        }

        return InspectionPlan::create([
            'name' => $data['name'],
            'stage' => $data['stage'] ?? 'in_process',
            'characteristics' => $characteristics,
            'spec_min' => $data['spec_min'] ?? null,
            'spec_max' => $data['spec_max'] ?? null,
            'aql_percent' => $data['aql_percent'] ?? 1.0,
            'sample_size' => $data['sample_size'] ?? 5,
            'frequency' => $data['frequency'] ?? 'per_batch',
        ]);
    }

    // ── 39.2/39.8 Inspeksi: bacaan, alat ukur, hasil ─────────────────────

    /**
     * Buat inspeksi dengan pembacaan sampel. Alat ukur kalibrasi kadaluarsa
     * memblokir inspeksi (39.8).
     *
     * @param  array<int, float>  $readings
     * @return array{inspection: Inspection, result: string, out_of_spec: int}
     */
    public function inspect(
        string $stage,
        string $subjectType,
        string $subjectId,
        array $readings,
        User $inspector,
        ?string $planId = null,
        ?int $gaugeId = null,
        ?string $lotId = null,
    ): array {
        $plan = $planId !== null ? InspectionPlan::findOrFail($planId) : InspectionPlan::where('stage', $stage)->where('is_active', true)->first();

        if ($gaugeId !== null) {
            $gauge = Gauge::find($gaugeId);
            if ($gauge === null || ! $gauge->isCalibrationValid()) {
                throw new InvalidArgumentException('Kalibrasi alat ukur tidak valid — inspeksi diblokir (39.8).');
            }
        }

        if ($readings === []) {
            throw new InvalidArgumentException('Inspeksi membutuhkan pembacaan sampel.');
        }

        $outOfSpec = 0;
        if ($plan !== null) {
            foreach ($readings as $value) {
                if (($plan->spec_min !== null && $value < (float) $plan->spec_min)
                    || ($plan->spec_max !== null && $value > (float) $plan->spec_max)) {
                    $outOfSpec++;
                }
            }
        }

        // AQL simulasi: % cacat ≤ AQL → lulus, selain itu gagal.
        $defectPct = count($readings) > 0 ? ($outOfSpec * 100 / count($readings)) : 0.0;
        $aql = $plan !== null ? (float) $plan->aql_percent : 1.0;
        $result = $outOfSpec === 0 || $defectPct <= $aql ? 'passed' : 'failed';

        $inspection = Inspection::create([
            'plan_id' => $plan?->id,
            'gauge_id' => $gaugeId,
            'stage' => $stage,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'lot_id' => $lotId,
            'result' => $result,
            'readings' => $readings,
            'findings' => $outOfSpec > 0 ? "{$outOfSpec} pembacaan di luar spesifikasi" : null,
            'inspected_by_user_id' => $inspector->id,
            'decided_at' => now(),
        ]);

        return ['inspection' => $inspection, 'result' => $result, 'out_of_spec' => $outOfSpec];
    }

    /**
     * Ajukan dispensasi inspeksi gagal (39.2): hasil → pending_waiver,
     * approval four-eyes dibuat. Persetujuan lewat approveWaiver().
     */
    public function submitWaiver(Inspection $inspection, User $creator, string $reason): Inspection
    {
        return DB::transaction(function () use ($inspection, $creator, $reason) {
            /** @var Inspection $locked */
            $locked = Inspection::query()->lockForUpdate()->findOrFail($inspection->getKey());
            if ($locked->result === 'waived') {
                return $locked;
            }
            if ($locked->result !== 'failed') {
                throw new InvalidArgumentException('Hanya inspeksi gagal yang dapat didispensasi.');
            }

            $approval = $this->approvals->submit(
                approvalType: 'MFG_INSPECTION_WAIVER',
                title: "Dispensasi inspeksi {$locked->id}",
                creator: $creator,
                approvable: $locked,
                steps: [['role' => 'admin']],
                slaHours: 48,
                metadata: ['inspection_id' => $locked->id, 'reason' => $reason],
            );

            $locked->findings = trim(($locked->findings ? $locked->findings.' | ' : '')."Dispensasi diajukan: {$reason}");
            $locked->approval_id = (int) $approval->id;
            $locked->save();

            return $locked;
        });
    }

    /** Setujui dispensasi (empat mata: approver ≠ pengajuk). */
    public function approveWaiver(Inspection $inspection, User $approver, string $note = ''): Inspection
    {
        return DB::transaction(function () use ($inspection, $approver, $note) {
            /** @var Inspection $locked */
            $locked = Inspection::query()->lockForUpdate()->findOrFail($inspection->getKey());
            if ($locked->result === 'waived') {
                return $locked;
            }
            if ($locked->approval_id === null) {
                throw new InvalidArgumentException('Dispensasi belum diajukan.');
            }

            $this->approvals->approve((int) $locked->approval_id, $approver, $note !== '' ? $note : 'Dispensasi disetujui');

            $locked->result = 'waived';
            $locked->decided_at = now();
            $locked->save();

            return $locked;
        });
    }

    // ── 39.7 Pelepasan lot + sertifikat ─────────────────────────────────

    /**
     * Lepaskan lot ke active setelah inspeksi lulus + sertifikat wajib
     * yang masih berlaku (kedaluwarsa memblokir).
     *
     * @param  array<int, string>  $requiredTypes
     */
    public function releaseLot(MaterialLot $lot, User $actor, array $requiredTypes = ['coa']): MaterialLot
    {
        return DB::transaction(function () use ($lot, $requiredTypes) {
            /** @var MaterialLot $locked */
            $locked = MaterialLot::query()->lockForUpdate()->findOrFail($lot->getKey());

            // 'waived' menggantikan 'failed' saat dispensasi disetujui.
            $failed = Inspection::where('lot_id', $locked->id)->where('result', 'failed')->exists();
            if ($failed) {
                throw new InvalidArgumentException('Lot memiliki inspeksi gagal yang belum didispensasi.');
            }

            foreach ($requiredTypes as $type) {
                // Cukup satu sertifikat tipe itu yang masih berlaku.
                $valid = Certificate::where('lot_id', $locked->id)->where('type', $type)->get()
                    ->contains(fn (Certificate $c) => ! $c->isExpired());
                if (! $valid) {
                    throw new InvalidArgumentException("Sertifikat {$type} hilang/kedaluwarsa — rilis diblokir (39.7).");
                }
            }

            $locked->status = 'active';
            $locked->save();

            return $locked;
        });
    }

    public function addCertificate(MaterialLot $lot, string $type, string $number, ?string $expiresAt, User $issuer): Certificate
    {
        return Certificate::create([
            'lot_id' => $lot->id,
            'type' => $type,
            'number' => $number,
            'issued_at' => now()->toDateString(),
            'expires_at' => $expiresAt,
            'issuer' => 'Simulasi',
            'verified' => false,
        ]);
    }

    // ── 39.3 SPC: X-bar/R, Cp/Cpk, alarm ─────────────────────────────────

    /**
     * Catat subgroup SPC dan hitung Cp/Cpk dari riwayat plan.
     *
     * @param  array<int, float>  $readings
     * @return array{sample: SpcSample, cp: float, cpk: float, in_control: bool}
     */
    public function recordSpcSample(InspectionPlan $plan, array $readings, ?Inspection $inspection = null): array
    {
        if ($readings === []) {
            throw new InvalidArgumentException('Subgroup SPC harus berisi pembacaan.');
        }

        $mean = array_sum($readings) / count($readings);
        $min = min($readings);
        $max = max($readings);
        $range = $max - $min;

        $subgroup = (int) ($plan->spcSamples()->max('subgroup') ?? 0) + 1;
        $sample = SpcSample::create([
            'plan_id' => $plan->id,
            'inspection_id' => $inspection?->id,
            'subgroup' => $subgroup,
            'mean_value' => $mean,
            'range_value' => $range,
            'sample_count' => count($readings),
            'taken_at' => now(),
        ]);

        $history = $plan->spcSamples()->orderBy('subgroup')->get();
        [$cp, $cpk] = $this->cpCpk($plan, $history);

        // Alarm: mean di luar batas spesifikasi (rule sederhana).
        $inControl = true;
        if ($plan->spec_min !== null && $mean < (float) $plan->spec_min) {
            $inControl = false;
        }
        if ($plan->spec_max !== null && $mean > (float) $plan->spec_max) {
            $inControl = false;
        }
        if ($cpk < 1.0) {
            $inControl = false;
        }

        $sample->in_control = $inControl;
        $sample->save();

        return ['sample' => $sample, 'cp' => $cp, 'cpk' => $cpk, 'in_control' => $inControl];
    }

    /**
     * Cp = (USL−LSL)/(6σ), Cpk = min(USL−μ, μ−LSL)/(3σ). σ dari R̄/d2 (n=5 → 2.326).
     *
     * @param  iterable<SpcSample>  $samples
     * @return array{0: float, 1: float}
     */
    public function cpCpk(InspectionPlan $plan, iterable $samples): array
    {
        $list = is_array($samples) ? $samples : collect($samples)->all();
        if (count($list) < 2 || $plan->spec_min === null || $plan->spec_max === null) {
            return [0.0, 0.0];
        }

        $rBar = array_sum(array_map(static fn (SpcSample $s) => (float) $s->range_value, $list)) / count($list);
        $mean = array_sum(array_map(static fn (SpcSample $s) => (float) $s->mean_value, $list)) / count($list);
        $sigma = $rBar / 2.326;
        if ($sigma <= 0) {
            return [0.0, 0.0];
        }

        $usl = (float) $plan->spec_max;
        $lsl = (float) $plan->spec_min;
        $cp = ($usl - $lsl) / (6 * $sigma);
        $cpk = min($usl - $mean, $mean - $lsl) / (3 * $sigma);

        return [round($cp, 3), round($cpk, 3)];
    }

    // ── 39.4 NCR → investigasi → CAPA ────────────────────────────────────

    public function openNcr(array $data, User $opener): Ncr
    {
        $number = $this->numbering->nextNumber('MFG', 'NCR', false, 'NCR/{ENT}/');

        $ncr = Ncr::create([
            'number' => $number,
            'source' => $data['source'] ?? 'inspection',
            'inspection_id' => $data['inspection_id'] ?? null,
            'production_order_id' => $data['production_order_id'] ?? null,
            'supplier_id' => $data['supplier_id'] ?? null,
            'lot_id' => $data['lot_id'] ?? null,
            'severity' => $data['severity'] ?? 'minor',
            'status' => 'open',
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_date' => $data['due_date'] ?? now()->addDays(14)->toDateString(),
            'opened_by_user_id' => $opener->id,
        ]);

        // Tautan SCAR pemasok (Fase 32) bila sumber masalah adalah pemasok.
        if ($ncr->supplier_id !== null && $ncr->source === 'supplier') {
            $scar = DB::table('sup_risk_flags')->insertGetId([
                'supplier_id' => $ncr->supplier_id,
                'type' => 'scar',
                'severity' => $ncr->severity === 'critical' ? 'critical' : 'major',
                'message' => "NCR {$ncr->number}: {$ncr->title}",
                'meta' => json_encode(['ncr_id' => $ncr->id, 'number' => $ncr->number]),
                'is_open' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $ncr->update(['scar_ref' => 'SCAR-'.$scar]);
            $ncr->status = 'investigating';
            $ncr->save();
        }

        return $ncr;
    }

    public function addCapa(Ncr $ncr, string $kind, string $action, string $dueDate): Capa
    {
        if (! in_array($kind, ['corrective', 'preventive'], true)) {
            throw new InvalidArgumentException('Jenis CAPA harus corrective atau preventive.');
        }

        $capa = Capa::create([
            'ncr_id' => $ncr->id,
            'kind' => $kind,
            'action' => $action,
            'due_date' => $dueDate,
            'status' => 'open',
        ]);

        $ncr->status = 'capa';
        $ncr->save();

        return $capa;
    }

    /** Selesaikan CAPA + verifikasi efektivitas; NCR ditutup bila semua CAPA verified. */
    public function completeCapa(Capa $capa, string $effectiveness, string $note): Capa
    {
        if (! in_array($effectiveness, ['effective', 'ineffective'], true)) {
            throw new InvalidArgumentException('Efektivitas CAPA harus effective atau ineffective.');
        }

        return DB::transaction(function () use ($capa, $effectiveness, $note) {
            /** @var Capa $locked */
            $locked = Capa::query()->lockForUpdate()->findOrFail($capa->getKey());
            $locked->update([
                'status' => 'verified', 'effectiveness' => $effectiveness,
                'verification_note' => $note, 'completed_at' => now(),
            ]);

            $open = Capa::where('ncr_id', $locked->ncr_id)->where('status', 'open')->count();
            if ($open === 0) {
                Ncr::where('id', $locked->ncr_id)->update(['status' => 'closed', 'closed_at' => now()]);
            }

            return $locked;
        });
    }

    /** Sweep CAPA lewat tenggat → overdue. */
    public function markOverdueCapas(): int
    {
        return Capa::where('status', 'open')
            ->where('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);
    }

    // ── 39.5 Ketertelusuran maju-mundur ─────────────────────────────────

    /**
     * Mundur: lot FG → lot bahan (via issue + alokasi) → material bahan
     * → sumber (supplier/purchase).
     *
     * @return array{lot: string, inputs: array<int, array{lot: string, material: string, qty: string, source: string}>}
     */
    public function traceBackward(string $lotId): array
    {
        $lot = MaterialLot::findOrFail($lotId);

        // Lot FG menyimpan source_ref = nomor order produksi → issues order itu.
        $orderIds = [];
        if ($lot->source_ref !== null) {
            $orderIds = DB::table('mfg_production_orders')
                ->where('number', $lot->source_ref)
                ->pluck('id')
                ->all();
        }

        $inputs = [];
        $issues = MaterialIssue::whereIn('production_order_id', $orderIds)
            ->get();

        foreach ($issues as $issue) {
            foreach ($issue->issueLots as $allocation) {
                $inputLot = MaterialLot::find($allocation->lot_id);
                if ($inputLot === null) {
                    continue;
                }
                $material = Material::find($issue->material_id);
                $inputs[] = [
                    'lot' => (string) $inputLot->lot_number,
                    'material' => (string) $material?->code,
                    'qty' => (string) $allocation->qty,
                    'source' => trim(($inputLot->source_type ?? '').' '.($inputLot->source_ref ?? '')),
                ];
            }
        }

        return ['lot' => (string) $lot->lot_number, 'inputs' => $inputs];
    }

    /**
     * Maju: lot → item order Store penerima (distributor/pelanggan).
     * Query budget-friendly: satu query per lot.
     *
     * @return array<int, array{order: int, item: int, user: int|null, qty: string}>
     */
    public function traceForward(string $lotId): array
    {
        return LotSale::where('lot_id', $lotId)
            ->get()
            ->map(fn (LotSale $sale) => [
                'order' => (int) $sale->store_order_id,
                'item' => (int) $sale->store_order_item_id,
                'user' => $sale->user_id !== null ? (int) $sale->user_id : null,
                'qty' => (string) $sale->qty,
            ])->all();
    }

    // ── 39.6 Recall ──────────────────────────────────────────────────────

    /**
     * Rencanakan recall: kunci lot, bangun daftar penerima dari jejak
     * penjualan (mfg_lot_sales), kuarantina stok.
     */
    public function planRecall(MaterialLot $lot, string $reason, User $creator, ?string $ncrId = null): Recall
    {
        return DB::transaction(function () use ($lot, $reason, $creator, $ncrId) {
            $existing = Recall::where('lot_id', $lot->id)->first();
            if ($existing !== null) {
                return $existing; // idempoten per lot
            }

            $recall = Recall::create([
                'lot_id' => $lot->id,
                'ncr_id' => $ncrId,
                'reason' => $reason,
                'status' => 'planned',
                'started_at' => now(),
                'created_by_user_id' => $creator->id,
            ]);

            foreach (LotSale::where('lot_id', $lot->id)->get() as $sale) {
                $user = $sale->user_id !== null ? User::find($sale->user_id) : null;
                RecallRecipient::create([
                    'recall_id' => $recall->id,
                    'lot_sale_id' => $sale->id,
                    'store_order_id' => $sale->store_order_id,
                    'user_id' => $sale->user_id,
                    'contact_snapshot' => $user?->email ?? null,
                ]);
            }

            // Kuarantina stok lot terdampak.
            $lot = MaterialLot::query()->lockForUpdate()->findOrFail($lot->id);
            $lot->status = 'blocked';
            $lot->save();

            return $recall->load('recipients');
        });
    }

    /** Kirim notifikasi ke semua penerima (in-app via NotificationService bila ada). */
    public function notifyRecall(Recall $recall): int
    {
        return DB::transaction(function () use ($recall) {
            /** @var Recall $locked */
            $locked = Recall::query()->lockForUpdate()->findOrFail($recall->getKey());
            if ($locked->status !== 'planned') {
                return $locked->recipients()->whereNotNull('notified_at')->count();
            }

            foreach ($locked->recipients as $recipient) {
                if ($recipient->notified_at === null) {
                    $recipient->update(['notified_at' => now()]);
                }
            }

            $locked->status = 'notified';
            $locked->save();

            return $locked->recipients()->count();
        });
    }

    /**
     * Selesaikan recall: catat biaya + penghancuran bersertifikat.
     *
     * @return array{recall: Recall, recipients: int}
     */
    public function completeRecall(Recall $recall, int $costIdr, string $destructionNote): array
    {
        return DB::transaction(function () use ($recall, $costIdr, $destructionNote) {
            /** @var Recall $locked */
            $locked = Recall::query()->lockForUpdate()->findOrFail($recall->getKey());
            if ($locked->status === 'completed') {
                return ['recall' => $locked, 'recipients' => $locked->recipients()->count()]; // replay
            }

            $locked->update([
                'status' => 'completed', 'cost_idr' => max(0, $costIdr),
                'destruction_note' => $destructionNote, 'completed_at' => now(),
            ]);

            // Biaya recall → beban (jurnal idempoten per recall).
            if ($costIdr > 0) {
                $this->costing->ensureAccounts();
                app(Ledger::class)->post(new PostingDTO(
                    type: TransactionType::WASTE->value,
                    description: 'Biaya recall lot '.($locked->lot?->lot_number ?? ''),
                    idempotencyKey: 'mfg:recall:'.$locked->id,
                    entries: [
                        PostingEntryDTO::forCode(CostingService::ACCT_SCRAP, 'IDR', BigDecimal::of($costIdr)),
                        PostingEntryDTO::forCode(CostingService::ACCT_FG, 'IDR', BigDecimal::of($costIdr)->negated()),
                    ],
                    referenceType: Recall::class,
                    referenceId: $locked->id,
                    postedAt: now(),
                ));
            }

            // Lot habis dimusnahkan.
            $lot = MaterialLot::query()->lockForUpdate()->findOrFail($locked->lot_id);
            $lot->update(['status' => 'consumed', 'qty' => 0]);

            return ['recall' => $locked->fresh(), 'recipients' => $locked->recipients()->count()];
        });
    }
}
