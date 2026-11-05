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
use Modules\Shared\Domain\Algorithms\Islamic\AsnafDistribution;
use Modules\Shared\Domain\Algorithms\Islamic\ZakatAssessment;
use Modules\Syariah\Domain\Models\SybGaplessCertificate;
use Modules\Syariah\Domain\Models\SybParameter;
use Modules\Syariah\Domain\Models\SybZakatAssessment;
use Modules\Syariah\Domain\Models\SybZakatDistribution;
use Modules\Syariah\Ledger\SyariahSubjectAccounts;
use Modules\Syariah\Ledger\SyariahTransactionTypes;

final class ZakatEngineService
{
    public function __construct(
        private readonly ShariahLedgerPoster $poster,
        private readonly ClockInterface $clock,
        private readonly DocumentNumberingInterface $documentNumbering,
        private readonly ZakatAssessment $zakatAssessment = new ZakatAssessment,
        private readonly AsnafDistribution $asnafDistribution = new AsnafDistribution
    ) {}

    /**
     * Penilaian kewajiban zakat mal per muzakki per haul hijriah (D162-4, B2).
     */
    public function assessHaul(array $data, User $actor): SybZakatAssessment
    {
        $customerId = (int) $data['customer_user_id'];
        $haulYear = (int) $data['haul_year'];

        // Cek keunikan penilaian per haul
        $existing = SybZakatAssessment::query()
            ->where('customer_user_id', $customerId)
            ->where('haul_year', $haulYear)
            ->first();

        if ($existing !== null) {
            throw new DomainException('DUPLICATE_HAUL_ZAKAT_ASSESSMENT: Penilaian zakat untuk muzakki pada haul tersebut sudah tercatat.');
        }

        $key = trim((string) ($data['idempotency_key'] ?? ''));
        if ($key === '') {
            throw new InvalidArgumentException('Idempotency key wajib diisi.');
        }

        $goldParam = SybParameter::getLatestValue('GOLD_PRICE_PER_GRAM_IDR', ['price_idr' => 1_500_000]);
        $goldPrice = (int) ($goldParam['price_idr'] ?? 1_500_000);

        $wealth = (int) ($data['qualifying_wealth_idr'] ?? 0);
        $debts = (int) ($data['due_debts_idr'] ?? 0);
        $days = (int) ($data['holding_days'] ?? 354);

        $assessment = $this->zakatAssessment->assess(
            goldPricePerGramIdr: $goldPrice,
            qualifyingWealthIdr: $wealth,
            dueDebtsIdr: $debts,
            holdingDays: $days
        );

        return DB::transaction(function () use ($data, $customerId, $haulYear, $goldPrice, $wealth, $debts, $days, $assessment, $key) {
            $certNumber = null;
            if ($assessment->isObligated && $assessment->zakatAmountIdr > 0) {
                $certNumber = $this->documentNumbering->nextNumber(
                    entityCode: 'SYB',
                    documentType: 'ZKT',
                    resetMonthly: false,
                    customPrefix: "ZKT/{$haulYear}/"
                );

                SybGaplessCertificate::create([
                    'certificate_number' => $certNumber,
                    'certificate_type' => 'ZAKAT_MAL',
                    'recipient_user_id' => $customerId,
                    'amount_idr' => $assessment->zakatAmountIdr,
                    'hash_signature' => hash('sha256', "ZKT:{$customerId}:{$haulYear}:{$assessment->zakatAmountIdr}"),
                    'issued_at' => $this->clock->now(),
                ]);
            }

            $record = SybZakatAssessment::create([
                'customer_user_id' => $customerId,
                'haul_year' => $haulYear,
                'gold_price_per_gram_idr' => $goldPrice,
                'nisab_idr' => $assessment->nisabIdr,
                'qualifying_wealth_idr' => $wealth,
                'due_debts_idr' => $debts,
                'net_zakat_base_idr' => $assessment->netZakatBaseIdr,
                'holding_days' => $days,
                'haul_fulfilled' => $assessment->haulFulfilled,
                'reaches_nisab' => $assessment->reachesNisab,
                'is_obligated' => $assessment->isObligated,
                'zakat_amount_idr' => $assessment->zakatAmountIdr,
                'is_collected' => false,
                'auto_debit' => (bool) ($data['auto_debit'] ?? false),
                'certificate_number' => $certNumber,
                'idempotency_key' => $key,
            ]);

            // Auto debit pemungutan zakat jika diizinkan muzakki (D162-6)
            if ($record->auto_debit && $record->zakatAmountIdr > 0) {
                $muzakkiWallet = SyariahSubjectAccounts::customerWallet($customerId);

                // Debit wallet:user:{muzakki}:IDR, Kredit syb:zakat_fund:IDR
                $this->poster->post(new PostingDTO(
                    type: SyariahTransactionTypes::SYB_ZAKAT_COLLECTION->value,
                    description: "Pungutan zakat mal haul {$haulYear} muzakki #{$customerId}",
                    idempotencyKey: "syb:zkt:col:{$record->id}:{$key}",
                    entries: Posting::lines()
                        ->debit($muzakkiWallet, $record->zakatAmountIdr, 'IDR')
                        ->credit('syb:zakat_fund:IDR', $record->zakatAmountIdr, 'IDR')
                        ->toEntries('IDR'),
                    referenceType: 'SYB_ZAKAT_ASSESSMENT',
                    referenceId: (string) $record->id,
                ));

                $record->update([
                    'is_collected' => true,
                    'collected_at' => $this->clock->now(),
                ]);
            }

            return $record;
        });
    }

