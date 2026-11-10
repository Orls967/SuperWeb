<?php

declare(strict_types=1);

namespace Tests\Acceptance\EnterpriseFinance;

use Carbon\CarbonImmutable;
use Modules\EnterpriseFinance\Domain\Algorithms\Certificates\ConsolidatedTrialBalanceCertificate;
use Modules\EnterpriseFinance\Domain\Algorithms\Certificates\ConsolidatedTrialBalanceCertificateVerifier;
use Modules\EnterpriseFinance\Domain\Algorithms\ConsolidatedTrialBalanceAggregator;
use Modules\EnterpriseFinance\Domain\DTOs\AccountBalanceDTO;
use Modules\EnterpriseFinance\Domain\DTOs\ConsolidatedTrialBalanceInputDTO;
use Modules\EnterpriseFinance\Domain\DTOs\EntityTrialBalanceDTO;
use Modules\EnterpriseFinance\Domain\Exceptions\InvalidCertificateException;
use Modules\EnterpriseFinance\Domain\Exceptions\UnbalancedTrialBalanceException;
use Tests\TestCase;

class ConsolidatedTrialBalanceAlgorithmVectorTest extends TestCase
{
    private ConsolidatedTrialBalanceAggregator $aggregator;

    private ConsolidatedTrialBalanceCertificateVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aggregator = new ConsolidatedTrialBalanceAggregator;
        $this->verifier = new ConsolidatedTrialBalanceCertificateVerifier;
    }

    public function test_consolidated_trial_balance_aggregator_matches_all_prompt_vectors(): void
    {
        $timestamp = CarbonImmutable::parse('2026-11-11T07:00:00Z');

        // Vektor 1: Agregasi 3 Entitas Zero-Sum Seimbang Sempurna
        $entity1 = new EntityTrialBalanceDTO(
            entityCode: 'HOLDING',
            entityName: 'PT Nusantara Holding Tbk',
            functionalCurrency: 'IDR',
            accountBalances: [
                new AccountBalanceDTO('1101-CASH', 'Kas dan Bank Holding', 500_000_000, 0),
                new AccountBalanceDTO('3101-CAPITAL', 'Modal Saham Disetor', 0, 500_000_000),
            ]
        );

        $entity2 = new EntityTrialBalanceDTO(
            entityCode: 'SUB_MANUF',
            entityName: 'PT Nusantara Mesin Presisi',
            functionalCurrency: 'IDR',
            accountBalances: [
                new AccountBalanceDTO('1101-CASH', 'Kas Operasional Pabrik', 250_000_000, 0),
                new AccountBalanceDTO('1501-MACHINERY', 'Mesin Produksi', 750_000_000, 0),
                new AccountBalanceDTO('2101-BANK-LOAN', 'Utang Bank Investasi', 0, 1_000_000_000),
            ]
        );

        $entity3 = new EntityTrialBalanceDTO(
            entityCode: 'SUB_DIST',
            entityName: 'PT Distribusi Niaga Sejahtera',
            functionalCurrency: 'IDR',
            accountBalances: [
                new AccountBalanceDTO('1201-AR', 'Piutang Dagang', 300_000_000, 0),
                new AccountBalanceDTO('1301-INVENTORY', 'Persediaan Barang Jadi', 200_000_000, 0),
                new AccountBalanceDTO('2201-AP', 'Utang Dagang Vendor', 0, 500_000_000),
            ]
        );

        $input1 = new ConsolidatedTrialBalanceInputDTO(
            period: '2026-10',
            reportingCurrency: 'IDR',
            entities: [$entity1, $entity2, $entity3]
        );

        $res1 = $this->aggregator->aggregate($input1, $timestamp);

        // Total Debit: 500M + 250M + 750M + 300M + 200M = 2.000.000.000
        // Total Kredit: 500M + 1000M + 500M = 2.000.000.000
        $this->assertSame(2_000_000_000, $res1->totalDebitMinor);
        $this->assertSame(2_000_000_000, $res1->totalCreditMinor);
        $this->assertSame(0, $res1->deltaBalanceMinor);
        $this->assertTrue($res1->isZeroSumBalanced);
        $this->assertSame(3, $res1->totalEntitiesCount);

        // Verifikasi sertifikat kriptografis
        $this->assertTrue($this->verifier->verify($res1->certificate));

        // Vektor 2: Agregasi Tidak Seimbang (Delta != 0) Melempar Exception dalam Mode Strict
        $unbalancedEntity = new EntityTrialBalanceDTO(
            entityCode: 'SUB_CORRUPTED',
            entityName: 'Unit Rusak',
            functionalCurrency: 'IDR',
            accountBalances: [
                new AccountBalanceDTO('1101-CASH', 'Kas Bocor', 100_000_000, 0),
                new AccountBalanceDTO('3101-CAPITAL', 'Modal Pincang', 0, 80_000_000), // Selisih 20 Juta
            ]
        );

        $input2 = new ConsolidatedTrialBalanceInputDTO(
            period: '2026-10',
            reportingCurrency: 'IDR',
            entities: [$unbalancedEntity]
        );

        $this->expectException(UnbalancedTrialBalanceException::class);
        $this->aggregator->aggregate($input2, $timestamp, strict: true);
    }

    public function test_corrupted_trial_balance_certificate_throws_exception(): void
    {
        $entity = new EntityTrialBalanceDTO(
            entityCode: 'HOLDING',
            entityName: 'PT Holding',
            functionalCurrency: 'IDR',
            accountBalances: [
                new AccountBalanceDTO('1101-CASH', 'Kas', 100_000_000, 0),
                new AccountBalanceDTO('3101-CAPITAL', 'Modal', 0, 100_000_000),
            ]
        );

        $input = new ConsolidatedTrialBalanceInputDTO('2026-10', 'IDR', [$entity]);
        $res = $this->aggregator->aggregate($input);

        $corruptedPayload = $res->certificate->payload;
        $corruptedPayload['total_debit_minor'] = 999_999_999;

        $tamperedCert = new ConsolidatedTrialBalanceCertificate(
            payload: $corruptedPayload,
            sha256Signature: $res->certificate->sha256Signature
        );

        $this->expectException(InvalidCertificateException::class);
        $this->verifier->verify($tamperedCert);
    }
}
