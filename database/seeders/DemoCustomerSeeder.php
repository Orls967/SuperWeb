<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Service;
use App\Models\Sparepart;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Modules\AutoDex\Domain\Models\Car;
use Modules\AutoServe\Application\Actions\CompleteBookingAction;
use Modules\AutoServe\Domain\Enums\BookingStatus;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\Banking\Application\Actions\SetPinAction;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Core\Application\Actions\AcquireVehicleAction;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Crypto\Application\Services\PriceEngineService;
use Modules\Crypto\Application\Services\TradeExecutionService;
use Modules\Crypto\Domain\Enums\TradeSide;
use Modules\Finance\Application\Actions\OpenLoanAction;
use Modules\Finance\Domain\Models\Loan;
use Modules\Payment\Contracts\PaymentGateway;
use Modules\Store\Application\Actions\ListCarProductAction;
use Modules\Store\Domain\Models\Product;

/**
 * Data demo lini otomotif (task 6.6): 20 customer dengan dompet terisi,
 * kendaraan di My Garage, riwayat booking yang benar-benar lunas lewat
 * PaymentGateway, dan 3 pembiayaan HODL-to-Drive.
 *
 * Seluruh uang bergerak lewat action asli (TopUpAction, PaymentGateway,
 * TradeExecutionService, OpenLoanAction) supaya bank:reconcile tetap 0 dan
 * tidak ada saldo yang dibuat langsung ke tabel.
 */
class DemoCustomerSeeder extends Seeder
{
    /** PIN dompet seragam untuk seluruh akun demo. */
    private const PIN = '123456';

    /** Batas satu transaksi top up di TopUpAction. */
    private const TOPUP_CHUNK = 50_000_000;

    /** Aset kolateral pembiayaan HODL-to-Drive. */
    private const COLLATERAL_SYMBOL = 'BTC';

    /** Rasio kolateral di atas kebutuhan minimum agar tidak langsung margin call. */
    private const COLLATERAL_BUFFER = '1.6';

    /**
     * 20 customer demo: nama, saldo dompet awal, dan jumlah kendaraan di garasi.
     *
     * @var list<array{name: string, balance: int, vehicles: int}>
     */
    private const CUSTOMERS = [
        ['name' => 'Siti Nurhaliza', 'balance' => 12_000_000, 'vehicles' => 2],
        ['name' => 'Bambang Pamungkas', 'balance' => 8_500_000, 'vehicles' => 1],
        ['name' => 'Dewi Lestari', 'balance' => 15_000_000, 'vehicles' => 1],
        ['name' => 'Agus Salim', 'balance' => 6_000_000, 'vehicles' => 1],
        ['name' => 'Rina Marlina', 'balance' => 22_000_000, 'vehicles' => 2],
        ['name' => 'Joko Susilo', 'balance' => 4_500_000, 'vehicles' => 1],
        ['name' => 'Maya Sari', 'balance' => 18_000_000, 'vehicles' => 1],
        ['name' => 'Hendra Wijaya', 'balance' => 9_750_000, 'vehicles' => 2],
        ['name' => 'Fitri Handayani', 'balance' => 7_250_000, 'vehicles' => 1],
        ['name' => 'Rizky Ramadhan', 'balance' => 13_500_000, 'vehicles' => 1],
        ['name' => 'Putri Ayu', 'balance' => 5_800_000, 'vehicles' => 1],
        ['name' => 'Dimas Anggara', 'balance' => 25_000_000, 'vehicles' => 2],
        ['name' => 'Lina Kusuma', 'balance' => 11_200_000, 'vehicles' => 1],
        ['name' => 'Yusuf Hidayat', 'balance' => 3_900_000, 'vehicles' => 1],
        ['name' => 'Anisa Rahma', 'balance' => 16_400_000, 'vehicles' => 1],
        ['name' => 'Fajar Nugroho', 'balance' => 10_000_000, 'vehicles' => 2],
        ['name' => 'Citra Dewanti', 'balance' => 14_750_000, 'vehicles' => 1],
        ['name' => 'Iwan Setiawan', 'balance' => 6_600_000, 'vehicles' => 1],
        ['name' => 'Sri Wahyuni', 'balance' => 19_300_000, 'vehicles' => 1],
        ['name' => 'Taufik Hidayat', 'balance' => 8_100_000, 'vehicles' => 1],
    ];

    /** Warna kendaraan demo, dirotasi per kendaraan. */
    private const COLORS = [
        'Putih Mutiara', 'Hitam Metalik', 'Silver Stone', 'Merah Marun',
        'Biru Nardo', 'Abu Gunmetal', 'Coklat Bronze', 'Hijau Army',
    ];

