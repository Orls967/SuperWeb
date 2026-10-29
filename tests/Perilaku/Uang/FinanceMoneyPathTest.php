<?php

declare(strict_types=1);

namespace Tests\Perilaku\Uang;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Finance\Application\Services\UniversalCreditEvaluationService;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TestCase;

#[Group('perilaku-uang')]
class FinanceMoneyPathTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.sqlite.database' => database_path('database.sqlite')]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
    }

    public function test_finance_credit_facility_disbursement_balance_and_idempotency(): void
    {
        $service = app(UniversalCreditEvaluationService::class);
        $nominal = 1_250_000;
        $loanId = 'LN-TEST-'.uniqid();
        $borrowerId = 'BORROWER-001';
        $purpose = 'working_capital';

        // a) Jalankan alur bisnis pencairan fasilitas pinjaman
        $result = $service->disburseCreditFacility($loanId, $borrowerId, $nominal, $purpose);

        $this->assertEquals('DISBURSED', $result['status']);
        $this->assertEquals($nominal, $result['disbursed_idr']);

        // b) Assert transaksi ledger yang terbentuk
        $expectedKey = 'loan_disb:'.hash('sha256', $loanId.':'.$borrowerId.':'.$purpose);
        $tx = LedgerTransaction::where('idempotency_key', $expectedKey)->with('entries.account')->first();

        $this->assertNotNull($tx, 'Transaksi ledger pencairan fasilitas kredit harus tercatat');
        $this->assertGreaterThanOrEqual(2, $tx->entries->count());

        // Σ entri = 0 per aset
        $sum = $tx->entries->sum(fn ($e) => (float) $e->amount);
        $this->assertEqualsWithDelta(0.0, $sum, 0.0001, 'Σ entri per aset harus = 0');

        // Akun di COA
        foreach ($tx->entries as $e) {
            $this->assertNotNull($e->account);
            $this->assertNotEmpty($e->account->code);
            $kind = $e->account->kind instanceof AccountKind ? $e->account->kind : AccountKind::tryFrom((string) $e->account->kind);
            if ($kind) {
                $this->assertContains($kind->normalSide(), ['debit', 'credit', 'free']);
            }
        }

        // d) Panggil 2x dengan permintaan identik -> idempoten
        $resultSecond = $service->disburseCreditFacility($loanId, $borrowerId, $nominal, $purpose);
        $this->assertEquals('DISBURSED', $resultSecond['status']);

        $txCount = LedgerTransaction::where('idempotency_key', $expectedKey)->count();
        $this->assertEquals(1, $txCount, 'Replay tidak boleh membuat transaksi ganda');
    }

    public function test_finance_boundary_values_zero_and_negative(): void
    {
        $service = app(UniversalCreditEvaluationService::class);

        // e) Batas nol atau negatif harus ditolak
        $zeroRejected = false;
        try {
            $service->disburseCreditFacility('LN-ZERO', 'BORROWER-ZERO', 0, 'working_capital');
        } catch (RuntimeException $e) {
            $zeroRejected = true;
        }
        $this->assertTrue($zeroRejected, 'Nominal pokok 0 wajib ditolak');

        $negativeRejected = false;
        try {
            $service->disburseCreditFacility('LN-NEG', 'BORROWER-NEG', -500_000, 'working_capital');
        } catch (RuntimeException $e) {
            $negativeRejected = true;
        }
        $this->assertTrue($negativeRejected, 'Nominal pokok negatif wajib ditolak');
    }
}
