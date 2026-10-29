<?php

declare(strict_types=1);

namespace Tests\Perilaku\Uang;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use Throwable;

#[Group('perilaku-uang')]
class AutoServeMoneyPathTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.sqlite.database' => database_path('database.sqlite')]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
    }

    public function test_autoserve_backorder_ledger_posting_balance_and_idempotency(): void
    {
        $ledger = app(Ledger::class);
        $nominal = 1_250_000;
        $orderUuid = (string) Str::uuid();
        $idempotencyKey = 'backorder_'.$orderUuid;

        $operator = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin']);

        // Posting alur pembelian backorder AutoServe
        $dto = new PostingDTO(
            type: TransactionType::PAYMENT->value,
            description: "Uji Pembelian sparepart backorder nominal Rp {$nominal}",
            idempotencyKey: $idempotencyKey,
            entries: [
                PostingEntryDTO::forCode('expense:autoserve:parts:IDR', 'IDR', -$nominal),
                PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', $nominal),
            ],
            referenceType: 'store_order',
            referenceId: 999991,
            meta: ['test' => true],
            createdBy: $operator->id,
            postedAt: now(),
        );

        $tx = $ledger->post($dto);

        // Assert transaksi ledger terbentuk
        $this->assertNotNull($tx);
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

        // Idempotensi: Panggil 2x dengan request identik -> transaksi lama dikembalikan
        $txSecond = $ledger->post($dto);
        $this->assertEquals($tx->id, $txSecond->id, 'Replay dengan idempotencyKey identik mengembalikan transaksi sama');

        // Dua permintaan berbeda dengan nominal sama -> dua posting terpisah
        $dtoOther = new PostingDTO(
            type: TransactionType::PAYMENT->value,
            description: "Uji Pembelian kedua nominal Rp {$nominal}",
            idempotencyKey: 'backorder_'.(string) Str::uuid(),
            entries: [
                PostingEntryDTO::forCode('expense:autoserve:parts:IDR', 'IDR', -$nominal),
                PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', $nominal),
            ],
            referenceType: 'store_order',
            referenceId: 999992,
            meta: ['test' => true],
            createdBy: $operator->id,
            postedAt: now(),
        );
        $txThird = $ledger->post($dtoOther);
        $this->assertNotEquals($tx->id, $txThird->id, 'Dua permintaan berbeda harus menghasilkan posting terpisah');
    }

    public function test_autoserve_boundary_values(): void
    {
        $ledger = app(Ledger::class);
        $operator = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin']);

        // Posting tidak seimbang harus ditolak
        $unbalancedRejected = false;
        try {
            $ledger->post(new PostingDTO(
                type: TransactionType::PAYMENT->value,
                description: 'Unbalanced test',
                idempotencyKey: 'unbalanced_'.(string) Str::uuid(),
                entries: [
                    PostingEntryDTO::forCode('expense:autoserve:parts:IDR', 'IDR', -100_000),
                    PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', 50_000), // Tidak seimbang!
                ],
                createdBy: $operator->id,
                postedAt: now(),
            ));
        } catch (Throwable) {
            $unbalancedRejected = true;
        }

        $this->assertTrue($unbalancedRejected, 'Posting tidak seimbang wajib ditolak oleh ledger engine');
    }
}
