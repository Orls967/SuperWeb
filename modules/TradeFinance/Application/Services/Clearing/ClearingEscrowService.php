<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Application\Services\Clearing;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Core\Contracts\ClockInterface;
use Modules\Crypto\Contracts\PriceFeed;
use Modules\Logistics\Contracts\CustodyVerifier;
use Modules\TradeFinance\Domain\Exceptions\IdempotencyConflictException;
use Modules\TradeFinance\Domain\Models\Clearing\ClearingEscrow;
use Modules\TradeFinance\Domain\Models\LetterOfCredit;
use Modules\TradeFinance\Ledger\ClearingSubjectAccounts;
use Modules\TradeFinance\Ledger\TradeFinanceTransactionTypes;
use RuntimeException;

/**
 * D83-1 Clearing house stablecoin: escrow USDT per L/C (micro-unit integer).
 *
 * Setoran : debit wallet:user:{importir}:USDT → kredit tf:escrow:{lc}:USDT
 * Rilis   : debit tf:escrow:{lc}:USDT → kredit wallet:user:{eksportir}:USDT, hanya bila K6 memverifikasi
 *           hash BL/POD DAN rantai custody valid; sekali per (L/C, hash dokumen).
 * Revaluasi: selisih (kurs rilis − kurs setoran) × USDT disimpan ke trs:fx_gain_loss:IDR vs tf:fx_position:IDR.
 *
 * Kompleksitas: O(1) per aksi (satu lock baris + paling banyak tiga posting ledger); verifikasi custody O(n) event satu pengiriman.
 */
final class ClearingEscrowService
{
    private const MICRO = 1_000_000;

