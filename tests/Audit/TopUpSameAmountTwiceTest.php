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
     * Membuktikan bahwa dua transaksi top-up sah dengan nominal yang sama
     * gagal tercatat jika tidak menyertakan explicit key, karena default key
     * berbasis nominal `banking:topup:{user}:{amount}` bertabrakan.
     */
    public function test_two_distinct_top_ups_with_same_amount_collide_under_default_key(): void
    {
        $user = User::factory()->create();
        $action = app(TopUpAction::class);

        // Permintaan 1: Klien top-up 100.000 (tanpa key)
        $tx1 = $action->execute($user, 100_000);

        // Permintaan 2: Klien top-up 100.000 lagi 5 menit kemudian (tanpa key)
        $tx2 = $action->execute($user, 100_000);

        // Di master saat ini, tx1->id === tx2->id karena key sama:
        $this->assertSame(
            $tx1->id,
            $tx2->id,
            'Terbukti: Di master, pemanggilan kedua mengembalikan transaksi pertama karena kunci default bertabrakan'
        );

        $wallet = $user->walletAccount('IDR');
        $this->assertSame(
            100_000,
            (int) $wallet->cached_balance,
            'Terbukti: Saldo dompet hanya bertambah 100.000 padahal top-up 2x (top-up kedua hilang)'
        );
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