    public function run(): void
    {
        if (User::where('email', 'customer01@autoserve.test')->exists()) {
            $this->command?->warn('DemoCustomerSeeder dilewati: akun demo sudah ada.');

            return;
        }

        $customers = $this->seedCustomers();
        $this->command?->info('  ✓ '.$customers->count().' customer demo dibuat dengan dompet & PIN aktif.');

        $vehicles = $this->seedGarageVehicles($customers);
        $this->command?->info('  ✓ '.$vehicles->count().' kendaraan terdaftar di My Garage (paspor terbentuk).');

        $bookings = $this->seedBookingHistory($customers, $vehicles);
        $this->command?->info('  ✓ '.$bookings.' riwayat booking bengkel dibuat.');

        $loans = $this->seedFinancedPurchases($customers);
        $this->command?->info('  ✓ '.$loans.' pembiayaan HODL-to-Drive aktif dengan jadwal cicilan.');
    }

    /**
     * 20 customer dengan dompet IDR, PIN, dan saldo hasil top up.
     *
     * @return Collection<int, User>
     */
    private function seedCustomers(): Collection
    {
        $setPin = app(SetPinAction::class);
        $customers = collect();

        foreach (self::CUSTOMERS as $index => $fixture) {
            $sequence = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);

            $user = User::create([
                'name' => $fixture['name'],
                'email' => "customer{$sequence}@autoserve.test",
                'phone' => '0812'.str_pad((string) (55000000 + $index), 8, '0', STR_PAD_LEFT),
                'role' => 'customer',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);

            $user->walletAccount('IDR');
            $setPin->execute($user, self::PIN);
            $this->topUp($user, $fixture['balance'], "seed_topup_{$user->id}");

            $customers->push($user);
        }

        return $customers;
    }

    /**
     * Top up bertahap karena TopUpAction membatasi Rp 50 juta per transaksi.
     */
    private function topUp(User $user, int $amount, string $keyPrefix): void
    {
        $topUp = app(TopUpAction::class);
        $remaining = $amount;
        $chunkIndex = 0;

        while ($remaining > 0) {
            $chunk = min($remaining, self::TOPUP_CHUNK);
            $topUp->execute($user, $chunk, "{$keyPrefix}_{$chunkIndex}");
            $remaining -= $chunk;
            $chunkIndex++;
        }
    }

    /**
     * Kendaraan My Garage dibuat lewat AcquireVehicleAction supaya rantai
     * Vehicle Passport ikut terbentuk dan lolos core:verify-passports.
     *
     * @param  Collection<int, User>  $customers
     * @return Collection<int, Vehicle>
     */
    private function seedGarageVehicles(Collection $customers): Collection
    {
        $acquire = app(AcquireVehicleAction::class);

        // Mobil harian (bukan supercar) supaya riwayat servis terasa wajar
        $cars = Car::query()
            ->whereNotNull('price_idr')
            ->where('price_idr', '<=', 800_000_000)
            ->orderBy('id')
            ->get();

        if ($cars->isEmpty()) {
            $cars = Car::query()->orderBy('id')->get();
        }

        $vehicles = collect();
        $plateSequence = 2000;
        $carIndex = 0;

        foreach ($customers as $index => $customer) {
            $target = self::CUSTOMERS[$index]['vehicles'] ?? 1;

            for ($n = 0; $n < $target; $n++) {
                $car = $cars[$carIndex % $cars->count()];
                $carIndex++;
                $plateSequence++;

                $vehicles->push($acquire->handle(
                    user: $customer,
                    car: $car,
                    plateNumber: 'DA '.$plateSequence.' CS',
                    color: self::COLORS[$vehicles->count() % count(self::COLORS)],
                    vin: 'DEMOCS'.str_pad((string) $plateSequence, 11, '0', STR_PAD_LEFT),
                    odometerKm: 15_000 + ($plateSequence % 40) * 2_500,
                    acquiredViaType: 'manual',
                ));
            }
        }

        return $vehicles;
    }

