<?php

declare(strict_types=1);

namespace Modules\Asset\Application\Services;

use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Asset\Domain\Enums\AssetCategoryCode;
use Modules\Asset\Domain\Enums\AssetEventType;
use Modules\Asset\Domain\Enums\AssetStatus;
use Modules\Asset\Domain\Models\Asset;
use Modules\Asset\Domain\Models\AssetAssignment;
use Modules\Asset\Domain\Models\AssetCategory;
use Modules\Asset\Domain\Models\AssetEvent;
use Modules\Asset\Domain\Models\AssetInsurance;
use Modules\Asset\Domain\Models\AssetLocation;
use Modules\Asset\Domain\Models\AssetStocktake;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Core\Contracts\ApprovalEngineInterface;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Core\Contracts\DocumentStoreInterface;

/**
 * Layanan inti aset (30.2–30.5).
 *
 * Invarian uang: seluruh nilai integer IDR; posting ledger memakai
 * idempotency key deterministik dari sumber akuisisi; buku besar aset
 * (`ast:fixed_assets`) seimbang dengan `ap`/kas.
 */
class AssetService
{
    public const ACCT_FIXED_ASSETS = 'ast:fixed_assets';

    public const GENESIS_HASH = 'GENESIS_AST_0000000000000000000000000000000000000000000000000000000000000';

    public function __construct(
        private readonly DocumentNumberingInterface $numbering,
        private readonly Ledger $ledger,
        private readonly DocumentStoreInterface $documents,
        private readonly ApprovalEngineInterface $approvals,
    ) {}

    // ── Akun ledger ──────────────────────────────────────────────────────

