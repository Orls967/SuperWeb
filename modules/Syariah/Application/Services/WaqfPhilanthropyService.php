<?php

declare(strict_types=1);

namespace Modules\Syariah\Application\Services;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Support\Posting;
use Modules\Core\Contracts\ClockInterface;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Syariah\Domain\Enums\WaqfType;
use Modules\Syariah\Domain\Models\SybGaplessCertificate;
use Modules\Syariah\Domain\Models\SybWaqfDistribution;
use Modules\Syariah\Domain\Models\SybWaqfPrincipal;
use Modules\Syariah\Ledger\SyariahSubjectAccounts;
use Modules\Syariah\Ledger\SyariahTransactionTypes;

final class WaqfPhilanthropyService
{
    public function __construct(
        private readonly ShariahLedgerPoster $poster,
        private readonly ClockInterface $clock,
        private readonly DocumentNumberingInterface $documentNumbering
    ) {}

    /**
     * Penerimaan wakaf uang dengan jaminan pokok abadi (D162-5).
     */
    public function receiveCashWaqf(array $data, User $actor): SybWaqfPrincipal
    {
        $key = trim((string) ($data['idempotency_key'] ?? ''));
        if ($key === '') {
            throw new InvalidArgumentException('Idempotency key wajib diisi.');
        }

        $existing = SybWaqfPrincipal::query()->where('idempotency_key', $key)->first();
        if ($existing !== null) {
            return $existing;
        }

        $wakifUserId = (int) $data['wakif_user_id'];
        $principalAmount = (int) $data['principal_amount_idr'];
        $purpose = trim((string) $data['purpose']);

        if ($principalAmount <= 0) {
            throw new InvalidArgumentException('Nominal pokok wakaf uang harus positif.');
        }

        return DB::transaction(function () use ($wakifUserId, $principalAmount, $purpose, $key) {
            $year = $this->clock->now()->year;
            $certNumber = $this->documentNumbering->nextNumber(
                entityCode: 'SYB',
                documentType: 'WQF',
                resetMonthly: false,
                customPrefix: "WQF/{$year}/"
            );

            SybGaplessCertificate::create([
                'certificate_number' => $certNumber,
                'certificate_type' => 'WAKIF_RECEIPT',
                'recipient_user_id' => $wakifUserId,
                'amount_idr' => $principalAmount,
                'hash_signature' => hash('sha256', "WQF:{$wakifUserId}:{$principalAmount}:{$certNumber}"),
                'issued_at' => $this->clock->now(),
            ]);

            $wakifWallet = SyariahSubjectAccounts::customerWallet($wakifUserId);

            // Double-entry posting: Debit wallet:user:{wakif}:IDR, Kredit syb:waqf_principal:IDR
            $this->poster->post(new PostingDTO(
                type: SyariahTransactionTypes::SYB_WAQF_RECEIPT->value,
                description: "Penerimaan wakaf uang abadi dari wakif #{$wakifUserId} ({$purpose})",
                idempotencyKey: $key,
                entries: Posting::lines()
                    ->debit($wakifWallet, $principalAmount, 'IDR')
                    ->credit('syb:waqf_principal:IDR', $principalAmount, 'IDR')
                    ->toEntries('IDR'),
                referenceType: 'SYB_WAQF_PRINCIPAL',
                referenceId: (string) $wakifUserId,
            ));

            return SybWaqfPrincipal::create([
                'wakif_user_id' => $wakifUserId,
                'waqf_type' => WaqfType::MONEY,
                'principal_amount_idr' => $principalAmount,
                'asset_description' => null,
                'purpose' => $purpose,
                'is_perpetual' => true,
                'certificate_number' => $certNumber,
                'status' => 'ACTIVE',
                'idempotency_key' => $key,
            ]);
        });
    }

    /**
     * Larangan mutlak pengurangan pokok wakaf abadi (D162-5 & Invarian audit).
     */
    public function withdrawPrincipal(int $waqfPrincipalId, int $amountIdr, string $idempotencyKey, User $actor): void
    {
        /** @var SybWaqfPrincipal $principal */
        $principal = SybWaqfPrincipal::findOrFail($waqfPrincipalId);

        if ($principal->is_perpetual) {
            throw new DomainException('PERPETUAL_WAQF_PRINCIPAL_IMMUTABLE: Pokok wakaf abadi dilindungi syariah dan dilarang dikurangi untuk tujuan konsumtif atau penarikan pokok.');
        }
    }

    /**
     * Penyaluran hasil manfaat pengelolaan wakaf ke mauquf alaih / penerima manfaat.
     */
    public function distributeBenefit(
        int $waqfPrincipalId,
        int $benefitAmountIdr,
        string $recipientName,
        string $idempotencyKey,
        User $nazhir
    ): SybWaqfDistribution {
        if ($benefitAmountIdr <= 0) {
            throw new InvalidArgumentException('Nominal penyaluran manfaat wakaf harus positif.');
        }

        return DB::transaction(function () use ($waqfPrincipalId, $benefitAmountIdr, $recipientName, $idempotencyKey) {
            /** @var SybWaqfPrincipal $principal */
            $principal = SybWaqfPrincipal::query()->lockForUpdate()->findOrFail($waqfPrincipalId);

            return SybWaqfDistribution::create([
                'waqf_principal_id' => $principal->id,
                'recipient_party_name' => trim($recipientName),
                'benefit_amount_idr' => $benefitAmountIdr,
                'distribution_date' => $this->clock->now()->toDateString(),
                'idempotency_key' => $idempotencyKey,
            ]);
        });
    }
}

