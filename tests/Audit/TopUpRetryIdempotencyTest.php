<?php

declare(strict_types=1);

namespace Tests\Audit;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Tests\TestCase;

/**
 * Audit Kasus Wajib 5: Pembuktian bug default idempotency key pada TopUpAction.
 *
 * Di commit 6469823 (branch feature/fase-r1bc-ledger), default idempotency key diubah menjadi:
 * $txCount = LedgerTransaction::where('reference_type', User::class)->where('reference_id', $user->id)->count();
 * $key = $idempotencyKey ?? sprintf('banking:topup:%s:%d', $user->id, $txCount + 1);
 *
 * Bug: Saat client melakukan retry (karena timeout / transient network error) tanpa menyertakan explicit key,
 * transaksi pertama telah mencatat txCount = 1, sehingga pemanggilan kedua menghasilkan key baru (:2),
 * menyebabkan saldo akun di-kredit DUA kali alih-alih di-idempotenkan ke transaksi lama.
 *
 * Di master, default key juga menggunakan Str::random(16) yang menghasilkan masalah serupa pada retry.
 *
 * @group audit-2026-10-18
 */
class TopUpRetryIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    /**
     * Membuktikan bahwa pemanggilan ulang TopUpAction dengan parameter identik (simulasi retry)
     * menghasilkan saldo berlipat ganda karena default idempotency key tidak deterministik per request.
     */
    public function test_simulated_retry_without_idempotency_key_fails_idempotency(): void
    {
        $user = User::factory()->create();
        $action = app(TopUpAction::class);

        // Request 1: Klien melakukan topup 100.000
        $action->execute($user, 100000);

        // Request 2: Klien melakukan retry dengan argumen identik (misal network timeout setelah request 1 berhasil)
        $action->execute($user, 100000);

        $txs = LedgerTransaction::query()
            ->where('reference_type', User::class)
            ->where('reference_id', $user->id)
            ->get();

        // Harapan akuntansi & R1.5: Retry harus menghasilkan tepat 1 transaksi ledger (idempotent).
        // Kenyataan implementasi: txs->count() == 2 (Temuan P0: Saldo ditambah dua kali).
        $actualCount = $txs->count();

        // Catat ke output pengujian untuk audit
        echo sprintf("\n[AUDIT TopUpRetry] Total Transaksi Ledger: %d (Kunci Tx: %s, %s)\n",
            $actualCount,
            $txs->first()?->idempotency_key ?? 'none',
            $txs->last()?->idempotency_key ?? 'none'
        );

        $this->assertSame(
            2,
            $actualCount,
            'Terbukti: pemanggilan ulang tanpa idempotencyKey menghasilkan 2 transaksi (tidak idempoten).'
        );
    }

    /**
     * Membuktikan secara spesifik logika commit 6469823 (txCount + 1).
     */
    public function test_sequence_counter_idempotency_key_generates_distinct_keys_on_retry(): void
    {
        $user = User::factory()->create();

        // Simulasi fungsi key generation di commit 6469823:
        $generateKey = function ($user) {
            $txCount = LedgerTransaction::query()
                ->where('reference_type', User::class)
                ->where('reference_id', $user->id)
                ->where('type', TransactionType::TOPUP->value)
                ->count();

            return sprintf('banking:topup:%s:%d', $user->id, $txCount + 1);
        };

        $key1 = $generateKey($user);
        $this->assertSame("banking:topup:{$user->id}:1", $key1);

        // Simulasikan transaksi 1 tercatat di database:
        LedgerTransaction::create([
            'idempotency_key' => $key1,
            'type' => TransactionType::TOPUP->value,
            'description' => 'Top Up 1',
            'reference_type' => User::class,
            'reference_id' => $user->id,
            'posted_at' => now(),
        ]);

        // Simulasikan retry tiba:
        $key2 = $generateKey($user);
        $this->assertSame("banking:topup:{$user->id}:2", $key2);

        // Terbukti key1 !== key2:
        $this->assertNotSame(
            $key1,
            $key2,
            'Sequence counter txCount+1 menghasilkan key berbeda pada retry, membuktikan bug dobel kredit.'
        );
    }
}
