<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\AutoServe\Domain\Models\Service;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Logistics\Application\Actions\BookPostpaidShipmentAction;
use Modules\Logistics\Application\Actions\CancelShipmentAction;
use Modules\Logistics\Application\Actions\QuoteShipmentAction;
use Modules\Logistics\Application\Actions\ReserveCapacityAction;
use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Exceptions\InvalidQuoteException;
use Modules\Logistics\Domain\Models\CapacityReservation;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Quote;
use Modules\Logistics\Domain\Models\RateCard;
use Modules\Logistics\Domain\Models\Schedule;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\ShipperAccount;
use Modules\Payment\Contracts\PaymentGateway;
use Modules\Payment\Domain\Models\PaymentIntent;
use Modules\Shared\Domain\ValueObjects\Money;
use Tests\TestCase;

/**
 * Regresi 26.3 — "race berurutan".
 *
 * SQLite in-memory tidak memuat dua koneksi serentak, jadi tiap skenario
 * meniru race dengan menjalankan permintaan identik dua kali berurutan
 * (interleaving kedua = dua request yang sama-sama lolos pre-check).
 * Perilaku wajib: tidak ada posting ganda, tidak ada alokasi ganda,
 * tidak ada penulisan parsial.
 */
class ActionConcurrencyRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected User $shipper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->shipper = User::where('role', 'shipper')->firstOrFail();
    }

    // ---------------------------------------------------------------
    // Payment — capture / release / refund idempoten
    // ---------------------------------------------------------------

    public function test_release_retry_dengan_key_sama_hanya_mengkredit_dompet_satu_kali(): void
    {
        $gateway = app(PaymentGateway::class);
        $intent = $this->heldIntent(400000, 'race_release_key');

        $payer = $intent->payer;
        $balanceBefore = $payer->fresh()->walletBalance('IDR')->amount->toInt();

        $gateway->release($intent, 'race_release_key');
        $balanceAfterFirst = $payer->fresh()->walletBalance('IDR')->amount->toInt();
        $this->assertSame($balanceBefore + 400000, $balanceAfterFirst);

        // Retry identik (double-submit / timeout + resend) tidak boleh mengkredit lagi.
        $gateway->release($intent->fresh(), 'race_release_key');
        $this->assertSame(
            $balanceAfterFirst,
            $payer->fresh()->walletBalance('IDR')->amount->toInt()
        );

        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    public function test_refund_partial_replay_dan_over_refund(): void
    {
        $gateway = app(PaymentGateway::class);
        $intent = $this->chargedIntent(1000000, 'race_refund_charge');

        $payer = $intent->payer;
        $start = $payer->fresh()->walletBalance('IDR')->amount->toInt();

        // Partial refund harus seimbang (sebelumnya membalik seluruh split → UnbalancedTransactionException).
        $gateway->refund($intent, Money::IDR(300000), 'sebagian', 'race_refund_1');
        $this->assertSame($start + 300000, $payer->fresh()->walletBalance('IDR')->amount->toInt());

        // Retry dengan key sama tidak menambah pengembalian.
        $gateway->refund($intent->fresh(), Money::IDR(300000), 'sebagian', 'race_refund_1');
        $this->assertSame($start + 300000, $payer->fresh()->walletBalance('IDR')->amount->toInt());

        // Mengembalikan melebihi sisa tangkapanan ditolak.
        $this->expectException(\InvalidArgumentException::class);
        $gateway->refund($intent->fresh(), Money::IDR(800000), 'lebih', 'race_refund_over');
    }

    // ---------------------------------------------------------------
    // Logistics — cancel ganda, quote ganda, kapasitas ganda
    // ---------------------------------------------------------------

    public function test_cancel_shipment_kedua_kali_tidak_refund_dua_kali(): void
    {
        $shipment = $this->makeBookedPrepaidShipment();
        $action = app(CancelShipmentAction::class);
        $shipper = User::findOrFail($shipment->shipper_id);

        $before = $shipper->fresh()->walletBalance('IDR')->amount->toInt();

        $action->execute($shipper, $shipment, 'coba pertama', 'race_cancel_1');
        $afterFirst = $shipper->fresh()->walletBalance('IDR')->amount->toInt();
        $this->assertGreaterThan($before, $afterFirst, 'Pembatalan pertama harus mengembalikan dana.');

        // Request kedua dengan model hasil-reload (stale-precheck sama-sama lolos)
        // harus idempoten, bukan refund lagi.
        $reloaded = Shipment::findOrFail($shipment->id);
        $action->execute($shipper, $reloaded, 'coba kedua', 'race_cancel_2');

        $this->assertSame(
            $afterFirst,
            $shipper->fresh()->walletBalance('IDR')->amount->toInt(),
            'Pembatalan ganda tidak boleh mengembalikan dana dua kali.'
        );

        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    public function test_quote_tidak_bisa_dibukukan_dua_kali(): void
    {
        $quote = $this->makeValidQuote();

        $action = app(BookPostpaidShipmentAction::class);
        $address = ['street' => 'Jl Race', 'city' => 'Banjarmasin'];

        $first = $action->execute($this->shipper, $quote, 'Budi', '081234567890', $address);
        $this->assertNotNull($first->id);

        // Request kedua pada quote yang sama harus ditolak, bukan membuat resi baru.
        try {
            $action->execute($this->shipper, Quote::findOrFail($quote->id), 'Budi Lagi', '081234567890', $address);
            $this->fail('Quote yang sudah dibukukan harus ditolak.');
        } catch (InvalidQuoteException $e) {
            $this->assertTrue($e->getMessage() !== '');
        }

        $this->assertSame(
            1,
            Shipment::where('quote_id', $quote->id)->count(),
            'Satu quote hanya boleh menghasilkan satu resi.'
        );
    }

    public function test_pemesanan_kapasitas_dengan_key_sama_hanya_mengalokasi_satu_kali(): void
    {
        $schedule = Schedule::query()->firstOrFail();
        $action = app(ReserveCapacityAction::class);

        $first = $action->execute(
            scheduleId: $schedule->id,
            weightKg: '100.000',
            volumeDm3: 50,
            idempotencyKey: 'race_cap_key',
            shipmentId: null
        );

        $second = $action->execute(
            scheduleId: $schedule->id,
            weightKg: '100.000',
            volumeDm3: 50,
            idempotencyKey: 'race_cap_key',
            shipmentId: null
        );

        $this->assertSame($first->id, $second->id, 'Retry dengan key sama harus memakai alokasi yang sama.');
        $this->assertSame(
            1,
            CapacityReservation::where('idempotency_key', 'race_cap_key')->count(),
            'Alokasi ganda dengan key sama tidak boleh terjadi.'
        );
        $this->artisan('lgx:capacity-check')->assertSuccessful();
    }

    // ---------------------------------------------------------------
    // helpers — fixture dibangun lewat action produksi
    // ---------------------------------------------------------------

    private function chargedIntent(int $amountIdr, string $key): PaymentIntent
    {
        $payer = User::factory()->create(['role' => 'customer']);
        app(TopUpAction::class)->execute($payer, (string) ($amountIdr + 1000000), 'charge_topup_'.$key);

        $service = Service::firstOrCreate(['name' => 'Race Probe Service'], ['price' => 100000]);
        $booking = Booking::create([
            'booking_code' => Booking::generateBookingCode(),
            'customer_id' => $payer->id,
            'service_id' => $service->id,
            'plate_number' => 'B 1 RACE',
            'vehicle_brand' => 'Toyota',
            'vehicle_model' => 'Avanza',
            'complaint' => 'Race probe',
            'booking_date' => now()->toDateString(),
            'booking_time' => '10:00',
            'status' => 'completed',
            'service_cost' => $amountIdr,
            'sparepart_cost' => 0,
            'grand_total' => $amountIdr,
            'payment_status' => 'unpaid',
        ]);

        return app(PaymentGateway::class)->charge($booking, $key);
    }

    private function heldIntent(int $amountIdr, string $key): PaymentIntent
    {
        $payer = User::factory()->create(['role' => 'customer']);
        app(TopUpAction::class)->execute($payer, (string) ($amountIdr + 1000000), 'hold_topup_'.$key);

        $service = Service::firstOrCreate(['name' => 'Race Hold Service'], ['price' => 100000]);
        $booking = Booking::create([
            'booking_code' => Booking::generateBookingCode(),
            'customer_id' => $payer->id,
            'service_id' => $service->id,
            'plate_number' => 'B 2 RACE',
            'vehicle_brand' => 'Toyota',
            'vehicle_model' => 'Avanza',
            'complaint' => 'Race hold',
            'booking_date' => now()->toDateString(),
            'booking_time' => '10:00',
            'status' => 'completed',
            'service_cost' => $amountIdr,
            'sparepart_cost' => 0,
            'grand_total' => $amountIdr,
            'payment_status' => 'unpaid',
        ]);

        return app(PaymentGateway::class)->hold($booking, Money::IDR($amountIdr), $key);
    }

    private function makeValidQuote(): Quote
    {
        // Booking pascabayar mensyaratkan akun B2B yang aktif.
        ShipperAccount::firstOrCreate(
            ['shipper_id' => $this->shipper->id],
            ['credit_limit_idr' => 100_000_000, 'payment_terms_days' => 30, 'is_active' => true]
        );

        // Pakai rute yang sudah punya rate card aktif dari seeder.
        $rateCard = RateCard::query()
            ->where('is_active', true)
            ->whereNotNull('origin_location_id')
            ->whereNotNull('destination_location_id')
            ->firstOrFail();

        return app(QuoteShipmentAction::class)->execute(
            shipper: $this->shipper,
            originLocationId: $rateCard->origin_location_id,
            destinationLocationId: $rateCard->destination_location_id,
            serviceLevel: ServiceLevel::Regular,
            packages: [[
                'weight_g' => 1000,
                'length_mm' => 100,
                'width_mm' => 100,
                'height_mm' => 100,
                'description' => 'Race probe',
            ]],
            declaredValueIdr: 100000,
            insured: false,
            codAmountIdr: 0,
        );
    }

    private function makeBookedPrepaidShipment(): Shipment
    {
        $existing = Shipment::query()
            ->where('payment_terms', 'prepaid')
            ->where('status', 'booked')
            ->where('total_amount_idr', '>', 0)
            ->whereDoesntHave('trackingEvents')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $locations = Location::query()->orderBy('id')->get();

        return Shipment::create([
            'tracking_number' => 'SRX'.random_int(10000000000, 99999999999),
            'shipper_id' => $this->shipper->id,
            'consignee_name' => 'Race Probe',
            'consignee_phone' => '081234567890',
            'consignee_address' => ['street' => 'Jl Race', 'city' => 'Banjarmasin'],
            'origin_location_id' => $locations->first()->id,
            'destination_location_id' => $locations->last()->id,
            'service_level' => 'regular',
            'mode' => 'road',
            'payment_terms' => 'prepaid',
            'status' => 'booked',
            'total_chargeable_weight_g' => 5000,
            'total_amount_idr' => 150000,
            'booked_at' => now(),
        ]);
    }
}