    /**
     * Distribusi dana zakat ke 8 asnaf menggunakan LargestRemainder (D162-4, B3).
     */
    public function distributeAsnaf(
        int $haulYear,
        int $totalAmountIdr,
        string $idempotencyKey,
        User $amil,
        ?array $customWeightsBps = null
    ): array {
        if ($totalAmountIdr <= 0) {
            throw new InvalidArgumentException('Total dana penyaluran zakat harus positif.');
        }

        $weights = $customWeightsBps ?? [
            AsnafDistribution::ASNAF_FAKIR => 2500,
            AsnafDistribution::ASNAF_MISKIN => 2500,
            AsnafDistribution::ASNAF_AMIL => 1250,
            AsnafDistribution::ASNAF_MUALAF => 500,
            AsnafDistribution::ASNAF_RIQAB => 250,
            AsnafDistribution::ASNAF_GHARIMIN => 1000,
            AsnafDistribution::ASNAF_FISABILILLAH => 1000,
            AsnafDistribution::ASNAF_IBNU_SABIL => 1000,
        ];

        $distributionResult = $this->asnafDistribution->distribute($totalAmountIdr, $weights);

        return DB::transaction(function () use ($haulYear, $totalAmountIdr, $distributionResult, $idempotencyKey) {
            $records = [];
            $posting = Posting::lines();
            $posting->debit('syb:zakat_fund:IDR', $totalAmountIdr, 'IDR');

            foreach ($distributionResult->allocations as $asnaf => $amount) {
                if ($amount > 0) {
                    $mustahikWallet = SyariahSubjectAccounts::customerWallet(9900); // Pool wallet per asnaf
                    $posting->credit($mustahikWallet, $amount, 'IDR');

                    $distRecord = SybZakatDistribution::create([
                        'haul_year' => $haulYear,
                        'asnaf' => $asnaf,
                        'allocated_amount_idr' => $amount,
                        'distributed_amount_idr' => $amount,
                        'mustahik_party_ref' => 9900,
                        'idempotency_key' => "syb:zkt:dist:{$haulYear}:{$asnaf}:{$idempotencyKey}",
                    ]);
                    $records[] = $distRecord;
                }
            }

            // Posting double-entry: Debit syb:zakat_fund:IDR, Kredit wallet:user:{mustahik}:IDR
            $this->poster->post(new PostingDTO(
                type: SyariahTransactionTypes::SYB_ZAKAT_DISBURSEMENT->value,
                description: "Penyaluran dana zakat mal haul {$haulYear} ke 8 asnaf",
                idempotencyKey: $idempotencyKey,
                entries: $posting->toEntries('IDR'),
                referenceType: 'SYB_ZAKAT_DISTRIBUTION',
                referenceId: (string) $haulYear,
            ));

            return $records;
        });
    }
}
