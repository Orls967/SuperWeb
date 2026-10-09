<?php

declare(strict_types=1);

namespace Modules\Supplier\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\ApprovalEngineInterface;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Supplier\Contracts\ReferenceCostUpdater;
use Modules\Supplier\Domain\Enums\SupplierStatus;
use Modules\Supplier\Domain\Models\Supplier;
use Modules\Supplier\Domain\Models\SupplierCertification;
use Modules\Supplier\Domain\Models\SupplierItem;
use Modules\Supplier\Domain\Models\SupplierPriceTier;
use Modules\Supplier\Domain\Models\SupplierQualification;
use Modules\Supplier\Domain\Models\SupplierRiskFlag;
use Modules\Supplier\Domain\Models\SupplierScorecard;
use Modules\Supplier\Domain\Models\SupplierStatusHistory;

/**
 * Layanan inti pemasok (Fase 32.1–32.7).
 *
 * - Onboarding berstatus dengan guard state machine + riwayat.
 * - Kualifikasi (kuesioner / audit lokasi) dievaluasi → approval four-eyes.
 * - Resolusi harga bertingkat (MOQ, periode, tanpa overlap) + kontrak kerangka.
 * - Skor periodik & manajemen risiko (konsentrasi, sertifikat, sanksi Party).
 *
 * Uang: harga disimpan decimal (multi-currency Fase 48 — minor unit), tidak ada float.
 */
class SupplierService
{
    public const SCORECARD_ACTION_THRESHOLD = 70;

    public function __construct(
        private readonly DocumentNumberingInterface $numbering,
        private readonly ApprovalEngineInterface $approvals,
    ) {}

    // ── 32.1 Registrasi profil pemasok ──────────────────────────────────

    /**
     * Daftarkan pemasok/produsen baru (status candidate).
     *
     * @param array{party_id?: string, name: string, kind?: string, lead_time_days?: int,
     *   payment_terms_days?: int, capabilities?: array, factory_locations?: array, notes?: string} $data
     */
    public function register(array $data, ?User $actor = null): Supplier
    {
        return DB::transaction(function () use ($data) {
            $code = $this->numbering->nextNumber(
                entityCode: 'SUP',
                documentType: 'SUP',
                resetMonthly: false,
                customPrefix: 'SUP/{ENT}/',
            );

            return Supplier::create([
                'party_id' => $data['party_id'] ?? null,
                'owner_user_id' => $data['owner_user_id'] ?? null,
                'code' => $code,
                'name' => $data['name'],
                'kind' => $data['kind'] ?? 'supplier',
                'status' => SupplierStatus::Candidate,
                'lead_time_days' => $data['lead_time_days'] ?? 7,
                'payment_terms_days' => $data['payment_terms_days'] ?? 30,
                'rating' => $data['rating'] ?? 5,
                'capabilities' => $data['capabilities'] ?? [],
                'factory_locations' => $data['factory_locations'] ?? [],
                'notes' => $data['notes'] ?? null,
                'is_active' => true,
            ]);
        });
    }

    // ── 32.2 Kualifikasi & onboarding ──────────────────────────────────