    private const OPEN_LC_STATUSES = ['issued', 'advised', 'amended', 'presented', 'accepted'];

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly PriceFeed $priceFeed,
        private readonly CustodyVerifier $custody,
        private readonly ClockInterface $clock,
    ) {}

    public function deposit(
        string $letterOfCreditId,
        int $importerUserId,
        int $exporterUserId,
        int $usdtMicro,
        string $idempotencyKey
    ): ClearingEscrow {
        $key = $this->requireKey($idempotencyKey);

        if ($usdtMicro <= 0) {
            throw new InvalidArgumentException('Nominal USDT (micro-unit) harus bilangan bulat positif.');
        }
        if ($importerUserId === $exporterUserId) {
            throw new InvalidArgumentException('Importir dan eksportir tidak boleh pihak yang sama.');
        }

        return DB::transaction(function () use ($letterOfCreditId, $importerUserId, $exporterUserId, $usdtMicro, $key) {
            $existing = ClearingEscrow::where('deposit_idempotency_key', $key)->first();
            if ($existing !== null) {
                $same = $existing->letter_of_credit_id === $letterOfCreditId
                    && (int) $existing->importer_user_id === $importerUserId
                    && (int) $existing->exporter_user_id === $exporterUserId
                    && (int) $existing->usdt_micro === $usdtMicro;
                if (! $same) {
                    throw IdempotencyConflictException::forKey($key);
                }

                return $existing;
            }

            $lc = LetterOfCredit::query()->lockForUpdate()->findOrFail($letterOfCreditId);
            if (! in_array($lc->status, self::OPEN_LC_STATUSES, true)) {
                throw new RuntimeException("L/C berstatus '{$lc->status}' tidak dapat didanai escrow.");
            }
            if (ClearingEscrow::where('letter_of_credit_id', $lc->id)->exists()) {
                throw new RuntimeException('L/C ini sudah memiliki escrow clearing.');
            }

            $rate = $this->currentRateIdr();
            $walletImporter = ClearingSubjectAccounts::usdtWallet($importerUserId);
            $escrowAccount = ClearingSubjectAccounts::escrow($lc->id);

            $this->ledger->post(new PostingDTO(
                type: TradeFinanceTransactionTypes::CLEARING_USDT_DEPOSIT->value,
                description: "Setoran escrow USDT L/C {$lc->lc_number}",
                idempotencyKey: "tf:clearing:deposit:{$lc->id}",
                entries: Posting::lines()
                    ->debit($walletImporter, $usdtMicro, 'USDT')
                    ->credit($escrowAccount, $usdtMicro, 'USDT')
                    ->toEntries('USDT'),
                referenceType: 'TF_CLEARING_ESCROW',
                referenceId: $lc->id,
            ));

            $entropy = hash('sha256', "{$lc->id}:{$importerUserId}:{$exporterUserId}:{$usdtMicro}");

            return ClearingEscrow::create([
                'escrow_code' => 'CLR-'.strtoupper(substr($entropy, 0, 12)),
                'letter_of_credit_id' => $lc->id,
                'importer_user_id' => $importerUserId,
                'exporter_user_id' => $exporterUserId,
                'usdt_micro' => $usdtMicro,
                'deposit_fx_rate_idr' => $rate,
                'status' => ClearingEscrow::STATUS_DEPOSITED,
                'deposit_idempotency_key' => $key,
                'deposited_at' => $this->clock->now(),
            ]);
        });
    }

    public function release(
        ClearingEscrow $escrow,
        string $documentType,
        string $documentHash,
        string $idempotencyKey,
        int $actorUserId
    ): ClearingEscrow {
        $key = $this->requireKey($idempotencyKey);
        $documentType = strtoupper(trim($documentType));
        $documentHash = strtolower(trim($documentHash));

        if (! in_array($documentType, ['BL', 'POD'], true)) {
            throw new InvalidArgumentException("Tipe dokumen '{$documentType}' tidak valid (BL atau POD).");
        }
        if (! preg_match('/^[0-9a-f]{64}$/', $documentHash)) {
            throw new InvalidArgumentException('Hash dokumen harus SHA-256 hex 64 karakter.');
        }

        return DB::transaction(function () use ($escrow, $documentType, $documentHash, $key, $actorUserId) {
            $locked = ClearingEscrow::query()->lockForUpdate()->findOrFail($escrow->id);

            if ($locked->status === ClearingEscrow::STATUS_RELEASED) {
                if ($locked->release_idempotency_key === $key) {
                    if ($locked->release_document_hash !== $documentHash) {
                        throw IdempotencyConflictException::forKey($key);
                    }

                    return $locked;
                }
                throw new RuntimeException('Escrow sudah dirilis; rilis ganda ditolak.');
            }

            // Hash dokumen yang sama tidak boleh melepas escrow L/C lain.
            if (ClearingEscrow::where('release_document_hash', $documentHash)->exists()) {
                throw new RuntimeException('Hash dokumen ini sudah dipakai untuk rilis escrow lain.');
            }

            $verified = $this->custody->verifyDocumentHash($documentType, $documentHash);
            if ($verified === null) {
                throw new RuntimeException("Hash {$documentType} tidak ditemukan di rantai custody Logistics; rilis ditolak.");
            }
            if (! $verified['chain_valid']) {
                throw new RuntimeException('Rantai custody pengiriman tidak valid (terindikasi dimanipulasi); rilis ditolak.');
            }

            $releaseRate = $this->currentRateIdr();
            $delta = $this->fxRevaluationIdr((int) $locked->usdt_micro, (int) $locked->deposit_fx_rate_idr, $releaseRate);

            $exporterWallet = ClearingSubjectAccounts::usdtWallet((int) $locked->exporter_user_id);
            $escrowAccount = ClearingSubjectAccounts::escrow($locked->letter_of_credit_id);

            $this->ledger->post(new PostingDTO(
                type: TradeFinanceTransactionTypes::CLEARING_USDT_RELEASE->value,
                description: "Rilis escrow USDT {$locked->escrow_code} setelah verifikasi {$documentType}",
                idempotencyKey: "tf:clearing:release:{$locked->letter_of_credit_id}:{$documentHash}",
                entries: Posting::lines()
                    ->debit($escrowAccount, (int) $locked->usdt_micro, 'USDT')
                    ->credit($exporterWallet, (int) $locked->usdt_micro, 'USDT')
                    ->toEntries('USDT'),
                referenceType: 'TF_CLEARING_ESCROW',
                referenceId: $locked->letter_of_credit_id,
            ));

            if ($delta !== 0) {
                $magnitude = abs($delta);
                $lines = Posting::lines();
                if ($delta > 0) {
                    $lines->debit('tf:fx_position:IDR', $magnitude, 'IDR')->credit('trs:fx_gain_loss:IDR', $magnitude, 'IDR');
                } else {
                    $lines->debit('trs:fx_gain_loss:IDR', $magnitude, 'IDR')->credit('tf:fx_position:IDR', $magnitude, 'IDR');
                }

                $this->ledger->post(new PostingDTO(
                    type: TradeFinanceTransactionTypes::CLEARING_FX_REVAL->value,
                    description: "Revaluasi kurs IDR/USDT escrow {$locked->escrow_code}",
                    idempotencyKey: "tf:clearing:fxreval:{$locked->letter_of_credit_id}:{$documentHash}",
                    entries: $lines->toEntries('IDR'),
                    referenceType: 'TF_CLEARING_ESCROW',
                    referenceId: $locked->letter_of_credit_id,
                ));
            }

            $locked->update([
                'status' => ClearingEscrow::STATUS_RELEASED,
                'release_document_type' => $documentType,
                'release_document_hash' => $documentHash,
                'custody_shipment_ref' => (string) $verified['shipment_id'],
                'custody_event_ref' => (string) $verified['custody_event_id'],
                'release_fx_rate_idr' => $releaseRate,
                'fx_revaluation_idr' => $delta,
                'released_by_user_id' => $actorUserId,
                'release_idempotency_key' => $key,
                'released_at' => $this->clock->now(),
            ]);

            return $locked;
        });
    }

    /**
     * On-ramp USDT tersimulasi (S3): mengisi dompet USDT pengguna dari akun sumber likuiditas clearing.
     * Idempoten per key; dipakai seeder T1 dan pendanaan awal importir pada simulasi.
     */
    public function simulateOnRamp(int $userId, int $usdtMicro, string $idempotencyKey): void
    {
        $key = $this->requireKey($idempotencyKey);
        if ($usdtMicro <= 0) {
            throw new InvalidArgumentException('Nominal USDT (micro-unit) harus bilangan bulat positif.');
        }

        $wallet = ClearingSubjectAccounts::usdtWallet($userId);

        $this->ledger->post(new PostingDTO(
            type: TradeFinanceTransactionTypes::CLEARING_USDT_FUNDING->value,
            description: "On-ramp USDT tersimulasi (S3) untuk pengguna #{$userId}",
            idempotencyKey: "tf:clearing:onramp:{$userId}:{$key}",
            entries: Posting::lines()
                ->debit('tf:usdt_liquidity_source:USDT', $usdtMicro, 'USDT')
                ->credit($wallet, $usdtMicro, 'USDT')
                ->toEntries('USDT'),
            referenceType: 'TF_CLEARING_ONRAMP',
            referenceId: (string) $userId,
        ));
    }

    /**
     * Selisih nilai IDR USDT yang ditahan: micro × (kurs_rilis − kurs_setoran) / 1.000.000, HalfUp.
     */
    public function fxRevaluationIdr(int $usdtMicro, int $depositRate, int $releaseRate): int
    {
        return BigDecimal::of($usdtMicro)
            ->multipliedBy($releaseRate - $depositRate)
            ->dividedBy(self::MICRO, 0, RoundingMode::HalfUp)
            ->toInt();
    }

    /** @return array{total:int, deposited:int, released:int, escrow_usdt_micro:int} */
    public function summary(?int $userId = null): array
    {
        $query = ClearingEscrow::query();
        if ($userId !== null) {
            $query->where(fn ($q) => $q->where('importer_user_id', $userId)->orWhere('exporter_user_id', $userId));
        }
        $rows = $query->orderBy('id')->get();

        return [
            'total' => $rows->count(),
            'deposited' => $rows->where('status', ClearingEscrow::STATUS_DEPOSITED)->count(),
            'released' => $rows->where('status', ClearingEscrow::STATUS_RELEASED)->count(),
            'escrow_usdt_micro' => (int) $rows->where('status', ClearingEscrow::STATUS_DEPOSITED)->sum('usdt_micro'),
        ];
    }

    /** @return Collection<int, ClearingEscrow> */
    public function listFor(?int $userId, int $limit = 100)
    {
        $query = ClearingEscrow::query()->orderByDesc('id')->limit($limit);
        if ($userId !== null) {
            $query->where(fn ($q) => $q->where('importer_user_id', $userId)->orWhere('exporter_user_id', $userId));
        }

        return $query->get();
    }

    /** Kurs IDR per 1 USDT dari PriceFeed Crypto; ditolak bila tidak tersedia (tidak ada default diam-diam). */
    private function currentRateIdr(): int
    {
        $rate = $this->priceFeed->currentPrice('USDT')->toScale(0, RoundingMode::HalfUp)->toInt();
        if ($rate <= 0) {
            throw new RuntimeException('Kurs IDR/USDT tidak tersedia dari PriceFeed.');
        }

        return $rate;
    }

    private function requireKey(string $key): string
    {
        $key = trim($key);
        if ($key === '' || strlen($key) > 64) {
            throw new InvalidArgumentException('Idempotency-Key wajib (1–64 karakter).');
        }

        return $key;
    }
}
