<?php

declare(strict_types=1);

namespace Modules\Logistics\tests\Support;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Banking\Application\Actions\SetPinAction;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Logistics\Application\Actions\BookPostpaidShipmentAction;
use Modules\Logistics\Application\Actions\BookShipmentAction;
use Modules\Logistics\Application\Actions\CompleteDeliveryAction;
use Modules\Logistics\Application\Actions\QuoteShipmentAction;
use Modules\Logistics\Application\Actions\StartDeliveryAction;
use Modules\Logistics\Domain\Enums\LocationType;
use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Models\Driver;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\ProofOfDelivery;
use Modules\Logistics\Domain\Models\RateBracket;
use Modules\Logistics\Domain\Models\RateCard;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\ShipperAccount;
use Modules\Logistics\Domain\Models\Surcharge;

/**
 * Dunia uji untuk alur uang logistik: jaringan 2 hub, tarif, shipper berdompet, driver, dan helper
 * booking -> pengantaran yang memakai action produksi (bukan jurnal manual).
 */
final class MoneyFlowWorld
{
    public Location $hubA;

    public Location $hubB;

    public User $admin;

    public User $dispatcher;

    public User $hubOperator;

    private int $driverSeq = 0;

    private int $userSeq = 0;

    public function __construct()
    {
        Storage::fake('local');
        (new BankingSeederRunner)->run();

        $mk = fn (string $code, string $city, string $tz) => Location::create([
            'code' => $code, 'name' => "Hub {$city}", 'type' => LocationType::HUB, 'city' => $city, 'province' => 'Kalimantan Selatan',
            'country_code' => 'ID', 'lat_e6' => -3300000, 'lng_e6' => 114500000, 'timezone' => $tz, 'min_connection_minutes' => 60,
        ]);
        $this->hubA = $mk('HUB-BDJ', 'Banjarmasin', 'Asia/Makassar');
        $this->hubB = $mk('HUB-BJB', 'Banjarbaru', 'Asia/Makassar');

        $card = RateCard::create([
            'name' => 'Tarif BDJ-BJB', 'origin_location_id' => $this->hubA->id, 'destination_location_id' => $this->hubB->id,
            'service_level' => ServiceLevel::Regular, 'mode' => TransportMode::ROAD, 'min_charge_idr' => 10_000,
            'valid_from' => '2026-01-01', 'valid_to' => null, 'is_active' => true,
        ]);
        RateBracket::create(['rate_card_id' => $card->id, 'min_weight_kg' => 0, 'max_weight_kg' => 100, 'rate_per_kg_idr' => 10_000, 'is_flat' => false]);
        Surcharge::create(['code' => Surcharge::CODE_FUEL, 'name' => 'Fuel 5%', 'type' => 'percentage', 'rate' => 0.05, 'is_active' => true]);

        $this->admin = $this->user('logistics_admin');
        $this->dispatcher = $this->user('dispatcher');
        $this->hubOperator = $this->user('hub_operator');
    }

    public function user(string $role, int $balance = 0): User
    {
        $this->userSeq++;
        $user = User::factory()->create(['role' => $role, 'name' => ucfirst($role)." {$this->userSeq}"]);
        app(SetPinAction::class)->execute($user, '123456');
        if ($balance > 0) {
            app(TopUpAction::class)->execute($user, (string) $balance, 'IDR', 'world_topup_'.uniqid());
        }

        return $user;
    }

    public function shipper(int $balance = 5_000_000, bool $withCreditAccount = false): User
    {
        $shipper = $this->user('shipper', $balance);
        if ($withCreditAccount) {
            ShipperAccount::create(['shipper_id' => $shipper->id, 'credit_limit_idr' => 50_000_000, 'payment_terms_days' => 30, 'is_active' => true]);
        }

        return $shipper;
    }

    public function driver(): Driver
    {
        $this->driverSeq++;

        return Driver::create([
            'user_id' => $this->user('driver')->id, 'driver_number' => 'DRV-W-'.str_pad((string) $this->driverSeq, 3, '0', STR_PAD_LEFT),
            'license_class' => 'SIM B1 Umum', 'license_expiry' => now()->addYear(), 'home_hub_id' => $this->hubA->id, 'status' => 'available',
        ]);
    }

    private function quote(User $shipper, int $cod, bool $insured, int $declared)
    {
        return app(QuoteShipmentAction::class)->execute(
            shipper: $shipper, originLocationId: $this->hubA->id, destinationLocationId: $this->hubB->id,
            serviceLevel: ServiceLevel::Regular,
            packages: [['weight_g' => 2000, 'length_mm' => 100, 'width_mm' => 100, 'height_mm' => 100, 'description' => 'Paket']],
            declaredValueIdr: $declared, insured: $insured, codAmountIdr: $cod,
        );
    }

    public function bookPrepaid(User $shipper, int $cod = 0, bool $insured = false, int $declared = 0): Shipment
    {
        return app(BookShipmentAction::class)->execute(
            shipper: $shipper, quote: $this->quote($shipper, $cod, $insured, $declared),
            consigneeName: 'Budi Santoso', consigneePhone: '081234567890',
            consigneeAddress: ['street' => 'Jl. Veteran 1', 'city' => 'Banjarbaru'], pin: '123456',
        );
    }

    public function bookPostpaid(User $shipper, int $cod = 0, bool $insured = false, int $declared = 0): Shipment
    {
        return app(BookPostpaidShipmentAction::class)->execute(
            shipper: $shipper, quote: $this->quote($shipper, $cod, $insured, $declared),
            consigneeName: 'Budi Santoso', consigneePhone: '081234567890',
            consigneeAddress: ['street' => 'Jl. Veteran 1', 'city' => 'Banjarbaru'],
        );
    }

    /**
     * Antar paket sampai Delivered lewat action produksi. Mengembalikan POD.
     */
    public function deliver(Shipment $shipment, ?Driver $driver = null, bool $codCollected = true): ProofOfDelivery
    {
        $driver ??= $this->driver();
        $shipment->update(['status' => ShipmentStatus::AtHub, 'driver_id' => $driver->id]);
        $otp = app(StartDeliveryAction::class)->execute($driver, $shipment->fresh())['otp'];

        $img = imagecreatetruecolor(200, 80);
        ob_start();
        imagepng($img);
        $signature = 'data:image/png;base64,'.base64_encode((string) ob_get_clean());

        return app(CompleteDeliveryAction::class)->execute(
            $driver, $shipment->fresh(), 'Siti Penerima', $otp, UploadedFile::fake()->image('pod.jpg'), $signature, $codCollected,
        );
    }
}

/** Membungkus BankingSeeder agar world dapat dibuat di dalam test mana pun. */
final class BankingSeederRunner
{
    public function run(): void
    {
        app(BankingSeeder::class)->run();
    }
}