    /**
     * Riwayat booking 6 bulan terakhir. Mayoritas sudah selesai dan lunas
     * lewat PaymentGateway; sebagian dibiarkan berjalan agar dashboard
     * admin dan mekanik punya pekerjaan aktif.
     *
     * @param  Collection<int, User>  $customers
     * @param  Collection<int, Vehicle>  $vehicles
     */
    private function seedBookingHistory(
        Collection $customers,
        Collection $vehicles
    ): int {
        $services = Service::query()->orderBy('id')->get();
        $spareparts = Sparepart::query()->orderBy('id')->get();
        $mechanics = User::where('role', 'mekanik')->orderBy('id')->get();

        if ($services->isEmpty() || $mechanics->isEmpty()) {
            return 0;
        }

        $complete = app(CompleteBookingAction::class);
        $gateway = app(PaymentGateway::class);

        $created = 0;
        $counter = 0;

        foreach ($customers as $customerIndex => $customer) {
            $garage = $vehicles->where('user_id', $customer->id)->values();

            if ($garage->isEmpty()) {
                continue;
            }

            // 2–4 booking per customer, tersebar pada 6 bulan terakhir
            $bookingCount = 2 + ($customerIndex % 3);

            for ($n = 0; $n < $bookingCount; $n++) {
                $counter++;
                $vehicle = $garage[$n % $garage->count()];
                $service = $services[$counter % $services->count()];
                $mechanic = $mechanics[$counter % $mechanics->count()];
                $bookingDate = Carbon::today()->subDays(($n * 37) + ($customerIndex * 4) + 3);

                $booking = Booking::create([
                    'booking_code' => Booking::generateBookingCode(),
                    'customer_id' => $customer->id,
                    'mechanic_id' => $mechanic->id,
                    'service_id' => $service->id,
                    'vehicle_id' => $vehicle->id,
                    'plate_number' => $vehicle->plate_number,
                    'vehicle_brand' => $vehicle->car?->brand?->name ?? 'Umum',
                    'vehicle_model' => $vehicle->car?->model ?? 'Model',
                    'vehicle_year' => $vehicle->car?->year_start ?? 2020,
                    'complaint' => $this->complaintFor($service->name),
                    'booking_date' => $bookingDate,
                    'booking_time' => ['09:00', '10:30', '13:00', '15:30'][$counter % 4],
                    'status' => BookingStatus::Pending,
                    'service_cost' => 0,
                    'sparepart_cost' => 0,
                    'grand_total' => 0,
                    'payment_status' => 'unpaid',
                ]);

                $created++;

                // Satu booking terakhir per customer dibiarkan berjalan
                $keepOpen = $n === $bookingCount - 1 && $customerIndex % 3 === 0;

                if ($keepOpen) {
                    $booking->transitionTo(BookingStatus::Confirmed);
                    $booking->recalculateCosts();

                    continue;
                }

                // Sebagian booking memakai sparepart supaya stok ikut bergerak
                if ($spareparts->isNotEmpty() && $counter % 2 === 0) {
                    $part = $spareparts[$counter % $spareparts->count()];
                    $qty = 1 + ($counter % 2);
                    $unitPrice = (int) $part->price;

                    $booking->spareparts()->attach($part->id, [
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'subtotal' => $unitPrice * $qty,
                    ]);
                }

                $booking->transitionTo(BookingStatus::Confirmed);
                $booking->transitionTo(BookingStatus::InProgress);

                $odometer = (int) $vehicle->fresh()->odometer_km + 1_200 + ($counter % 5) * 300;

                $complete->execute(
                    booking: $booking->fresh(['spareparts', 'service']),
                    notes: 'Pekerjaan selesai, unit sudah dites jalan dan tidak ada keluhan lanjutan.',
                    odometerKm: $odometer,
                    actorId: $mechanic->id,
                );

                $booking = $booking->fresh();

                // Lunasi memakai jalur yang sama dengan InvoicePaymentController
                if ((int) $booking->grand_total > 0) {
                    $balance = (int) $customer->walletAccount('IDR')->fresh()->cached_balance;

                    if ($balance < (int) $booking->grand_total) {
                        continue;
                    }

                    $gateway->charge($booking, "invoice_pay_{$booking->id}_{$booking->booking_code}");
                    $booking->fresh()->transitionTo(BookingStatus::Invoiced);
                }
            }
        }

        return $created;
    }

