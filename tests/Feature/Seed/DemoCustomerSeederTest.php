<?php

declare(strict_types=1);

namespace Tests\Feature\Seed;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\AutoServe\Domain\Enums\BookingStatus;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\Banking\Contracts\VerifiesWalletPin;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Crypto\Contracts\PriceFeed;
use Modules\Finance\Domain\Enums\LoanStatus;
use Modules\Finance\Domain\Models\Loan;
use Modules\Store\Domain\Models\Product;
use Tests\TestCase;

class DemoCustomerSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_twenty_demo_customers_exist_with_pin_and_positive_balance(): void
    {
        $pinVerifier = app(VerifiesWalletPin::class);

        for ($i = 1; $i <= 20; $i++) {
            $seq = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $email = "customer{$seq}@autoserve.test";

            $customer = User::where('email', $email)->first();
            $this->assertNotNull($customer, "Customer {$email} harus terdaftar di database.");
            $this->assertSame('customer', $customer->role);

            // Verifikasi PIN dompet '123456' valid
            $isPinValid = false;
            try {
                $pinVerifier->execute($customer, '123456');
                $isPinValid = true;
            } catch (\Throwable) {
                $isPinValid = false;
            }
            $this->assertTrue($isPinValid, "PIN dompet customer {$email} harus valid (123456).");

            // Saldo dompet IDR harus > 0
            $wallet = $customer->walletAccount('IDR');
            $this->assertNotNull($wallet, "Dompet IDR customer {$email} harus ada.");
            $this->assertGreaterThan(
                0,
                (int) $wallet->fresh()->cached_balance,
                "Saldo dompet IDR customer {$email} harus lebih besar dari 0."
            );
        }
    }

    public function test_at_least_twenty_vehicles_exist_with_valid_passports(): void
    {
        $vehicleCount = Vehicle::count();
        $this->assertGreaterThanOrEqual(20, $vehicleCount, 'Jumlah kendaraan berpaspor minimal 20.');

        // Rantai hash paspor seluruh kendaraan harus utuh dan valid
        $this->artisan('core:verify-passports')->assertSuccessful();
    }

    public function test_at_least_forty_bookings_exist_and_invoiced_bookings_paid_via_ledger(): void
    {
        $bookingCount = Booking::count();
        $this->assertGreaterThanOrEqual(40, $bookingCount, 'Jumlah booking bengkel minimal 40.');

        $invoicedBookings = Booking::where('status', BookingStatus::Invoiced->value)->get();
        $this->assertNotEmpty($invoicedBookings, 'Harus ada riwayat booking berstatus Invoiced.');

        foreach ($invoicedBookings as $booking) {
            $this->assertSame('paid', $booking->payment_status);

            // Pastikan pembayaran tercatat di transaksi ledger
            $hasLedgerPosting = DB::table('bank_ledger_transactions')
                ->where('reference_type', Booking::class)
                ->where('reference_id', $booking->id)
                ->exists();

            $this->assertTrue(
                $hasLedgerPosting,
                "Booking #{$booking->booking_code} yang Invoiced harus memiliki posting transaksi ledger."
            );
        }
    }

    public function test_exactly_three_active_hodl_to_drive_loans_with_ltv_under_seventy_percent(): void
    {
        $activeLoans = Loan::where('status', LoanStatus::Active->value)->get();
        $this->assertCount(3, $activeLoans, 'Harus ada tepat 3 pembiayaan HODL-to-Drive aktif.');

        $priceFeed = app(PriceFeed::class);

        foreach ($activeLoans as $loan) {
            $price = $priceFeed->currentPrice($loan->collateralAsset->symbol);
            $ltv = $loan->currentLtv($price);

            $this->assertLessThan(
                0.70,
                $ltv,
                "Pinjaman #{$loan->id} harus memiliki LTV < 70% (LTV saat ini: {$ltv})."
            );
            $this->assertNull($loan->margin_called_at, "Pinjaman #{$loan->id} tidak boleh dalam kondisi margin call.");
            $this->assertGreaterThan(0, $loan->outstanding_principal);
            $this->assertNotEmpty($loan->installments, "Pinjaman #{$loan->id} harus memiliki jadwal cicilan.");
        }
    }

    public function test_six_car_products_registered_in_store(): void
    {
        $carProducts = Product::where('is_car', true)->get();
        $this->assertCount(6, $carProducts, 'Harus ada tepat 6 produk mobil di Store.');

        foreach ($carProducts as $car) {
            $this->assertSame('dex_car', $car->productable_type);
            $this->assertTrue($car->is_listed);
        }
    }

    public function test_bank_reconcile_returns_zero_discrepancy(): void
    {
        $this->artisan('bank:reconcile')->assertSuccessful();
    }
}
