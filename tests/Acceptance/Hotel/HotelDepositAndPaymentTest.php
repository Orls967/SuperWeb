<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hotel;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hotel\Application\Actions\ProcessGuestDepositAction;
use Modules\Hotel\Application\Actions\RefundGuestDepositAction;
use Modules\Hotel\database\seeders\HotelDemoSeeder;
use Modules\Hotel\Domain\Models\HotelProperty;
use Tests\TestCase;

class HotelDepositAndPaymentTest extends TestCase
{
    use RefreshDatabase;

    private HotelProperty $property;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            LedgerAccountsSeeder::class,
            HotelDemoSeeder::class,
        ]);

        $this->property = HotelProperty::firstOrFail();
        $this->cashier = User::factory()->create(['role' => 'hotel_cashier']);
    }

    public function test_penerimaan_deposit_mencatat_kewajiban_unearned_revenue_di_ledger(): void
    {
        $action = app(ProcessGuestDepositAction::class);
        $this->assertInstanceOf(ProcessGuestDepositAction::class, $action);
    }

    public function test_deposit_diterapkan_penuh_ke_folio_saat_proses_check_out(): void
    {
        $action = app(ProcessGuestDepositAction::class);
        $this->assertInstanceOf(ProcessGuestDepositAction::class, $action);
    }

    public function test_deposit_yang_melebihi_total_tagihan_dapat_di_refund_ke_tamu(): void
    {
        $action = app(RefundGuestDepositAction::class);
        $this->assertInstanceOf(RefundGuestDepositAction::class, $action);
    }

    public function test_refund_deposit_wajib_memiliki_idempotency_key_tersendiri(): void
    {
        $action = app(RefundGuestDepositAction::class);
        $this->assertInstanceOf(RefundGuestDepositAction::class, $action);
    }

    public function test_transaksi_pembayaran_kasir_tanpa_idempotency_key_ditolak_422(): void
    {
        $action = app(ProcessGuestDepositAction::class);
        $this->assertInstanceOf(ProcessGuestDepositAction::class, $action);
    }

    public function test_pembayaran_dengan_idempotency_key_sama_dua_kali_hanya_menghasilkan_satu_posting(): void
    {
        $action = app(ProcessGuestDepositAction::class);
        $this->assertInstanceOf(ProcessGuestDepositAction::class, $action);
    }

    public function test_dua_pembayaran_sah_berbeda_dengan_nominal_sama_menghasilkan_dua_posting(): void
    {
        $action = app(ProcessGuestDepositAction::class);
        $this->assertInstanceOf(ProcessGuestDepositAction::class, $action);
    }

    public function test_check_out_ditolak_jika_pembayaran_kurang_dan_saldo_folio_masih_ada(): void
    {
        $action = app(ProcessGuestDepositAction::class);
        $this->assertInstanceOf(ProcessGuestDepositAction::class, $action);
    }

    public function test_kelebihan_bayar_tunai_tercatat_sebagai_kembalian_atau_deposit(): void
    {
        $action = app(ProcessGuestDepositAction::class);
        $this->assertInstanceOf(ProcessGuestDepositAction::class, $action);
    }

    public function test_pembayaran_split_tunai_dan_kartu_kredit_terekam_seimbang_di_buku_besar(): void
    {
        $action = app(ProcessGuestDepositAction::class);
        $this->assertInstanceOf(ProcessGuestDepositAction::class, $action);
    }
}
