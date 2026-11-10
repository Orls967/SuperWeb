<?php

declare(strict_types=1);

namespace Tests\Acceptance\Treasury;

use Modules\Treasury\Domain\Algorithms\BankReconciliationMatcher;
use Modules\Treasury\Domain\Algorithms\Certificates\BankReconciliationCertificateVerifier;
use Modules\Treasury\Domain\DTOs\BankStatementLineDTO;
use Modules\Treasury\Domain\DTOs\BookLedgerLineDTO;
use Modules\Treasury\Domain\Exceptions\InvalidCertificateException;
use Tests\TestCase;

class BankReconciliationAlgorithmVectorTest extends TestCase
{
    public function test_bank_reconciliation_matcher_matches_all_prompt_vectors(): void
    {
        $matcher = new BankReconciliationMatcher;

        $bankLines = [
            new BankStatementLineDTO(id: 'B1', day: 1, amount: 1_000_000, reference: 'INV-1'),
            new BankStatementLineDTO(id: 'B2', day: 2, amount: 2_500_000, reference: ''),
            new BankStatementLineDTO(id: 'B3', day: 3, amount: 750_500, reference: 'INV-9'),
            new BankStatementLineDTO(id: 'B4', day: 5, amount: 3_000_000, reference: 'PAY-7'),
            new BankStatementLineDTO(id: 'B5', day: 6, amount: 99_999, reference: 'X'),
        ];

        $bookLines = [
            new BookLedgerLineDTO(id: 'L1', day: 1, amount: 1_000_000, reference: 'INV-1'),
            new BookLedgerLineDTO(id: 'L2', day: 2, amount: 2_500_400, reference: ''),
            new BookLedgerLineDTO(id: 'L3', day: 4, amount: 750_500, reference: 'INV-9-A'),
            new BookLedgerLineDTO(id: 'L4', day: 4, amount: 1_200_000, reference: '-'),
            new BookLedgerLineDTO(id: 'L5', day: 5, amount: 1_000_000, reference: '-'),
            new BookLedgerLineDTO(id: 'L6', day: 5, amount: 800_000, reference: '-'),
            new BookLedgerLineDTO(id: 'L7', day: 6, amount: 1_000_000, reference: '-'),
        ];

        $result = $matcher->match($bankLines, $bookLines);

        // Tahap 1: Exact [(B1, L1)]
        $this->assertCount(1, $result->exactMatches);
        $this->assertSame('B1', $result->exactMatches[0]->bankId);
        $this->assertSame('L1', $result->exactMatches[0]->bookId);

        // Tahap 2: Toleransi [(B2, L2, selisih 400), (B3, L3, selisih 0)]
        $this->assertCount(2, $result->toleranceMatches);
        $this->assertSame('B2', $result->toleranceMatches[0]->bankId);
        $this->assertSame('L2', $result->toleranceMatches[0]->bookId);
        $this->assertSame(400, $result->toleranceMatches[0]->discrepancyAmount);

        $this->assertSame('B3', $result->toleranceMatches[1]->bankId);
        $this->assertSame('L3', $result->toleranceMatches[1]->bookId);
        $this->assertSame(0, $result->toleranceMatches[1]->discrepancyAmount);

        // Tahap 3: Many-to-One [(B4, [L4, L5, L6])]
        $this->assertCount(1, $result->manyToOneMatches);
        $this->assertSame('B4', $result->manyToOneMatches[0]->bankId);
        $this->assertSame(['L4', 'L5', 'L6'], $result->manyToOneMatches[0]->bookIds);

        // Bank tak terpadankan: [B5]
        $this->assertCount(1, $result->unmatchedBankLines);
        $this->assertSame('B5', $result->unmatchedBankLines[0]->id);

        // Buku tak terpadankan: [L7]
        $this->assertCount(1, $result->unmatchedBookLines);
        $this->assertSame('L7', $result->unmatchedBookLines[0]->id);

        // Verifikasi sertifikat
        $this->assertTrue(BankReconciliationCertificateVerifier::verify($result->certificate));
    }

    public function test_corrupted_bank_reconciliation_certificate_throws_exception(): void
    {
        $matcher = new BankReconciliationMatcher;
        $result = $matcher->match([], []);

        $corruptCert = $result->certificate;
        $corruptCert->sha256 = 'invalid_sha256_hash';

        $this->expectException(InvalidCertificateException::class);
        BankReconciliationCertificateVerifier::assertValid($corruptCert);
    }
}