    public function ensureAccounts(): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => self::ACCT_FIXED_ASSETS, 'asset_code' => 'IDR'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Aset Tetap',
                'kind' => AccountKind::ASSET->value,
                'allow_negative' => false,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }

    // ── 30.1 Kategori default (seed sekali, idempoten) ──────────────────

    public function ensureDefaultCategories(): void
    {
        foreach (AssetCategoryCode::cases() as $code) {
            AssetCategory::firstOrCreate(
                ['code' => $code->value],
                [
                    'name' => $code->label(),
                    'useful_life_years' => $code->usefulLifeYears(),
                    'depreciation_method' => $code->depreciationMethod(),
                    'is_active' => true,
                ]
            );
        }
    }

    // ── 30.2 Register aset ──────────────────────────────────────────────

    /**
     * Buat aset baru dari pembelian langsung / PO / konstruksi (30.3).
     *
     * @param array{
     *     name: string, category_id: int, description?: string,
     *     location_id?: int, legal_entity_id?: string, responsible_user_id?: int,
     *     brand?: string, serial_number?: string, condition?: string,
     *     acquisition_cost_idr: int, landed_cost_idr?: int,
     *     acquired_at?: string, in_service_at?: string,
     *     source_type?: string, source_id?: int, photo_path?: string,
     * } $data
     */
    public function register(array $data, ?User $actor = null, bool $postLedger = true): Asset
    {
        return DB::transaction(function () use ($data, $actor, $postLedger) {
            $category = AssetCategory::query()->lockForUpdate()->findOrFail($data['category_id']);

            // {ENT} ikut dieksekusi oleh DocumentNumberingService sehingga dua
            // sequence berbeda (per sumber/entity) tidak pernah menghasilkan
            // nomor string yang sama.
            $assetNumber = $this->numbering->nextNumber(
                entityCode: strtoupper(substr((string) ($data['source_type'] ?? 'AST'), 0, 3)),
                documentType: 'AST',
                resetMonthly: false,
                customPrefix: 'AST/{ENT}/',
            );

            $tag = 'QR-'.Str::upper(Str::random(6));
            $totalCost = (int) $data['acquisition_cost_idr'] + (int) ($data['landed_cost_idr'] ?? 0);

            $asset = Asset::create([
                'asset_number' => $assetNumber,
                'asset_tag' => $tag,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'category_id' => $category->id,
                'location_id' => $data['location_id'] ?? null,
                'legal_entity_id' => $data['legal_entity_id'] ?? null,
                'responsible_user_id' => $data['responsible_user_id'] ?? null,
                'condition' => $data['condition'] ?? 'good',
                'status' => AssetStatus::InUse,
                'brand' => $data['brand'] ?? null,
                'serial_number' => $data['serial_number'] ?? null,
                'acquired_at' => $data['acquired_at'] ?? now()->toDateString(),
                'in_service_at' => $data['in_service_at'] ?? now()->toDateString(),
                'acquisition_cost_idr' => (int) $data['acquisition_cost_idr'],
                'landed_cost_idr' => (int) ($data['landed_cost_idr'] ?? 0),
                'accumulated_depreciation_idr' => 0,
                'book_value_idr' => $totalCost,
                'source_type' => $data['source_type'] ?? 'direct',
                'source_id' => $data['source_id'] ?? null,
                'photo_path' => $data['photo_path'] ?? null,
            ]);

            // Posting pembelian: debit ast:fixed_assets, kredit ap/kas (tergantung sumber).
            if ($postLedger && $totalCost > 0) {
                $this->postAcquisition($asset, $data['source_type'] ?? 'direct', $actor?->id);
            }

            // Event akuisisi ke rantai hash.
            $this->recordEvent($asset, AssetEventType::Acquisition, [
                'acquisition_cost_idr' => (int) $data['acquisition_cost_idr'],
                'landed_cost_idr' => (int) ($data['landed_cost_idr'] ?? 0),
                'source_type' => $data['source_type'] ?? 'direct',
                'source_id' => $data['source_id'] ?? null,
                'category_code' => $category->code,
            ], $actor?->name);

            return $asset;
        });
    }

    /**
     * Naikkan aset yang sudah ada ke ledger (idempoten per key `ast:acquire:{id}`).
     *
     * Dipakai seeder & data awal agar subledger buku selalu sama dengan
     * ledger modul Asset; aset legacy (source_type=legacy_backfill) sengaja
     * TIDAK dinaikkan — biayanya sudah tercatat di modul asal.
     */
    public function ensureCapitalized(Asset $asset): bool
    {
        $total = (int) $asset->acquisition_cost_idr + (int) $asset->landed_cost_idr;

        if ($total <= 0 || $asset->source_type === 'legacy_backfill') {
            return false;
        }

        $exists = LedgerTransaction::query()
            ->where('idempotency_key', 'ast:acquire:'.$asset->id)
            ->exists();

        if ($exists) {
            return false;
        }

        return (bool) DB::transaction(function () use ($asset) {
            $this->postAcquisition($asset, 'direct', null);

            return true;
        });
    }

    /**
     * Posting ledger akuisisi: debit `ast:fixed_assets`, kredit sumber.
     */
    private function postAcquisition(Asset $asset, string $sourceType, ?int $actorId): void
    {
        $this->ensureAccounts();
        $total = (int) $asset->acquisition_cost_idr + (int) $asset->landed_cost_idr;

        $creditCode = match ($sourceType) {
            'purchase' => 'ap:asset_purchase:IDR',
            'construction' => 'ap:cip:IDR',
            default => 'clearing:external:IDR',
        };

        // Buat akun kredit bila belum ada (dengan jenis yang sesuai).
        LedgerAccount::firstOrCreate(
            ['code' => $creditCode, 'asset_code' => 'IDR'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => strtoupper($creditCode),
                'kind' => ($sourceType === 'direct' ? AccountKind::CLEARING : AccountKind::AP)->value,
                'allow_negative' => true,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );

        $this->ledger->post(new PostingDTO(
            type: TransactionType::ASSET_ACQUISITION->value,
            description: "Akuisisi aset {$asset->asset_number} — {$asset->name}",
            idempotencyKey: 'ast:acquire:'.$asset->id,
            entries: [
                PostingEntryDTO::forCode(self::ACCT_FIXED_ASSETS, 'IDR', BigDecimal::of($total)),
                PostingEntryDTO::forCode($creditCode, 'IDR', BigDecimal::of($total)->negated()),
            ],
            referenceType: Asset::class,
            referenceId: $asset->id,
            meta: [
                'asset_number' => $asset->asset_number,
                'acquisition_cost_idr' => $asset->acquisition_cost_idr,
                'landed_cost_idr' => $asset->landed_cost_idr,
                'source_type' => $sourceType,
            ],
            createdBy: $actorId,
            postedAt: now(),
        ));
    }

    // ── 30.4 Riwayat hash-chain ────────────────────────────────────────

    /**
     * Catat satu event aset ke rantai append-only (SHA-256 berantai).
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordEvent(
        Asset $asset,
        AssetEventType $type,
        array $payload,
        ?string $actorName = null,
        ?string $occurredAtIso = null
    ): AssetEvent {
        return DB::transaction(function () use ($asset, $type, $payload, $actorName, $occurredAtIso) {
            $latest = AssetEvent::query()
                ->where('asset_id', $asset->id)
                ->orderByDesc('sequence')
                ->lockForUpdate()
                ->first();

            $sequence = ($latest?->sequence ?? 0) + 1;
            $prevHash = $latest?->hash ?? self::GENESIS_HASH;
            $occurred = $occurredAtIso ?? now()->toIso8601String();
            $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);

            $hash = AssetEvent::calculateHash($prevHash, $sequence, $type->value, $payloadJson, $occurred);

            return AssetEvent::create([
                'asset_id' => $asset->id,
                'sequence' => $sequence,
                'event_type' => $type,
                'payload' => $payload,
                'prev_hash' => $prevHash,
                'hash' => $hash,
                'created_by_name' => $actorName,
                'occurred_at' => $occurred,
            ]);
        });
    }

    /**
     * Verifikasi rantai hash seluruh event aset (atau satu aset).
     *
     * @return array{checked: int, valid: bool, broken: array<int, array{asset: string, sequence: int, message: string}>}
     */
    public function verifyChain(?string $assetId = null): array
    {
        $query = AssetEvent::query()->orderBy('asset_id')->orderBy('sequence');
        if ($assetId !== null) {
            $query->where('asset_id', $assetId);
        }

        $events = $query->get()->groupBy('asset_id');
        $broken = [];
        $checked = 0;

        foreach ($events as $assetIdKey => $rows) {
            $expectedPrev = self::GENESIS_HASH;
            $expectedSeq = 1;

            foreach ($rows as $event) {
                $checked++;

                if ($event->sequence !== $expectedSeq) {
                    $broken[] = [
                        'asset' => $assetIdKey,
                        'sequence' => $event->sequence,
                        'message' => "Urutan rusak: diharapkan {$expectedSeq}",
                    ];
                    break;
                }

                if ($event->prev_hash !== $expectedPrev) {
                    $broken[] = [
                        'asset' => $assetIdKey,
                        'sequence' => $event->sequence,
                        'message' => 'prev_hash tidak cocok dengan event sebelumnya',
                    ];
                    break;
                }

                $payloadJson = json_encode($event->payload, JSON_UNESCAPED_SLASHES);
                $expectedHash = AssetEvent::calculateHash(
                    $event->prev_hash,
                    $event->sequence,
                    $event->event_type->value,
                    $payloadJson,
                    $event->occurred_at->toIso8601String()
                );

                if (! hash_equals($expectedHash, $event->hash)) {
                    $broken[] = [
                        'asset' => $assetIdKey,
                        'sequence' => $event->sequence,
                        'message' => 'Hash digest tidak cocok (data dimanipulasi)',
                    ];
                    break;
                }

                $expectedPrev = $event->hash;
                $expectedSeq++;
            }
        }

        return [
            'checked' => $checked,
            'valid' => $broken === [],
            'broken' => $broken,
        ];
    }

    // ── 30.5 Mutasi aset antar lokasi (approval) ────────────────────────

    /**
     * Ajukan mutasi aset ke lokasi lain; bila butuh approval → terbitkan
     * permintaan ApprovalEngine, jika tidak → eksekusi langsung.
     *
     * @return array{status: string, approval?: object, event?: AssetEvent}
     */
    public function requestMove(Asset $asset, int $targetLocationId, ?User $actor, bool $requiresApproval = true): array
    {
        $target = AssetLocation::query()->findOrFail($targetLocationId);

        if ($asset->location_id === $target->id) {
            throw new InvalidArgumentException('Lokasi tujuan sama dengan lokasi asal.');
        }

        if ($asset->status === AssetStatus::Disposed) {
            throw new InvalidArgumentException('Aset berstatus disposal tidak dapat dimutasi.');
        }

        if ($requiresApproval && $actor !== null) {
            $approval = $this->approvals->submit(
                approvalType: 'ASSET_MOVE',
                title: "Mutasi Aset {$asset->asset_number} → {$target->name}",
                creator: $actor,
                approvable: $asset,
                amount: null,
                steps: [['role' => 'admin']],
                slaHours: 48,
                metadata: [
                    'asset_number' => $asset->asset_number,
                    'from_location_id' => $asset->location_id,
                    'to_location_id' => $target->id,
                ]
            );

            return ['status' => 'pending', 'approval' => $approval];
        }

        return ['status' => 'moved', 'event' => $this->executeMove($asset, $target, $actor?->name)];
    }

    /**
     * Eksekusi mutasi (dipanggil setelah approval, atau saat approval tidak wajib).
     */
    public function executeMove(Asset $asset, AssetLocation $target, ?string $actorName = null): AssetEvent
    {
        return DB::transaction(function () use ($asset, $target, $actorName) {
            /** @var Asset $locked */
            $locked = Asset::query()->lockForUpdate()->findOrFail($asset->getKey());

            $from = $locked->location_id;
            $locked->location_id = $target->id;
            $locked->save();

            return $this->recordEvent($locked, AssetEventType::Move, [
                'from_location_id' => $from,
                'to_location_id' => $target->id,
                'to_location_code' => $target->code,
            ], $actorName);
        });
    }

    // ── 30.7/30.8 Operasional ───────────────────────────────────────────

    /**
     * Catat hasil stok opname satu aset (scan QR).
     *
     * @param  string  $result  found|missing|unexpected
     */
    public function recordStocktake(Asset $asset, int $cycleId, string $result, ?User $scanner, ?string $note = null): AssetStocktake
    {
        if (! in_array($result, ['found', 'missing', 'unexpected'], true)) {
            throw new InvalidArgumentException('Hasil opname harus found, missing, atau unexpected.');
        }

        return DB::transaction(function () use ($asset, $cycleId, $result, $scanner, $note) {
            $existing = AssetStocktake::query()
                ->where('asset_id', $asset->id)
                ->where('cycle_id', $cycleId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing; // idempoten: scan ganda dalam satu siklus
            }

            $stocktake = AssetStocktake::create([
                'asset_id' => $asset->id,
                'cycle_id' => $cycleId,
                'result' => $result,
                'scanned_by_user_id' => $scanner?->id,
                'note' => $note,
                'adjustment_status' => $result === 'found' ? 'none' : 'pending',
            ]);

            $this->recordEvent($asset, AssetEventType::Stocktake, [
                'cycle_id' => $cycleId,
                'result' => $result,
                'note' => $note,
            ], $scanner?->name);

            return $stocktake;
        });
    }

    /**
     * Check-out aset ke pengguna.
     */
    public function checkOut(Asset $asset, User $to, User $issuer, ?string $purpose = null, ?string $conditionOut = null): AssetAssignment
    {
        return DB::transaction(function () use ($asset, $to, $issuer, $purpose, $conditionOut) {
            $open = AssetAssignment::query()
                ->where('asset_id', $asset->id)
                ->where('status', 'out')
                ->lockForUpdate()
                ->exists();

            if ($open) {
                throw new InvalidArgumentException('Aset masih dalam peminjaman aktif. Kembalikan terlebih dahulu.');
            }

            $assignment = AssetAssignment::create([
                'asset_id' => $asset->id,
                'assigned_to_user_id' => $to->id,
                'issued_by_user_id' => $issuer->id,
                'checked_out_at' => now(),
                'purpose' => $purpose,
                'condition_out' => $conditionOut,
                'status' => 'out',
            ]);

            $this->recordEvent($asset, AssetEventType::Assignment, [
                'action' => 'check_out',
                'user_id' => $to->id,
                'purpose' => $purpose,
            ], $issuer->name);

            return $assignment;
        });
    }

    /**
     * Check-in (pengembalian) aset.
     */
    public function checkIn(AssetAssignment $assignment, ?string $conditionIn = null): AssetAssignment
    {
        return DB::transaction(function () use ($assignment, $conditionIn) {
            /** @var AssetAssignment $locked */
            $locked = AssetAssignment::query()->lockForUpdate()->findOrFail($assignment->getKey());

            if ($locked->status !== 'out') {
                throw new InvalidArgumentException('Penugasan ini sudah ditutup.');
            }

            $locked->checked_in_at = now();
            $locked->condition_in = $conditionIn;
            $locked->status = 'returned';
            $locked->save();

            $this->recordEvent($locked->asset, AssetEventType::Assignment, [
                'action' => 'check_in',
                'assignment_id' => $locked->id,
                'condition_in' => $conditionIn,
            ]);

            return $locked;
        });
    }

    /**
     * Daftarkan polis asuransi aset.
     */
    public function addInsurance(
        Asset $asset,
        string $policyNumber,
        string $provider,
        int $coverageAmountIdr,
        int $annualPremiumIdr,
        string $startDate,
        string $endDate
    ): AssetInsurance {
        return DB::transaction(function () use (
            $asset, $policyNumber, $provider, $coverageAmountIdr, $annualPremiumIdr, $startDate, $endDate
        ) {
            $insurance = AssetInsurance::create([
                'asset_id' => $asset->id,
                'policy_number' => $policyNumber,
                'provider' => $provider,
                'coverage_amount_idr' => $coverageAmountIdr,
                'annual_premium_idr' => $annualPremiumIdr,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 'active',
            ]);

            $this->recordEvent($asset, AssetEventType::Insurance, [
                'policy_number' => $policyNumber,
                'provider' => $provider,
                'coverage_amount_idr' => $coverageAmountIdr,
            ]);

            return $insurance;
        });
    }

    /**
     * Simpan foto/dokumen aset melalui Core DocumentStore (checksum + retensi).
     *
     * @return object dokumen tersimpan
     */
    public function addDocument(Asset $asset, UploadedFile|string $file, string $filename, ?User $uploader = null): object
    {
        return $this->documents->store(
            file: $file,
            filename: $filename,
            documentType: 'ASSET_PHOTO',
            documentable: $asset,
            uploadedBy: $uploader,
            metadata: ['asset_number' => $asset->asset_number],
            retentionYears: 7,
        );
    }
}
