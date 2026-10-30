<?php

declare(strict_types=1);

namespace Tests\Acceptance\Support;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Models\Role;
use Tests\TestCase;

abstract class HospitalAcceptanceHelper extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LedgerAccountsSeeder::class);
    }

    protected function createHospitalUser(string $roleName): User
    {
        Role::firstOrCreate(['name' => $roleName], ['label' => ucfirst($roleName)]);

        return User::factory()->create([
            'role' => $roleName,
        ]);
    }

    protected function assertLedgerPosted(string $accountCode, int $amount, string $direction = 'DEBIT'): void
    {
        $account = DB::table('bank_ledger_accounts')->where('code', $accountCode)->first();
        $this->assertNotNull($account, "Akun ledger '{$accountCode}' tidak ditemukan di tabel bank_ledger_accounts.");

        $signedAmount = $direction === 'DEBIT' ? -abs($amount) : abs($amount);

        $exists = DB::table('bank_ledger_entries')
            ->where('account_id', $account->id)
            ->whereRaw('CAST(amount AS INTEGER) = ?', [$signedAmount])
            ->exists();

        $this->assertTrue(
            $exists,
            "Gagal menemukan posting pada akun '{$accountCode}' dengan arah {$direction} dan nominal {$amount} (nilai tersimpan {$signedAmount})."
        );
    }
}