    /**
     * 3 pembiayaan HODL-to-Drive lengkap: customer top up, beli BTC lewat
     * bursa simulasi, lalu membuka pinjaman berjaminan kripto atas unit mobil
     * yang benar-benar terdaftar di Store.
     *
     * @param  Collection<int, User>  $customers
     */
    private function seedFinancedPurchases(Collection $customers): int
    {
        $products = $this->listFinanceableCars();

        if ($products->isEmpty()) {
            return 0;
        }

        $priceEngine = app(PriceEngineService::class);
        $trade = app(TradeExecutionService::class);
        $openLoan = app(OpenLoanAction::class);

        // Tenor berbeda agar jadwal cicilan demo bervariasi
        $plans = [
            ['customer' => 0, 'tenor' => 12, 'dp_percent' => 30],
            ['customer' => 4, 'tenor' => 24, 'dp_percent' => 25],
            ['customer' => 11, 'tenor' => 36, 'dp_percent' => 20],
        ];

        $opened = 0;

        foreach ($plans as $planIndex => $plan) {
            $customer = $customers[$plan['customer']] ?? null;
            $product = $products[$planIndex] ?? null;

            if ($customer === null || $product === null) {
                continue;
            }

            $grandTotal = (int) $product->price + 500_000;
            $downPayment = (int) round($grandTotal * $plan['dp_percent'] / 100);
            $principal = $grandTotal - $downPayment;

            $btcPrice = $priceEngine->currentPrice(self::COLLATERAL_SYMBOL);

            if ($btcPrice->isZero()) {
                continue;
            }

            // Kolateral minimum LTV 50% ditambah buffer agar tidak langsung margin call
            $collateralQty = Loan::requiredCollateralQty($principal, $btcPrice, 8)
                ->multipliedBy(self::COLLATERAL_BUFFER)
                ->toScale(8, RoundingMode::Up);

            // Dana untuk membeli kolateral + uang muka, dibulatkan ke atas
            $collateralCost = $collateralQty->multipliedBy($btcPrice);
            $funding = BigDecimal::of($collateralCost)
                ->multipliedBy('1.05')
                ->plus($downPayment)
                ->toScale(0, RoundingMode::Up)
                ->toInt();

            $this->topUp($customer, $funding, "seed_loan_funding_{$customer->id}");

            $quote = $priceEngine->generateQuote(
                user: $customer,
                symbol: self::COLLATERAL_SYMBOL,
                side: TradeSide::BUY,
                amountIdr: null,
                cryptoQty: (string) $collateralQty,
            );

            // Pastikan saldo IDR customer mencukupi untuk biaya beli kripto (termasuk fee) dan DP
            $quoteCost = BigDecimal::of($quote->gross_idr)->plus($quote->fee_idr);
            $walletBalance = BigDecimal::of($customer->walletAccount('IDR')->fresh()->cached_balance ?: '0');
            $needed = $quoteCost->plus($downPayment);
            if ($walletBalance->isLessThan($needed)) {
                $deficit = $needed->minus($walletBalance)->toScale(0, RoundingMode::Up)->toInt();
                $this->topUp($customer, $deficit, "seed_loan_buffer_{$customer->id}");
            }

            $trade->executeTrade($customer, $quote->uuid, self::PIN);

            $openLoan->execute(
                user: $customer,
                product: $product,
                downPayment: $downPayment,
                tenorMonths: $plan['tenor'],
                collateralSymbol: self::COLLATERAL_SYMBOL,
                shippingAddress: [
                    'name' => $customer->name,
                    'phone' => (string) $customer->phone,
                    'address' => 'Jl. Ahmad Yani KM '.(4 + $planIndex).' No. '.(12 + $planIndex * 7),
                    'city' => 'Banjarmasin',
                    'postal_code' => '7011'.$planIndex,
                ],
                pin: self::PIN,
                idempotencyKey: "seed_loan_{$customer->id}_{$product->id}",
                collateralQty: $collateralQty,
            );

            $opened++;
        }

        return $opened;
    }

    /**
     * Daftarkan beberapa unit mobil AutoDex ke Store supaya etalase tidak
     * kosong dan pembiayaan punya objek yang bisa dibeli.
     *
     * @return Collection<int, Product>
     */
    private function listFinanceableCars(): Collection
    {
        $listCar = app(ListCarProductAction::class);

        $cars = Car::query()
            ->with('brand')
            ->whereNotNull('price_idr')
            ->whereBetween('price_idr', [150_000_000, 900_000_000])
            ->orderBy('price_idr')
            ->limit(6)
            ->get();

        return $cars->map(fn (Car $car) => $listCar->execute(car: $car, stock: 2))->values();
    }

    private function complaintFor(string $serviceName): string
    {
        return match (true) {
            str_contains($serviceName, 'Oli') => 'Sudah lewat 5.000 km dari servis terakhir, minta ganti oli sekalian cek filter.',
            str_contains($serviceName, 'Rem') => 'Rem terasa dalam dan ada suara berdecit saat pengereman mendadak.',
            str_contains($serviceName, 'AC') => 'AC kurang dingin saat siang dan bau apek ketika baru dinyalakan.',
            str_contains($serviceName, 'Spooring') => 'Setir tertarik ke kiri dan ban depan habis tidak rata.',
            str_contains($serviceName, 'Timing') => 'Ada suara kasar dari area mesin, ingin sekalian ganti timing belt.',
            str_contains($serviceName, 'Overhaul') => 'Mesin sering overheat dan oli cepat berkurang, minta pemeriksaan menyeluruh.',
            str_contains($serviceName, 'Kopling') => 'Pedal kopling tinggi dan perpindahan gigi terasa keras.',
            default => 'Servis berkala sesuai jadwal, mohon sekalian dicek menyeluruh.',
        };
    }
}