    /**
     * Ajukan kuesioner/audit lokasi: hitung skor lalu kirim approval.
     *
     * @param  array<string, int>  $scores  butir => skor 0..100
     */
    public function submitQualification(
        Supplier $supplier,
        string $type,
        array $answers,
        array $scores,
        User $assessor,
        ?string $notes = null
    ): SupplierQualification {
        if (! in_array($type, ['questionnaire', 'site_audit'], true)) {
            throw new InvalidArgumentException('Tipe kualifikasi harus questionnaire atau site_audit.');
        }

        return DB::transaction(function () use ($supplier, $type, $answers, $scores, $assessor, $notes) {
            $total = $scores === [] ? 0 : (int) round(array_sum($scores) / count($scores));
            $result = $total >= 70 ? 'pass' : ($total < 50 ? 'fail' : 'pending');

            $approval = $this->approvals->submit(
                approvalType: $type === 'site_audit' ? 'SUPPLIER_SITE_AUDIT' : 'SUPPLIER_QUALIFICATION',
                title: ucfirst(str_replace('_', ' ', $type))." pemasok {$supplier->name} (skor {$total})",
                creator: $assessor,
                approvable: $supplier,
                amount: null,
                steps: [['role' => 'procurement'], ['role' => 'admin']],
                slaHours: 72,
                metadata: [
                    'supplier_id' => $supplier->id,
                    'supplier_code' => $supplier->code,
                    'type' => $type,
                    'total_score' => $total,
                    'result' => $result,
                ]
            );

            return SupplierQualification::create([
                'supplier_id' => $supplier->id,
                'type' => $type,
                'answers' => $answers,
                'scores' => $scores,
                'total_score' => $total,
                'result' => $result,
                'approval_id' => $approval->uuid,
                'approval_status' => 'pending',
                'assessed_by_user_id' => $assessor->id,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Transisi status onboarding (guard state machine + riwayat wajib).
     */
    public function transition(Supplier $supplier, SupplierStatus $next, ?string $reason, ?User $actor): Supplier
    {
        if (! $supplier->status->canTransitionTo($next)) {
            throw new InvalidArgumentException(
                "Transisi status pemasok dari [{$supplier->status->label()}] ke [{$next->label()}] tidak diizinkan."
            );
        }

        if ($reason === null || trim($reason) === '') {
            throw new InvalidArgumentException('Alasan transisi wajib diisi (jejak audit).');
        }

        return DB::transaction(function () use ($supplier, $next, $reason, $actor) {
            /** @var Supplier $locked */
            $locked = Supplier::query()->lockForUpdate()->findOrFail($supplier->getKey());

            $from = $locked->status;
            $locked->status = $next;
            $locked->save();

            SupplierStatusHistory::create([
                'supplier_id' => $locked->id,
                'from_status' => $from->value,
                'to_status' => $next->value,
                'reason' => $reason,
                'changed_by_user_id' => $actor?->id,
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Setujui kualifikasi → pemasok naik status (approval disetujui).
     */
    public function approveQualification(SupplierQualification $qualification, ?User $actor = null): Supplier
    {
        return DB::transaction(function () use ($qualification, $actor) {
            /** @var SupplierQualification $locked */
            $locked = SupplierQualification::query()->lockForUpdate()->findOrFail($qualification->getKey());

            if ($locked->approval_status !== 'pending') {
                return $locked->supplier;
            }

            $locked->approval_status = 'approved';
            $locked->save();

            /** @var Supplier $supplier */
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($locked->supplier_id);

            if ($supplier->status === SupplierStatus::Candidate) {
                $supplier = $this->transition(
                    $supplier,
                    SupplierStatus::Approved,
                    'Kualifikasi lolos (skor '.$locked->total_score.')',
                    $actor
                );
            }

            return $supplier;
        });
    }

    public function rejectQualification(SupplierQualification $qualification, string $reason, ?User $actor = null): SupplierQualification
    {
        return DB::transaction(function () use ($qualification) {
            $qualification->approval_status = 'rejected';
            $qualification->save();

            return $qualification;
        });
    }

    // ── 32.3 / 32.4 Resolusi harga & kontrak kerangka ──────────────────

    /**
     * Resolusi harga berlaku: kontrak kerangka (jika ada) → harga tier aktif.
     *
     * @param  array{contract_id?: string, valid_at?: string}  $context
     * @return array{unit_price: string, currency: string, source: string, supplier_item_id: int|null}
     */
    public function resolvePrice(SupplierItem $item, int $qty, array $context = []): array
    {
        if ($qty < (int) $item->moq) {
            throw new InvalidArgumentException("Jumlah minimal order (MOQ) adalah {$item->moq} {$item->unit}.");
        }

        // 32.4: harga kontrak mengalahkan katalog.
        if (! empty($context['contract_id'])) {
            $contractPrice = $this->contractPrice((string) $context['contract_id'], $item);
            if ($contractPrice !== null) {
                return $contractPrice;
            }
        }

        $tier = $item->activeTier($qty, $context['valid_at'] ?? null);
        if ($tier === null) {
            throw new InvalidArgumentException("Tidak ada harga bertingkat yang berlaku untuk {$item->supplier_sku} (qty {$qty}).");
        }

        return [
            'unit_price' => (string) $tier->unit_price,
            'currency' => $tier->currency,
            'source' => 'catalog',
            'supplier_item_id' => $item->id,
        ];
    }

    /**
     * Harga dari kontrak kerangka (Fase 28/29): tier harga yang terikat pada
     * `contract_id` mengalahkan harga katalog (32.4). Kolom kait nullable tanpa
     * FK — tidak mengimpor Domain Contract.
     *
     * @return array{unit_price: string, currency: string, source: string, supplier_item_id: int|null}|null
     */
    private function contractPrice(string $contractId, SupplierItem $item): ?array
    {
        // Tautan kontrak ↔ item pemasok disimpan via metadata kontrak (Fase 28) —
        // dicari dari ctr_contracts ketika tersedia; null saat belum.
        $tier = SupplierPriceTier::query()
            ->where('item_id', $item->id)
            ->where('contract_id', $contractId)
            ->where('is_active', true)
            ->whereDate('valid_from', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', now()->toDateString());
            })
            ->orderByDesc('min_qty')
            ->first();

        if ($tier === null) {
            return null;
        }

        // Nomor kontrak hanya untuk identifikasi sumber (tabel, tanpa import Domain).
        $contractNumber = DB::table('ctr_contracts')
            ->where('id', $contractId)
            ->value('contract_number');

        return [
            'unit_price' => (string) $tier->unit_price,
            'currency' => $tier->currency,
            'source' => 'contract:'.((string) ($contractNumber ?? $contractId)),
            'supplier_item_id' => $item->id,
        ];
    }

    /**
     * 32.8 — harga terakhir pemasok → biaya referensi internal (MAC) lewat kontrak.
     * Resto mengikat implementasi; Supplier tidak pernah mengimpor Domain Resto.
     */
    public function applyReferenceCost(SupplierPriceTier $tier): bool
    {
        if (! app()->bound(ReferenceCostUpdater::class)) {
            return false;
        }

        $item = $tier->item;
        if ($item === null || $item->internal_product_id === null) {
            return false;
        }

        return app(ReferenceCostUpdater::class)
            ->updateReferenceCost((int) $item->internal_product_id, (string) $tier->unit_price, $tier->currency);
    }

    // ── 32.6 Supplier scorecard ─────────────────────────────────────────

    /**
     * Hitung skor periodik dari metrik yang disediakan (skala 0–100).
     *
     * @param  array{otd_percent?: float, reject_percent?: float, price_index?: float, response_days?: float}  $metrics
     * @return array{otd_score: int, quality_score: int, price_score: int, responsiveness_score: int, overall_score: int, action: string}
     */
    public function score(string $period, array $metrics): array
    {
        $otd = max(0, min(100, (int) round($metrics['otd_percent'] ?? 100)));
        $quality = max(0, min(100, (int) round(100 - ($metrics['reject_percent'] ?? 0))));
        $price = max(0, min(100, (int) round(100 - (($metrics['price_index'] ?? 1) - 1) * 100)));
        $responsiveness = max(0, min(100, (int) round(100 - ($metrics['response_days'] ?? 0) * 5)));

        $overall = (int) round(($otd + $quality + $price + $responsiveness) / 4);

        $action = match (true) {
            $overall < 50 => 'scar',
            $overall < self::SCORECARD_ACTION_THRESHOLD => 'corrective',
            $overall < 85 => 'review',
            default => 'none',
        };

        return [
            'otd_score' => $otd,
            'quality_score' => $quality,
            'price_score' => $price,
            'responsiveness_score' => $responsiveness,
            'overall_score' => $overall,
            'action' => $action,
        ];
    }

    /**
     * Simpan skor periodik (idempoten per supplier+periode).
     */
    public function saveScorecard(Supplier $supplier, string $period, array $metrics, ?string $notes = null): SupplierScorecard
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw new InvalidArgumentException('Format periode scorecard harus YYYY-MM.');
        }

        $scored = $this->score($period, $metrics);

        return DB::transaction(function () use ($supplier, $period, $scored, $notes) {
            $existing = SupplierScorecard::query()
                ->where('supplier_id', $supplier->id)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $existing->update($scored + ['notes' => $notes]);

                return $existing;
            }

            return SupplierScorecard::create($scored + [
                'supplier_id' => $supplier->id,
                'period' => $period,
                'notes' => $notes,
            ]);
        });
    }

    // ── 32.7 Manajemen risiko ──────────────────────────────────────────

    /**
     * Buka/segel flag risiko (konsentrasi, sertifikat, sanksi, skor).
     *
     * @param  array<string, mixed>  $meta
     */
    public function flag(Supplier $supplier, string $type, string $severity, string $message, array $meta = []): SupplierRiskFlag
    {
        return SupplierRiskFlag::firstOrCreate(
            [
                'supplier_id' => $supplier->id,
                'type' => $type,
                'is_open' => true,
            ],
            [
                'severity' => $severity,
                'message' => $message,
                'meta' => $meta,
            ]
        );
    }

    public function resolveFlag(SupplierRiskFlag $flag, ?string $note = null): SupplierRiskFlag
    {
        return DB::transaction(function () use ($flag, $note) {
            /** @var SupplierRiskFlag $locked */
            $locked = SupplierRiskFlag::query()->lockForUpdate()->findOrFail($flag->getKey());
            $locked->is_open = false;
            $locked->resolved_at = now();
            if ($note !== null) {
                $locked->meta = array_merge($locked->meta ?? [], ['resolution' => $note]);
            }
            $locked->save();

            return $locked;
        });
    }

    /**
     * Pindai risiko terbuka: sertifikat kedaluwarsa / < 60 hari, skor rendah,
     * dan sanksi Party (Fase 27.6 — cek lewat tabel, bukan import Domain).
     *
     * @return array<int, array{supplier: string, type: string, severity: string, message: string}>
     */
    public function scanRisks(): array
    {
        $opened = [];

        // Sertifikat kedaluwarsa / hampir habis — model-aware query.
        $certs = SupplierCertification::query()
            ->where('is_active', true)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', now()->addDays(60)->toDateString())
            ->with('supplier')
            ->get();

        foreach ($certs as $cert) {
            $supplier = $cert->supplier;
            if ($supplier === null) {
                continue;
            }

            $expired = $cert->isExpired();
            $flag = $this->flag(
                $supplier,
                $expired ? 'expired_cert' : 'expiring_cert',
                $expired ? 'high' : 'medium',
                $expired
                    ? "Sertifikasi {$cert->type} sudah kedaluwarsa ({$cert->expires_at})."
                    : "Sertifikasi {$cert->type} berakhir dalam < 60 hari ({$cert->expires_at}).",
                ['cert_type' => $cert->type, 'expires_at' => $cert->expires_at]
            );
            $opened[] = ['supplier' => $supplier->name, 'type' => $flag->type, 'severity' => $flag->severity, 'message' => $flag->message];
        }

        // Skor periodik di bawah ambang korektif.
        $lowScores = DB::table('sup_scorecards')
            ->where('overall_score', '<', self::SCORECARD_ACTION_THRESHOLD)
            ->orderByDesc('period')
            ->get(['supplier_id', 'period', 'overall_score', 'action'])
            ->unique('supplier_id');

        foreach ($lowScores as $score) {
            $supplier = Supplier::find($score->supplier_id);
            if ($supplier === null) {
                continue;
            }

            $flag = $this->flag(
                $supplier,
                'score_below_threshold',
                $score->overall_score < 50 ? 'critical' : 'high',
                "Skor pemasok periode {$score->period} = {$score->overall_score} (tindakan: {$score->action}).",
                ['period' => $score->period, 'overall_score' => $score->overall_score]
            );
            $opened[] = ['supplier' => $supplier->name, 'type' => $flag->type, 'severity' => $flag->severity, 'message' => $flag->message];
        }

        // Sanksi / blacklist dari screening Party (Fase 27.6) — lewat tabel.
        $sanctionedPartyIds = DB::table('pty_sanctions_checks')
            ->whereIn('status', ['hit', 'review'])
            ->pluck('party_id');

        if ($sanctionedPartyIds->isNotEmpty()) {
            foreach (Supplier::whereIn('party_id', $sanctionedPartyIds)->get() as $supplier) {
                $flag = $this->flag(
                    $supplier,
                    'sanctions',
                    'critical',
                    'Pihak ini tertaut pada pemeriksaan sanksi/partai yang perlu ditinjau (screening Fase 27.6).',
                    ['party_id' => $supplier->party_id]
                );
                $opened[] = ['supplier' => $supplier->name, 'type' => $flag->type, 'severity' => $flag->severity, 'message' => $flag->message];
            }
        }

        // Konsentrasi pemasok: satu-satunya penyedia kategori tertentu (single source).
        $categorySuppliers = DB::table('sup_items')
            ->where('is_active', true)
            ->selectRaw('name, COUNT(DISTINCT supplier_id) as supplier_count, GROUP_CONCAT(DISTINCT supplier_id) as supplier_ids')
            ->groupBy('name')
            ->having('supplier_count', 1)
            ->limit(20)
            ->get();

        foreach ($categorySuppliers as $row) {
            $soleSupplier = Supplier::find(explode(',', $row->supplier_ids)[0] ?? null);
            if ($soleSupplier !== null) {
                $flag = $this->flag(
                    $soleSupplier,
                    'single_source',
                    'medium',
                    "Pemasok tunggal untuk item '{$row->name}' (risiko konsentrasi).",
                    ['item' => $row->name]
                );
                $opened[] = ['supplier' => $soleSupplier->name, 'type' => $flag->type, 'severity' => $flag->severity, 'message' => $flag->message];
            }
        }

        return $opened;
    }
}
