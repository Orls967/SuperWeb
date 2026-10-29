<?php

declare(strict_types=1);

namespace Tests\Audit;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Application\Actions\TopUpAction;
use Tests\TestCase;

/**
 * Test pembukti forensic audit 2026-10-30:
 * Membuktikan bug default key TopUpAction `banking:topup:{user}:{amount}` di master.
 *
 * @group audit-2026-10-30
 */
class TopUpSameAmountTwiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    /**
     * Membuktikan bahwa transaksi top-up tanpa key ditolak dengan InvalidArgumentException (K-3),
     * sehingga menghilangkan risiko tabrakan kunci default lama banking:topup:{user}:{amount}.
     */
    public function test_top_up_without_idempotency_key_throws_invalid_argument_exception(): void
    {
        $user = User::factory()->create();
        $action = app(TopUpAction::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Idempotency key wajib diisi');

        $action->execute($user, 100_000);
    }

    /**
     * Membuktikan bahwa dengan explicit request key yang benar (K-3),
     * dua permintaan berbeda nominal sama menghasilkan 2 transaksi & saldo 200.000,
     * sedangkan retry permintaan sama menghasilkan 1 transaksi.
     */
    public function test_explicit_keys_distinguish_distinct_requests_and_deduplicate_retries(): void
    {
        $user = User::factory()->create();
        $action = app(TopUpAction::class);

        // Permintaan A (ID request unik: req-001)
        $txA1 = $action->execute($user, 100_000, 'req-topup-001');

        // Retry permintaan A (ID request sama: req-001)
        $txA2 = $action->execute($user, 100_000, 'req-topup-001');
        $this->assertSame($txA1->id, $txA2->id, 'Retry permintaan sama harus menghasilkan transaksi yang sama (idempoten)');

        // Permintaan B yang berbeda tapi nominal sama (ID request unik: req-002)
        $txB = $action->execute($user, 100_000, 'req-topup-002');
        $this->assertNotSame($txA1->id, $txB->id, 'Dua permintaan berbeda nominal sama harus menghasilkan 2 transaksi berbeda');

        $wallet = $user->walletAccount('IDR');
        $this->assertSame(200_000, (int) $wallet->cached_balance, 'Saldo harus 200.000 setelah dua top up sah');
    }
}
