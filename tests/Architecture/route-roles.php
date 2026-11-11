<?php

declare(strict_types=1);
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\AutoDex\Domain\Models\Brand;
use Modules\AutoDex\Domain\Models\Car;
use Modules\AutoServe\Domain\Enums\EstimateStatus;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\AutoServe\Domain\Models\Estimate;
use Modules\AutoServe\Domain\Models\Service;
use Modules\AutoServe\Domain\Models\Sparepart;
use Modules\Aviation\Domain\Models\AviationAirport;
use Modules\Aviation\Domain\Models\AviationCodeshareAgreement;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Domain\Models\AviationOperator;
use Modules\Aviation\Domain\Models\AviationRoute;
use Modules\Aviation\Domain\Models\AviationTicket;
use Modules\Circular\Domain\Models\CirAuctionRound;
use Modules\Circular\Domain\Models\CirBid;
use Modules\Circular\Domain\Models\CirListing;
use Modules\Circular\Domain\Models\CirOrder;
use Modules\CloudKitchen\Domain\Models\CateringSubscription;
use Modules\CloudKitchen\Domain\Models\CloudKitchen;
use Modules\Core\Domain\Models\AuditLog;
use Modules\Core\Domain\Models\CoreAiAnomalyQuarantine;
use Modules\Core\Domain\Models\CoreAiStockRecommendation;
use Modules\Core\Domain\Models\CoreBoundaryViolation;
use Modules\Core\Domain\Models\CoreMobileDevice;
use Modules\Core\Domain\Models\CoreTask;
use Modules\Core\Domain\Models\PartnerWebhook;
use Modules\Core\Domain\Models\Role;
use Modules\Core\Domain\Models\SimRun;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Core\Domain\Models\WebhookDelivery;
use Modules\Crypto\Domain\Enums\AlertCondition;
use Modules\Crypto\Domain\Models\CryptoAlert;
use Modules\Crypto\Domain\Models\CryptoAsset;
use Modules\Crypto\Domain\Models\CryptoPriceTick;
use Modules\Distribution\Domain\Models\Distributor;
use Modules\Egy\Domain\Models\PpaContract;
use Modules\Egy\Domain\Models\UtilityConsolidatedInvoice;
use Modules\EnterpriseFinance\Domain\Models\EfCapexProposal;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseBudget;
use Modules\EnterpriseFinance\Domain\Models\SyndicatedFacility;
use Modules\Finance\Domain\Models\Loan;
use Modules\Hcm\Domain\Models\Gig\GovBounty;
use Modules\Hcm\Domain\Models\Gig\GovBountyClaim;
use Modules\Hospital\Domain\Models\Admission;
use Modules\Hospital\Domain\Models\ClinicalTrial;
use Modules\Hospital\Domain\Models\CodeBlueEvent;
use Modules\Hospital\Domain\Models\ImagingStudy;
use Modules\Hospital\Domain\Models\OutpatientEncounter;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hotel\database\seeders\HotelDemoSeeder;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Insurance\Domain\Models\InsuranceProduct;
use Modules\Logistics\Domain\Enums\LocationType;
use Modules\Logistics\Domain\Models\Autonomous\DroneMission;
use Modules\Logistics\Domain\Models\Autonomous\DroneUnit;
use Modules\Logistics\Domain\Models\Autonomous\TempBreachHold;
use Modules\Logistics\Domain\Models\Carrier;
use Modules\Logistics\Domain\Models\Claim;
use Modules\Logistics\Domain\Models\Container;
use Modules\Logistics\Domain\Models\ContainerDwell;
use Modules\Logistics\Domain\Models\CustomsDeclaration;
use Modules\Logistics\Domain\Models\Driver;
use Modules\Logistics\Domain\Models\HubOperator;
use Modules\Logistics\Domain\Models\Lane;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Reverse\ReverseOrder;
use Modules\Logistics\Domain\Models\Schedule;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\ShipmentException;
use Modules\Logistics\Domain\Models\ShipmentLeg;
use Modules\Mall\database\seeders\MallSeeder;
use Modules\Mall\Domain\Enums\TenantCategory;
use Modules\Mall\Domain\Models\Invoice;
use Modules\Mall\Domain\Models\Lease;
use Modules\Mall\Domain\Models\Property;
use Modules\Mall\Domain\Models\Tenant;
use Modules\Mall\Domain\Models\VoucherTemplate;
use Modules\Mall\Domain\Models\WorkOrder;
use Modules\Manufacturing\Domain\Models\C2m\C2mCustomOrder;
use Modules\Manufacturing\Domain\Models\C2m\VmiReplenishment;
use Modules\Med\Domain\Models\MedIpLicense;
use Modules\Med\Domain\Models\MedRoyaltyStatement;
use Modules\Mining\Domain\Enums\FuelAnomalyCaseStatus;
use Modules\Mining\Domain\Enums\HaulCycleStatus;
use Modules\Mining\Domain\Enums\MinePlanStatus;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningFuelAnomalyCase;
use Modules\Mining\Domain\Models\MiningHaulCycle;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningPlan;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mobility\Ev\Domain\Models\EvCharger;
use Modules\Mobility\Ev\Domain\Models\EvSession;
use Modules\Mobility\Ev\Domain\Models\EvStation;
use Modules\Mobility\Fleet\Domain\Models\FleetContract;
use Modules\Mobility\Telematics\Domain\Models\TelematicsDevice;
use Modules\Partner\Domain\Models\Partner;
use Modules\Platform\Domain\Models\ChangeRequest;
use Modules\Platform\Domain\Models\ReleaseTrain;
use Modules\Platform\Domain\Models\SandboxEnvironment;
use Modules\Proptech\Domain\Models\Bim\BimModel;
use Modules\Proptech\Domain\Models\BuildingZone;
use Modules\Proptech\Domain\Models\Flex\FlexBooking;
use Modules\Proptech\Domain\Models\Flex\FlexSpace;
use Modules\Resto\database\seeders\RestoMenuSeeder;
use Modules\Resto\Domain\Models\CateringOrder;
use Modules\Resto\Domain\Models\Delivery;
use Modules\Resto\Domain\Models\DisplayTray;
use Modules\Resto\Domain\Models\Ingredient;
use Modules\Resto\Domain\Models\MenuItem;
use Modules\Resto\Domain\Models\Order;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\PurchaseOrder;
use Modules\Resto\Domain\Models\RestoTable;
use Modules\Resto\Domain\Models\StockCount;
use Modules\Resto\Domain\Models\StockTransfer;
use Modules\Resto\Domain\Models\TableSession;
use Modules\Rwa\Domain\Models\RwaAsset;
use Modules\Store\Domain\Enums\OrderStatus;
use Modules\Store\Domain\Models\Product;
use Modules\Supplier\Domain\Models\Supplier;
use Modules\Syariah\Domain\Models\MurabahahContract;
use Modules\Tlx\Domain\Models\IotConnectivityInvoice;
use Modules\Trade\Domain\Models\Bidding\BiddingAgent;
use Modules\Trade\Domain\Models\Bidding\BidRun;
use Modules\Trade\Domain\Models\Bidding\BidSubmission;
use Modules\TradeFinance\Domain\Models\Clearing\ClearingEscrow;
use Modules\TradeFinance\Domain\Models\CrossBorder\CrossBorderEscrow;
use Modules\TradeFinance\Domain\Models\LetterOfCredit;
use Modules\Vending\Domain\Models\VendingUnit;
use Modules\Venue\Domain\Models\CrowdCrushIncident;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTableBooking;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Wealth\Domain\Models\WmProfile;

/**
 * Route authorization mapping (PROGRESS R0.9, KONSEP §A14.3).
 *
 * Maps route names (or "METHOD uri") to:
 * - "public": publicly accessible
 * - "auth": requires authenticated user
 * - ["roles" => [...], "params" => fn () => [...]]
 */
$restoSeedHelper = function (): void {
    if (! MenuItem::exists()) {
        (new RestoMenuSeeder)->run();
    }
    $outlet = Outlet::first();
    $item = MenuItem::first();
    $table = RestoTable::first();

    $supplier = Modules\Resto\Domain\Models\Supplier::firstOrCreate(
        ['name' => 'Supplier Test'],
        ['contact' => '081', 'terms_days' => 30, 'is_active' => true]
    );

    DisplayTray::firstOrCreate(
        ['outlet_id' => $outlet->id],
        ['menu_item_id' => $item->id, 'cooked_at' => now(), 'expires_at' => now()->addHours(4), 'status' => 'displayed', 'portions_remaining' => 5]
    );

    TableSession::firstOrCreate(
        ['outlet_id' => $outlet->id],
        ['table_id' => $table?->id ?? 1, 'opened_by' => 1, 'session_code' => 'SES-001', 'guest_count' => 2, 'status' => 'open', 'opened_at' => now()]
    );

    $order = Order::firstOrCreate(
        ['outlet_id' => $outlet->id],
        ['order_number' => 'ORD-TEST-001', 'channel' => 'dine_in', 'status' => 'pending', 'subtotal' => 50000, 'tax' => 5000, 'total' => 55000]
    );

    PurchaseOrder::firstOrCreate(
        ['outlet_id' => $outlet->id],
        ['number' => 'PO-TEST-001', 'supplier_id' => $supplier->id, 'status' => 'draft', 'created_by' => 1]
    );

    StockTransfer::firstOrCreate(
        ['from_outlet_id' => $outlet->id],
        ['number' => 'TRF-TEST-001', 'to_outlet_id' => $outlet->id, 'status' => 'draft']
    );

    StockCount::firstOrCreate(
        ['outlet_id' => $outlet->id],
        ['date' => now()->toDateString(), 'status' => 'draft', 'counted_by' => 1]
    );

    Delivery::firstOrCreate(
        ['outlet_id' => $outlet->id],
        ['order_id' => $order->id, 'delivery_number' => 'DEL-001', 'status' => 'preparing', 'recipient_name' => 'Budi', 'recipient_phone' => '081', 'delivery_address' => 'Jl Test', 'fee' => 10000]
    );

    CateringOrder::firstOrCreate(
        ['outlet_id' => $outlet->id],
        ['number' => 'CAT-001', 'customer_name' => 'Budi', 'customer_phone' => '081', 'event_date' => now()->addDays(5), 'event_time' => '12:00:00', 'pax' => 50, 'delivery_address' => 'Jl Test', 'subtotal' => 1000000, 'grand_total' => 1000000, 'status' => 'inquiry']
    );
};

$restoManagerSetup = function (User $user) use ($restoSeedHelper): void {
    $restoSeedHelper();
    if ($user->hasRole('outlet_manager')) {
        $outlet = Outlet::first();
        if ($outlet !== null) {
            DB::table('resto_staff_assignments')->updateOrInsert(
                ['user_id' => $user->id, 'outlet_id' => $outlet->id],
                ['role' => 'outlet_manager', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
};

$logisticsSeedHelper = function () {
    $hubA = Location::firstOrCreate(
        ['code' => 'HUB-BDJ'],
        [
            'name' => 'Hub Banjarmasin',
            'type' => LocationType::HUB,
            'city' => 'Banjarmasin',
            'province' => 'Kalimantan Selatan',
            'country' => 'ID',
            'lat_e6' => -3300000,
            'lng_e6' => 114500000,
            'timezone' => 'Asia/Makassar',
            'min_connection_minutes' => 60,
            'is_active' => true,
        ]
    );

    $hubB = Location::firstOrCreate(
        ['code' => 'HUB-BJB'],
        [
            'name' => 'Hub Banjarbaru',
            'type' => LocationType::HUB,
            'city' => 'Banjarbaru',
            'province' => 'Kalimantan Selatan',
            'country' => 'ID',
            'lat_e6' => -3400000,
            'lng_e6' => 114800000,
            'timezone' => 'Asia/Makassar',
            'min_connection_minutes' => 60,
            'is_active' => true,
        ]
    );

    $lane = Lane::firstOrCreate(
        ['origin_id' => $hubA->id, 'destination_id' => $hubB->id, 'mode' => 'road'],
        ['distance_m' => 35000, 'standard_transit_minutes' => 90, 'is_active' => true]
    );

    $shipper = User::firstOrCreate(
        ['email' => 'shipper_helper@autoserve.test'],
        ['name' => 'Shipper Helper', 'password' => 'secret', 'role' => 'shipper']
    );

    $shipment = Shipment::firstOrCreate(
        ['tracking_number' => 'LGX-SEED-0001'],
        [
            'shipper_id' => $shipper->id,
            'consignee_name' => 'Penerima Helper',
            'consignee_phone' => '081234567890',
            'consignee_address' => ['street' => 'Jl. A. Yani', 'city' => 'Banjarbaru'],
            'origin_location_id' => $hubA->id,
            'destination_location_id' => $hubB->id,
            'service_level' => 'regular',
            'mode' => 'road',
            'payment_terms' => 'prepaid',
            'status' => 'booked',
            'total_chargeable_weight_g' => 2000,
            'total_amount_idr' => 25000,
            'booked_at' => now(),
        ]
    );

    $carrier = Carrier::firstOrCreate(
        ['code' => 'CRR-HELPER'],
        ['name' => 'Barito Carrier Helper', 'mode' => 'road', 'payment_terms_days' => 7, 'is_active' => true]
    );

    $leg = ShipmentLeg::firstOrCreate(
        ['shipment_id' => $shipment->id, 'leg_sequence' => 1],
        ['mode' => 'road', 'origin_location_id' => $hubA->id, 'destination_location_id' => $hubB->id, 'estimated_departure' => now(), 'estimated_arrival' => now()->addHours(2), 'status' => 'pending']
    );

    $claim = Claim::firstOrCreate(
        ['shipment_id' => $shipment->id],
        ['claim_number' => 'CLM-0001', 'created_by' => $shipper->id, 'claim_type' => 'damage', 'claimed_amount_idr' => 50000, 'cap_amount_idr' => 50000, 'description' => 'Rusak', 'status' => 'draft']
    );

    $schedule = Schedule::firstOrCreate(
        ['schedule_number' => 'SCH-0001'],
        [
            'mode' => 'road',
            'origin_location_id' => $hubA->id,
            'destination_location_id' => $hubB->id,
            'etd' => now()->addDay(),
            'eta' => now()->addDay()->addHours(2),
            'cutoff_at' => now()->addHours(12),
            'status' => 'scheduled',
            'cap_weight_kg' => '5000.000',
            'cap_volume_dm3' => 14000,
        ]
    );

    $container = Container::firstOrCreate(
        ['container_number' => 'CSQU3054383'],
        ['size_type' => '22G1', 'tare_kg' => 2200, 'max_gross_kg' => 30480, 'status' => 'available', 'current_location_id' => $hubA->id]
    );

    $dwell = ContainerDwell::firstOrCreate(
        ['container_id' => $container->id, 'shipment_id' => $shipment->id],
        ['shipper_id' => $shipper->id, 'location_id' => $hubA->id, 'kind' => 'demurrage', 'started_at' => now()->subDays(5), 'status' => 'open']
    );

    $declaration = CustomsDeclaration::firstOrCreate(
        ['shipment_id' => $shipment->id],
        ['declaration_number' => 'PIB-0001', 'type' => 'PIB', 'status' => 'draft', 'customs_value_idr' => 1000000, 'lines' => [], 'total_duty_idr' => 100000, 'submitted_at' => now()]
    );

    $exception = ShipmentException::firstOrCreate(
        ['shipment_id' => $shipment->id],
        ['reported_by' => $shipper->id, 'type' => 'damaged', 'severity' => 'critical', 'description' => 'Test exception', 'status' => 'open', 'detected_at' => now()]
    );

    $reportDir = storage_path('app/logistics/bulk_reports');
    if (! is_dir($reportDir)) {
        mkdir($reportDir, 0755, true);
    }
    file_put_contents($reportDir.'/batch-test-01_errors.csv', "row,error\n1,Invalid phone\n");

    return compact('hubA', 'hubB', 'lane', 'shipment', 'carrier', 'leg', 'claim', 'schedule', 'dwell', 'declaration', 'exception');
};

$driverSetup = function (User $user) use ($logisticsSeedHelper): void {
    $d = $logisticsSeedHelper();
    $driverUser = $user->isDriver() ? $user : User::firstOrCreate(
        ['email' => 'driver_seed@autoserve.test'],
        ['name' => 'Driver Seed', 'password' => 'secret', 'role' => 'driver']
    );
    Driver::firstOrCreate(
        ['user_id' => $driverUser->id],
        [
            'driver_number' => 'DRV-'.$driverUser->id,
            'home_hub_id' => $d['hubA']->id,
            'license_class' => 'SIM B1 Umum',
            'license_expiry' => now()->addYear()->toDateString(),
            'status' => 'available',
        ]
    );
};

$hubOperatorSetup = function (User $user) use ($logisticsSeedHelper): void {
    if ($user->isHubOperator()) {
        $d = $logisticsSeedHelper();
        HubOperator::firstOrCreate(
            ['user_id' => $user->id],
            ['hub_id' => $d['hubA']->id, 'is_active' => true]
        );
    }
};

$storeAutoServeSeedHelper = function (): array {
    $owner = User::firstOrCreate(
        ['id' => 1],
        ['name' => 'Arch User', 'email' => 'arch@test.test', 'password' => 'secret', 'role' => 'customer']
    );

    $brand = Brand::firstOrCreate(
        ['slug' => 'toyota-arch'],
        ['name' => 'Toyota Arch', 'country' => 'Japan', 'category' => 'jdm']
    );

    $car = Car::firstOrCreate(
        ['slug' => 'corolla-arch'],
        ['brand_id' => $brand->id, 'model' => 'Corolla Arch', 'year_start' => 2022, 'body_type' => 'sedan', 'fuel_type' => 'gasoline', 'is_active' => true]
    );

    $product = Product::firstOrCreate(
        ['slug' => 'product-arch-test'],
        [
            'uuid' => (string) Str::uuid(),
            'sku' => 'SKU-ARCH-001',
            'name' => 'Product Arch Test',
            'price' => 150000,
            'cached_stock' => 10,
            'is_listed' => true,
        ]
    );

    $order = Modules\Store\Domain\Models\Order::firstOrCreate(
        ['number' => 'ORD-ARCH-001'],
        [
            'user_id' => $owner->id,
            'status' => OrderStatus::PENDING_PAYMENT->value,
            'total_amount' => 150000,
        ]
    );

    $service = Service::firstOrCreate(
        ['name' => 'Servis Arch Test'],
        ['price' => 150000, 'is_active' => true]
    );

    $sparepart = Sparepart::firstOrCreate(
        ['code' => 'SP-ARCH-001'],
        ['name' => 'Sparepart Arch Test', 'price' => 50000, 'stock' => 10, 'unit' => 'pcs', 'is_active' => true]
    );

    $booking = Booking::firstOrCreate(
        ['booking_code' => 'SRV-ARCH-001'],
        [
            'customer_id' => $owner->id,
            'service_id' => $service->id,
            'plate_number' => 'B1234ARC',
            'vehicle_brand' => 'Toyota',
            'complaint' => 'Bunyi mesin',
            'booking_date' => now()->toDateString(),
            'status' => 'pending',
            'service_fee' => 150000,
            'total_cost' => 150000,
        ]
    );

    $estimate = Estimate::firstOrCreate(
        ['booking_id' => $booking->id],
        [
            'items' => [['type' => 'service', 'ref_id' => $service->id, 'name' => 'Jasa', 'qty' => 1, 'unit_price' => 100000, 'subtotal' => 100000]],
            'status' => EstimateStatus::Sent,
            'total' => 100000,
            'service_total' => 100000,
            'parts_total' => 0,
            'note' => 'Estimasi Arch Test',
        ]
    );

    return compact('brand', 'car', 'product', 'order', 'service', 'sparepart', 'booking', 'estimate');
};

$storeAutoServeCustomerSetup = function (User $user) use ($storeAutoServeSeedHelper): void {
    $d = $storeAutoServeSeedHelper();
    if ($user->isCustomer()) {
        $d['order']->user_id = $user->id;
        $d['order']->save();

        $d['booking']->customer_id = $user->id;
        $d['booking']->save();
    }
};

$financeCryptoSeedHelper = function (): array {
    $owner = User::firstOrCreate(
        ['id' => 1],
        ['name' => 'Arch User', 'email' => 'arch@test.test', 'password' => 'secret', 'role' => 'customer']
    );

    $asset = CryptoAsset::firstOrCreate(
        ['symbol' => 'BTC'],
        ['name' => 'Bitcoin', 'decimals' => 8, 'volatility' => 0.03, 'is_active' => true]
    );

    CryptoPriceTick::firstOrCreate(
        ['asset_id' => $asset->id],
        ['price_idr' => '1000000000', 'recorded_at' => now()]
    );

    $brand = Brand::firstOrCreate(
        ['slug' => 'toyota-fin'],
        ['name' => 'Toyota Finance', 'country' => 'Japan', 'category' => 'jdm']
    );

    $car = Car::firstOrCreate(
        ['slug' => 'corolla-fin'],
        ['brand_id' => $brand->id, 'model' => 'Corolla Finance', 'year_start' => 2022, 'body_type' => 'sedan', 'fuel_type' => 'gasoline', 'is_active' => true]
    );

    $product = Product::firstOrCreate(
        ['slug' => 'lexus-lc300-fin'],
        [
            'uuid' => (string) Str::uuid(),
            'sku' => 'SKU-FIN-001',
            'name' => 'Lexus LC 300 Finance',
            'price' => 300000000,
            'cached_stock' => 5,
            'is_listed' => true,
        ]
    );

    $loan = Loan::firstOrCreate(
        ['uuid' => '99999999-9999-9999-9999-999999999999'],
        [
            'user_id' => $owner->id,
            'status' => 'active',
            'principal' => 100000000,
            'outstanding_principal' => 100000000,
            'interest_rate_annual' => 0.08,
            'tenor_months' => 12,
            'collateral_asset_id' => $asset->id,
            'collateral_qty' => '0.2',
            'ltv_at_open' => 0.5,
            'opened_at' => now(),
        ]
    );

    $alert = CryptoAlert::firstOrCreate(
        ['user_id' => $owner->id, 'asset_id' => $asset->id],
        [
            'condition' => AlertCondition::ABOVE,
            'target_price_idr' => '1100000000',
            'is_triggered' => false,
        ]
    );

    return compact('owner', 'asset', 'product', 'loan', 'alert');
};

$financeCryptoCustomerSetup = function (User $user) use ($financeCryptoSeedHelper): void {
    $d = $financeCryptoSeedHelper();
    if ($user->isCustomer()) {
        $d['loan']->user_id = $user->id;
        $d['loan']->save();

        $d['alert']->user_id = $user->id;
        $d['alert']->save();
    }
};

$distributorParamHelper = function (): array {
    $dist = Distributor::firstOrCreate(
        ['code' => 'DST-DYNAMIC-01'],
        ['name' => 'Dist Dynamic', 'tier' => 'bronze', 'is_active' => true]
    );

    return ['distributor' => $dist->id];
};

$partnerParamHelper = function (): array {
    $ptn = Partner::firstOrCreate(
        ['code' => 'PTN-DYNAMIC-01'],
        ['name' => 'Partner Dynamic', 'kind' => 'strategic', 'status' => 'active']
    );

    return ['partner' => $ptn->id];
};

if (! function_exists('routeRolesClearingEscrow')) {
    /** Fixture escrow clearing untuk matriks otorisasi rute (route-roles). */
    function routeRolesClearingEscrow(): ClearingEscrow
    {
        $existing = ClearingEscrow::where('escrow_code', 'CLR-ROUTE-TEST')->first();
        if ($existing !== null) {
            return $existing;
        }

        $importer = User::factory()->create(['role' => 'importer']);
        $exporter = User::factory()->create(['role' => 'exporter']);

        $lc = new LetterOfCredit;
        $lc->forceFill([
            'lc_number' => 'LC-ROUTE-TEST', 'type' => 'sight', 'issuing_bank' => 'Bank Uji', 'advising_bank' => 'Bank Uji',
            'applicant_name' => 'Importir Uji', 'beneficiary_name' => 'Eksportir Uji', 'currency' => 'USD',
            'amount_foreign' => 100000, 'amount_functional_idr' => 1600000000, 'issue_date' => '2026-11-01',
            'expiry_date' => '2027-11-01', 'tenor_days' => 0, 'status' => 'issued',
        ])->save();

        return ClearingEscrow::create([
            'escrow_code' => 'CLR-ROUTE-TEST', 'letter_of_credit_id' => $lc->id, 'importer_user_id' => $importer->id,
            'exporter_user_id' => $exporter->id, 'usdt_micro' => 1_000_000, 'deposit_fx_rate_idr' => 16200,
            'status' => 'DEPOSITED', 'deposit_idempotency_key' => 'route-roles-clearing', 'deposited_at' => now(),
        ]);
    }
}

if (! function_exists('routeRolesMurabahahContract')) {
    /** Fixture akad murabahah untuk matriks otorisasi rute (route-roles). */
    function routeRolesMurabahahContract(): MurabahahContract
    {
        $existing = MurabahahContract::where('contract_code', 'MRB-ROUTE-TEST')->first();
        if ($existing !== null) {
            return $existing;
        }

        $nasabah = User::factory()->create(['role' => 'nasabah']);

        return MurabahahContract::create([
            'contract_code' => 'MRB-ROUTE-TEST',
            'customer_user_id' => $nasabah->id,
            'supplier_party_ref' => 1,
            'asset_description' => 'Mesin Produksi Murabahah Uji',
            'cost_price_idr' => 100_000_000,
            'margin_amount_idr' => 18_000_000,
            'total_selling_price_idr' => 118_000_000,
            'tenor_months' => 12,
            'status' => 'PROPOSED',
            'proposed_by_user_id' => $nasabah->id,
            'idempotency_key' => 'route-roles-murabahah',
        ]);
    }
}

return [
    'GET /' => 'public',
    'GET up' => 'public',
    'POST confirm-password' => 'auth',
    'POST login' => 'public',
    'POST register' => 'public',
    'activity.index' => 'auth',
    'admin.audit-logs.index' => ['roles' => ['admin']],
    'admin.audit-logs.show' => [
        'roles' => ['admin'],
        'params' => fn () => ['auditLog' => AuditLog::firstOrCreate(['action' => 'test_action'], ['entity_type' => 'test', 'entity_id' => 1])->id],
    ],
    'admin.ai.calculate-pricing' => ['roles' => ['admin']],
    'admin.ai.generate-stock-recommendation' => ['roles' => ['admin']],
    'admin.ai.index' => ['roles' => ['admin']],
    'admin.ai.release-quarantine' => [
        'roles' => ['admin'],
        'params' => fn () => ['quarantine' => CoreAiAnomalyQuarantine::firstOrCreate(['quarantine_code' => 'QRN-TEST-SEED'], [
            'transaction_ref' => 'TX-TEST-ROLES',
            'customer_ref' => 'CUST-TEST',
            'amount_idr' => 1000000,
            'risk_score' => 0.85,
            'anomaly_reasons' => ['test'],
            'status' => 'QUARANTINED',
            'quarantined_at' => now(),
        ])->id],
    ],
    'admin.ai.screen-anomaly' => ['roles' => ['admin']],
    'admin.design-system.releases.index' => ['roles' => ['admin']],
    'admin.design-system.releases.create' => ['roles' => ['admin']],
    'admin.design-system.releases.store' => ['roles' => ['admin']],
    'admin.design-system.releases.transition' => [
        'roles' => ['admin'],
        'params' => fn () => ['name' => 'button'],
    ],
    'admin.design-system.research.index' => ['roles' => ['admin']],
    'admin.design-system.research.export-csv' => ['roles' => ['admin']],
    'admin.group-dashboard' => ['roles' => ['admin']],
    'admin.health.index' => ['roles' => ['admin']],
    'admin.health.run' => ['roles' => ['admin']],
    'admin.ledger.adjust' => ['roles' => ['admin'], 'params' => fn () => ['account' => 1]],
    'admin.ledger.freeze' => ['roles' => ['admin'], 'params' => fn () => ['account' => 1]],
    'admin.ledger.index' => ['roles' => ['admin']],
    'admin.ledger.show' => ['roles' => ['admin'], 'params' => fn () => ['transaction' => 1]],
    'admin.mobile.index' => ['roles' => ['admin']],
    'admin.mobile.record-action' => ['roles' => ['admin']],
    'admin.mobile.revoke-device' => [
        'roles' => ['admin'],
        'params' => fn () => ['device' => CoreMobileDevice::firstOrCreate(['device_uuid' => 'DEV-TEST-ROUTE-ROLES'], [
            'role_scope' => 'driver',
            'app_version' => '1.0.0',
            'platform' => 'ANDROID',
        ])->id],
    ],
    'admin.mobile.submit-epod' => ['roles' => ['admin']],
    'admin.mobile.sync-batch' => ['roles' => ['admin']],
    'admin.rbac.assign' => ['roles' => ['admin']],
    'admin.rbac.index' => ['roles' => ['admin']],
    'admin.rbac.revoke' => ['roles' => ['admin']],
    'admin.rbac.role' => [
        'roles' => ['admin'],
        'params' => fn () => ['role' => Role::firstOrCreate(['name' => 'admin'], ['label' => 'Administrator', 'is_system' => true])->id],
    ],
    'admin.rbac.toggle-permission' => ['roles' => ['admin'], 'params' => fn () => ['role' => 1]],
    'admin.rbac.user' => [
        'roles' => ['admin'],
        'params' => fn () => ['user' => User::firstOrCreate(['email' => 'admin_test@test.test'], ['name' => 'Admin Test', 'password' => 'secret', 'role' => 'admin'])->id],
    ],
    'admin.resilience.check-residency' => ['roles' => ['admin']],
    'admin.resilience.execute-drill' => ['roles' => ['admin']],
    'admin.resilience.index' => ['roles' => ['admin']],
    'admin.resilience.rotate-kms' => ['roles' => ['admin']],
    'admin.simulation.advance' => ['roles' => ['admin']],
    'admin.simulation.index' => ['roles' => ['admin']],
    'admin.simulation.publish-spine' => ['roles' => ['admin']],
    'admin.simulation.record-twin' => ['roles' => ['admin']],
    'admin.simulation.scale-provision' => ['roles' => ['admin']],
    'agency.accruals.clawback' => ['roles' => ['admin', 'procurement']],
    'agency.accruals.release' => ['roles' => ['admin', 'procurement']],
    'agency.accruals.store' => ['roles' => ['admin', 'procurement']],
    'agency.agents.store' => ['roles' => ['admin', 'procurement']],
    'agency.agents.transition' => ['roles' => ['admin', 'procurement'], 'params' => fn () => ['agent' => 1]],
    'agency.attributions.store' => ['roles' => ['admin', 'procurement']],
    'agency.brands.store' => ['roles' => ['admin', 'procurement'], 'params' => fn () => ['agent' => 1]],
    'agency.certifications.store' => ['roles' => ['admin', 'procurement'], 'params' => fn () => ['agent' => 1]],
    'agency.compliance.store' => ['roles' => ['admin', 'procurement'], 'params' => fn () => ['agent' => 1]],
    'agency.fraud.store' => ['roles' => ['admin', 'procurement'], 'params' => fn () => ['agent' => 1]],
    'agency.index' => ['roles' => ['admin', 'agent', 'auditor', 'procurement']],
    'agency.leads.convert' => ['roles' => ['admin', 'procurement'], 'params' => fn () => ['lead' => 1]],
    'agency.leads.store' => ['roles' => ['admin', 'procurement']],
    'agency.payouts.approve' => ['roles' => ['admin', 'procurement'], 'params' => fn () => ['payout' => 1]],
    'agency.payouts.store' => ['roles' => ['admin', 'procurement'], 'params' => fn () => ['agent' => 1]],
    'agency.schemes.store' => ['roles' => ['admin', 'procurement'], 'params' => fn () => ['agent' => 1]],
    'agency.show' => ['roles' => ['admin', 'agent', 'auditor', 'procurement'], 'params' => fn () => ['agent' => 1]],
    'agri.index' => ['roles' => ['admin', 'farmer', 'procurement']],
    'api.global-search' => 'auth',
    'api.v1.integration.ping' => 'public',
    'api.v1.logistics.quotes.store' => ['roles' => ['admin', 'customer', 'dispatcher', 'logistics_admin', 'shipper']],
    'api.v1.logistics.shipments.index' => ['roles' => ['admin', 'auditor', 'dispatcher', 'driver', 'hub_operator', 'logistics_admin', 'shipper']],
    'api.v1.logistics.shipments.show' => ['roles' => ['admin', 'auditor', 'dispatcher', 'driver', 'hub_operator', 'logistics_admin', 'shipper'], 'params' => fn () => ['tracking_number' => $logisticsSeedHelper()['shipment']->tracking_number]],
    'api.v1.logistics.shipments.store' => ['roles' => ['admin', 'dispatcher', 'logistics_admin', 'shipper']],
    'api.v1.logistics.tracking.show' => ['protection' => 'public', 'params' => fn () => ['tracking_number' => 'TRK-001']],
    'api.v3.executive.kpis' => 'public',
    'api.v3.executive.snapshot' => 'public',
    'api.v3.mobile.sync.conflicts' => 'public',
    'api.v3.mobile.sync.pull' => 'public',
    'api.v3.mobile.sync.push' => 'public',
    'api.v3.partner.webhooks.index' => 'public',
    'api.v3.partner.webhooks.replay' => [
        'roles' => ['admin'],
        'params' => function () {
            $webhook = PartnerWebhook::firstOrCreate(
                ['partner_name' => 'Route Roles DLQ Replay Partner'],
                [
                    'tenant_id' => 'tenant-default',
                    'endpoint_url' => 'https://partner.example.com/webhook',
                    'hmac_secret' => 'test-secret-key-32-chars-long!!',
                    'is_active' => true,
                ]
            );

            $delivery = WebhookDelivery::firstOrCreate(
                [
                    'webhook_id' => $webhook->id,
                    'event_name' => 'test.event.replay',
                ],
                [
                    'payload' => json_encode(['foo' => 'bar']),
                    'attempt' => 1,
                    'status' => 'DEAD_LETTER_QUEUE',
                    'http_status' => 500,
                ]
            );

            return ['id' => $delivery->id];
        },
    ],
    'api.v3.partner.webhooks.store' => 'public',
    'asset.audit' => ['roles' => ['admin', 'asset_manager']],
    'asset.checkin' => ['roles' => ['admin', 'asset_manager'], 'params' => fn () => ['asset' => 1, 'assignment' => 1]],
    'asset.checkout' => ['roles' => ['admin', 'asset_manager'], 'params' => fn () => ['asset' => 1]],
    'asset.create' => ['roles' => ['admin', 'asset_manager']],
    'asset.depreciate' => ['roles' => ['admin', 'asset_manager'], 'params' => fn () => ['asset' => 1]],
    'asset.depreciation.index' => ['roles' => ['admin', 'asset_manager']],
    'asset.depreciation.run' => ['roles' => ['admin', 'asset_manager']],
    'asset.disposal.finalize' => ['roles' => ['admin', 'asset_manager'], 'params' => fn () => ['asset' => 1, 'disposal' => 1]],
    'asset.disposal.request' => ['roles' => ['admin', 'asset_manager'], 'params' => fn () => ['asset' => 1]],
    'asset.index' => ['roles' => ['admin', 'asset_manager']],
    'asset.insurance.store' => ['roles' => ['admin', 'asset_manager'], 'params' => fn () => ['asset' => 1]],
    'asset.leases.pay' => ['roles' => ['admin', 'asset_manager'], 'params' => fn () => ['lease' => 1]],
    'asset.leases.store' => ['roles' => ['admin', 'asset_manager'], 'params' => fn () => ['asset' => 1]],
    'asset.move' => ['roles' => ['admin', 'asset_manager'], 'params' => fn () => ['asset' => 1]],
    'asset.move.approve' => ['roles' => ['admin', 'asset_manager'], 'params' => fn () => ['asset' => 1, 'location' => 1]],
    'asset.revaluation.apply' => ['roles' => ['admin', 'asset_manager'], 'params' => fn () => ['asset' => 1, 'revaluation' => 1]],
    'asset.revaluation.request' => ['roles' => ['admin', 'asset_manager'], 'params' => fn () => ['asset' => 1]],
    'asset.scan' => ['roles' => ['admin', 'asset_manager']],
    'asset.scan.form' => ['roles' => ['admin', 'asset_manager']],
    'asset.show' => ['roles' => ['admin', 'asset_manager'], 'params' => fn () => ['asset' => 1]],
    'asset.store' => ['roles' => ['admin', 'asset_manager']],
    'asset.verify-chain' => ['roles' => ['admin', 'asset_manager']],
    'asset.work-orders.complete' => ['roles' => ['admin', 'asset_manager'], 'params' => fn () => ['asset' => 1, 'workOrder' => 1]],
    'asset.work-orders.store' => ['roles' => ['admin', 'asset_manager'], 'params' => fn () => ['asset' => 1]],
    'autodex.garage.index' => ['roles' => ['admin', 'auditor', 'customer']],
    'autodex.garage.toggle' => ['roles' => ['admin', 'customer'], 'params' => fn () => ['car' => $storeAutoServeSeedHelper()['car']->id]],
    'autodex.index' => ['roles' => ['admin', 'auditor', 'customer']],
    'autodex.show' => ['roles' => ['admin', 'auditor', 'customer'], 'params' => fn () => ['car' => $storeAutoServeSeedHelper()['car']->slug]],
    'autodex.wishlist.toggle' => ['roles' => ['admin', 'customer'], 'params' => fn () => ['car' => $storeAutoServeSeedHelper()['car']->id]],
    'b2b.index' => ['roles' => ['admin', 'b2b_buyer', 'procurement']],
    'bookings.addSparepart' => ['roles' => ['admin', 'mekanik'], 'params' => fn () => ['booking' => $storeAutoServeSeedHelper()['booking']->id]],
    'bookings.approveExtra' => ['roles' => ['admin', 'customer'], 'params' => fn () => ['booking' => $storeAutoServeSeedHelper()['booking']->id], 'setup' => $storeAutoServeCustomerSetup],
    'bookings.assign' => ['roles' => ['admin', 'mekanik'], 'params' => fn () => ['booking' => $storeAutoServeSeedHelper()['booking']->id]],
    'bookings.create' => ['roles' => ['admin', 'auditor', 'customer']],
    'bookings.invoice' => ['roles' => ['admin', 'auditor', 'customer', 'mekanik'], 'params' => fn () => ['booking' => $storeAutoServeSeedHelper()['booking']->id], 'setup' => $storeAutoServeCustomerSetup],
    'bookings.pay' => ['roles' => ['admin', 'customer'], 'params' => fn () => ['booking' => $storeAutoServeSeedHelper()['booking']->id], 'setup' => $storeAutoServeCustomerSetup],
    'bookings.refund' => ['roles' => ['admin'], 'params' => fn () => ['booking' => $storeAutoServeSeedHelper()['booking']->id]],
    'bookings.removeSparepart' => ['roles' => ['admin', 'mekanik'], 'params' => fn () => ['booking' => $storeAutoServeSeedHelper()['booking']->id, 'sparepart' => $storeAutoServeSeedHelper()['sparepart']->id]],
    'bookings.show' => ['roles' => ['admin', 'auditor', 'customer', 'mekanik'], 'params' => fn () => ['booking' => $storeAutoServeSeedHelper()['booking']->id], 'setup' => $storeAutoServeCustomerSetup],
    'bookings.store' => ['roles' => ['admin', 'customer']],
    'bookings.updateStatus' => ['roles' => ['admin', 'mekanik'], 'params' => fn () => ['booking' => $storeAutoServeSeedHelper()['booking']->id]],
    'boost.browser-logs' => 'public',
    'cloud_kitchen.index' => ['roles' => ['admin', 'kitchen', 'outlet_manager']],
    'cloud_kitchen.orders.store' => ['roles' => ['admin', 'kitchen', 'outlet_manager']],
    'cloud_kitchen.subscriptions.dispatch' => [
        'roles' => ['admin', 'kitchen', 'outlet_manager'],
        'params' => function () {
            $user = User::firstOrCreate(['id' => 1], ['name' => 'Arch User', 'email' => 'arch@test.test', 'password' => 'secret', 'role' => 'customer']);
            $sub = CateringSubscription::firstOrCreate(
                ['subscription_code' => 'SUB-ARCH-001'],
                ['user_id' => $user->id, 'package_name' => 'Demo Lunch', 'daily_quota' => 5, 'used_quota_today' => 0, 'monthly_fee_idr' => 1000000, 'daily_meal_price_idr' => 50000, 'starts_at' => now(), 'expires_at' => now()->addMonth(), 'status' => 'active']
            );
            CloudKitchen::firstOrCreate(
                ['kitchen_code' => 'CK-ARCH-01'],
                ['name' => 'Arch Central Kitchen', 'type' => 'central', 'hourly_capacity' => 100, 'current_hourly_load' => 0, 'service_radius_km' => 10, 'is_active' => true]
            );

            return ['subscription' => $sub->id];
        },
    ],
    'contract.advance.pay' => ['roles' => ['admin', 'contract_manager', 'legal'], 'params' => fn () => ['contract' => 1]],
    'contract.amend' => ['roles' => ['admin', 'contract_manager', 'legal'], 'params' => fn () => ['contract' => 1]],
    'contract.append-version' => ['roles' => ['admin', 'contract_manager', 'legal'], 'params' => fn () => ['contract' => 1]],
    'contract.attachment.destroy' => ['roles' => ['admin', 'contract_manager', 'legal'], 'params' => fn () => ['contract' => 1, 'attachment' => 1]],
    'contract.attachment.store' => ['roles' => ['admin', 'contract_manager', 'legal'], 'params' => fn () => ['contract' => 1]],
    'contract.clauses.create' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.clauses.edit' => ['roles' => ['admin', 'contract_manager', 'legal'], 'params' => fn () => ['clause' => 1]],
    'contract.clauses.index' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.clauses.store' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.clauses.update' => ['roles' => ['admin', 'contract_manager', 'legal'], 'params' => fn () => ['clause' => 1]],
    'contract.create' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.edit' => ['roles' => ['admin', 'contract_manager', 'legal'], 'params' => fn () => ['contract' => 1]],
    'contract.escalation.apply' => ['roles' => ['admin', 'contract_manager', 'legal'], 'params' => fn () => ['contract' => 1]],
    'contract.escalation.preview' => ['roles' => ['admin', 'contract_manager', 'legal'], 'params' => fn () => ['contract' => 1]],
    'contract.index' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.milestone.complete' => ['roles' => ['admin', 'contract_manager', 'legal'], 'params' => fn () => ['contract' => 1, 'milestone' => 1]],
    'contract.milestone.store' => ['roles' => ['admin', 'contract_manager', 'legal'], 'params' => fn () => ['contract' => 1]],
    'contract.obligations' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.party.add' => ['roles' => ['admin', 'contract_manager', 'legal'], 'params' => fn () => ['contract' => 1]],
    'contract.party.remove' => ['roles' => ['admin', 'contract_manager', 'legal'], 'params' => fn () => ['contract' => 1, 'party' => 1]],
    'contract.reports' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.risk.rescore' => ['roles' => ['admin', 'contract_manager', 'legal'], 'params' => fn () => ['contract' => 1]],
    'contract.schedule.build' => ['roles' => ['admin', 'contract_manager', 'legal'], 'params' => fn () => ['contract' => 1]],
    'contract.schedule.pay' => ['roles' => ['admin', 'contract_manager', 'legal'], 'params' => fn () => ['contract' => 1, 'schedule' => 1]],
    'contract.schedule.penalty.pay' => ['roles' => ['admin', 'contract_manager', 'legal'], 'params' => fn () => ['contract' => 1, 'schedule' => 1]],
    'contract.schedule.penalty.show' => ['roles' => ['admin', 'contract_manager', 'legal'], 'params' => fn () => ['contract' => 1, 'schedule' => 1]],
    'contract.schedule.penalty.waiver' => ['roles' => ['admin', 'contract_manager', 'legal'], 'params' => fn () => ['contract' => 1, 'schedule' => 1]],
    'contract.schedule.penalty.waiver.apply' => ['roles' => ['admin', 'contract_manager', 'legal'], 'params' => fn () => ['contract' => 1, 'schedule' => 1]],
    'contract.show' => ['roles' => ['admin', 'contract_manager', 'legal'], 'params' => fn () => ['contract' => 1]],
    'contract.sign-party' => ['roles' => ['admin', 'contract_manager', 'legal'], 'params' => fn () => ['contract' => 1, 'party' => 1]],
    'contract.store' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.submit-approval' => ['roles' => ['admin', 'contract_manager', 'legal'], 'params' => fn () => ['contract' => 1]],
    'contract.templates.create' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.templates.index' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.templates.store' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.transition' => ['roles' => ['admin', 'contract_manager', 'legal'], 'params' => fn () => ['contract' => 1]],
    'contract.update' => ['roles' => ['admin', 'contract_manager', 'legal'], 'params' => fn () => ['contract' => 1]],
    'contract.usage.sync' => ['roles' => ['admin', 'contract_manager', 'legal'], 'params' => fn () => ['contract' => 1]],
    'contract.version-diff' => ['roles' => ['admin', 'contract_manager', 'legal'], 'params' => fn () => ['contract' => 1]],
    'contract.versions' => ['roles' => ['admin', 'contract_manager', 'legal'], 'params' => fn () => ['contract' => 1]],
    'control_tower.index' => ['roles' => ['admin', 'logistics_admin', 'planner', 'auditor']],
    'control_tower.promises' => ['roles' => ['admin', 'logistics_admin', 'planner', 'auditor']],
    'crypto.alerts.destroy' => ['roles' => ['admin', 'customer', 'treasury'], 'params' => fn () => ['alert' => $financeCryptoSeedHelper()['alert']->id], 'setup' => $financeCryptoCustomerSetup],
    'crypto.alerts.index' => ['roles' => ['admin', 'auditor', 'customer', 'treasury']],
    'crypto.alerts.store' => ['roles' => ['admin', 'customer', 'treasury']],
    'crypto.api.chart' => ['protection' => 'public', 'params' => fn () => ['symbol' => 'BTC']],
    'crypto.api.prices' => 'public',
    'crypto.market.index' => 'public',
    'crypto.market.show' => ['protection' => 'public', 'params' => fn () => ['symbol' => 'BTC']],
    'crypto.portfolio.index' => ['roles' => ['admin', 'auditor', 'customer', 'treasury']],
    'crypto.trade.execute' => ['roles' => ['admin', 'customer', 'treasury']],
    'crypto.trade.quote' => ['roles' => ['admin', 'customer', 'treasury']],
    'dashboard' => 'auth',
    'design-system.index' => ['roles' => ['admin', 'design_system_owner', 'operator']],
    'design-system.show' => [
        'roles' => ['admin', 'design_system_owner', 'operator'],
        'params' => fn () => ['name' => 'button'],
    ],
    'design-system.releases.index' => ['roles' => ['admin', 'design_system_owner']],
    'design-system.releases.create' => ['roles' => ['admin', 'design_system_owner']],
    'design-system.releases.store' => ['roles' => ['admin', 'design_system_owner']],
    'design-system.releases.transition' => [
        'roles' => ['admin', 'design_system_owner'],
        'params' => fn () => ['name' => 'button'],
    ],
    'design-system.research.index' => ['roles' => ['admin', 'design_system_owner']],
    'design-system.research.export-csv' => ['roles' => ['admin', 'design_system_owner']],
    'user.theme-preference.update' => ['roles' => ['admin', 'design_system_owner', 'operator']],
    'distribution.coverages.store' => ['roles' => ['admin', 'procurement']],
    'distribution.distributors.store' => ['roles' => ['admin', 'procurement']],
    'distribution.index' => ['roles' => ['admin', 'auditor', 'distributor', 'procurement']],
    'distribution.invoices.pay' => ['roles' => ['admin', 'procurement']],
    'distribution.invoices.store' => ['roles' => ['admin', 'procurement']],
    'distribution.onboarding.approve' => ['roles' => ['admin', 'procurement'], 'params' => $distributorParamHelper],
    'distribution.onboarding.submit' => ['roles' => ['admin', 'procurement'], 'params' => $distributorParamHelper],
    'distribution.outlets.store' => ['roles' => ['admin', 'procurement'], 'params' => $distributorParamHelper],
    'distribution.portal.home' => [
        'roles' => ['admin', 'distributor'],
        'setup' => function (User $user): void {
            if (class_exists(Distributor::class)) {
                Distributor::firstOrCreate(
                    ['owner_user_id' => $user->id],
                    ['name' => 'Dist Test', 'code' => 'DST-'.$user->id, 'tier' => 'bronze', 'is_active' => true]
                );
            }
        },
    ],
    'distribution.scorecards.store' => ['roles' => ['admin', 'procurement']],
    'distribution.securities.store' => ['roles' => ['admin', 'procurement'], 'params' => $distributorParamHelper],
    'distribution.show' => [
        'roles' => ['admin', 'auditor', 'distributor', 'procurement'],
        'params' => $distributorParamHelper,
        'setup' => function (User $user): void {
            if ($user->role === 'distributor') {
                $dist = Distributor::firstOrCreate(
                    ['code' => 'DST-DYNAMIC-01'],
                    ['name' => 'Dist Dynamic', 'tier' => 'bronze', 'is_active' => true]
                );
                $dist->owner_user_id = $user->id;
                $dist->save();
            }
        },
    ],
    'distribution.sweep' => ['roles' => ['admin', 'procurement']],
    'distribution.targets.store' => ['roles' => ['admin', 'procurement']],
    'distribution.territories.store' => ['roles' => ['admin', 'procurement']],
    'distribution.tier.evaluate' => ['roles' => ['admin', 'procurement'], 'params' => $distributorParamHelper],
    'distribution.transition' => ['roles' => ['admin', 'procurement'], 'params' => $distributorParamHelper],
    'enterprise_finance.budgets' => ['roles' => ['admin', 'auditor', 'treasury']],
    'enterprise_finance.budget_lifecycle' => ['roles' => ['admin', 'auditor', 'treasury']],
    'enterprise_finance.compliance_calendar' => ['roles' => ['admin', 'auditor', 'treasury']],
    'enterprise_finance.index' => ['roles' => ['admin', 'auditor', 'treasury']],
    'enterprise_finance.ma_goodwill' => ['roles' => ['admin', 'auditor', 'treasury']],
    'enterprise_finance.reports' => ['roles' => ['admin', 'auditor', 'treasury']],
    'enterprise_finance.sod_matrix' => ['roles' => ['admin', 'auditor', 'treasury']],
    'enterprise_finance.tax_compliance' => ['roles' => ['admin', 'auditor', 'treasury']],
    'enterprise_finance.working_capital' => ['roles' => ['admin', 'auditor', 'treasury']],
    'epc.index' => ['roles' => ['admin', 'epc_manager', 'asset_manager']],
    'esg.index' => ['roles' => ['admin', 'esg_officer', 'auditor']],
    'ev.index' => ['roles' => ['admin', 'driver', 'ev_admin', 'station_operator']],
    'ev.chargers.reserve' => [
        'roles' => ['admin', 'driver', 'ev_admin', 'station_operator'],
        'params' => function () {
            $station = EvStation::firstOrCreate(['station_code' => 'SPKLU-PLN-01'], ['name' => 'SPKLU Sudirman', 'location_type' => 'PUBLIC', 'address' => 'Sudirman', 'latitude' => -6.2, 'longitude' => 106.8, 'is_active' => true]);
            $charger = EvCharger::firstOrCreate(['charger_code' => 'CHG-CCS2-01'], ['station_id' => $station->id, 'type' => 'DC_FAST', 'max_kw' => 150, 'status' => 'available']);

            return ['charger' => $charger->id];
        },
    ],
    'ev.chargers.checkin' => [
        'roles' => ['admin', 'driver', 'ev_admin', 'station_operator'],
        'params' => function () {
            $station = EvStation::firstOrCreate(['station_code' => 'SPKLU-PLN-01'], ['name' => 'SPKLU Sudirman', 'location_type' => 'PUBLIC', 'address' => 'Sudirman', 'latitude' => -6.2, 'longitude' => 106.8, 'is_active' => true]);
            $charger = EvCharger::firstOrCreate(['charger_code' => 'CHG-CCS2-01'], ['station_id' => $station->id, 'type' => 'DC_FAST', 'max_kw' => 150, 'status' => 'available']);

            return ['charger' => $charger->id];
        },
    ],
    'ev.sessions.complete' => [
        'roles' => ['admin', 'ev_admin', 'station_operator'],
        'params' => function () {
            $user = User::firstOrCreate(['email' => 'driver-route@example.com'], ['name' => 'Route Driver', 'password' => 'secret']);
            $vehicle = Vehicle::firstOrCreate(['vin' => 'VIN-ROUTE-01'], ['user_id' => $user->id, 'plate_number' => 'B 1111 EV', 'color' => 'White', 'odometer_km' => 1000, 'status' => 'active']);
            $station = EvStation::firstOrCreate(['station_code' => 'SPKLU-PLN-01'], ['name' => 'SPKLU Sudirman', 'location_type' => 'PUBLIC', 'address' => 'Sudirman', 'latitude' => -6.2, 'longitude' => 106.8, 'is_active' => true]);
            $charger = EvCharger::firstOrCreate(['charger_code' => 'CHG-CCS2-01'], ['station_id' => $station->id, 'type' => 'DC_FAST', 'max_kw' => 150, 'status' => 'available']);
            $session = EvSession::firstOrCreate(['session_code' => 'EV-SESS-ROUTE-01'], [
                'charger_id' => $charger->id,
                'vehicle_id' => $vehicle->id,
                'user_id' => $user->id,
                'status' => 'plugged_in',
            ]);

            return ['session' => $session->id];
        },
    ],
    'ev.stations.loadbalance' => [
        'roles' => ['admin', 'ev_admin', 'station_operator'],
        'params' => function () {
            $station = EvStation::firstOrCreate(['station_code' => 'SPKLU-PLN-01'], ['name' => 'SPKLU Sudirman', 'location_type' => 'PUBLIC', 'address' => 'Sudirman', 'latitude' => -6.2, 'longitude' => 106.8, 'is_active' => true]);

            return ['station' => $station->id];
        },
    ],
    'estimates.approve' => ['roles' => ['admin', 'customer'], 'params' => fn () => ['estimate' => $storeAutoServeSeedHelper()['estimate']->id], 'setup' => $storeAutoServeCustomerSetup],
    'estimates.receiveBackorder' => ['roles' => ['admin', 'mekanik'], 'params' => fn () => ['estimate' => $storeAutoServeSeedHelper()['estimate']->id]],
    'estimates.reject' => ['roles' => ['admin', 'customer'], 'params' => fn () => ['estimate' => $storeAutoServeSeedHelper()['estimate']->id], 'setup' => $storeAutoServeCustomerSetup],
    'estimates.send' => ['roles' => ['admin', 'mekanik'], 'params' => fn () => ['estimate' => $storeAutoServeSeedHelper()['estimate']->id]],
    'estimates.store' => ['roles' => ['admin', 'mekanik'], 'params' => fn () => ['booking' => $storeAutoServeSeedHelper()['booking']->id]],
    'finance.loans.index' => ['roles' => ['admin', 'auditor', 'customer', 'treasury']],
    'finance.loans.payInstallment' => ['roles' => ['admin', 'customer', 'treasury'], 'params' => fn () => ['loan' => $financeCryptoSeedHelper()['loan']->uuid], 'setup' => $financeCryptoCustomerSetup],
    'finance.loans.payOff' => ['roles' => ['admin', 'customer', 'treasury'], 'params' => fn () => ['loan' => $financeCryptoSeedHelper()['loan']->uuid], 'setup' => $financeCryptoCustomerSetup],
    'finance.loans.quote' => ['roles' => ['admin', 'customer', 'treasury']],
    'finance.loans.show' => ['roles' => ['admin', 'auditor', 'customer', 'treasury'], 'params' => fn () => ['loan' => $financeCryptoSeedHelper()['loan']->uuid], 'setup' => $financeCryptoCustomerSetup],
    'finance.loans.simulate' => ['roles' => ['admin', 'auditor', 'customer', 'treasury'], 'params' => fn () => ['product' => $financeCryptoSeedHelper()['product']->slug]],
    'finance.loans.store' => ['roles' => ['admin', 'customer', 'treasury'], 'params' => fn () => ['product' => $financeCryptoSeedHelper()['product']->slug]],
    'finance.loans.topUpCollateral' => ['roles' => ['admin', 'customer', 'treasury'], 'params' => fn () => ['loan' => $financeCryptoSeedHelper()['loan']->uuid], 'setup' => $financeCryptoCustomerSetup],
    'fleet.index' => ['roles' => ['admin', 'fleet_admin', 'lease_manager']],
    'fleet.contracts.store' => ['roles' => ['admin', 'fleet_admin', 'lease_manager']],
    'fleet.contracts.amortize' => [
        'roles' => ['admin', 'fleet_admin', 'lease_manager'],
        'params' => function () {
            $contract = FleetContract::firstOrCreate(['contract_number' => 'FLT-DEMO-001'], ['party_id' => 501, 'duration_months' => 12, 'total_units' => 1, 'monthly_rental_idr' => 15000000, 'total_lease_value_idr' => 180000000, 'accumulated_amortized_idr' => 0, 'max_downtime_hours_per_month' => 24, 'start_date' => now(), 'end_date' => now()->addMonths(12), 'status' => 'active']);

            return ['contract' => $contract->id];
        },
    ],
    'fleet.contracts.sla-claim' => [
        'roles' => ['admin', 'fleet_admin', 'lease_manager'],
        'params' => function () {
            $contract = FleetContract::firstOrCreate(['contract_number' => 'FLT-DEMO-001'], ['party_id' => 501, 'duration_months' => 12, 'total_units' => 1, 'monthly_rental_idr' => 15000000, 'total_lease_value_idr' => 180000000, 'accumulated_amortized_idr' => 0, 'max_downtime_hours_per_month' => 24, 'start_date' => now(), 'end_date' => now()->addMonths(12), 'status' => 'active']);

            return ['contract' => $contract->id];
        },
    ],
    'fleet.contracts.return-unit' => [
        'roles' => ['admin', 'fleet_admin', 'lease_manager'],
        'params' => function () {
            $contract = FleetContract::firstOrCreate(['contract_number' => 'FLT-DEMO-001'], ['party_id' => 501, 'duration_months' => 12, 'total_units' => 1, 'monthly_rental_idr' => 15000000, 'total_lease_value_idr' => 180000000, 'accumulated_amortized_idr' => 0, 'max_downtime_hours_per_month' => 24, 'start_date' => now(), 'end_date' => now()->addMonths(12), 'status' => 'active']);

            return ['contract' => $contract->id];
        },
    ],
    'food.brands.index' => ['roles' => ['admin', 'brand_manager']],
    'food.brands.store' => ['roles' => ['admin', 'brand_manager']],
    'governance.index' => ['roles' => ['admin', 'governance_admin', 'voter']],
    'guest.tickets.index' => ['roles' => ['admin', 'customer']],
    'guest.tickets.show' => [
        'roles' => ['admin', 'customer'],
        'params' => function () {
            $venue = EntertainmentVenue::firstOrCreate(['venue_code' => 'VEN-ATLAS-01'], ['name' => 'Atlas', 'venue_type' => 'BEACH_CLUB', 'city' => 'Badung, Bali', 'min_age_requirement' => 18, 'max_legal_capacity' => 1000]);
            $event = EntertainmentEvent::firstOrCreate(['event_code' => 'EVT-TEST-01'], ['venue_id' => $venue->id, 'title' => 'Test Event', 'doors_open_at' => now()->addDays(7), 'ticket_floor_price_idr' => 100000, 'ticket_ceiling_price_idr' => 1000000, 'current_ticket_price_idr' => 500000]);
            $tck = EntertainmentTicket::firstOrCreate(['ticket_number' => 'TCK-GUEST-001'], ['event_id' => $event->id, 'buyer_user_id' => 1, 'ticket_uuid' => 'tck-uuid-demo-001', 'price_paid_idr' => 750000, 'ticket_hash' => 'hash', 'status' => 'VALID']);

            return ['ticket_uuid' => $tck->ticket_uuid];
        },
        'setup' => function ($user) {
            EntertainmentTicket::where('ticket_uuid', 'tck-uuid-demo-001')->update(['buyer_user_id' => $user->id]);
        },
    ],
    'marine.index' => ['roles' => ['admin', 'farmer', 'officer', 'manager']],
    'marine.ponds.show' => ['roles' => ['admin', 'farmer', 'officer', 'manager'], 'params' => fn () => ['id' => 1]],
    'marine.aquaculture.index' => ['roles' => ['admin', 'farmer', 'officer', 'manager']],
    'marine.traceability.index' => ['roles' => ['admin', 'farmer', 'officer', 'manager']],
    'marine.escrow.index' => ['roles' => ['admin', 'farmer', 'officer', 'manager']],
    'marine.blue_carbon.index' => ['roles' => ['admin', 'farmer', 'officer', 'manager']],
    'marine.aquaculture.batches.store' => ['roles' => ['admin', 'farmer', 'officer', 'manager']],
    'marine.aquaculture.batches.sampling' => ['roles' => ['admin', 'farmer', 'officer', 'manager'], 'params' => fn () => ['id' => 1]],
    'marine.aquaculture.batches.telemetry' => ['roles' => ['admin', 'farmer', 'officer', 'manager'], 'params' => fn () => ['id' => 1]],
    'marine.aquaculture.batches.fcr' => ['roles' => ['admin', 'farmer', 'officer', 'manager'], 'params' => fn () => ['id' => 1]],
    'marine.shipments.health_cert' => ['roles' => ['admin', 'farmer', 'officer', 'manager'], 'params' => fn () => ['id' => 1]],
    'marine.shipments.coo' => ['roles' => ['admin', 'farmer', 'officer', 'manager'], 'params' => fn () => ['id' => 1]],
    'marine.shipments.telemetry' => ['roles' => ['admin', 'farmer', 'officer', 'manager'], 'params' => fn () => ['id' => 1]],
    'marine.shipments.dispatch' => ['roles' => ['admin', 'farmer', 'officer', 'manager'], 'params' => fn () => ['id' => 1]],
    'marine.disputes.arbitrate' => ['roles' => ['admin', 'farmer', 'officer', 'manager']],
    'hcm.index' => ['roles' => ['admin', 'hcm_manager']],
    'hcm.bounties.index' => ['roles' => ['admin', 'hcm_manager']],
    'hcm.bounties.store' => ['roles' => ['admin', 'hcm_manager']],
    'hcm.bounties.claim' => [
        'roles' => ['admin', 'hcm_manager'],
        'params' => function () {
            $bounty = GovBounty::firstOrCreate(
                ['bounty_code' => 'BNT-ROUTE-TEST'],
                [
                    'business_unit_cost_center' => 'WMS_CIKARANG_01',
                    'title' => 'Shift Unloading Route Test',
                    'description' => 'Route test description',
                    'shift_start' => now()->addHours(1),
                    'shift_end' => now()->addHours(5),
                    'duration_hours' => 4.0,
                    'hourly_rate_idr' => 75000,
                    'required_certification' => null,
                    'status' => 'OPEN',
                ]
            );

            return ['bounty' => $bounty->id];
        },
    ],
    'hcm.bounties.payout' => [
        'roles' => ['admin', 'hcm_manager'],
        'params' => function () {
            $bounty = GovBounty::firstOrCreate(
                ['bounty_code' => 'BNT-ROUTE-TEST'],
                [
                    'business_unit_cost_center' => 'WMS_CIKARANG_01',
                    'title' => 'Shift Unloading Route Test',
                    'description' => 'Route test description',
                    'shift_start' => now()->addHours(1),
                    'shift_end' => now()->addHours(5),
                    'duration_hours' => 4.0,
                    'hourly_rate_idr' => 75000,
                    'required_certification' => null,
                    'status' => 'CLAIMED',
                ]
            );
            $claim = GovBountyClaim::firstOrCreate(
                ['claim_code' => 'CLM-ROUTE-TEST'],
                [
                    'bounty_id' => $bounty->id,
                    'employee_id' => 101,
                    'claimed_at' => now(),
                    'status' => 'ASSIGNED',
                ]
            );

            return ['claim' => $claim->id];
        },
    ],
    'hospital.beds.index' => ['roles' => ['admin', 'bed_manager', 'rs_admin']],
    'hospital.er.index' => ['roles' => ['admin', 'doctor', 'nurse']],
    'hospital.nursing.index' => ['roles' => ['admin', 'nurse']],
    'hospital.critical_care.index' => ['roles' => ['admin', 'doctor', 'nurse']],
    'hospital.patients.passport' => [
        'roles' => ['admin', 'doctor', 'hospital_registration'],
        'params' => fn () => ['patient' => Patient::firstOrCreate(['mrn' => 'MRN-TEST-001'], ['name' => 'Test Patient', 'date_of_birth' => '1990-01-01', 'blood_type' => 'O', 'passport_hash' => '0000000000000000000000000000000000000000000000000000000000000000'])->id],
    ],
    'hospital.efficiency.index' => ['roles' => ['admin', 'rs_admin']],
    'hospital.admissions.store' => ['roles' => ['admin', 'bed_manager', 'hospital_registration', 'rs_admin']],
    'hospital.admissions.accrue_bed_day' => [
        'roles' => ['admin', 'bed_manager', 'rs_admin'],
        'params' => fn () => ['admission' => Admission::firstOrCreate(['admission_no' => 'ADM-ARCH-001'], ['patient_id' => 1, 'entitled_class' => 'CLASS_1', 'current_class' => 'CLASS_1', 'status' => 'admitted'])->id],
    ],
    'hospital.admissions.discharge' => [
        'roles' => ['admin', 'bed_manager', 'rs_admin'],
        'params' => fn () => ['admission' => Admission::firstOrCreate(['admission_no' => 'ADM-ARCH-001'], ['patient_id' => 1, 'entitled_class' => 'CLASS_1', 'current_class' => 'CLASS_1', 'status' => 'admitted'])->id],
    ],
    'hospital.code_blue.ack' => [
        'roles' => ['admin', 'doctor', 'nurse'],
        'params' => fn () => ['event' => CodeBlueEvent::firstOrCreate(['event_no' => 'CB-ARCH-001'], ['status' => 'raised', 'escalation_level' => 'NURSE', 'proof_hash' => 'proof-001'])->id],
    ],
    'hospital.code_blue.resolve' => [
        'roles' => ['admin', 'doctor'],
        'params' => fn () => ['event' => CodeBlueEvent::firstOrCreate(['event_no' => 'CB-ARCH-001'], ['status' => 'raised', 'escalation_level' => 'NURSE', 'proof_hash' => 'proof-001'])->id],
    ],
    'hospital.epharmacy.routing.dispatch' => ['roles' => ['admin', 'pharmacist']],
    'hospital.epharmacy.subscriptions.index' => ['roles' => ['admin', 'auditor', 'doctor', 'pharmacist']],
    'hospital.imaging.pacs.index' => ['roles' => ['admin', 'auditor', 'doctor']],
    'hospital.index' => ['roles' => ['admin', 'doctor', 'hospital_admin']],
    'hospital.laboratory.catalog.index' => ['roles' => ['admin', 'auditor', 'doctor', 'nurse', 'pharmacist']],
    'hospital.laboratory.clearings.settle' => ['roles' => ['admin']],
    'hospital.laboratory.outsourcing.index' => ['roles' => ['admin', 'auditor', 'doctor']],
    'hospital.laboratory.outsourcing.manifests.store' => ['roles' => ['admin', 'doctor']],
    'hospital.laboratory.qc.evaluate' => ['roles' => ['admin', 'doctor', 'pharmacist']],
    'hospital.laboratory.results.verify' => ['roles' => ['admin', 'doctor']],
    'hospital.laboratory.specimens.custody' => ['roles' => ['admin', 'doctor', 'nurse']],
    'hospital.radiology.dicom.studies.store' => ['roles' => ['admin', 'doctor', 'nurse']],
    'hospital.radiology.dicom.studies.metadata' => [
        'roles' => ['admin', 'auditor', 'doctor'],
        'params' => function (): array {
            $study = ImagingStudy::first();
            if (! $study) {
                $patient = Patient::firstOrCreate(
                    ['mrn' => 'MRN-DICOM-001'],
                    [
                        'name' => 'Test Patient',
                        'date_of_birth' => '1990-01-01',
                        'blood_type' => 'O+',
                        'national_id_nik' => '3171010101010001',
                        'passport_hash' => hash('sha256', 'MRN-DICOM-001'),
                    ]
                );
                $study = ImagingStudy::create([
                    'study_instance_uid' => '1.2.826.0.1.3680043.9.7134.1',
                    'patient_id' => $patient->id,
                    'modality' => 'CT',
                    'body_part' => 'Chest',
                    'performed_at' => now(),
                    'fee_idr' => 750000,
                    'status' => 'COMPLETED',
                ]);
            }

            return ['id' => $study->id];
        },
    ],
    'hospital.telemedicine.index' => ['roles' => ['admin', 'auditor', 'doctor', 'nurse']],
    'hospital.telemedicine.prescriptions.evaluate' => ['roles' => ['admin', 'doctor', 'pharmacist']],
    'hospital.trials.bill' => [
        'roles' => ['admin', 'doctor', 'hospital_admin'],
        'params' => fn () => ['trial' => ClinicalTrial::firstOrCreate(['trial_code' => 'TRL-CARDIO-01'], ['title' => 'Trial Card', 'phase' => 'PHASE_3', 'sponsor_name' => 'Novartis', 'target_subjects' => 10, 'status' => 'RECRUITING'])->id],
    ],
    // hospital-rawat-jalan
    'hospital.outpatient.registration.index' => ['roles' => ['admin', 'hospital_registration']],
    'hospital.outpatient.registration.store' => ['roles' => ['admin', 'hospital_registration']],
    'hospital.outpatient.queue.index' => ['roles' => ['admin', 'doctor', 'hospital_registration', 'nurse']],
    'hospital.outpatient.queue.call' => [
        'roles' => ['admin', 'doctor', 'nurse'],
        'params' => fn () => ['queue' => OutpatientEncounter::getOrCreateArchFixture()['queue']->id],
    ],
    'hospital.outpatient.encounters.triage' => [
        'roles' => ['admin', 'nurse'],
        'params' => fn () => ['encounter' => OutpatientEncounter::getOrCreateArchFixture()['encounter']->id],
    ],
    'hospital.outpatient.encounters.examine' => [
        'roles' => ['admin', 'doctor'],
        'params' => fn () => ['encounter' => OutpatientEncounter::getOrCreateArchFixture()['encounter']->id],
    ],
    'hospital.outpatient.encounters.prescribe' => [
        'roles' => ['admin', 'doctor'],
        'params' => fn () => ['encounter' => OutpatientEncounter::getOrCreateArchFixture()['encounter']->id],
    ],
    'hospital.outpatient.pharmacy.index' => ['roles' => ['admin', 'pharmacist']],
    'hospital.outpatient.pharmacy.dispense' => [
        'roles' => ['admin', 'pharmacist'],
        'params' => fn () => ['prescription' => OutpatientEncounter::getOrCreateArchFixture()['prescription']->id],
    ],
    'hospital.outpatient.cashier.index' => ['roles' => ['admin', 'cashier', 'hospital_finance']],
    'hospital.outpatient.cashier.pay' => [
        'roles' => ['admin', 'cashier'],
        'params' => fn () => ['invoice' => OutpatientEncounter::getOrCreateArchFixture()['invoice']->id],
    ],
    'hospital.outpatient.bpjs.claim' => [
        'roles' => ['admin', 'cashier', 'hospital_finance'],
        'params' => fn () => ['invoice' => OutpatientEncounter::getOrCreateArchFixture()['invoice']->id],
    ],
    'hospital.outpatient.bpjs.adjudicate' => [
        'roles' => ['admin', 'hospital_finance'],
        'params' => fn () => ['claim' => OutpatientEncounter::getOrCreateArchFixture()['claim']->id],
    ],
    'hospital.outpatient.reports.daily' => ['roles' => ['admin', 'auditor', 'hospital_finance']],
    'hospital.outpatient.portal.index' => ['roles' => ['admin', 'patient']],
    'hospital.outpatient.encounters.show' => [
        'roles' => ['admin', 'doctor', 'nurse', 'patient'],
        'setup' => function (User $user): void {
            $p = OutpatientEncounter::getOrCreateArchFixture()['patient'];
            if ($p->user_id !== $user->id) {
                $p->user_id = $user->id;
                $p->save();
            }
        },
        'params' => fn () => ['encounter' => OutpatientEncounter::getOrCreateArchFixture()['encounter']->id],
    ],
    'hospital.outpatient.invoices.show' => [
        'roles' => ['admin', 'cashier', 'hospital_finance', 'patient'],
        'setup' => function (User $user): void {
            $p = OutpatientEncounter::getOrCreateArchFixture()['patient'];
            if ($p->user_id !== $user->id) {
                $p->user_id = $user->id;
                $p->save();
            }
        },
        'params' => fn () => ['invoice' => OutpatientEncounter::getOrCreateArchFixture()['invoice']->id],
    ],
    'hospital.billing.aging_report' => ['roles' => ['admin', 'hospital_finance', 'rs_admin']],
    'hospital.billing.episodes.create' => ['roles' => ['admin', 'cashier', 'hospital_finance', 'rs_admin']],
    'hospital.billing.folio_items.add' => ['roles' => ['admin', 'cashier', 'doctor', 'hospital_finance', 'nurse', 'rs_admin']],
    'hospital.billing.reconcile_settle' => ['roles' => ['admin', 'cashier', 'hospital_finance', 'rs_admin']],
    'hospital.operations.cold_chain.record' => ['roles' => ['admin', 'nurse', 'pharmacist', 'rs_admin']],
    'hospital.operations.waste.handover' => ['roles' => ['admin', 'environmental_officer', 'rs_admin']],
    'hospital.operations.waste.log' => ['roles' => ['admin', 'environmental_officer', 'nurse', 'rs_admin']],
    'hospital.operations.waste.verify_chain' => ['roles' => ['admin', 'environmental_officer', 'rs_admin']],
    'hotel.index' => ['roles' => ['admin', 'front_desk', 'hotel_admin']],
    'hotel.reservations.settle' => [
        'roles' => ['admin', 'front_desk', 'hotel_admin'],
        'params' => function () {
            (new HotelDemoSeeder)->run();
            $res = HotelReservation::firstOrFail();

            return ['reservation' => $res->id];
        },
    ],
    'mining.index' => ['roles' => ['admin', 'mining_admin', 'site_manager']],
    'mining.dispatch' => [
        'roles' => ['admin', 'mining_admin', 'site_manager'],
        'params' => function () {
            $site = MiningSite::firstOrCreate(['site_code' => 'SITE-KUTAI-01'], ['id' => (string) Str::uuid(), 'name' => 'Kutai Site', 'commodity' => 'COAL', 'location' => 'Kaltim']);
            $pit = MiningPit::firstOrCreate(['site_id' => $site->id, 'pit_code' => 'PIT-NORTH-01'], ['id' => (string) Str::uuid(), 'name' => 'North Pit', 'target_daily_production_ton' => 5000, 'actual_production_ton' => 3000]);

            return ['pit' => $pit->id];
        },
    ],
    'insurance.index' => ['roles' => ['admin', 'customer', 'insurance_admin', 'underwriter']],
    'insurance.policies' => ['roles' => ['admin', 'customer', 'insurance_admin', 'underwriter']],
    'insurance.policies.issue' => ['roles' => ['admin', 'customer', 'insurance_admin', 'underwriter']],
    'insurance.products.issue' => [
        'roles' => ['admin', 'customer', 'insurance_admin', 'underwriter'],
        'params' => function () {
            $prod = InsuranceProduct::firstOrCreate(['product_code' => 'INS-FLIGHT-DELAY'], ['name' => 'Flight Delay', 'premium_amount_idr' => 75000, 'max_payout_idr' => 500000, 'trigger_event_type' => 'FLIGHT_DELAY', 'is_active' => true]);

            return ['product' => $prod->id];
        },
    ],
    'integration.index' => ['roles' => ['admin']],
    'integration.webhooks' => ['roles' => ['admin']],
    'intercompany.index' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'intercompany.transactions' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'intercompany.transactions.store' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'intercompany.loans' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'intercompany.loans.store' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'intercompany.eliminations' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'intercompany.transfer-pricing' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'intercompany.cost-sharing' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'intercompany.cost-sharing.allocate' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'intercompany.asset-transfers' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'intercompany.assets.transfer' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'intercompany.stocks.transfer' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'intercompany.segments' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'intercompany.export.matrix' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'international.index' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'international.jvs' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'login' => 'public',
    'logistics.carriers.index' => ['roles' => ['admin', 'auditor', 'dispatcher', 'logistics_admin', 'procurement', 'treasury']],
    'logistics.carriers.pay' => ['roles' => ['admin', 'logistics_admin', 'treasury'], 'params' => function () use ($logisticsSeedHelper) {
        $d = $logisticsSeedHelper();

        return ['carrier' => $d['carrier']->id];
    }],
    'logistics.carriers.store' => ['roles' => ['admin', 'logistics_admin', 'procurement']],
    'logistics.circular-ledger.index' => ['roles' => ['admin', 'auditor', 'logistics_admin', 'treasury']],
    'logistics.claims.decide' => ['roles' => ['admin', 'dispatcher', 'logistics_admin'], 'params' => function () use ($logisticsSeedHelper) {
        $d = $logisticsSeedHelper();

        return ['claim' => $d['claim']->id];
    }],
    'logistics.claims.index' => ['roles' => ['admin', 'auditor', 'dispatcher', 'logistics_admin', 'shipper', 'treasury']],
    'logistics.claims.pay' => ['roles' => ['admin', 'logistics_admin', 'treasury'], 'params' => function () use ($logisticsSeedHelper) {
        $d = $logisticsSeedHelper();

        return ['claim' => $d['claim']->id];
    }],
    'logistics.claims.store' => ['roles' => ['admin', 'dispatcher', 'logistics_admin', 'shipper']],
    'logistics.claims.submit' => ['roles' => ['admin', 'dispatcher', 'logistics_admin', 'shipper'], 'params' => function () use ($logisticsSeedHelper) {
        $d = $logisticsSeedHelper();

        return ['claim' => $d['claim']->id];
    }],
    'logistics.cod.deposit' => ['roles' => ['admin', 'hub_operator', 'logistics_admin'], 'setup' => $hubOperatorSetup],
    'logistics.cod.index' => ['roles' => ['admin', 'auditor', 'dispatcher', 'hub_operator', 'logistics_admin', 'treasury']],
    'logistics.control-tower.index' => ['roles' => ['admin', 'logistics_admin']],
    'logistics.customs.clear' => ['roles' => ['admin', 'logistics_admin', 'shipper'], 'params' => function () use ($logisticsSeedHelper) {
        $d = $logisticsSeedHelper();

        return ['declaration' => $d['declaration']->id];
    }],
    'logistics.customs.index' => ['roles' => ['admin', 'auditor', 'dispatcher', 'logistics_admin', 'shipper', 'treasury']],
    'logistics.customs.pay' => ['roles' => ['admin', 'shipper', 'treasury'], 'params' => function () use ($logisticsSeedHelper) {
        $d = $logisticsSeedHelper();

        return ['declaration' => $d['declaration']->id];
    }],
    'logistics.customs.store' => ['roles' => ['admin', 'dispatcher', 'logistics_admin', 'shipper']],
    'logistics.customs.tariffs.store' => ['roles' => ['admin', 'logistics_admin']],
    'logistics.dashboard' => ['roles' => ['admin', 'auditor', 'dispatcher', 'hub_operator', 'logistics_admin']],
    'logistics.dd.end' => ['roles' => ['admin', 'logistics_admin'], 'params' => function () use ($logisticsSeedHelper) {
        $d = $logisticsSeedHelper();

        return ['dwell' => $d['dwell']->id];
    }],
    'logistics.dd.index' => ['roles' => ['admin', 'auditor', 'dispatcher', 'logistics_admin', 'treasury']],
    'logistics.dd.invoice' => ['roles' => ['admin', 'logistics_admin']],
    'logistics.dd.start' => ['roles' => ['admin', 'logistics_admin']],
    'logistics.dd.tariffs.store' => ['roles' => ['admin', 'logistics_admin']],
    'logistics.dispatch.assign' => ['roles' => ['admin', 'dispatcher', 'logistics_admin']],
    'logistics.dispatch.assign-shipment' => ['roles' => ['admin', 'dispatcher', 'logistics_admin']],
    'logistics.dispatch.index' => ['roles' => ['admin', 'auditor', 'dispatcher', 'logistics_admin']],
    'logistics.dispatch.release' => ['roles' => ['admin', 'dispatcher', 'logistics_admin'], 'params' => function () use ($logisticsSeedHelper) {
        $d = $logisticsSeedHelper();

        return ['schedule' => $d['schedule']->id];
    }],
    'logistics.driver.deliver' => ['roles' => ['admin', 'driver', 'logistics_admin'], 'params' => function () use ($logisticsSeedHelper) {
        $d = $logisticsSeedHelper();

        return ['shipment' => $d['shipment']->id];
    }, 'setup' => $driverSetup],
    'logistics.driver.fail' => ['roles' => ['admin', 'driver', 'logistics_admin'], 'params' => function () use ($logisticsSeedHelper) {
        $d = $logisticsSeedHelper();

        return ['shipment' => $d['shipment']->id];
    }, 'setup' => $driverSetup],
    'logistics.driver.pickup' => ['roles' => ['admin', 'driver', 'logistics_admin'], 'setup' => $driverSetup],
    'logistics.driver.start-delivery' => ['roles' => ['admin', 'driver', 'logistics_admin'], 'params' => function () use ($logisticsSeedHelper) {
        $d = $logisticsSeedHelper();

        return ['shipment' => $d['shipment']->id];
    }, 'setup' => $driverSetup],
    'logistics.driver.tasks' => ['roles' => ['admin', 'driver', 'logistics_admin'], 'setup' => $driverSetup],
    'logistics.drivers.index' => ['roles' => ['admin', 'auditor', 'dispatcher', 'logistics_admin']],
    'logistics.exceptions.index' => ['roles' => ['admin', 'auditor', 'dispatcher', 'hub_operator', 'logistics_admin']],
    'logistics.exceptions.resolve' => ['roles' => ['admin', 'dispatcher', 'logistics_admin'], 'params' => function () use ($logisticsSeedHelper) {
        $d = $logisticsSeedHelper();

        return ['exception' => $d['exception']->id];
    }],
    'logistics.exceptions.store' => ['roles' => ['admin', 'dispatcher', 'hub_operator', 'logistics_admin']],
    'logistics.fleet.index' => ['roles' => ['admin', 'auditor', 'dispatcher', 'hub_operator', 'logistics_admin']],
    'logistics.fuel.index' => ['roles' => ['admin', 'auditor', 'dispatcher', 'driver', 'logistics_admin']],
    'logistics.fuel.store' => ['roles' => ['admin', 'dispatcher', 'driver', 'logistics_admin']],
    'logistics.hub.inbound' => ['roles' => ['admin', 'hub_operator', 'logistics_admin'], 'setup' => $hubOperatorSetup],
    'logistics.hub.index' => ['roles' => ['admin', 'auditor', 'hub_operator', 'logistics_admin'], 'setup' => $hubOperatorSetup],
    'logistics.hub.outbound' => ['roles' => ['admin', 'hub_operator', 'logistics_admin'], 'setup' => $hubOperatorSetup],
    'logistics.hub.sort' => ['roles' => ['admin', 'hub_operator', 'logistics_admin'], 'setup' => $hubOperatorSetup],
    'logistics.lanes.destroy' => ['roles' => ['admin', 'logistics_admin'], 'params' => function () use ($logisticsSeedHelper) {
        $d = $logisticsSeedHelper();

        return ['lane' => $d['lane']->id];
    }],
    'logistics.lanes.index' => ['roles' => ['admin', 'auditor', 'dispatcher', 'logistics_admin']],
    'logistics.lanes.store' => ['roles' => ['admin', 'dispatcher', 'logistics_admin']],
    'logistics.legs.assign-carrier' => ['roles' => ['admin', 'logistics_admin'], 'params' => function () use ($logisticsSeedHelper) {
        $d = $logisticsSeedHelper();

        return ['leg' => $d['leg']->id];
    }],
    'logistics.legs.complete' => ['roles' => ['admin', 'dispatcher', 'logistics_admin'], 'params' => function () use ($logisticsSeedHelper) {
        $d = $logisticsSeedHelper();

        return ['leg' => $d['leg']->id];
    }],
    'logistics.locations.create' => ['roles' => ['admin', 'logistics_admin']],
    'logistics.locations.destroy' => ['roles' => ['admin', 'logistics_admin'], 'params' => function () use ($logisticsSeedHelper) {
        $d = $logisticsSeedHelper();

        return ['location' => $d['hubB']->id];
    }],
    'logistics.locations.edit' => ['roles' => ['admin', 'logistics_admin'], 'params' => function () use ($logisticsSeedHelper) {
        $d = $logisticsSeedHelper();

        return ['location' => $d['hubB']->id];
    }],
    'logistics.locations.index' => ['roles' => ['admin', 'auditor', 'dispatcher', 'hub_operator', 'logistics_admin']],
    'logistics.locations.store' => ['roles' => ['admin', 'logistics_admin']],
    'logistics.locations.update' => ['roles' => ['admin', 'logistics_admin'], 'params' => function () use ($logisticsSeedHelper) {
        $d = $logisticsSeedHelper();

        return ['location' => $d['hubB']->id];
    }],
    'logistics.margins' => ['roles' => ['admin', 'auditor', 'dispatcher', 'logistics_admin', 'procurement', 'treasury']],
    'logistics.shipments.bulk' => ['roles' => ['admin', 'dispatcher', 'logistics_admin', 'shipper']],
    'logistics.shipments.bulk.process' => ['roles' => ['admin', 'dispatcher', 'logistics_admin', 'shipper']],
    'logistics.shipments.create' => ['roles' => ['admin', 'dispatcher', 'logistics_admin', 'shipper']],
    'logistics.shipments.errors' => ['roles' => ['admin', 'dispatcher', 'logistics_admin', 'shipper'], 'params' => function () use ($logisticsSeedHelper) {
        $logisticsSeedHelper();

        return ['batchId' => 'batch-test-01'];
    }],
    'logistics.reverse.index' => ['roles' => ['admin', 'auditor', 'dispatcher', 'hub_operator', 'logistics_admin']],
    'logistics.reverse.store' => ['roles' => ['admin', 'dispatcher', 'hub_operator', 'logistics_admin']],
    'logistics.reverse.intake' => [
        'roles' => ['admin', 'dispatcher', 'hub_operator', 'logistics_admin'],
        'params' => function () {
            $order = ReverseOrder::firstOrCreate(
                ['reverse_code' => 'REV-SEED-ROUTER-01'],
                [
                    'event_trigger_type' => 'resto.waste_bulk',
                    'source_reference_id' => 'REF-ROUTER-01',
                    'pickup_location' => 'Banjarmasin',
                    'destination_facility' => 'Cikarang',
                    'status' => 'SCHEDULED',
                    'manifest_number' => 'MNF-SEED-ROUTER-01',
                    'custody_hash' => hash('sha256', 'seed'),
                    'total_appraised_value_idr' => 100000,
                    'avoided_emissions_kg_co2' => 21.0,
                ]
            );

            return ['order' => $order->id];
        },
    ],
    'logistics.reverse.optimize' => ['roles' => ['admin', 'dispatcher', 'hub_operator', 'logistics_admin']],
    'logistics.reverse.shipments.index' => ['roles' => ['admin', 'auditor', 'dispatcher', 'hub_operator', 'logistics_admin']],
    'logistics.reverse.shipments.show' => [
        'roles' => ['admin', 'auditor', 'dispatcher', 'hub_operator', 'logistics_admin'],
        'params' => function () use ($logisticsSeedHelper) {
            $d = $logisticsSeedHelper();

            return ['shipment' => $d['shipment']->id];
        },
    ],
    'logistics.reverse.shipments.store' => ['roles' => ['admin', 'dispatcher', 'hub_operator', 'logistics_admin']],
    'logistics.reverse.shipments.transition' => [
        'roles' => ['admin', 'dispatcher', 'hub_operator', 'logistics_admin'],
        'params' => function () use ($logisticsSeedHelper) {
            $d = $logisticsSeedHelper();

            return ['shipment' => $d['shipment']->id];
        },
    ],
    'logistics.autonomous.index' => ['roles' => ['admin', 'auditor', 'dispatcher', 'hub_operator', 'logistics_admin']],
    'logistics.cold-chain.index' => ['roles' => ['admin', 'auditor', 'dispatcher', 'hub_operator', 'logistics_admin']],
    'logistics.drone.index' => ['roles' => ['admin', 'auditor', 'dispatcher', 'hub_operator', 'logistics_admin']],
    'logistics.autonomous.dispatch' => ['roles' => ['admin', 'dispatcher', 'hub_operator', 'logistics_admin']],
    'logistics.autonomous.deliver' => [
        'roles' => ['admin', 'dispatcher', 'hub_operator', 'logistics_admin'],
        'params' => function () {
            $drone = DroneUnit::firstOrCreate(
                ['drone_code' => 'DRN-SEED-ROUTER-01'],
                ['model_name' => 'OctoCopter Route Test', 'battery_percent' => 100, 'max_payload_kg' => 5.0, 'max_range_km' => 15.0, 'status' => 'IDLE']
            );
            $mission = DroneMission::firstOrCreate(
                ['mission_code' => 'DRN-MSN-ROUTER-01'],
                [
                    'drone_unit_id' => $drone->id,
                    'shipment_code' => 'SHP-ROUTER-01',
                    'package_weight_kg' => 2.0,
                    'distance_km' => 5.0,
                    'destination_geo_hash' => 'qqs-seed-01',
                    'status' => 'DISPATCHED',
                ]
            );

            return ['mission' => $mission->id];
        },
    ],
    'logistics.autonomous.breaches.release' => [
        'roles' => ['admin', 'dispatcher', 'logistics_admin', 'treasury'],
        'params' => function () {
            $hold = TempBreachHold::firstOrCreate(
                ['breach_code' => 'BRC-SEED-ROUTER-01'],
                [
                    'shipment_code' => 'SHP-ROUTER-01',
                    'carrier_id' => 1,
                    'recorded_temp_c' => 12.0,
                    'max_allowed_temp_c' => 4.0,
                    'duration_minutes' => 20,
                    'hold_amount_idr' => 1000000,
                    'hash_proof' => hash('sha256', 'seed'),
                    'status' => 'HELD',
                ]
            );

            return ['hold' => $hold->id];
        },
    ],
    'logistics.shipments.index' => ['roles' => ['admin', 'auditor', 'dispatcher', 'logistics_admin', 'shipper']],
    'logistics.shipments.label' => ['roles' => ['admin', 'auditor', 'dispatcher', 'driver', 'hub_operator', 'logistics_admin', 'shipper'], 'params' => function () use ($logisticsSeedHelper) {
        $d = $logisticsSeedHelper();

        return ['id' => $d['shipment']->id];
    }],
    'logistics.shipments.show' => ['roles' => ['admin', 'auditor', 'dispatcher', 'driver', 'hub_operator', 'logistics_admin', 'shipper'], 'params' => function () use ($logisticsSeedHelper) {
        $d = $logisticsSeedHelper();

        return ['id' => $d['shipment']->id];
    }],
    'logistics.shipments.store' => ['roles' => ['admin', 'dispatcher', 'logistics_admin', 'shipper']],
    'logistics.shipments.template' => ['roles' => ['admin', 'dispatcher', 'logistics_admin', 'shipper']],
    'logout' => 'auth',
    'mall.billing.apply-penalties' => ['roles' => ['admin', 'mall_admin']],
    'mall.billing.generate' => ['roles' => ['admin', 'mall_admin']],
    'mall.billing.index' => ['roles' => ['admin', 'auditor', 'mall_admin']],
    'mall.directory.index' => 'public',
    'mall.events.index' => ['roles' => ['admin', 'auditor', 'mall_admin']],
    'mall.events.store' => ['roles' => ['admin', 'mall_admin']],
    'mall.facilities.bill-tenant' => [
        'roles' => ['admin', 'mall_admin', 'technician'],
        'params' => function (): array {
            if (! Property::where('code', 'DM-BJM')->exists()) {
                (new MallSeeder)->run();
            }

            return ['workOrder' => WorkOrder::first()?->id ?? 1];
        },
    ],
    'mall.facilities.generate-pm' => ['roles' => ['admin', 'mall_admin', 'technician']],
    'mall.facilities.index' => ['roles' => ['admin', 'auditor', 'mall_admin', 'technician']],
    'mall.invoices.auto-debit' => [
        'roles' => ['admin', 'mall_admin'],
        'params' => function (): array {
            if (! Property::where('code', 'DM-BJM')->exists()) {
                (new MallSeeder)->run();
            }

            return ['id' => Invoice::first()?->id ?? 1];
        },
    ],
    'mall.invoices.show' => [
        'roles' => ['admin', 'auditor', 'mall_admin'],
        'params' => function (): array {
            if (! Property::where('code', 'DM-BJM')->exists()) {
                (new MallSeeder)->run();
            }

            return ['id' => Invoice::first()?->id ?? 1];
        },
    ],
    'mall.leases.activate' => [
        'roles' => ['admin', 'mall_admin'],
        'params' => function (): array {
            if (! Property::where('code', 'DM-BJM')->exists()) {
                (new MallSeeder)->run();
            }

            return ['lease' => Lease::first()?->id ?? 1];
        },
    ],
    'mall.leases.create' => ['roles' => ['admin', 'mall_admin']],
    'mall.leases.index' => ['roles' => ['admin', 'auditor', 'mall_admin']],
    'mall.leases.renew' => [
        'roles' => ['admin', 'mall_admin'],
        'params' => function (): array {
            if (! Property::where('code', 'DM-BJM')->exists()) {
                (new MallSeeder)->run();
            }

            return ['lease' => Lease::first()?->id ?? 1];
        },
    ],
    'mall.leases.show' => [
        'roles' => ['admin', 'auditor', 'mall_admin'],
        'params' => function (): array {
            if (! Property::where('code', 'DM-BJM')->exists()) {
                (new MallSeeder)->run();
            }

            return ['lease' => Lease::first()?->id ?? 1];
        },
    ],
    'mall.leases.store' => ['roles' => ['admin', 'mall_admin']],
    'mall.leases.terminate' => [
        'roles' => ['admin', 'mall_admin'],
        'params' => function (): array {
            if (! Property::where('code', 'DM-BJM')->exists()) {
                (new MallSeeder)->run();
            }

            return ['lease' => Lease::first()?->id ?? 1];
        },
    ],
    'mall.loyalty.claim' => ['roles' => ['admin', 'customer', 'mall_admin']],
    'mall.loyalty.index' => ['roles' => ['admin', 'auditor', 'customer', 'mall_admin']],
    'mall.loyalty.redeem' => [
        'roles' => ['admin', 'customer', 'mall_admin'],
        'params' => function (): array {
            if (! Property::where('code', 'DM-BJM')->exists()) {
                (new MallSeeder)->run();
            }

            return ['template' => VoucherTemplate::first()?->id ?? 1];
        },
    ],
    'mall.loyalty.settle' => ['roles' => ['admin', 'mall_admin']],
    'mall.loyalty.use' => ['roles' => ['admin', 'customer', 'mall_admin']],
    'mall.parking.footfall' => ['roles' => ['admin', 'auditor', 'mall_admin', 'technician']],
    'mall.parking.gate.check-in' => ['roles' => ['admin', 'mall_admin', 'technician']],
    'mall.parking.gate.check-out' => ['roles' => ['admin', 'mall_admin', 'technician']],
    'mall.parking.gate.entry' => ['roles' => ['admin', 'mall_admin', 'technician']],
    'mall.parking.gate.exit' => ['roles' => ['admin', 'mall_admin', 'technician']],
    'mall.parking.gate.lost-ticket' => ['roles' => ['admin', 'mall_admin', 'technician']],
    'mall.parking.gate.settle' => [
        'roles' => ['admin', 'mall_admin', 'technician'],
        'params' => function (): array {
            return ['ticketNumber' => 'TKT-TEST-001'];
        },
    ],
    'mall.parking.index' => ['roles' => ['admin', 'auditor', 'mall_admin', 'technician']],
    'mall.parking.members' => ['roles' => ['admin', 'auditor', 'customer', 'mall_admin', 'technician']],
    'mall.parking.members.store' => ['roles' => ['admin', 'customer', 'mall_admin', 'technician']],
    'mall.parking.occupancy' => ['roles' => ['admin', 'auditor', 'mall_admin', 'technician']],
    'mall.parking.validate' => ['roles' => ['admin', 'mall_admin', 'technician']],
    'mall.portal.index' => [
        'roles' => ['admin', 'tenant'],
        'setup' => function (User $user): void {
            if ($user->role === 'tenant') {
                if (! Property::where('code', 'DM-BJM')->exists()) {
                    (new MallSeeder)->run();
                }
                Tenant::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'company_name' => 'PT Tenant '.$user->id,
                        'brand_name' => 'Brand '.$user->id,
                        'category' => TenantCategory::FNB,
                        'pic_name' => 'PIC '.$user->name,
                        'pic_email' => $user->email,
                        'pic_phone' => '0812345678',
                        'is_active' => true,
                    ]
                );
            }
        },
    ],
    'mall.portal.invoice' => [
        'roles' => ['admin', 'tenant'],
        'params' => function (): array {
            if (! Property::where('code', 'DM-BJM')->exists()) {
                (new MallSeeder)->run();
            }

            return ['id' => Invoice::first()?->id ?? 1];
        },
        'setup' => function (User $user): void {
            if ($user->role === 'tenant') {
                if (! Property::where('code', 'DM-BJM')->exists()) {
                    (new MallSeeder)->run();
                }
                $tenant = Tenant::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'company_name' => 'PT Tenant '.$user->id,
                        'brand_name' => 'Brand '.$user->id,
                        'category' => TenantCategory::FNB,
                        'pic_name' => 'PIC '.$user->name,
                        'pic_email' => $user->email,
                        'pic_phone' => '0812345678',
                        'is_active' => true,
                    ]
                );
                $invoice = Invoice::first();
                if ($invoice) {
                    $invoice->update(['tenant_id' => $tenant->id]);
                }
            }
        },
    ],
    'mall.portal.overtime' => [
        'roles' => ['admin', 'tenant'],
        'setup' => function (User $user): void {
            if ($user->role === 'tenant') {
                if (! Property::where('code', 'DM-BJM')->exists()) {
                    (new MallSeeder)->run();
                }
                Tenant::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'company_name' => 'PT Tenant '.$user->id,
                        'brand_name' => 'Brand '.$user->id,
                        'category' => TenantCategory::FNB,
                        'pic_name' => 'PIC '.$user->name,
                        'pic_email' => $user->email,
                        'pic_phone' => '0812345678',
                        'is_active' => true,
                    ]
                );
            }
        },
    ],
    'mall.portal.pay' => [
        'roles' => ['admin', 'tenant'],
        'params' => function (): array {
            if (! Property::where('code', 'DM-BJM')->exists()) {
                (new MallSeeder)->run();
            }

            return ['id' => Invoice::first()?->id ?? 1];
        },
        'setup' => function (User $user): void {
            if ($user->role === 'tenant') {
                if (! Property::where('code', 'DM-BJM')->exists()) {
                    (new MallSeeder)->run();
                }
                $tenant = Tenant::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'company_name' => 'PT Tenant '.$user->id,
                        'brand_name' => 'Brand '.$user->id,
                        'category' => TenantCategory::FNB,
                        'pic_name' => 'PIC '.$user->name,
                        'pic_email' => $user->email,
                        'pic_phone' => '0812345678',
                        'is_active' => true,
                    ]
                );
                $invoice = Invoice::first();
                if ($invoice) {
                    $invoice->update(['tenant_id' => $tenant->id]);
                }
            }
        },
    ],
    'mall.portal.sales' => [
        'roles' => ['admin', 'tenant'],
        'setup' => function (User $user): void {
            if ($user->role === 'tenant') {
                if (! Property::where('code', 'DM-BJM')->exists()) {
                    (new MallSeeder)->run();
                }
                Tenant::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'company_name' => 'PT Tenant '.$user->id,
                        'brand_name' => 'Brand '.$user->id,
                        'category' => TenantCategory::FNB,
                        'pic_name' => 'PIC '.$user->name,
                        'pic_email' => $user->email,
                        'pic_phone' => '0812345678',
                        'is_active' => true,
                    ]
                );
            }
        },
    ],
    'mall.portal.sales.store' => [
        'roles' => ['admin', 'tenant'],
        'setup' => function (User $user): void {
            if ($user->role === 'tenant') {
                if (! Property::where('code', 'DM-BJM')->exists()) {
                    (new MallSeeder)->run();
                }
                Tenant::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'company_name' => 'PT Tenant '.$user->id,
                        'brand_name' => 'Brand '.$user->id,
                        'category' => TenantCategory::FNB,
                        'pic_name' => 'PIC '.$user->name,
                        'pic_email' => $user->email,
                        'pic_phone' => '0812345678',
                        'is_active' => true,
                    ]
                );
            }
        },
    ],
    'mall.site-plan.index' => ['roles' => ['admin', 'auditor', 'mall_admin']],
    'mall.tenants.create' => ['roles' => ['admin', 'mall_admin']],
    'mall.tenants.index' => ['roles' => ['admin', 'auditor', 'mall_admin']],
    'mall.tenants.show' => [
        'roles' => ['admin', 'auditor', 'mall_admin'],
        'params' => function (): array {
            if (! Property::where('code', 'DM-BJM')->exists()) {
                (new MallSeeder)->run();
            }

            return ['tenant' => Tenant::first()?->id ?? 1];
        },
    ],
    'mall.tenants.store' => ['roles' => ['admin', 'mall_admin']],
    'mall.utilities.batch' => ['roles' => ['admin', 'mall_admin', 'technician']],
    'mall.utilities.index' => ['roles' => ['admin', 'auditor', 'mall_admin', 'technician']],
    'manufacturing.c2m.orders.cancel' => [
        'roles' => ['admin', 'planner', 'procurement'],
        'params' => fn () => ['order' => C2mCustomOrder::firstOrCreate(['c2m_code' => 'C2M-ROUTE-CANCEL'], [
            'user_id' => 1,
            'base_model' => 'ROUTE_TEST',
            'parameters' => ['size' => 10],
            'complexity_factor' => 1.0,
            'rolled_up_bom_cost_idr' => 1000000,
            'final_price_idr' => 1300000,
            'status' => 'PENDING_APPROVAL',
            'estimated_lead_days' => 5,
        ])->id],
    ],
    'manufacturing.c2m.orders.spk' => [
        'roles' => ['admin', 'planner', 'procurement'],
        'params' => fn () => ['order' => C2mCustomOrder::firstOrCreate(['c2m_code' => 'C2M-ROUTE-SPK'], [
            'user_id' => 1,
            'base_model' => 'ROUTE_TEST',
            'parameters' => ['size' => 10],
            'complexity_factor' => 1.0,
            'rolled_up_bom_cost_idr' => 1000000,
            'final_price_idr' => 1300000,
            'status' => 'DESIGN_VALIDATED',
            'estimated_lead_days' => 5,
        ])->id],
    ],
    'manufacturing.c2m.orders.store' => ['roles' => ['admin', 'planner', 'procurement']],
    'manufacturing.agri-processing.batches.optimize' => ['roles' => ['admin', 'food_qa', 'planner']],
    'manufacturing.agri-processing.index' => ['roles' => ['admin', 'food_qa', 'planner']],
    'manufacturing.boms.store' => ['roles' => ['admin', 'planner']],
    'manufacturing.costing.index' => ['roles' => ['admin', 'auditor', 'planner']],
    'manufacturing.costing.versions.approve' => ['roles' => ['admin', 'planner'], 'params' => fn () => ['version' => 1]],
    'manufacturing.costing.versions.submit' => ['roles' => ['admin', 'planner'], 'params' => fn () => ['version' => 1]],
    'manufacturing.formulas.approve' => ['roles' => ['admin', 'qc_inspector'], 'params' => fn () => ['formula' => 1]],
    'manufacturing.formulas.store' => ['roles' => ['admin', 'planner']],
    'manufacturing.formulas.submit' => ['roles' => ['admin', 'planner'], 'params' => fn () => ['formula' => 1]],
    'manufacturing.index' => ['roles' => ['admin', 'operator', 'planner', 'qc_inspector']],
    'manufacturing.maintenance.incidents.close' => ['roles' => ['admin', 'asset_manager', 'operator', 'planner'], 'params' => fn () => ['incident' => 1]],
    'manufacturing.maintenance.incidents.store' => ['roles' => ['admin', 'asset_manager', 'operator', 'planner']],
    'manufacturing.maintenance.index' => ['roles' => ['admin', 'asset_manager', 'auditor', 'operator', 'planner']],
    'manufacturing.maintenance.oee.compute' => ['roles' => ['admin', 'asset_manager', 'operator', 'planner']],
    'manufacturing.maintenance.orders.store' => ['roles' => ['admin', 'asset_manager', 'operator', 'planner']],
    'manufacturing.maintenance.orders.transition' => ['roles' => ['admin', 'asset_manager', 'operator', 'planner'], 'params' => fn () => ['order' => 1]],
    'manufacturing.maintenance.parts.store' => ['roles' => ['admin', 'asset_manager', 'operator', 'planner']],
    'manufacturing.maintenance.permits.approve' => ['roles' => ['admin', 'asset_manager', 'operator', 'planner'], 'params' => fn () => ['permit' => 1]],
    'manufacturing.maintenance.permits.store' => ['roles' => ['admin', 'asset_manager', 'operator', 'planner']],
    'manufacturing.maintenance.sensors.store' => ['roles' => ['admin', 'asset_manager', 'operator', 'planner']],
    'manufacturing.materials.store' => ['roles' => ['admin', 'planner']],
    'manufacturing.planning.balances.store' => ['roles' => ['admin', 'planner']],
    'manufacturing.planning.forecast.activate' => ['roles' => ['admin', 'planner'], 'params' => fn () => ['scenario' => 1]],
    'manufacturing.planning.forecast.store' => ['roles' => ['admin', 'planner']],
    'manufacturing.planning.index' => ['roles' => ['admin', 'planner']],
    'manufacturing.planning.mps.store' => ['roles' => ['admin', 'planner']],
    'manufacturing.planning.mrp.run' => ['roles' => ['admin', 'planner']],
    'manufacturing.planning.orders.firm' => ['roles' => ['admin', 'planner'], 'params' => fn () => ['order' => 1]],
    'manufacturing.planning.params.store' => ['roles' => ['admin', 'planner']],
    'manufacturing.planning.receipts.store' => ['roles' => ['admin', 'planner']],
    'manufacturing.planning.runs.propose' => ['roles' => ['admin', 'planner'], 'params' => fn () => ['run' => 1]],
    'manufacturing.plants.store' => ['roles' => ['admin', 'planner']],
    'manufacturing.production.downtimes.end' => ['roles' => ['admin', 'operator', 'planner', 'qc_inspector'], 'params' => fn () => ['downtime' => 1]],
    'manufacturing.production.downtimes.store' => ['roles' => ['admin', 'operator', 'planner', 'qc_inspector']],
    'manufacturing.production.index' => ['roles' => ['admin', 'operator', 'planner', 'qc_inspector']],
    'manufacturing.production.operations.finish' => ['roles' => ['admin', 'operator', 'planner', 'qc_inspector'], 'params' => fn () => ['order' => 1]],
    'manufacturing.production.operations.start' => ['roles' => ['admin', 'operator', 'planner', 'qc_inspector'], 'params' => fn () => ['order' => 1]],
    'manufacturing.production.orders.invariants' => ['roles' => ['admin', 'operator', 'planner', 'qc_inspector'], 'params' => fn () => ['order' => 1]],
    'manufacturing.production.orders.issue' => ['roles' => ['admin', 'operator', 'planner', 'qc_inspector'], 'params' => fn () => ['order' => 1]],
    'manufacturing.production.orders.receive-fg' => ['roles' => ['admin', 'operator', 'planner', 'qc_inspector'], 'params' => fn () => ['order' => 1]],
    'manufacturing.production.orders.scrap' => ['roles' => ['admin', 'operator', 'planner', 'qc_inspector'], 'params' => fn () => ['order' => 1]],
    'manufacturing.production.orders.store' => ['roles' => ['admin', 'planner']],
    'manufacturing.production.orders.transition' => ['roles' => ['admin', 'operator', 'planner', 'qc_inspector'], 'params' => fn () => ['order' => 1]],
    'manufacturing.quality.capas.complete' => ['roles' => ['admin', 'planner', 'qc_inspector'], 'params' => fn () => ['capa' => 1]],
    'manufacturing.quality.capas.store' => ['roles' => ['admin', 'planner', 'qc_inspector'], 'params' => fn () => ['ncr' => 1]],
    'manufacturing.quality.index' => ['roles' => ['admin', 'auditor', 'planner', 'qc_inspector']],
    'manufacturing.quality.inspections.approve' => ['roles' => ['admin', 'planner', 'qc_inspector'], 'params' => fn () => ['inspection' => 1]],
    'manufacturing.quality.inspections.store' => ['roles' => ['admin', 'planner', 'qc_inspector']],
    'manufacturing.quality.inspections.waiver' => ['roles' => ['admin', 'planner', 'qc_inspector'], 'params' => fn () => ['inspection' => 1]],
    'manufacturing.quality.lots.certificates' => ['roles' => ['admin', 'planner', 'qc_inspector'], 'params' => fn () => ['lot' => 1]],
    'manufacturing.quality.lots.recall' => ['roles' => ['admin', 'planner', 'qc_inspector'], 'params' => fn () => ['lot' => 1]],
    'manufacturing.quality.lots.release' => ['roles' => ['admin', 'planner', 'qc_inspector'], 'params' => fn () => ['lot' => 1]],
    'manufacturing.quality.lots.trace' => ['roles' => ['admin', 'auditor', 'planner', 'qc_inspector'], 'params' => fn () => ['lot' => 1]],
    'manufacturing.quality.ncrs.store' => ['roles' => ['admin', 'planner', 'qc_inspector']],
    'manufacturing.quality.plans.store' => ['roles' => ['admin', 'planner', 'qc_inspector']],
    'manufacturing.quality.recalls.complete' => ['roles' => ['admin', 'planner', 'qc_inspector'], 'params' => fn () => ['recall' => 1]],
    'manufacturing.quality.recalls.notify' => ['roles' => ['admin', 'planner', 'qc_inspector'], 'params' => fn () => ['recall' => 1]],
    'manufacturing.recycled-materials.index' => ['roles' => ['admin', 'auditor', 'operator', 'planner', 'plant_receiver']],
    'manufacturing.recycled-materials.intake' => ['roles' => ['admin', 'planner', 'plant_receiver']],
    'manufacturing.routings.store' => ['roles' => ['admin', 'planner']],
    'manufacturing.workers.store' => ['roles' => ['admin', 'planner']],
    'manufacturing.vmi-c2m.index' => ['roles' => ['admin', 'auditor', 'operator', 'planner', 'procurement']],
    'manufacturing.c2m.index' => ['roles' => ['admin', 'planner']],
    'manufacturing.vmi.approve' => [
        'roles' => ['admin', 'planner', 'procurement'],
        'params' => fn () => ['replenishment' => VmiReplenishment::firstOrCreate(['vmi_code' => 'VMI-ROUTE-APP'], [
            'supplier_id' => 1,
            'sku' => 'SKU-ROUTE-APP',
            'current_shelf_stock' => 5,
            'reorder_point' => 10,
            'suggested_reorder_qty' => 20,
            'total_estimated_idr' => 500000,
            'contract_plafond_idr' => 10000000,
            'status' => 'PROPOSED',
            'idempotency_key' => 'ROUTE-APP-VMI-01',
        ])->id],
    ],
    'manufacturing.vmi.trigger' => ['roles' => ['admin', 'planner', 'procurement']],
    'notifications.index' => 'auth',
    'notifications.markAllRead' => 'auth',
    'notifications.markAllReadJson' => 'auth',
    'notifications.read' => ['protection' => 'auth', 'params' => fn () => ['notification' => 1]],
    'notifications.recent' => 'auth',
    'ops.metrics' => 'public',
    'partners.index' => ['roles' => ['admin', 'auditor', 'procurement']],
    'partners.show' => ['roles' => ['admin', 'auditor', 'procurement'], 'params' => $partnerParamHelper],
    'partners.store' => ['roles' => ['admin', 'procurement']],
    'partners.transition' => ['roles' => ['admin', 'procurement'], 'params' => $partnerParamHelper],
    'party.create' => ['roles' => ['admin', 'logistics_admin', 'party_manager']],
    'party.index' => ['roles' => ['admin', 'logistics_admin', 'party_manager']],
    'party.kyc.approve' => ['roles' => ['admin', 'logistics_admin', 'party_manager'], 'params' => fn () => ['party' => 1, 'doc' => 1]],
    'party.kyc.reject' => ['roles' => ['admin', 'logistics_admin', 'party_manager'], 'params' => fn () => ['party' => 1, 'doc' => 1]],
    'party.kyc.submit' => ['roles' => ['admin', 'logistics_admin', 'party_manager'], 'params' => fn () => ['party' => 1]],
    'party.legal-entities' => ['roles' => ['admin', 'logistics_admin', 'party_manager']],
    'party.legal-entities.store' => ['roles' => ['admin', 'logistics_admin', 'party_manager']],
    'party.rescore' => ['roles' => ['admin', 'logistics_admin', 'party_manager'], 'params' => fn () => ['party' => 1]],
    'party.screen' => ['roles' => ['admin', 'logistics_admin', 'party_manager'], 'params' => fn () => ['party' => 1]],
    'party.show' => ['roles' => ['admin', 'logistics_admin', 'party_manager'], 'params' => fn () => ['party' => 1]],
    'party.store' => ['roles' => ['admin', 'logistics_admin', 'party_manager']],
    'passport.show' => ['protection' => 'public', 'params' => fn () => ['uuid' => 'test-uuid']],
    'password.confirm' => 'auth',
    'password.email' => 'public',
    'password.request' => 'public',
    'password.reset' => ['protection' => 'public', 'params' => fn () => ['token' => 'test-token']],
    'password.store' => 'public',
    'password.update' => 'auth',
    'plm.index' => ['roles' => ['admin', 'rnd_specialist']],
    'pricing.analytics.compute' => ['roles' => ['admin', 'procurement']],
    'pricing.checkout-quote' => ['roles' => ['pricing_manager']],
    'pricing.claims.settle' => ['roles' => ['admin', 'procurement'], 'params' => fn () => ['claim' => 1]],
    'pricing.claims.validate' => ['roles' => ['admin', 'procurement'], 'params' => fn () => ['claim' => 1]],
    'pricing.discounts.store' => ['roles' => ['admin', 'procurement']],
    'pricing.index' => ['roles' => ['admin', 'auditor', 'procurement']],
    'pricing.margin-policies.store' => ['roles' => ['admin', 'procurement']],
    'pricing.overrides.decide' => ['roles' => ['admin', 'procurement'], 'params' => fn () => ['override' => 1]],
    'pricing.overrides.store' => ['roles' => ['admin', 'procurement']],
    'pricing.price-lists.activate' => ['roles' => ['admin', 'procurement'], 'params' => fn () => ['list' => 1]],
    'pricing.price-lists.items.store' => ['roles' => ['admin', 'procurement']],
    'pricing.price-lists.store' => ['roles' => ['admin', 'procurement']],
    'pricing.promotions.claims.store' => ['roles' => ['admin', 'procurement']],
    'pricing.promotions.store' => ['roles' => ['admin', 'procurement']],
    'pricing.quote.convert' => ['roles' => ['admin', 'procurement']],
    'pricing.quote.freeze' => ['roles' => ['admin', 'procurement']],
    'pricing.rejections' => ['roles' => ['pricing_manager']],
    'pricing.surge' => ['roles' => ['pricing_manager']],
    'pricing.surge.calculate' => ['roles' => ['pricing_manager']],
    'pricing.ticks' => ['roles' => ['admin', 'auditor', 'procurement']],
    'pricing.ticks.store' => ['roles' => ['admin', 'procurement']],
    'procurement.bid-assistant.index' => ['roles' => ['admin', 'procurement', 'procurement_manager']],
    'procurement.bid-assistant.evaluate' => ['roles' => ['admin', 'procurement']],
    'procurement.bid-assistant.prepare' => [
        'roles' => ['admin', 'procurement'],
        'params' => function () {
            $agent = BiddingAgent::firstOrCreate(
                ['agent_code' => 'AGT-ROUTE-TEST'],
                [
                    'entity_code' => 'ENT-001',
                    'commodity_code' => 'COAL-GAR4200',
                    'min_price_floor_idr' => 1000000,
                    'max_price_ceiling_idr' => 1500000,
                    'target_margin_pct' => 15.0,
                    'max_risk_score' => 0.40,
                    'status' => 'ACTIVE',
                ]
            );
            $run = BidRun::firstOrCreate(
                ['run_code' => 'RUN-ROUTE-TEST'],
                [
                    'bidding_agent_id' => $agent->id,
                    'tender_reference_code' => 'TDR-ROUTE-001',
                    'commodity_feed_price_idr' => 1100000,
                    'estimated_logistics_cost_idr' => 50000,
                    'buyer_risk_score' => 0.20,
                    'optimal_bid_price_idr' => 1322500,
                    'clause_drafts' => ['payment_terms' => 'LC_AT_SIGHT'],
                    'evaluation_hash' => hash('sha256', 'RUN-ROUTE-TEST'),
                ]
            );

            return ['run' => $run->id];
        },
    ],
    'procurement.bid-assistant.approve' => [
        'roles' => ['admin', 'procurement_manager'],
        'params' => function () {
            $agent = BiddingAgent::firstOrCreate(
                ['agent_code' => 'AGT-ROUTE-TEST'],
                [
                    'entity_code' => 'ENT-001',
                    'commodity_code' => 'COAL-GAR4200',
                    'min_price_floor_idr' => 1000000,
                    'max_price_ceiling_idr' => 1500000,
                    'target_margin_pct' => 15.0,
                    'max_risk_score' => 0.40,
                    'status' => 'ACTIVE',
                ]
            );
            $run = BidRun::firstOrCreate(
                ['run_code' => 'RUN-ROUTE-TEST'],
                [
                    'bidding_agent_id' => $agent->id,
                    'tender_reference_code' => 'TDR-ROUTE-001',
                    'commodity_feed_price_idr' => 1100000,
                    'estimated_logistics_cost_idr' => 50000,
                    'buyer_risk_score' => 0.20,
                    'optimal_bid_price_idr' => 1322500,
                    'clause_drafts' => ['payment_terms' => 'LC_AT_SIGHT'],
                    'evaluation_hash' => hash('sha256', 'RUN-ROUTE-TEST'),
                ]
            );
            $submission = BidSubmission::firstOrCreate(
                ['submission_code' => 'SUB-ROUTE-TEST'],
                [
                    'bid_run_id' => $run->id,
                    'submitted_bid_price_idr' => 1322500,
                    'requires_four_eyes' => true,
                    'status' => 'PENDING_APPROVAL',
                ]
            );

            return ['submission' => $submission->id];
        },
    ],
    'procurement.bid-assistant.result' => [
        'roles' => ['admin', 'procurement'],
        'params' => function () {
            $agent = BiddingAgent::firstOrCreate(
                ['agent_code' => 'AGT-ROUTE-TEST'],
                [
                    'entity_code' => 'ENT-001',
                    'commodity_code' => 'COAL-GAR4200',
                    'min_price_floor_idr' => 1000000,
                    'max_price_ceiling_idr' => 1500000,
                    'target_margin_pct' => 15.0,
                    'max_risk_score' => 0.40,
                    'status' => 'ACTIVE',
                ]
            );
            $run = BidRun::firstOrCreate(
                ['run_code' => 'RUN-ROUTE-TEST'],
                [
                    'bidding_agent_id' => $agent->id,
                    'tender_reference_code' => 'TDR-ROUTE-001',
                    'commodity_feed_price_idr' => 1100000,
                    'estimated_logistics_cost_idr' => 50000,
                    'buyer_risk_score' => 0.20,
                    'optimal_bid_price_idr' => 1322500,
                    'clause_drafts' => ['payment_terms' => 'LC_AT_SIGHT'],
                    'evaluation_hash' => hash('sha256', 'RUN-ROUTE-TEST'),
                ]
            );
            $submission = BidSubmission::firstOrCreate(
                ['submission_code' => 'SUB-ROUTE-TEST'],
                [
                    'bid_run_id' => $run->id,
                    'submitted_bid_price_idr' => 1322500,
                    'requires_four_eyes' => true,
                    'status' => 'PENDING_APPROVAL',
                ]
            );

            return ['submission' => $submission->id];
        },
    ],
    'procurement.dashboard' => ['roles' => ['admin', 'procurement']],
    'procurement.pos.cancel' => ['roles' => ['admin', 'procurement'], 'params' => fn () => ['po' => 1]],
    'procurement.pos.close' => ['roles' => ['admin', 'procurement'], 'params' => fn () => ['po' => 1]],
    'procurement.pos.import-profile' => ['roles' => ['admin', 'procurement'], 'params' => fn () => ['po' => 1]],
    'procurement.pos.inbound' => ['roles' => ['admin', 'procurement'], 'params' => fn () => ['po' => 1]],
    'procurement.pos.revise' => ['roles' => ['admin', 'procurement'], 'params' => fn () => ['po' => 1]],
    'procurement.pos.show' => ['roles' => ['admin', 'procurement', 'supplier'], 'params' => fn () => ['po' => 1]],
    'procurement.pos.store' => ['roles' => ['admin', 'procurement']],
    'procurement.quotes.store' => ['roles' => ['admin', 'procurement', 'supplier'], 'params' => fn () => ['rfq' => 1]],
    'procurement.requisitions.approve' => ['roles' => ['admin', 'procurement'], 'params' => fn () => ['requisition' => 1]],
    'procurement.requisitions.show' => ['roles' => ['admin', 'procurement', 'supplier'], 'params' => fn () => ['requisition' => 1]],
    'procurement.requisitions.store' => ['roles' => ['admin', 'procurement']],
    'procurement.rfqs.award' => ['roles' => ['admin', 'procurement'], 'params' => fn () => ['rfq' => 1]],
    'procurement.rfqs.show' => ['roles' => ['admin', 'procurement', 'supplier'], 'params' => fn () => ['rfq' => 1]],
    'procurement.rfqs.store' => ['roles' => ['admin', 'procurement']],
    'procurement.tenders.award' => ['roles' => ['admin', 'procurement'], 'params' => fn () => ['tender' => 1]],
    'procurement.tenders.evaluate' => ['roles' => ['admin', 'procurement'], 'params' => fn () => ['tender' => 1]],
    'procurement.tenders.open' => ['roles' => ['admin', 'procurement'], 'params' => fn () => ['tender' => 1]],
    'procurement.tenders.seal' => ['roles' => ['admin', 'procurement', 'supplier'], 'params' => fn () => ['tender' => 1]],
    'procurement.tenders.show' => ['roles' => ['admin', 'procurement', 'supplier'], 'params' => fn () => ['tender' => 1]],
    'procurement.tenders.store' => ['roles' => ['admin', 'procurement']],
    'profile.api-tokens.destroy' => 'auth',
    'profile.api-tokens.store' => 'auth',
    'profile.destroy' => 'auth',
    'profile.edit' => 'auth',
    'profile.update' => 'auth',
    'proptech.index' => ['roles' => ['admin', 'facility_manager', 'proptech_admin']],
    'proptech.bim.index' => ['roles' => ['admin', 'facility_manager', 'proptech_admin']],
    'proptech.bim.simulate' => [
        'roles' => ['admin', 'facility_manager', 'proptech_admin'],
        'params' => function () {
            $model = BimModel::firstOrCreate(
                ['model_code' => 'BIM-ROUTER-TEST-01'],
                [
                    'project_code' => 'PRJ-TEST-01',
                    'name' => 'BIM Model Router Test',
                    'version' => 1,
                    'current_hash' => hash('sha256', 'seed'),
                    'status' => 'APPROVED',
                ]
            );

            return ['model' => $model->id];
        },
    ],
    'proptech.bim.capitalize' => [
        'roles' => ['admin', 'facility_manager', 'proptech_admin'],
        'params' => function () {
            $model = BimModel::firstOrCreate(
                ['model_code' => 'BIM-ROUTER-TEST-01'],
                [
                    'project_code' => 'PRJ-TEST-01',
                    'name' => 'BIM Model Router Test',
                    'version' => 1,
                    'current_hash' => hash('sha256', 'seed'),
                    'status' => 'APPROVED',
                ]
            );

            return ['model' => $model->id];
        },
    ],
    'proptech.flex.index' => ['roles' => ['admin', 'facility_manager', 'proptech_admin']],
    'proptech.flex.store' => ['roles' => ['admin', 'facility_manager', 'proptech_admin']],
    'proptech.flex.checkin' => [
        'roles' => ['admin', 'facility_manager', 'proptech_admin'],
        'params' => function () {
            $space = FlexSpace::firstOrCreate(
                ['space_code' => 'SP-ROUTER-01'],
                ['name' => 'Pod 1', 'space_type' => 'HOT_DESK', 'rate_per_hour_idr' => 50000, 'capacity_persons' => 1, 'status' => 'AVAILABLE']
            );
            $booking = FlexBooking::firstOrCreate(
                ['booking_code' => 'FBK-ROUTER-01'],
                [
                    'flex_space_id' => $space->id,
                    'user_id' => 1,
                    'start_time' => now()->subHour(),
                    'end_time' => now()->addHour(),
                    'total_price_idr' => 100000,
                    'deposit_amount_idr' => 30000,
                    'status' => 'CONFIRMED',
                ]
            );

            return ['booking' => $booking->id];
        },
    ],
    'proptech.flex.noshow' => [
        'roles' => ['admin', 'facility_manager', 'proptech_admin'],
        'params' => function () {
            $space = FlexSpace::firstOrCreate(
                ['space_code' => 'SP-ROUTER-01'],
                ['name' => 'Pod 1', 'space_type' => 'HOT_DESK', 'rate_per_hour_idr' => 50000, 'capacity_persons' => 1, 'status' => 'AVAILABLE']
            );
            $booking = FlexBooking::firstOrCreate(
                ['booking_code' => 'FBK-ROUTER-02'],
                [
                    'flex_space_id' => $space->id,
                    'user_id' => 1,
                    'start_time' => now()->subHours(2),
                    'end_time' => now()->subHour(),
                    'total_price_idr' => 100000,
                    'deposit_amount_idr' => 30000,
                    'status' => 'CONFIRMED',
                ]
            );

            return ['booking' => $booking->id];
        },
    ],
    'proptech.zones.bill' => [
        'roles' => ['admin', 'facility_manager', 'proptech_admin'],
        'params' => function () {
            $zone = BuildingZone::firstOrCreate(['zone_code' => 'ZN-TOWER-A-L10'], ['building_code' => 'BLD-SCBD-01', 'name' => 'SCBD Tower A Floor 10', 'floor_level' => '10', 'area_sqm' => 600.0, 'current_temp_c' => 23.5, 'current_occupancy' => 35, 'current_kwh_rate' => 2000.0]);

            return ['zone' => $zone->id];
        },
    ],
    'register' => 'public',
    'rwa.index' => ['roles' => ['admin', 'asset_manager', 'investor', 'rwa_admin']],
    'rwa.assets.distribute' => [
        'roles' => ['admin', 'asset_manager', 'investor', 'rwa_admin'],
        'params' => function () {
            $asset = RwaAsset::firstOrCreate(['token_symbol' => 'RWA-MALL01'], ['name' => 'Mall Tower', 'underlying_asset_type' => 'PROPERTY', 'underlying_asset_id' => 'BLD-01', 'appraisal_value_idr' => 1000000000, 'total_supply_tokens' => 1000, 'token_price_idr' => 1000000, 'status' => 'active']);

            return ['asset' => $asset->id];
        },
    ],
    'rwa.offerings' => ['roles' => ['admin', 'asset_manager', 'investor', 'rwa_admin']],
    'rwa.offerings.subscribe' => [
        'roles' => ['admin', 'asset_manager', 'investor', 'rwa_admin'],
        'params' => fn () => ['offering' => 1],
    ],
    'rwa.orderbook' => ['roles' => ['admin', 'asset_manager', 'investor', 'rwa_admin']],
    'rwa.yield-distribution' => ['roles' => ['admin', 'asset_manager', 'investor', 'rwa_admin']],
    'resto.analytics.index' => ['roles' => ['admin', 'auditor', 'outlet_manager']],
    'resto.catering.cancel' => [
        'roles' => ['admin', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['order' => CateringOrder::first()?->id ?? 1];
        },
    ],
    'resto.catering.complete' => [
        'roles' => ['admin', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['order' => CateringOrder::first()?->id ?? 1];
        },
    ],
    'resto.catering.create' => ['roles' => ['admin', 'outlet_manager']],
    'resto.catering.deposit' => [
        'roles' => ['admin', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['order' => CateringOrder::first()?->id ?? 1];
        },
    ],
    'resto.catering.destroy' => [
        'roles' => ['admin', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['catering' => CateringOrder::first()?->id ?? 1];
        },
    ],
    'resto.catering.edit' => [
        'roles' => ['admin', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['catering' => CateringOrder::first()?->id ?? 1];
        },
    ],
    'resto.catering.index' => ['roles' => ['admin', 'auditor', 'outlet_manager']],
    'resto.catering.show' => [
        'roles' => ['admin', 'auditor', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['catering' => CateringOrder::first()?->id ?? 1];
        },
    ],
    'resto.catering.store' => ['roles' => ['admin', 'outlet_manager']],
    'resto.catering.update' => [
        'roles' => ['admin', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['catering' => CateringOrder::first()?->id ?? 1];
        },
    ],
    'resto.deliveries.create' => ['roles' => ['admin', 'driver', 'outlet_manager']],
    'resto.deliveries.destroy' => [
        'roles' => ['admin', 'driver', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['delivery' => Delivery::first()?->id ?? 1];
        },
    ],
    'resto.deliveries.edit' => [
        'roles' => ['admin', 'driver', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['delivery' => Delivery::first()?->id ?? 1];
        },
    ],
    'resto.deliveries.fail' => [
        'roles' => ['admin', 'driver', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['delivery' => Delivery::first()?->id ?? 1];
        },
    ],
    'resto.deliveries.index' => ['roles' => ['admin', 'auditor', 'driver', 'outlet_manager']],
    'resto.deliveries.show' => [
        'roles' => ['admin', 'auditor', 'driver', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['delivery' => Delivery::first()?->id ?? 1];
        },
    ],
    'resto.deliveries.status' => [
        'roles' => ['admin', 'driver', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['delivery' => Delivery::first()?->id ?? 1];
        },
    ],
    'resto.deliveries.store' => ['roles' => ['admin', 'driver', 'outlet_manager']],
    'resto.deliveries.update' => [
        'roles' => ['admin', 'driver', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['delivery' => Delivery::first()?->id ?? 1];
        },
    ],
    'resto.franchise.contract.store' => ['roles' => ['admin', 'outlet_manager']],
    'resto.franchise.index' => ['roles' => ['admin', 'auditor', 'outlet_manager']],
    'resto.franchise.royalty.run' => ['roles' => ['admin', 'outlet_manager']],
    'resto.ingredients.conversions.store' => ['roles' => ['admin', 'outlet_manager']],
    'resto.ingredients.create' => ['roles' => ['admin', 'outlet_manager']],
    'resto.ingredients.destroy' => [
        'roles' => ['admin', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['ingredient' => Ingredient::first()?->id ?? 1];
        },
    ],
    'resto.ingredients.edit' => [
        'roles' => ['admin', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['ingredient' => Ingredient::first()?->id ?? 1];
        },
    ],
    'resto.ingredients.index' => ['roles' => ['admin', 'auditor', 'outlet_manager']],
    'resto.ingredients.show' => [
        'roles' => ['admin', 'auditor', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['ingredient' => Ingredient::first()?->id ?? 1];
        },
    ],
    'resto.ingredients.store' => ['roles' => ['admin', 'outlet_manager']],
    'resto.ingredients.update' => [
        'roles' => ['admin', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['ingredient' => Ingredient::first()?->id ?? 1];
        },
    ],
    'resto.kitchen.cook' => ['roles' => ['admin', 'kitchen', 'outlet_manager']],
    'resto.kitchen.index' => ['roles' => ['admin', 'kitchen', 'outlet_manager']],
    'resto.kitchen.simulate' => ['roles' => ['admin', 'kitchen', 'outlet_manager']],
    'resto.kitchen.trays.discard' => [
        'roles' => ['admin', 'kitchen', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['tray' => DisplayTray::first()?->id ?? 1];
        },
    ],
    'resto.kitchen.trays.recirculate' => [
        'roles' => ['admin', 'kitchen', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['tray' => DisplayTray::first()?->id ?? 1];
        },
    ],
    'resto.menu.calculate-cost' => ['roles' => ['admin', 'outlet_manager']],
    'resto.menu.create' => ['roles' => ['admin', 'outlet_manager']],
    'resto.menu.destroy' => [
        'roles' => ['admin', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['menu' => MenuItem::first()?->id ?? 1];
        },
    ],
    'resto.menu.edit' => [
        'roles' => ['admin', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['menu' => MenuItem::first()?->id ?? 1];
        },
    ],
    'resto.menu.index' => ['roles' => ['admin', 'auditor', 'outlet_manager']],
    'resto.menu.show' => [
        'roles' => ['admin', 'auditor', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['menu' => MenuItem::first()?->id ?? 1];
        },
    ],
    'resto.menu.store' => ['roles' => ['admin', 'outlet_manager']],
    'resto.menu.update' => [
        'roles' => ['admin', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['menu' => MenuItem::first()?->id ?? 1];
        },
    ],
    'resto.outlets.create' => ['roles' => ['admin', 'outlet_manager']],
    'resto.outlets.destroy' => [
        'roles' => ['admin', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['outlet' => Outlet::first()?->id ?? 1];
        },
        'setup' => $restoManagerSetup,
    ],
    'resto.outlets.edit' => [
        'roles' => ['admin', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['outlet' => Outlet::first()?->id ?? 1];
        },
        'setup' => $restoManagerSetup,
    ],
    'resto.outlets.index' => ['roles' => ['admin', 'auditor', 'outlet_manager']],
    'resto.outlets.show' => [
        'roles' => ['admin', 'auditor', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['outlet' => Outlet::first()?->id ?? 1];
        },
        'setup' => $restoManagerSetup,
    ],
    'resto.outlets.store' => ['roles' => ['admin', 'outlet_manager']],
    'resto.outlets.update' => [
        'roles' => ['admin', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['outlet' => Outlet::first()?->id ?? 1];
        },
        'setup' => $restoManagerSetup,
    ],
    'resto.pos.index' => ['roles' => ['admin', 'cashier', 'outlet_manager']],
    'resto.pos.order.pay' => [
        'roles' => ['admin', 'cashier', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['order' => Order::first()?->id ?? 1];
        },
    ],
    'resto.pos.order.receipt' => [
        'roles' => ['admin', 'cashier', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['order' => Order::first()?->id ?? 1];
        },
    ],
    'resto.pos.order.validate-parking' => [
        'roles' => ['admin', 'cashier', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['order' => Order::first()?->id ?? 1];
        },
    ],
    'resto.pos.order.void' => [
        'roles' => ['admin', 'cashier', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['order' => Order::first()?->id ?? 1];
        },
    ],
    'resto.pos.session.bill' => [
        'roles' => ['admin', 'cashier', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['session' => TableSession::first()?->id ?? 1];
        },
    ],
    'resto.pos.session.hidang' => [
        'roles' => ['admin', 'cashier', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['session' => TableSession::first()?->id ?? 1];
        },
    ],
    'resto.pos.session.item' => [
        'roles' => ['admin', 'cashier', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['session' => TableSession::first()?->id ?? 1];
        },
    ],
    'resto.pos.session.open' => ['roles' => ['admin', 'cashier', 'outlet_manager']],
    'resto.pos.session.show' => [
        'roles' => ['admin', 'cashier', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['session' => TableSession::first()?->id ?? 1];
        },
    ],
    'resto.pos.shift.close' => ['roles' => ['admin', 'cashier', 'outlet_manager']],
    'resto.pos.shift.open' => ['roles' => ['admin', 'cashier', 'outlet_manager']],
    'resto.pos.shift.settle' => ['roles' => ['admin', 'cashier', 'outlet_manager']],
    'resto.purchases.create' => ['roles' => ['admin', 'outlet_manager', 'procurement']],
    'resto.purchases.destroy' => [
        'roles' => ['admin', 'outlet_manager', 'procurement'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['purchase' => PurchaseOrder::first()?->id ?? 1];
        },
        'setup' => $restoManagerSetup,
    ],
    'resto.purchases.edit' => [
        'roles' => ['admin', 'outlet_manager', 'procurement'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['purchase' => PurchaseOrder::first()?->id ?? 1];
        },
        'setup' => $restoManagerSetup,
    ],
    'resto.purchases.index' => ['roles' => ['admin', 'auditor', 'outlet_manager', 'procurement']],
    'resto.purchases.pay' => [
        'roles' => ['admin', 'outlet_manager', 'procurement'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['purchase' => PurchaseOrder::first()?->id ?? 1];
        },
        'setup' => $restoManagerSetup,
    ],
    'resto.purchases.receive' => [
        'roles' => ['admin', 'outlet_manager', 'procurement'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['purchase' => PurchaseOrder::first()?->id ?? 1];
        },
        'setup' => $restoManagerSetup,
    ],
    'resto.purchases.show' => [
        'roles' => ['admin', 'auditor', 'outlet_manager', 'procurement'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['purchase' => PurchaseOrder::first()?->id ?? 1];
        },
        'setup' => $restoManagerSetup,
    ],
    'resto.purchases.store' => ['roles' => ['admin', 'outlet_manager', 'procurement']],
    'resto.purchases.update' => [
        'roles' => ['admin', 'outlet_manager', 'procurement'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['purchase' => PurchaseOrder::first()?->id ?? 1];
        },
        'setup' => $restoManagerSetup,
    ],
    'resto.stock-counts.approve' => [
        'roles' => ['admin', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['stock_count' => StockCount::first()?->id ?? 1];
        },
    ],
    'resto.stock-counts.create' => ['roles' => ['admin', 'outlet_manager']],
    'resto.stock-counts.destroy' => [
        'roles' => ['admin', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['stock_count' => StockCount::first()?->id ?? 1];
        },
    ],
    'resto.stock-counts.edit' => [
        'roles' => ['admin', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['stock_count' => StockCount::first()?->id ?? 1];
        },
    ],
    'resto.stock-counts.index' => ['roles' => ['admin', 'auditor', 'outlet_manager']],
    'resto.stock-counts.show' => [
        'roles' => ['admin', 'auditor', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['stock_count' => StockCount::first()?->id ?? 1];
        },
    ],
    'resto.stock-counts.store' => ['roles' => ['admin', 'outlet_manager']],
    'resto.stock-counts.update' => [
        'roles' => ['admin', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['stock_count' => StockCount::first()?->id ?? 1];
        },
    ],
    'resto.transfers.create' => ['roles' => ['admin', 'kitchen', 'outlet_manager']],
    'resto.transfers.destroy' => [
        'roles' => ['admin', 'kitchen', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['transfer' => StockTransfer::first()?->id ?? 1];
        },
    ],
    'resto.transfers.edit' => [
        'roles' => ['admin', 'kitchen', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['transfer' => StockTransfer::first()?->id ?? 1];
        },
    ],
    'resto.transfers.index' => ['roles' => ['admin', 'auditor', 'kitchen', 'outlet_manager']],
    'resto.transfers.receive' => [
        'roles' => ['admin', 'kitchen', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['transfer' => StockTransfer::first()?->id ?? 1];
        },
    ],
    'resto.transfers.show' => [
        'roles' => ['admin', 'auditor', 'kitchen', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['transfer' => StockTransfer::first()?->id ?? 1];
        },
    ],
    'resto.transfers.store' => ['roles' => ['admin', 'kitchen', 'outlet_manager']],
    'resto.transfers.update' => [
        'roles' => ['admin', 'kitchen', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['transfer' => StockTransfer::first()?->id ?? 1];
        },
    ],
    'sanctum.csrf-cookie' => 'public',
    'services.destroy' => ['roles' => ['admin'], 'params' => fn () => ['service' => $storeAutoServeSeedHelper()['service']->id]],
    'services.index' => ['roles' => ['admin']],
    'services.store' => ['roles' => ['admin']],
    'services.update' => ['roles' => ['admin'], 'params' => fn () => ['service' => $storeAutoServeSeedHelper()['service']->id]],
    'spareparts.destroy' => ['roles' => ['admin'], 'params' => fn () => ['sparepart' => $storeAutoServeSeedHelper()['sparepart']->id]],
    'spareparts.index' => ['roles' => ['admin']],
    'spareparts.store' => ['roles' => ['admin']],
    'spareparts.update' => ['roles' => ['admin'], 'params' => fn () => ['sparepart' => $storeAutoServeSeedHelper()['sparepart']->id]],
    'storage.local' => ['protection' => 'public', 'params' => fn () => ['path' => 'test.txt']],
    'storage.local.upload' => ['protection' => 'signed', 'params' => fn () => ['path' => 'test.txt']],
    'store.admin.orders.cancel' => ['roles' => ['admin'], 'params' => fn () => ['order' => $storeAutoServeSeedHelper()['order']->uuid]],
    'store.admin.orders.complete' => ['roles' => ['admin'], 'params' => fn () => ['order' => $storeAutoServeSeedHelper()['order']->uuid]],
    'store.admin.orders.index' => ['roles' => ['admin']],
    'store.admin.orders.resolveDispute' => ['roles' => ['admin'], 'params' => fn () => ['order' => $storeAutoServeSeedHelper()['order']->uuid]],
    'store.admin.orders.ship' => ['roles' => ['admin'], 'params' => fn () => ['order' => $storeAutoServeSeedHelper()['order']->uuid]],
    'store.admin.orders.show' => ['roles' => ['admin'], 'params' => fn () => ['order' => $storeAutoServeSeedHelper()['order']->uuid]],
    'store.admin.products.adjustStock' => ['roles' => ['admin'], 'params' => fn () => ['product' => $storeAutoServeSeedHelper()['product']->id]],
    'store.admin.products.createCarListing' => ['roles' => ['admin']],
    'store.admin.products.index' => ['roles' => ['admin']],
    'store.admin.products.toggle' => ['roles' => ['admin'], 'params' => fn () => ['product' => $storeAutoServeSeedHelper()['product']->id]],
    'store.c2c.buy' => ['roles' => ['admin', 'customer'], 'params' => fn () => ['product' => $storeAutoServeSeedHelper()['product']->slug]],
    'store.c2m.index' => ['roles' => ['admin', 'customer']],
    'store.c2m.configure' => ['roles' => ['admin', 'customer']],
    'store.c2c.buyForm' => ['roles' => ['admin', 'auditor', 'customer'], 'params' => fn () => ['product' => $storeAutoServeSeedHelper()['product']->slug]],
    'store.c2c.cancel' => ['roles' => ['admin', 'customer'], 'params' => fn () => ['order' => $storeAutoServeSeedHelper()['order']->uuid], 'setup' => $storeAutoServeCustomerSetup],
    'store.c2c.confirm' => ['roles' => ['admin', 'customer'], 'params' => fn () => ['order' => $storeAutoServeSeedHelper()['order']->uuid], 'setup' => $storeAutoServeCustomerSetup],
    'store.c2c.dispute' => ['roles' => ['admin', 'customer'], 'params' => fn () => ['order' => $storeAutoServeSeedHelper()['order']->uuid], 'setup' => $storeAutoServeCustomerSetup],
    'store.c2c.handover' => ['roles' => ['admin', 'customer'], 'params' => fn () => ['order' => $storeAutoServeSeedHelper()['order']->uuid], 'setup' => $storeAutoServeCustomerSetup],
    'store.c2c.index' => 'public',
    'store.c2c.mySales' => ['roles' => ['admin', 'auditor', 'customer']],
    'store.c2c.store' => ['roles' => ['admin', 'customer']],
    'store.c2c.unlist' => ['roles' => ['admin', 'customer'], 'params' => fn () => ['product' => $storeAutoServeSeedHelper()['product']->id]],
    'store.cart.count' => ['roles' => ['admin', 'auditor', 'customer']],
    'store.cart.destroy' => ['roles' => ['admin', 'customer'], 'params' => fn () => ['product' => $storeAutoServeSeedHelper()['product']->id]],
    'store.cart.index' => ['roles' => ['admin', 'auditor', 'customer']],
    'store.cart.store' => ['roles' => ['admin', 'customer']],
    'store.cart.update' => ['roles' => ['admin', 'customer'], 'params' => fn () => ['product' => $storeAutoServeSeedHelper()['product']->id]],
    'store.catalog.index' => 'public',
    'store.checkout.index' => ['roles' => ['admin', 'auditor', 'customer']],
    'store.checkout.process' => ['roles' => ['admin', 'customer']],
    'store.orders.cancel' => ['roles' => ['admin', 'customer'], 'params' => fn () => ['order' => $storeAutoServeSeedHelper()['order']->uuid], 'setup' => $storeAutoServeCustomerSetup],
    'store.orders.confirm' => ['roles' => ['admin', 'customer'], 'params' => fn () => ['order' => $storeAutoServeSeedHelper()['order']->uuid], 'setup' => $storeAutoServeCustomerSetup],
    'store.orders.index' => ['roles' => ['admin', 'auditor', 'customer']],
    'store.orders.show' => ['roles' => ['admin', 'auditor', 'customer'], 'params' => fn () => ['order' => $storeAutoServeSeedHelper()['order']->uuid], 'setup' => $storeAutoServeCustomerSetup],
    'store.products.show' => ['protection' => 'public', 'params' => fn () => ['slug' => 'test-product']],
    'supplier.certifications.store' => ['roles' => ['admin', 'party_manager', 'procurement'], 'params' => fn () => ['supplier' => 1]],
    'supplier.create' => ['roles' => ['admin', 'party_manager', 'procurement']],
    'supplier.index' => ['roles' => ['admin', 'party_manager', 'procurement']],
    'supplier.items.store' => ['roles' => ['admin', 'party_manager', 'procurement'], 'params' => fn () => ['supplier' => 1]],
    'supplier.portal.asn.ship' => [
        'roles' => ['admin', 'supplier'], 'params' => fn () => ['asn' => 1],
        'setup' => function (User $user): void {
            if (class_exists(Supplier::class)) {
                Supplier::firstOrCreate(
                    ['owner_user_id' => $user->id],
                    ['name' => 'Supplier Test', 'code' => 'SUP-'.$user->id, 'kind' => 'supplier', 'is_active' => true]
                );
            }
        },
    ],
    'supplier.portal.asn.store' => [
        'roles' => ['admin', 'supplier'],
        'setup' => function (User $user): void {
            if (class_exists(Supplier::class)) {
                Supplier::firstOrCreate(
                    ['owner_user_id' => $user->id],
                    ['name' => 'Supplier Test', 'code' => 'SUP-'.$user->id, 'kind' => 'supplier', 'is_active' => true]
                );
            }
        },
    ],
    'supplier.portal.documents.store' => [
        'roles' => ['admin', 'supplier'],
        'setup' => function (User $user): void {
            if (class_exists(Supplier::class)) {
                Supplier::firstOrCreate(
                    ['owner_user_id' => $user->id],
                    ['name' => 'Supplier Test', 'code' => 'SUP-'.$user->id, 'kind' => 'supplier', 'is_active' => true]
                );
            }
        },
    ],
    'supplier.portal.home' => [
        'roles' => ['admin', 'supplier'],
        'setup' => function (User $user): void {
            if (class_exists(Supplier::class)) {
                Supplier::firstOrCreate(
                    ['owner_user_id' => $user->id],
                    ['name' => 'Supplier Test', 'code' => 'SUP-'.$user->id, 'kind' => 'supplier', 'is_active' => true]
                );
            }
        },
    ],
    'supplier.qualifications.approve' => ['roles' => ['admin', 'party_manager', 'procurement'], 'params' => fn () => ['supplier' => 1]],
    'supplier.qualifications.store' => ['roles' => ['admin', 'party_manager', 'procurement'], 'params' => fn () => ['supplier' => 1]],
    'supplier.scan-risks' => ['roles' => ['admin', 'party_manager', 'procurement']],
    'supplier.scorecards.store' => ['roles' => ['admin', 'party_manager', 'procurement'], 'params' => fn () => ['supplier' => 1]],
    'supplier.show' => ['roles' => ['admin', 'party_manager', 'procurement'], 'params' => fn () => ['supplier' => 1]],
    'supplier.store' => ['roles' => ['admin', 'party_manager', 'procurement']],
    'supplier.transition' => ['roles' => ['admin', 'party_manager', 'procurement'], 'params' => fn () => ['supplier' => 1]],
    'telematics.index' => ['roles' => ['admin', 'fleet_manager', 'telematics_admin']],
    'telematics.devices.ingest' => [
        'roles' => ['admin', 'fleet_manager', 'telematics_admin'],
        'params' => function () {
            $user = User::firstOrCreate(['id' => 1], ['name' => 'Arch User', 'email' => 'arch@test.test', 'password' => 'secret', 'role' => 'customer']);
            $vehicle = Vehicle::firstOrCreate(
                ['plate_number' => 'B-1234-TLM'],
                ['uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'vin' => 'VIN-TLM-001', 'status' => 'active']
            );
            $device = TelematicsDevice::firstOrCreate(['device_id' => 'DEV-OBD2-001'], ['vehicle_id' => $vehicle->id, 'serial_number' => 'SN-TLM-889900', 'protocol' => 'MQTT_CANBUS', 'status' => 'online', 'last_heartbeat_at' => now()]);

            return ['device' => $device->id];
        },
    ],
    'track.index' => 'public',
    'track.show' => ['protection' => 'public', 'params' => fn () => ['tracking_number' => 'TRK-001']],
    'trade.index' => ['roles' => ['admin', 'auditor', 'procurement', 'treasury']],
    'trade.bidding.index' => ['roles' => ['admin', 'auditor', 'procurement', 'treasury']],
    'trade.bidding.agents.store' => ['roles' => ['admin', 'auditor', 'procurement', 'treasury']],
    'trade.bidding.runs.evaluate' => ['roles' => ['admin', 'auditor', 'procurement', 'treasury']],
    'trade.bidding.runs.prepare' => [
        'roles' => ['admin', 'auditor', 'procurement', 'treasury'],
        'params' => function () {
            $agent = BiddingAgent::firstOrCreate(
                ['agent_code' => 'AGT-ROUTE-TEST'],
                [
                    'entity_code' => 'ENT-001',
                    'commodity_code' => 'COAL-GAR4200',
                    'min_price_floor_idr' => 1000000,
                    'max_price_ceiling_idr' => 1500000,
                    'target_margin_pct' => 15.0,
                    'max_risk_score' => 0.40,
                    'status' => 'ACTIVE',
                ]
            );
            $run = BidRun::firstOrCreate(
                ['run_code' => 'RUN-ROUTE-TEST'],
                [
                    'bidding_agent_id' => $agent->id,
                    'tender_reference_code' => 'TDR-ROUTE-001',
                    'commodity_feed_price_idr' => 1100000,
                    'estimated_logistics_cost_idr' => 50000,
                    'buyer_risk_score' => 0.20,
                    'optimal_bid_price_idr' => 1322500,
                    'clause_drafts' => ['payment_terms' => 'LC_AT_SIGHT'],
                    'evaluation_hash' => hash('sha256', 'RUN-ROUTE-TEST'),
                ]
            );

            return ['run' => $run->id];
        },
    ],
    'trade.bidding.submissions.approve' => [
        'roles' => ['admin', 'auditor', 'procurement', 'treasury'],
        'params' => function () {
            $agent = BiddingAgent::firstOrCreate(
                ['agent_code' => 'AGT-ROUTE-TEST'],
                [
                    'entity_code' => 'ENT-001',
                    'commodity_code' => 'COAL-GAR4200',
                    'min_price_floor_idr' => 1000000,
                    'max_price_ceiling_idr' => 1500000,
                    'target_margin_pct' => 15.0,
                    'max_risk_score' => 0.40,
                    'status' => 'ACTIVE',
                ]
            );
            $run = BidRun::firstOrCreate(
                ['run_code' => 'RUN-ROUTE-TEST'],
                [
                    'bidding_agent_id' => $agent->id,
                    'tender_reference_code' => 'TDR-ROUTE-001',
                    'commodity_feed_price_idr' => 1100000,
                    'estimated_logistics_cost_idr' => 50000,
                    'buyer_risk_score' => 0.20,
                    'optimal_bid_price_idr' => 1322500,
                    'clause_drafts' => ['payment_terms' => 'LC_AT_SIGHT'],
                    'evaluation_hash' => hash('sha256', 'RUN-ROUTE-TEST'),
                ]
            );
            $submission = BidSubmission::firstOrCreate(
                ['submission_code' => 'SUB-ROUTE-TEST'],
                [
                    'bid_run_id' => $run->id,
                    'submitted_bid_price_idr' => 1322500,
                    'requires_four_eyes' => true,
                    'status' => 'PENDING_APPROVAL',
                ]
            );

            return ['submission' => $submission->id];
        },
    ],
    'trade.bidding.submissions.result' => [
        'roles' => ['admin', 'auditor', 'procurement', 'treasury'],
        'params' => function () {
            $agent = BiddingAgent::firstOrCreate(
                ['agent_code' => 'AGT-ROUTE-TEST'],
                [
                    'entity_code' => 'ENT-001',
                    'commodity_code' => 'COAL-GAR4200',
                    'min_price_floor_idr' => 1000000,
                    'max_price_ceiling_idr' => 1500000,
                    'target_margin_pct' => 15.0,
                    'max_risk_score' => 0.40,
                    'status' => 'ACTIVE',
                ]
            );
            $run = BidRun::firstOrCreate(
                ['run_code' => 'RUN-ROUTE-TEST'],
                [
                    'bidding_agent_id' => $agent->id,
                    'tender_reference_code' => 'TDR-ROUTE-001',
                    'commodity_feed_price_idr' => 1100000,
                    'estimated_logistics_cost_idr' => 50000,
                    'buyer_risk_score' => 0.20,
                    'optimal_bid_price_idr' => 1322500,
                    'clause_drafts' => ['payment_terms' => 'LC_AT_SIGHT'],
                    'evaluation_hash' => hash('sha256', 'RUN-ROUTE-TEST'),
                ]
            );
            $submission = BidSubmission::firstOrCreate(
                ['submission_code' => 'SUB-ROUTE-TEST-2'],
                [
                    'bid_run_id' => $run->id,
                    'submitted_bid_price_idr' => 1322500,
                    'requires_four_eyes' => false,
                    'status' => 'SUBMITTED',
                ]
            );

            return ['submission' => $submission->id];
        },
    ],
    'trade_finance.index' => ['roles' => ['admin', 'auditor', 'procurement', 'treasury']],
    'trade_finance.lcs' => ['roles' => ['admin', 'auditor', 'procurement', 'treasury']],
    'trade_finance.crossborder.cbam' => ['roles' => ['admin', 'auditor', 'procurement', 'treasury']],
    'trade_finance.crossborder.deposit' => ['roles' => ['admin', 'auditor', 'procurement', 'treasury']],
    'trade_finance.crossborder.dispute' => [
        'roles' => ['admin', 'auditor', 'procurement', 'treasury'],
        'params' => fn () => ['escrow' => CrossBorderEscrow::firstOrCreate(['escrow_code' => 'CBE-ROUTE-TEST'], [
            'importer_id' => 1,
            'exporter_id' => 2,
            'stablecoin_asset' => 'USDC',
            'amount_cents' => 100000,
            'locked_fx_rate_idr' => 16000,
            'bl_number' => 'BL-ROUTE-RELEASE',
            'bl_document_hash' => 'HASH-ROUTE',
            'status' => 'DEPOSITED',
        ])->id],
    ],
    'trade_finance.crossborder.index' => ['roles' => ['admin', 'auditor', 'procurement', 'treasury']],
    'trade_finance.crossborder.release' => [
        'roles' => ['admin', 'auditor', 'procurement', 'treasury'],
        'params' => fn () => ['escrow' => CrossBorderEscrow::firstOrCreate(['escrow_code' => 'CBE-ROUTE-TEST'], [
            'importer_id' => 1,
            'exporter_id' => 2,
            'stablecoin_asset' => 'USDC',
            'amount_cents' => 100000,
            'locked_fx_rate_idr' => 16000,
            'bl_number' => 'BL-ROUTE-RELEASE',
            'bl_document_hash' => 'HASH-ROUTE',
            'status' => 'DEPOSITED',
        ])->id],
    ],
    'trade_finance.crossborder.resolve-dispute' => [
        'roles' => ['admin', 'auditor', 'procurement', 'treasury'],
        'params' => fn () => ['escrow' => CrossBorderEscrow::firstOrCreate(['escrow_code' => 'CBE-ROUTE-DISP'], [
            'importer_id' => 1,
            'exporter_id' => 2,
            'stablecoin_asset' => 'USDC',
            'amount_cents' => 100000,
            'locked_fx_rate_idr' => 16000,
            'bl_number' => 'BL-ROUTE-DISP',
            'bl_document_hash' => 'HASH-ROUTE-DISP',
            'status' => 'DISPUTED',
        ])->id],
    ],
    'treasury.index' => ['roles' => ['admin', 'auditor', 'treasury']],
    'verification.notice' => 'auth',
    'verification.send' => 'auth',
    'verification.verify' => ['protection' => 'auth', 'params' => fn () => ['id' => 1, 'hash' => 'testhash']],
    'vmi.portal' => ['roles' => ['admin', 'supplier']],
    'vmi.trigger' => ['roles' => ['admin', 'supplier']],
    'vmi.approve' => [
        'roles' => ['admin', 'procurement', 'procurement_manager'],
        'params' => fn () => ['replenishment' => VmiReplenishment::firstOrCreate(['vmi_code' => 'VMI-ROUTE-APP-NEW'], [
            'supplier_id' => 1,
            'sku' => 'SKU-ROUTE-APP',
            'current_shelf_stock' => 10,
            'reorder_point' => 20,
            'suggested_reorder_qty' => 50,
            'total_estimated_idr' => 500000,
            'contract_plafond_idr' => 1000000,
            'status' => 'PENDING_APPROVAL',
            'idempotency_key' => 'ROUTE-APP-VMI-NEW',
        ])->id],
    ],
    'vending.dispense.api' => ['roles' => ['admin', 'store_manager', 'vending_operator']],
    'vending.index' => ['roles' => ['admin', 'store_manager', 'vending_operator']],
    'vending.units.dispense' => [
        'roles' => ['admin', 'store_manager', 'vending_operator'],
        'params' => function () {
            $unit = VendingUnit::firstOrCreate(
                ['unit_code' => 'VEN-ROUTER-01'],
                [
                    'location_name' => 'Main Lobby',
                    'status' => 'ACTIVE',
                    'current_stock' => 50,
                    'critical_threshold' => 10,
                    'max_capacity' => 100,
                    'total_sales_count' => 0,
                    'total_sales_idr' => 0,
                    'sensor_status' => ['temp' => 4.0],
                ]
            );

            return ['unit' => $unit->id];
        },
    ],
    'venue.gate.scan' => [
        'roles' => ['admin', 'venue_manager', 'venue_staff'],
        'params' => function () {
            $venue = EntertainmentVenue::firstOrCreate(['venue_code' => 'VEN-ATLAS-01'], ['name' => 'Atlas', 'venue_type' => 'BEACH_CLUB', 'city' => 'Badung, Bali', 'min_age_requirement' => 18, 'max_legal_capacity' => 1000]);
            $event = EntertainmentEvent::firstOrCreate(['event_code' => 'EVT-TEST-01'], ['venue_id' => $venue->id, 'title' => 'Test Event', 'doors_open_at' => now()->addDays(7), 'ticket_floor_price_idr' => 100000, 'ticket_ceiling_price_idr' => 1000000, 'current_ticket_price_idr' => 500000]);
            $tck = EntertainmentTicket::firstOrCreate(['ticket_number' => 'TCK-DEMO-001'], ['event_id' => $event->id, 'buyer_user_id' => 1, 'price_paid_idr' => 750000, 'ticket_hash' => 'hash', 'status' => 'VALID']);

            return ['ticket' => $tck->id];
        },
    ],
    'venue.index' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'wallet.index' => ['roles' => ['admin', 'auditor', 'customer']],
    'wallet.mutasi' => ['roles' => ['admin', 'auditor', 'customer']],
    'wallet.mutasi.export' => ['roles' => ['admin', 'auditor', 'customer']],
    'wallet.pin.store' => ['roles' => ['admin', 'customer']],
    'wallet.topup.store' => ['roles' => ['admin', 'customer']],
    'wallet.transfer' => ['roles' => ['admin', 'auditor', 'customer']],
    'wallet.transfer.lookup' => ['roles' => ['admin', 'customer']],
    'wallet.transfer.store' => ['roles' => ['admin', 'customer']],
    'wealth.cash-sweep' => ['roles' => ['admin', 'customer', 'investor', 'treasury', 'wealth_manager']],
    'wealth.index' => ['roles' => ['admin', 'customer', 'investor', 'treasury', 'wealth_manager']],
    'wealth.portfolio-rebalancing' => ['roles' => ['admin', 'customer', 'investor', 'treasury', 'wealth_manager']],
    'wealth.profiles.invest' => [
        'roles' => ['admin', 'customer', 'investor', 'treasury', 'wealth_manager'],
        'params' => function () {
            $prof = WmProfile::firstOrCreate(['user_id' => 1], ['risk_profile' => 'moderate', 'monthly_spend_baseline_idr' => 10000000, 'emergency_fund_target_idr' => 20000000, 'auto_invest_enabled' => true]);

            return ['profile' => $prof->id];
        },
    ],
    'wealth.risk-profiles.submit' => ['roles' => ['admin', 'customer', 'investor', 'treasury', 'wealth_manager']],
    'wms.cycle-counts.apply' => ['roles' => ['admin'], 'params' => fn () => ['count' => 1]],
    'wms.cycle-counts.store' => ['roles' => ['admin', 'hub_operator']],
    'wms.cycle-counts.submit' => ['roles' => ['admin', 'hub_operator'], 'params' => fn () => ['count' => 1]],
    'wms.dock.store' => ['roles' => ['admin', 'hub_operator']],
    'wms.index' => ['roles' => ['admin', 'auditor', 'hub_operator', 'procurement']],
    'wms.pick' => ['roles' => ['admin', 'hub_operator']],
    'wms.putaway' => ['roles' => ['admin', 'hub_operator']],
    'wms.replenishments.compute' => ['roles' => ['admin', 'hub_operator']],
    'wms.transfers.receive' => ['roles' => ['admin', 'hub_operator'], 'params' => fn () => ['transfer' => 1]],
    'wms.transfers.ship' => ['roles' => ['admin', 'hub_operator'], 'params' => fn () => ['transfer' => 1]],
    'wms.transfers.store' => ['roles' => ['admin', 'hub_operator']],
    'wms.warehouses.store' => ['roles' => ['admin', 'hub_operator']],
    'wms.waves.release' => ['roles' => ['admin', 'hub_operator'], 'params' => fn () => ['wave' => 1]],
    'wms.waves.store' => ['roles' => ['admin', 'hub_operator']],

    // --- TAHAP 8 CERTIFICATION ROUTES ---
    'agri.contracts.index' => ['roles' => ['admin', 'farmer', 'procurement']],
    'agri.contracts.register' => ['roles' => ['admin', 'farmer', 'procurement']],
    'agri.dashboard' => ['roles' => ['admin', 'farmer', 'procurement']],
    'agri.grading.evaluate' => ['roles' => ['admin', 'farmer', 'procurement']],
    'agri.grading.index' => ['roles' => ['admin', 'farmer', 'procurement']],
    'agri.payout.index' => ['roles' => ['admin', 'farmer', 'procurement']],
    'agri.payout.settle' => ['roles' => ['admin', 'procurement']],
    'b2b.auction.create' => ['roles' => ['admin', 'b2b_buyer', 'procurement']],
    'b2b.auction.index' => ['roles' => ['admin', 'b2b_buyer', 'procurement']],
    'b2b.dashboard' => ['roles' => ['admin', 'b2b_buyer', 'procurement']],
    'b2b.escrow.index' => ['roles' => ['admin', 'b2b_buyer', 'procurement']],
    'b2b.escrow.lock' => ['roles' => ['admin', 'b2b_buyer', 'procurement']],
    'b2b.escrow.release' => ['roles' => ['admin', 'procurement']],
    'b2b.wholesale.index' => ['roles' => ['admin', 'b2b_buyer', 'procurement']],
    'b2b.wholesale.publish' => ['roles' => ['admin', 'b2b_buyer', 'procurement']],
    'cloud_kitchen.operations.audit-thermal' => ['roles' => ['admin', 'kitchen', 'outlet_manager']],
    'cloud_kitchen.operations.batch-couriers' => ['roles' => ['admin', 'kitchen', 'outlet_manager']],
    'cloud_kitchen.operations.courier-batches' => ['roles' => ['admin', 'kitchen', 'outlet_manager']],
    'cloud_kitchen.operations.order-routing' => ['roles' => ['admin', 'kitchen', 'outlet_manager']],
    'cloud_kitchen.operations.revenue-split' => ['roles' => ['admin', 'kitchen', 'outlet_manager']],
    'cloud_kitchen.operations.route-order' => ['roles' => ['admin', 'kitchen', 'outlet_manager']],
    'cloud_kitchen.operations.settle-split' => ['roles' => ['admin', 'kitchen', 'outlet_manager']],
    'cloud_kitchen.operations.thermal-audit' => ['roles' => ['admin', 'kitchen', 'outlet_manager']],
    'control_tower.autonomous.bottlenecks' => ['roles' => ['admin', 'auditor', 'logistics_admin', 'planner']],
    'control_tower.autonomous.forecast' => ['roles' => ['admin', 'auditor', 'logistics_admin', 'planner']],
    'control_tower.autonomous.index' => ['roles' => ['admin', 'auditor', 'logistics_admin', 'planner']],
    'control_tower.autonomous.reallocate' => ['roles' => ['admin', 'auditor', 'logistics_admin', 'planner']],
    'edu.academy.enroll' => ['roles' => ['admin', 'cert_officer', 'corp_lnd', 'edu_admin', 'instructor']],
    'edu.academy.index' => ['roles' => ['admin', 'cert_officer', 'corp_lnd', 'edu_admin', 'instructor']],
    'edu.academy.show' => [
        'roles' => ['admin', 'cert_officer', 'corp_lnd', 'edu_admin', 'instructor'],
        'params' => fn () => ['id' => 1],
    ],
    'edu.admin.programs.index' => ['roles' => ['academy_admin', 'admin']],
    'edu.admin.programs.store' => ['roles' => ['academy_admin', 'admin']],
    'edu.api.certificates.verify' => 'public',
    'edu.certification.index' => ['roles' => ['admin', 'cert_officer', 'corp_lnd', 'edu_admin', 'instructor']],
    'edu.certification.issue' => ['roles' => ['admin', 'cert_officer', 'corp_lnd', 'edu_admin', 'instructor']],
    'edu.certification.verify' => [
        'roles' => ['admin', 'cert_officer', 'corp_lnd', 'edu_admin', 'instructor'],
        'params' => fn () => ['hash' => 1],
    ],
    'edu.grants.disburse' => [
        'roles' => ['admin', 'cert_officer', 'corp_lnd', 'edu_admin', 'instructor'],
        'params' => fn () => ['milestoneId' => 1],
    ],
    'edu.grants.index' => ['roles' => ['admin', 'cert_officer', 'corp_lnd', 'edu_admin', 'instructor']],
    'edu.grants.show' => [
        'roles' => ['admin', 'cert_officer', 'corp_lnd', 'edu_admin', 'instructor'],
        'params' => fn () => ['id' => 1],
    ],
    'edu.student.attempts.show' => [
        'roles' => ['admin', 'student'],
        'params' => fn () => ['id' => 1],
    ],
    'edu.student.certificates.show' => [
        'roles' => ['admin', 'student'],
        'params' => fn () => ['id' => 1],
    ],
    'edu.student.dashboard' => ['roles' => ['admin', 'student']],
    'edu.web.certificates.verify' => 'public',
    'education.index' => ['roles' => ['admin', 'cert_officer', 'corp_lnd', 'edu_admin', 'instructor']],
    'egy.bess.dispatch_arbitrage' => [
        'roles' => ['admin', 'grid_operator'],
        'params' => fn () => ['id' => 1],
    ],
    'egy.bess.index' => ['roles' => ['admin', 'grid_operator']],
    'egy.bills.adjust' => [
        'roles' => ['admin', 'energy_billing'],
        'params' => fn () => ['id' => 1],
    ],
    'egy.bills.consolidated.export' => ['roles' => ['admin', 'energy_billing']],
    'egy.bills.consolidated.index' => ['roles' => ['admin', 'energy_billing']],
    'egy.bills.consolidated.show' => [
        'roles' => ['admin', 'customer', 'energy_billing'],
        'params' => fn () => ['id' => 1],
    ],
    'egy.bills.export' => ['roles' => ['admin', 'energy_billing']],
    'egy.bills.generate' => ['roles' => ['admin', 'energy_billing']],
    'egy.bills.index' => ['roles' => ['admin', 'energy_billing']],
    'egy.bills.pay' => [
        'roles' => ['admin', 'energy_billing'],
        'params' => fn () => ['id' => 1],
    ],
    'egy.bills.show' => [
        'roles' => ['admin', 'energy_billing'],
        'params' => fn () => ['id' => 1],
    ],
    'egy.carbon.export' => ['roles' => ['admin', 'carbon_trader']],
    'egy.carbon.index' => ['roles' => ['admin', 'carbon_trader']],
    'egy.carbon.match' => ['roles' => ['admin', 'carbon_trader']],
    'egy.carbon.order.store' => ['roles' => ['admin', 'carbon_trader']],
    'egy.cbam.export' => ['roles' => ['admin', 'export_officer']],
    'egy.cbam.index' => ['roles' => ['admin', 'export_officer']],
    'egy.cooling.export' => ['roles' => ['admin', 'utility_operator']],
    'egy.cooling.index' => ['roles' => ['admin', 'utility_operator']],
    'egy.customer.bill' => [
        'roles' => ['admin', 'customer'],
        'params' => fn () => ['id' => 1],
    ],
    'egy.customer.index' => ['roles' => ['admin', 'customer']],
    'egy.customer.portal' => ['roles' => ['admin', 'customer']],
    'egy.demand_response.index' => ['roles' => ['admin', 'grid_operator']],
    'egy.demand_response.respond' => ['roles' => ['admin', 'grid_operator']],
    'egy.dispatch.index' => ['roles' => ['admin', 'grid_operator']],
    'egy.dispatch.run' => ['roles' => ['admin', 'grid_operator']],
    'egy.dispatch.store' => ['roles' => ['admin', 'grid_operator']],
    'egy.esco.index' => ['roles' => ['admin', 'energy_auditor']],
    'egy.esco.show' => [
        'roles' => ['admin', 'customer', 'energy_auditor'],
        'params' => fn () => ['id' => 1],
    ],
    'egy.esg.marketplace.export' => ['roles' => ['admin', 'customer', 'energy_auditor']],
    'egy.esg.marketplace.index' => ['roles' => ['admin', 'customer', 'energy_auditor']],
    'egy.esg.marketplace.purchase' => ['roles' => ['admin', 'customer', 'energy_auditor']],
    'egy.generators.index' => ['roles' => ['admin', 'grid_operator']],
    'egy.generators.test' => ['roles' => ['admin', 'grid_operator']],
    'egy.grid.anomaly' => [
        'roles' => ['admin', 'energy_auditor', 'genco_trader', 'grid_operator', 'renewable_dev'],
        'params' => fn () => ['nodeId' => 1],
    ],
    'egy.grid.dashboard' => ['roles' => ['admin', 'grid_operator']],
    'egy.grid.index' => ['roles' => ['admin', 'energy_auditor', 'genco_trader', 'grid_operator', 'renewable_dev']],
    'egy.grid.meters' => ['roles' => ['admin', 'energy_auditor', 'genco_trader', 'grid_operator', 'renewable_dev']],
    'egy.grid.telemetry' => ['roles' => ['admin', 'energy_auditor', 'genco_trader', 'grid_operator', 'renewable_dev']],
    'egy.meters.index' => ['roles' => ['admin', 'energy_billing']],
    'egy.microgrid.controllers.index' => ['roles' => ['admin', 'grid_operator']],
    'egy.microgrid.controllers.island' => [
        'roles' => ['admin', 'grid_operator'],
        'params' => fn () => ['id' => 1],
    ],
    'egy.microgrid.dispatch' => ['roles' => ['admin', 'energy_auditor', 'genco_trader', 'grid_operator', 'renewable_dev']],
    'egy.microgrid.index' => ['roles' => ['admin', 'energy_auditor', 'genco_trader', 'grid_operator', 'renewable_dev']],
    'egy.microgrid.p2p' => ['roles' => ['admin', 'energy_auditor', 'genco_trader', 'grid_operator', 'renewable_dev']],
    'egy.p2p.index' => ['roles' => ['admin', 'grid_operator']],
    'egy.p2p.trade' => ['roles' => ['admin', 'grid_operator']],
    'egy.ppa.detail' => [
        'roles' => ['admin', 'genco_trader'],
        'params' => fn () => ['id' => 1],
    ],
    'egy.ppa.index' => ['roles' => ['admin', 'energy_auditor', 'genco_trader', 'grid_operator', 'renewable_dev']],
    'egy.ppa.list' => ['roles' => ['admin', 'genco_trader']],
    'egy.ppa.process_settle' => [
        'roles' => ['admin', 'genco_trader'],
        'params' => fn () => ['id' => 1],
    ],
    'egy.ppa.settle' => [
        'roles' => ['admin', 'energy_auditor', 'genco_trader', 'grid_operator', 'renewable_dev'],
        'params' => fn () => ['id' => 1],
    ],
    'egy.ppa.show' => [
        'roles' => ['admin', 'energy_auditor', 'genco_trader', 'grid_operator', 'renewable_dev'],
        'params' => fn () => ['id' => 1],
    ],
    'egy.rec.export' => ['roles' => ['admin', 'renewable_dev']],
    'egy.rec.index' => ['roles' => ['admin', 'renewable_dev']],
    'egy.rec.mint' => ['roles' => ['admin', 'energy_auditor', 'genco_trader', 'grid_operator', 'renewable_dev']],
    'egy.rec.retire' => ['roles' => ['admin', 'renewable_dev']],
    'egy.waste.export' => ['roles' => ['admin', 'utility_operator']],
    'egy.waste.index' => ['roles' => ['admin', 'utility_operator']],
    'egy.water.export' => ['roles' => ['admin', 'utility_operator']],
    'egy.water.index' => ['roles' => ['admin', 'utility_operator']],
    'egy.water.leaks' => ['roles' => ['admin', 'utility_operator']],
    'energy.index' => ['roles' => ['admin', 'energy_auditor', 'genco_trader', 'grid_operator', 'renewable_dev']],
    'enterprise_finance.governance.budget.enforce' => [
        'roles' => ['admin', 'auditor', 'treasury'],
        'params' => fn () => ['budget' => 1],
    ],
    'enterprise_finance.governance.covenants.monitor' => ['roles' => ['admin', 'auditor', 'treasury']],
    'enterprise_finance.governance.index' => ['roles' => ['admin', 'auditor', 'treasury']],
    'enterprise_finance.governance.trial_balance.consolidate' => ['roles' => ['admin', 'auditor', 'treasury']],
    'epc.bast.index' => ['roles' => ['admin', 'asset_manager', 'epc_manager']],
    'epc.bast.issue' => ['roles' => ['admin', 'asset_manager', 'epc_manager']],
    'epc.capitalization.capitalize' => ['roles' => ['admin', 'asset_manager', 'epc_manager']],
    'epc.capitalization.index' => ['roles' => ['admin', 'asset_manager', 'epc_manager']],
    'epc.claims.index' => ['roles' => ['admin', 'asset_manager', 'epc_manager']],
    'epc.claims.record' => ['roles' => ['admin', 'asset_manager', 'epc_manager']],
    'epc.dashboard' => ['roles' => ['admin', 'asset_manager', 'epc_manager']],
    'esg.operations.credits.purchase' => ['roles' => ['admin', 'auditor', 'esg_officer']],
    'esg.operations.credits.retire' => [
        'roles' => ['admin', 'auditor', 'esg_officer'],
        'params' => fn () => ['credit' => 1],
    ],
    'esg.operations.emissions.calculate' => ['roles' => ['admin', 'auditor', 'esg_officer']],
    'esg.operations.index' => ['roles' => ['admin', 'auditor', 'esg_officer']],
    'esg.operations.supplier.evaluate' => ['roles' => ['admin', 'auditor', 'esg_officer']],
    'ev.smart-grid.battery-passport' => ['roles' => ['admin', 'driver', 'ev_admin', 'station_operator']],
    'ev.smart-grid.discharge-v2g' => ['roles' => ['admin', 'driver', 'ev_admin', 'station_operator']],
    'ev.smart-grid.ocpp-sessions' => ['roles' => ['admin', 'driver', 'ev_admin', 'station_operator']],
    'ev.smart-grid.settle-tou' => ['roles' => ['admin', 'driver', 'ev_admin', 'station_operator']],
    'ev.smart-grid.start-ocpp' => ['roles' => ['admin', 'driver', 'ev_admin', 'station_operator']],
    'ev.smart-grid.tou-tariffs' => ['roles' => ['admin', 'driver', 'ev_admin', 'station_operator']],
    'ev.smart-grid.v2g-feedin' => ['roles' => ['admin', 'driver', 'ev_admin', 'station_operator']],
    'fleet.lifecycle.amortization-schedule' => ['roles' => ['admin', 'fleet_admin', 'lease_manager']],
    'fleet.lifecycle.bill-excess' => ['roles' => ['admin', 'fleet_admin', 'lease_manager']],
    'fleet.lifecycle.excess-mileage' => ['roles' => ['admin', 'fleet_admin', 'lease_manager']],
    'fleet.lifecycle.originate-psak73' => ['roles' => ['admin', 'fleet_admin', 'lease_manager']],
    'fleet.lifecycle.psak73-leases' => ['roles' => ['admin', 'fleet_admin', 'lease_manager']],
    'fleet.lifecycle.recognize-amortization' => ['roles' => ['admin', 'fleet_admin', 'lease_manager']],
    'fleet.lifecycle.run-tco' => ['roles' => ['admin', 'fleet_admin', 'lease_manager']],
    'fleet.lifecycle.tco-optimization' => ['roles' => ['admin', 'fleet_admin', 'lease_manager']],
    'hcm.operations.attendance.record' => ['roles' => ['admin', 'hcm_manager']],
    'hcm.operations.audit.reconcile' => ['roles' => ['admin', 'hcm_manager']],
    'hcm.operations.department.create' => ['roles' => ['admin', 'hcm_manager']],
    'hcm.operations.index' => ['roles' => ['admin', 'hcm_manager']],
    'hcm.operations.job_grade.assign' => ['roles' => ['admin', 'hcm_manager']],
    'hcm.operations.labor.allocate' => [
        'roles' => ['admin', 'hcm_manager'],
        'params' => fn () => ['payroll' => 1],
    ],
    'hcm.operations.payroll.calculate' => [
        'roles' => ['admin', 'hcm_manager'],
        'params' => fn () => ['employee' => 1],
    ],
    'hotel.pms.banquet' => ['roles' => ['admin', 'front_desk', 'hotel_admin']],
    'hotel.pms.banquet.create' => ['roles' => ['admin', 'front_desk', 'hotel_admin']],
    'hotel.pms.destination-packages' => ['roles' => ['admin', 'front_desk', 'hotel_admin']],
    'hotel.pms.destination-packages.settle' => ['roles' => ['admin', 'front_desk', 'hotel_admin']],
    'hotel.pms.close-day' => ['roles' => ['admin', 'front_desk', 'hotel_admin']],
    'hotel.pms.close-day.run' => ['roles' => ['admin', 'front_desk', 'hotel_admin']],
    'hotel.pms.overbooking' => ['roles' => ['admin', 'front_desk', 'hotel_admin']],
    'hotel.pms.overbooking.walk' => ['roles' => ['admin', 'front_desk', 'hotel_admin']],
    'hotel.pms.reservations' => ['roles' => ['admin', 'front_desk', 'hotel_admin']],
    'hotel.pms.reservations.book' => ['roles' => ['admin', 'front_desk', 'hotel_admin']],
    'hotel.pms.split-folios' => ['roles' => ['admin', 'front_desk', 'hotel_admin']],
    'hotel.pms.split-folios.settle' => ['roles' => ['admin', 'front_desk', 'hotel_admin']],
    'hotel.pms.timeshare' => ['roles' => ['admin', 'front_desk', 'hotel_admin']],
    'hotel.pms.timeshare.mint' => ['roles' => ['admin', 'front_desk', 'hotel_admin']],
    'hotel.pms.yield-rates' => ['roles' => ['admin', 'front_desk', 'hotel_admin']],
    'hotel.pms.yield-rates.calculate' => ['roles' => ['admin', 'front_desk', 'hotel_admin']],
    'insurance.workflow.claims' => ['roles' => ['admin', 'customer', 'insurance_admin', 'underwriter']],
    'insurance.workflow.evaluate-fraud' => ['roles' => ['admin', 'customer', 'insurance_admin', 'underwriter']],
    'insurance.workflow.fraud-evaluations' => ['roles' => ['admin', 'customer', 'insurance_admin', 'underwriter']],
    'insurance.workflow.issue-policy' => ['roles' => ['admin', 'customer', 'insurance_admin', 'underwriter']],
    'insurance.workflow.parametric-triggers' => ['roles' => ['admin', 'customer', 'insurance_admin', 'underwriter']],
    'insurance.workflow.policies' => ['roles' => ['admin', 'customer', 'insurance_admin', 'underwriter']],
    'insurance.workflow.settle-claim' => ['roles' => ['admin', 'customer', 'insurance_admin', 'underwriter']],
    'insurance.workflow.trigger-parametric' => ['roles' => ['admin', 'customer', 'insurance_admin', 'underwriter']],
    'intercompany.consolidation.elimination' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'intercompany.consolidation.index' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'intercompany.consolidation.markup' => [
        'roles' => ['admin', 'auditor', 'legal', 'treasury'],
        'params' => fn () => ['rule' => 1],
    ],
    'intercompany.consolidation.mirror-tx' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'international.tax_royalty.index' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'international.tax_royalty.oem.tooling' => [
        'roles' => ['admin', 'auditor', 'legal', 'treasury'],
        'params' => fn () => ['contract' => 1],
    ],
    'international.tax_royalty.royalty.calculate' => [
        'roles' => ['admin', 'auditor', 'legal', 'treasury'],
        'params' => fn () => ['license' => 1],
    ],
    'international.tax_royalty.treaty.relief' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'inventory.explorer.index' => ['roles' => ['admin', 'auditor', 'procurement']],
    'inventory.explorer.show' => [
        'roles' => ['admin', 'auditor', 'procurement'],
        'params' => fn () => ['id' => 1],
    ],
    'med.ads.book' => ['roles' => ['admin', 'ip_manager', 'producer', 'studio_ops', 'talent_mgmt']],
    'med.ads.index' => ['roles' => ['admin', 'ip_manager', 'producer', 'studio_ops', 'talent_mgmt']],
    'med.licensing.grant' => ['roles' => ['admin', 'ip_manager', 'producer', 'studio_ops', 'talent_mgmt']],
    'med.licensing.index' => ['roles' => ['admin', 'ip_manager', 'producer', 'studio_ops', 'talent_mgmt']],
    'med.licensing.show' => [
        'roles' => ['admin', 'ip_manager', 'producer', 'studio_ops', 'talent_mgmt'],
        'params' => fn () => ['id' => 1],
    ],
    'med.royalties.distribute' => [
        'roles' => ['admin', 'ip_manager', 'producer', 'studio_ops', 'talent_mgmt'],
        'params' => fn () => ['id' => 1],
    ],
    'med.royalties.index' => ['roles' => ['admin', 'ip_manager', 'producer', 'studio_ops', 'talent_mgmt']],
    'med.royalties.talent.settle' => [
        'roles' => ['admin', 'ip_manager', 'producer', 'studio_ops', 'talent_mgmt'],
        'params' => fn () => ['contractId' => 1],
    ],
    'med.studios.greenlight' => [
        'roles' => ['admin', 'ip_manager', 'producer', 'studio_ops', 'talent_mgmt'],
        'params' => fn () => ['id' => 1],
    ],
    'med.studios.index' => ['roles' => ['admin', 'ip_manager', 'producer', 'studio_ops', 'talent_mgmt']],
    'med.studios.show' => [
        'roles' => ['admin', 'ip_manager', 'producer', 'studio_ops', 'talent_mgmt'],
        'params' => fn () => ['id' => 1],
    ],
    'media.index' => ['roles' => ['admin', 'ip_manager', 'producer', 'studio_ops', 'talent_mgmt']],
    'mining.operations.blast-safety' => ['roles' => ['admin', 'mining_admin', 'site_manager']],
    'mining.operations.blast-safety.lock' => ['roles' => ['admin', 'mining_admin', 'site_manager']],
    'mining.operations.block-planning' => ['roles' => ['admin', 'mining_admin', 'site_manager']],
    'mining.operations.block-planning.plan' => ['roles' => ['admin', 'mining_admin', 'site_manager']],
    'mining.operations.grade-reconciliation' => ['roles' => ['admin', 'mining_admin', 'site_manager']],
    'mining.operations.grade-reconciliation.reconcile' => ['roles' => ['admin', 'mining_admin', 'site_manager']],
    'mining.operations.haul-dispatch' => ['roles' => ['admin', 'mining_admin', 'site_manager']],
    'mining.operations.haul-dispatch.truck' => ['roles' => ['admin', 'mining_admin', 'site_manager']],
    'mining.operations.pnbp-royalty' => ['roles' => ['admin', 'mining_admin', 'site_manager']],
    'mining.operations.pnbp-royalty.calculate' => ['roles' => ['admin', 'mining_admin', 'site_manager']],
    'mining.operations.reclamation' => ['roles' => ['admin', 'mining_admin', 'site_manager']],
    'mining.operations.reclamation.bond' => ['roles' => ['admin', 'mining_admin', 'site_manager']],
    'mining.operations.smelter' => ['roles' => ['admin', 'mining_admin', 'site_manager']],
    'mining.operations.smelter.run' => ['roles' => ['admin', 'mining_admin', 'site_manager']],
    'mining.operations.weighbridge' => ['roles' => ['admin', 'mining_admin', 'site_manager']],
    'mining.operations.weighbridge.ticket' => ['roles' => ['admin', 'mining_admin', 'site_manager']],
    'partner.governance.audit_ip' => [
        'roles' => ['admin', 'auditor', 'procurement'],
        'params' => fn () => ['partner' => 1],
    ],
    'partner.governance.profit_share' => [
        'roles' => ['admin', 'auditor', 'procurement'],
        'params' => fn () => ['partner' => 1],
    ],
    'partner.governance.settle_milestone' => [
        'roles' => ['admin', 'auditor', 'procurement'],
        'params' => fn () => ['jointPlan' => 1],
    ],
    'payment.inspector.index' => ['roles' => ['admin', 'auditor', 'treasury']],
    'payment.inspector.show' => [
        'roles' => ['admin', 'auditor', 'treasury'],
        'params' => fn () => ['uuid' => 1],
    ],
    'plm.operations.eco.execute' => [
        'roles' => ['admin', 'rnd_specialist'],
        'params' => fn () => ['ebom' => 1],
    ],
    'plm.operations.index' => ['roles' => ['admin', 'rnd_specialist']],
    'plm.operations.lab.test' => [
        'roles' => ['admin', 'rnd_specialist'],
        'params' => fn () => ['project' => 1],
    ],
    'plm.operations.mbom.convert' => [
        'roles' => ['admin', 'rnd_specialist'],
        'params' => fn () => ['ebom' => 1],
    ],
    'plm.operations.stage.advance' => [
        'roles' => ['admin', 'rnd_specialist'],
        'params' => fn () => ['project' => 1],
    ],
    'proptech.intelligence.bms' => ['roles' => ['admin', 'facility_manager', 'proptech_admin']],
    'proptech.intelligence.bms.optimize' => ['roles' => ['admin', 'facility_manager', 'proptech_admin']],
    'proptech.intelligence.epc-handover' => ['roles' => ['admin', 'facility_manager', 'proptech_admin']],
    'proptech.intelligence.epc-handover.handover' => ['roles' => ['admin', 'facility_manager', 'proptech_admin']],
    'proptech.intelligence.evacuation' => ['roles' => ['admin', 'facility_manager', 'proptech_admin']],
    'proptech.intelligence.evacuation.run' => ['roles' => ['admin', 'facility_manager', 'proptech_admin']],
    'proptech.intelligence.flex-booking' => ['roles' => ['admin', 'facility_manager', 'proptech_admin']],
    'proptech.intelligence.flex-booking.store' => ['roles' => ['admin', 'facility_manager', 'proptech_admin']],
    'proptech.intelligence.retrofit' => ['roles' => ['admin', 'facility_manager', 'proptech_admin']],
    'proptech.intelligence.retrofit.calculate' => ['roles' => ['admin', 'facility_manager', 'proptech_admin']],
    'proptech.intelligence.smart-lock' => ['roles' => ['admin', 'facility_manager', 'proptech_admin']],
    'proptech.intelligence.smart-lock.generate' => ['roles' => ['admin', 'facility_manager', 'proptech_admin']],
    'ret.compliance.audit' => ['roles' => ['admin', 'category_mgr', 'last_mile_cs', 'marketplace_mgr', 'retail_ops']],
    'ret.compliance.index' => ['roles' => ['admin', 'category_mgr', 'last_mile_cs', 'marketplace_mgr', 'retail_ops']],
    'ret.darkstore.allocate' => ['roles' => ['admin', 'category_mgr', 'last_mile_cs', 'marketplace_mgr', 'retail_ops']],
    'ret.darkstore.index' => ['roles' => ['admin', 'category_mgr', 'last_mile_cs', 'marketplace_mgr', 'retail_ops']],
    'ret.darkstore.pickpack' => ['roles' => ['admin', 'category_mgr', 'last_mile_cs', 'marketplace_mgr', 'retail_ops']],
    'ret.orders.checkout' => ['roles' => ['admin', 'category_mgr', 'last_mile_cs', 'marketplace_mgr', 'retail_ops']],
    'ret.orders.index' => ['roles' => ['admin', 'category_mgr', 'last_mile_cs', 'marketplace_mgr', 'retail_ops']],
    'ret.orders.pickup' => ['roles' => ['admin', 'category_mgr', 'last_mile_cs', 'marketplace_mgr', 'retail_ops']],
    'ret.orders.show' => [
        'roles' => ['admin', 'category_mgr', 'last_mile_cs', 'marketplace_mgr', 'retail_ops'],
        'params' => fn () => ['id' => 1],
    ],
    'ret.pricing.index' => ['roles' => ['admin', 'category_mgr', 'last_mile_cs', 'marketplace_mgr', 'retail_ops']],
    'ret.pricing.markdown' => ['roles' => ['admin', 'category_mgr', 'last_mile_cs', 'marketplace_mgr', 'retail_ops']],
    'ret.pricing.shrinkage' => ['roles' => ['admin', 'category_mgr', 'last_mile_cs', 'marketplace_mgr', 'retail_ops']],
    'retail.index' => ['roles' => ['admin', 'category_mgr', 'last_mile_cs', 'marketplace_mgr', 'retail_ops']],
    'rwa.exchange.distribute-yield' => ['roles' => ['admin', 'asset_manager', 'investor', 'rwa_admin']],
    'rwa.exchange.holdings' => ['roles' => ['admin', 'asset_manager', 'investor', 'rwa_admin']],
    'rwa.exchange.match-trade' => ['roles' => ['admin', 'asset_manager', 'investor', 'rwa_admin']],
    'rwa.exchange.offerings' => ['roles' => ['admin', 'asset_manager', 'investor', 'rwa_admin']],
    'rwa.exchange.orderbook' => ['roles' => ['admin', 'asset_manager', 'investor', 'rwa_admin']],
    'rwa.exchange.subscribe-offering' => ['roles' => ['admin', 'asset_manager', 'investor', 'rwa_admin']],
    'rwa.exchange.yield-distribution' => ['roles' => ['admin', 'asset_manager', 'investor', 'rwa_admin']],
    'telecom.index' => ['roles' => ['admin', 'datacenter_ops', 'noc_engineer', 'telecom_billing', 'tlx_admin']],
    'telematics.operations.baselines' => ['roles' => ['admin', 'fleet_manager', 'telematics_admin']],
    'telematics.operations.dispatch-tow' => ['roles' => ['admin', 'fleet_manager', 'telematics_admin']],
    'telematics.operations.driver-scores' => ['roles' => ['admin', 'fleet_manager', 'telematics_admin']],
    'telematics.operations.dtc-alerts' => ['roles' => ['admin', 'fleet_manager', 'telematics_admin']],
    'telematics.operations.emergency-dispatches' => ['roles' => ['admin', 'fleet_manager', 'telematics_admin']],
    'telematics.operations.evaluate-dtc' => ['roles' => ['admin', 'fleet_manager', 'telematics_admin']],
    'tlx.billing.cdr.rate' => ['roles' => ['admin', 'datacenter_ops', 'noc_engineer', 'telecom_billing', 'tlx_admin']],
    'tlx.billing.index' => ['roles' => ['admin', 'datacenter_ops', 'noc_engineer', 'telecom_billing', 'tlx_admin']],
    'tlx.billing.roaming' => ['roles' => ['admin', 'datacenter_ops', 'noc_engineer', 'telecom_billing', 'tlx_admin']],
    'tlx.billing.roaming.settle' => ['roles' => ['admin', 'datacenter_ops', 'noc_engineer', 'telecom_billing', 'tlx_admin']],
    'tlx.datacenter.index' => ['roles' => ['admin', 'datacenter_ops', 'noc_engineer', 'telecom_billing', 'tlx_admin']],
    'tlx.datacenter.pue.enforce' => ['roles' => ['admin', 'datacenter_ops', 'noc_engineer', 'telecom_billing', 'tlx_admin']],
    'tlx.datacenter.racks' => ['roles' => ['admin', 'datacenter_ops', 'noc_engineer', 'telecom_billing', 'tlx_admin']],
    'tlx.datacenter.racks.allocate' => ['roles' => ['admin', 'datacenter_ops', 'noc_engineer', 'telecom_billing', 'tlx_admin']],
    'tlx.network.esim.activate' => ['roles' => ['admin', 'datacenter_ops', 'noc_engineer', 'telecom_billing', 'tlx_admin']],
    'tlx.network.index' => ['roles' => ['admin', 'datacenter_ops', 'noc_engineer', 'telecom_billing', 'tlx_admin']],
    'tlx.network.slices' => ['roles' => ['admin', 'datacenter_ops', 'noc_engineer', 'telecom_billing', 'tlx_admin']],
    'tlx.network.slices.provision' => ['roles' => ['admin', 'datacenter_ops', 'noc_engineer', 'telecom_billing', 'tlx_admin']],
    'trade.customs.audit_bonded_zone' => [
        'roles' => ['admin', 'auditor', 'procurement', 'treasury'],
        'params' => fn () => ['importOrder' => 1],
    ],
    'trade.customs.landed_cost' => [
        'roles' => ['admin', 'auditor', 'procurement', 'treasury'],
        'params' => fn () => ['importOrder' => 1],
    ],
    'trade.customs.submit_peb' => [
        'roles' => ['admin', 'auditor', 'procurement', 'treasury'],
        'params' => fn () => ['exportOrder' => 1],
    ],
    'trade.customs.submit_pib' => [
        'roles' => ['admin', 'auditor', 'procurement', 'treasury'],
        'params' => fn () => ['importOrder' => 1],
    ],
    'trade_finance.operations.index' => ['roles' => ['admin', 'auditor', 'procurement', 'treasury']],
    'trade_finance.operations.lc.issue' => ['roles' => ['admin', 'auditor', 'procurement', 'treasury']],
    'trade_finance.operations.lc.verify-presentation' => [
        'roles' => ['admin', 'auditor', 'procurement', 'treasury'],
        'params' => fn () => ['lc' => 1],
    ],
    'trade_finance.operations.scf.discount' => ['roles' => ['admin', 'auditor', 'procurement', 'treasury']],
    'treasury.fx_revalue' => ['roles' => ['admin', 'auditor', 'treasury']],
    'treasury.settle_hedge' => [
        'roles' => ['admin', 'auditor', 'treasury'],
        'params' => fn () => ['contract' => 1],
    ],
    'treasury.sweep_cash_pool' => [
        'roles' => ['admin', 'auditor', 'treasury'],
        'params' => fn () => ['cashPool' => 1],
    ],
    'treasury.facilities' => ['roles' => ['admin', 'auditor', 'treasury']],
    'treasury.forecast' => ['roles' => ['admin', 'auditor', 'treasury']],
    'treasury.fx' => ['roles' => ['admin', 'treasury']],
    'treasury.hedges' => ['roles' => ['admin', 'treasury']],
    'treasury.pools' => ['roles' => ['admin', 'treasury']],
    'treasury.positions' => ['roles' => ['admin', 'auditor', 'treasury']],
    'treasury.reconciliation' => ['roles' => ['admin', 'treasury']],
    'api.treasury.draw_facility' => [
        'roles' => ['admin', 'treasury'],
        'params' => fn () => ['facility' => 1],
    ],
    'api.treasury.fx_transfer' => ['roles' => ['admin', 'treasury']],
    'api.treasury.get_hedge' => [
        'roles' => ['admin', 'treasury'],
        'params' => fn () => ['contract' => 1],
    ],
    'api.intercompany.entities' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'api.intercompany.transactions.index' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'api.intercompany.transactions.store' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'api.intercompany.loans.index' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'api.intercompany.loans.store' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'api.intercompany.eliminations.index' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'api.intercompany.eliminations.run' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'api.intercompany.matrix' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'api.intercompany.tp.evaluate' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'api.intercompany.nci' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'api.enterprise_finance.budgets.spend' => [
        'roles' => ['admin', 'treasury'],
        'params' => fn () => ['budget' => EnterpriseBudget::firstOrCreate(['budget_code' => 'BGT-TEST-ROLES'], ['fiscal_year' => '2026', 'cost_center_code' => 'TEST', 'account_code' => 'TEST', 'allocated_amount_idr' => 1000000, 'control_type' => 'HARD_STOP'])->id],
    ],
    'api.enterprise_finance.capex.approve' => [
        'roles' => ['admin', 'treasury'],
        'params' => fn () => ['code' => EfCapexProposal::firstOrCreate(['proposal_code' => 'CPX-TEST-ROLES'], ['id' => (string) Str::uuid(), 'line_code' => 'LINE_TEST', 'project_title' => 'Test', 'requested_budget_minor' => 1000000, 'irr_pct' => 15.0, 'npv_minor' => 500000, 'esg_score' => 85.0, 'status' => 'SUBMITTED'])->proposal_code],
    ],
    'api.enterprise_finance.covenants.evaluate' => [
        'roles' => ['admin', 'treasury'],
        'params' => fn () => ['facility' => SyndicatedFacility::firstOrCreate(['facility_code' => 'FAC-TEST-ROLES'], ['facility_name' => 'Test', 'facility_type' => 'TERM_LOAN', 'lead_arranger' => 'Test', 'currency' => 'IDR', 'total_commitment_minor' => 1000000, 'start_date' => '2026-01-01', 'maturity_date' => '2029-01-01'])->id],
    ],
    'api.enterprise_finance.trial_balance.consolidate' => ['roles' => ['admin', 'treasury']],
    'api.enterprise_finance.sod.check' => ['roles' => ['admin', 'treasury']],
    'vending.operations.auto-replenishment' => ['roles' => ['admin', 'store_manager', 'vending_operator']],
    'vending.operations.auto-replenishment.trigger' => ['roles' => ['admin', 'store_manager', 'vending_operator']],
    'vending.operations.cart-dispense' => ['roles' => ['admin', 'store_manager', 'vending_operator']],
    'vending.operations.cart-dispense.dispense' => ['roles' => ['admin', 'store_manager', 'vending_operator']],
    'vending.operations.demand-forecast' => ['roles' => ['admin', 'store_manager', 'vending_operator']],
    'vending.operations.demand-forecast.run' => ['roles' => ['admin', 'store_manager', 'vending_operator']],
    'vending.operations.markdowns' => ['roles' => ['admin', 'store_manager', 'vending_operator']],
    'vending.operations.markdowns.calculate' => ['roles' => ['admin', 'store_manager', 'vending_operator']],
    'venue.artist.index' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.artist.royalties' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.artist.royalties.accrue' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.artist.royalties.release' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.artist.settle' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.bar-shifts.audit' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.economy.artists' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.economy.artists.settle' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.economy.bar' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.economy.bar.audit' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.economy.bundles' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.economy.bundles.allocate' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.economy.members' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.economy.members.evaluate' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.economy.pnl' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.economy.sponsors' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.economy.sponsors.milestones.valuate' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.events.close' => [
        'roles' => ['admin', 'venue_manager'],
        'params' => function () {
            $venue = EntertainmentVenue::firstOrCreate(['venue_code' => 'VEN-ATLAS-01'], ['name' => 'Atlas', 'venue_type' => 'BEACH_CLUB', 'city' => 'Badung, Bali', 'min_age_requirement' => 18, 'max_legal_capacity' => 1000]);
            $event = EntertainmentEvent::firstOrCreate(['event_code' => 'EVT-TEST-01'], ['venue_id' => $venue->id, 'title' => 'Test Event', 'doors_open_at' => now()->addDays(7), 'ticket_floor_price_idr' => 100000, 'ticket_ceiling_price_idr' => 1000000, 'current_ticket_price_idr' => 500000, 'status' => 'on_sale']);

            return ['event' => $event->id];
        },
    ],
    'venue.events.create' => ['roles' => ['admin', 'venue_manager']],
    'venue.events.index' => ['roles' => ['admin', 'venue_manager']],
    'venue.events.show' => [
        'roles' => ['admin', 'venue_manager'],
        'params' => function () {
            $venue = EntertainmentVenue::firstOrCreate(['venue_code' => 'VEN-ATLAS-01'], ['name' => 'Atlas', 'venue_type' => 'BEACH_CLUB', 'city' => 'Badung, Bali', 'min_age_requirement' => 18, 'max_legal_capacity' => 1000]);
            $event = EntertainmentEvent::firstOrCreate(['event_code' => 'EVT-TEST-01'], ['venue_id' => $venue->id, 'title' => 'Test Event', 'doors_open_at' => now()->addDays(7), 'ticket_floor_price_idr' => 100000, 'ticket_ceiling_price_idr' => 1000000, 'current_ticket_price_idr' => 500000]);

            return ['event' => $event->id];
        },
    ],
    'venue.events.simulator' => [
        'roles' => ['admin', 'venue_manager'],
        'params' => function () {
            $venue = EntertainmentVenue::firstOrCreate(['venue_code' => 'VEN-ATLAS-01'], ['name' => 'Atlas', 'venue_type' => 'BEACH_CLUB', 'city' => 'Badung, Bali', 'min_age_requirement' => 18, 'max_legal_capacity' => 1000]);
            $event = EntertainmentEvent::firstOrCreate(['event_code' => 'EVT-TEST-01'], ['venue_id' => $venue->id, 'title' => 'Test Event', 'doors_open_at' => now()->addDays(7), 'ticket_floor_price_idr' => 100000, 'ticket_ceiling_price_idr' => 1000000, 'current_ticket_price_idr' => 500000]);

            return ['event' => $event->id];
        },
    ],
    'venue.events.store' => ['roles' => ['admin', 'venue_manager']],
    'venue.gate.scan-action' => ['roles' => ['venue_staff']],
    'venue.inspections.index' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.permits.index' => ['roles' => ['admin', 'compliance_officer', 'venue_manager']],
    'venue.permits.store' => ['roles' => ['admin', 'compliance_officer', 'venue_manager']],
    'venue.relocations.index' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.reports.export' => ['roles' => ['admin', 'venue_manager']],
    'venue.reports.index' => ['roles' => ['admin', 'venue_manager']],
    'venue.reports.pnl' => ['roles' => ['admin', 'venue_manager']],
    'venue.tables.forfeit' => [
        'roles' => ['admin', 'venue_manager', 'venue_staff'],
        'params' => function () {
            $venue = EntertainmentVenue::firstOrCreate(['venue_code' => 'VEN-ATLAS-01'], ['name' => 'Atlas', 'venue_type' => 'BEACH_CLUB', 'city' => 'Badung, Bali', 'min_age_requirement' => 18, 'max_legal_capacity' => 1000]);
            $event = EntertainmentEvent::firstOrCreate(['event_code' => 'EVT-TEST-01'], ['venue_id' => $venue->id, 'title' => 'Test Event', 'doors_open_at' => now()->addDays(7), 'ticket_floor_price_idr' => 100000, 'ticket_ceiling_price_idr' => 1000000, 'current_ticket_price_idr' => 500000]);
            $bk = EntertainmentTableBooking::firstOrCreate(['booking_code' => 'BK-TEST-01'], ['table_number' => 'V01', 'event_id' => $event->id, 'user_id' => 1, 'customer_name' => 'Test', 'deposit_amount_idr' => 100000, 'minimum_spend_idr' => 1000000, 'status' => 'held']);

            return ['booking' => $bk->id];
        },
    ],
    'venue.tables.index' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.tables.liquor-audit' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.tables.liquor-audit.reconcile' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.tables.reserve' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.tables.settle' => [
        'roles' => ['admin', 'venue_manager', 'venue_staff'],
        'params' => function () {
            $venue = EntertainmentVenue::firstOrCreate(['venue_code' => 'VEN-ATLAS-01'], ['name' => 'Atlas', 'venue_type' => 'BEACH_CLUB', 'city' => 'Badung, Bali', 'min_age_requirement' => 18, 'max_legal_capacity' => 1000]);
            $event = EntertainmentEvent::firstOrCreate(['event_code' => 'EVT-TEST-01'], ['venue_id' => $venue->id, 'title' => 'Test Event', 'doors_open_at' => now()->addDays(7), 'ticket_floor_price_idr' => 100000, 'ticket_ceiling_price_idr' => 1000000, 'current_ticket_price_idr' => 500000]);
            $bk = EntertainmentTableBooking::firstOrCreate(['booking_code' => 'BK-TEST-01'], ['table_number' => 'V01', 'event_id' => $event->id, 'user_id' => 1, 'customer_name' => 'Test', 'deposit_amount_idr' => 100000, 'minimum_spend_idr' => 1000000, 'status' => 'held']);

            return ['booking' => $bk->id];
        },
    ],
    'venue.tables.surge' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.tables.surge.calculate' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.ticketing.gate' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.ticketing.gate.scan' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.ticketing.index' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.ticketing.issue' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.zones.crowd-safety' => ['roles' => ['admin', 'crowd_safety']],
    'venue.zones.index' => ['roles' => ['admin', 'crowd_safety']],
    'venue.zones.lockdown' => [
        'roles' => ['admin', 'crowd_safety'],
        'params' => function () {
            $venue = EntertainmentVenue::firstOrCreate(['venue_code' => 'VEN-ATLAS-01'], ['name' => 'Atlas', 'venue_type' => 'BEACH_CLUB', 'city' => 'Badung, Bali', 'min_age_requirement' => 18, 'max_legal_capacity' => 1000]);
            $zone = EntertainmentZone::firstOrCreate(['zone_code' => 'ZN-TEST-01'], ['venue_id' => $venue->id, 'name' => 'Test Zone', 'capacity_limit' => 500]);

            return ['zone' => $zone->id];
        },
    ],
    'venue.zones.weather-adjust' => [
        'roles' => ['admin', 'crowd_safety'],
        'params' => function () {
            $venue = EntertainmentVenue::firstOrCreate(['venue_code' => 'VEN-ATLAS-01'], ['name' => 'Atlas', 'venue_type' => 'BEACH_CLUB', 'city' => 'Badung, Bali', 'min_age_requirement' => 18, 'max_legal_capacity' => 1000]);
            $zone = EntertainmentZone::firstOrCreate(['zone_code' => 'ZN-TEST-01'], ['venue_id' => $venue->id, 'name' => 'Test Zone', 'capacity_limit' => 500]);

            return ['zone' => $zone->id];
        },
    ],
    'venue.secondary.marketplace' => ['roles' => ['admin', 'customer', 'venue_manager', 'venue_staff']],
    'venue.secondary.sell' => ['roles' => ['admin', 'customer', 'venue_manager', 'venue_staff']],
    'venue.secondary.sell.store' => ['roles' => ['admin', 'customer', 'venue_manager', 'venue_staff']],
    'venue.secondary.orders' => ['roles' => ['admin', 'customer', 'venue_manager', 'venue_staff']],
    'venue.secondary.bundles' => ['roles' => ['admin', 'customer', 'venue_manager', 'venue_staff']],
    'venue.secondary.bundles.store' => ['roles' => ['admin', 'customer', 'venue_manager', 'venue_staff']],
    'venue.secondary.waitlist' => ['roles' => ['admin', 'customer', 'venue_manager', 'venue_staff']],
    'venue.secondary.waitlist.claim' => ['roles' => ['admin', 'customer', 'venue_manager', 'venue_staff']],
    'venue.secondary.reports' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.secondary.admin.blacklist' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.secondary.admin.reconcile' => ['roles' => ['admin', 'venue_manager', 'venue_staff']],
    'venue.biometrics.enrol' => ['roles' => ['admin', 'customer', 'venue_manager', 'venue_staff']],
    'venue.biometrics.enrolment' => ['roles' => ['admin', 'customer', 'venue_manager', 'venue_staff']],
    'venue.biometrics.kiosk' => ['roles' => ['admin', 'customer', 'venue_manager', 'venue_staff']],
    'venue.biometrics.revoke' => ['roles' => ['admin', 'customer', 'venue_manager', 'venue_staff']],
    'venue.biometrics.scan' => ['roles' => ['admin', 'customer', 'venue_manager', 'venue_staff']],
    'venue.crowd.dashboard' => ['roles' => ['admin', 'crowd_safety', 'venue_manager']],
    'venue.crowd.emergency' => ['roles' => ['admin', 'crowd_safety', 'venue_manager']],
    'venue.crowd.heatmap-data' => ['roles' => ['admin', 'crowd_safety', 'venue_manager']],
    'venue.crowd.incidents.resolve' => [
        'roles' => ['admin', 'crowd_safety', 'venue_manager'],
        'params' => function () {
            $venue = EntertainmentVenue::firstOrCreate(['venue_code' => 'VEN-ATLAS-01'], ['name' => 'Atlas', 'venue_type' => 'BEACH_CLUB', 'city' => 'Badung, Bali', 'min_age_requirement' => 18, 'max_legal_capacity' => 1000]);
            $event = EntertainmentEvent::firstOrCreate(['event_code' => 'EVT-TEST-01'], ['venue_id' => $venue->id, 'title' => 'Test Event', 'doors_open_at' => now()->addDays(7), 'ticket_floor_price_idr' => 100000, 'ticket_ceiling_price_idr' => 1000000, 'current_ticket_price_idr' => 500000]);
            $zone = EntertainmentZone::firstOrCreate(['zone_code' => 'ZN-TEST-01'], ['venue_id' => $venue->id, 'name' => 'Test Zone', 'capacity_limit' => 500]);
            $incident = CrowdCrushIncident::firstOrCreate(
                ['incident_code' => 'INC-DUMMY-01'],
                [
                    'venue_id' => $venue->id,
                    'event_id' => $event->id,
                    'zone_id' => $zone->id,
                    'density_sqm' => 4.5,
                    'risk_level' => 'HIGH',
                    'action_taken' => 'HOLD',
                    'reported_by_user_id' => 1,
                    'status' => 'OPEN',
                ]
            );

            return ['incident' => $incident->id];
        },
    ],
    'venue.experience.promos' => ['roles' => ['admin', 'customer', 'venue_manager', 'venue_staff']],
    'venue.experience.promos.dispatch' => ['roles' => ['admin', 'customer', 'venue_manager', 'venue_staff']],
    'venue.experience.reinforce' => ['roles' => ['admin', 'customer', 'venue_manager', 'venue_staff']],
    'venue.experience.wps' => ['roles' => ['admin', 'customer', 'venue_manager', 'venue_staff']],
    'wealth.treasury.allocate-mpt' => ['roles' => ['admin', 'customer', 'investor', 'treasury', 'wealth_manager']],
    'wealth.treasury.cash-sweep' => ['roles' => ['admin', 'customer', 'investor', 'treasury', 'wealth_manager']],
    'wealth.treasury.compute-profile' => ['roles' => ['admin', 'customer', 'investor', 'treasury', 'wealth_manager']],
    'wealth.treasury.mpt-allocation' => ['roles' => ['admin', 'customer', 'investor', 'treasury', 'wealth_manager']],
    'wealth.treasury.portfolio-rebalancing' => ['roles' => ['admin', 'customer', 'investor', 'treasury', 'wealth_manager']],
    'wealth.treasury.rebalance' => ['roles' => ['admin', 'customer', 'investor', 'treasury', 'wealth_manager']],
    'wealth.treasury.risk-profiles' => ['roles' => ['admin', 'customer', 'investor', 'treasury', 'wealth_manager']],
    'wealth.treasury.sweep-cash' => ['roles' => ['admin', 'customer', 'investor', 'treasury', 'wealth_manager']],

    // hotel-siklus-tamu
    'hotel.reservations.index' => ['roles' => ['admin', 'front_desk', 'front_office', 'hotel_admin', 'hotel_manager', 'reservation_agent']],
    'hotel.reservations.store' => ['roles' => ['admin', 'front_desk', 'front_office', 'hotel_admin', 'hotel_manager', 'reservation_agent']],
    'hotel.housekeeping.index' => ['roles' => ['admin', 'front_office', 'hotel_manager', 'housekeeping']],
    'hotel.housekeeping.update' => [
        'roles' => ['admin', 'front_office', 'hotel_manager', 'housekeeping'],
        'params' => fn () => ['room' => HotelRoom::first()?->id ?? 1],
    ],
    'hotel.close-day.index' => ['roles' => ['admin', 'hotel_manager', 'night_auditor']],
    'hotel.close-day.run' => ['roles' => ['admin', 'hotel_manager', 'night_auditor']],
    'hotel.portal.guest' => ['roles' => ['admin', 'customer', 'guest']],
    'hotel.portal.corporate' => ['roles' => ['admin', 'corporate_client']],

    // energy-mvp
    'egy.grid.dispatch' => ['roles' => ['admin', 'energy_auditor', 'genco_trader', 'grid_operator', 'renewable_dev']],
    'egy.grid.invoices' => ['roles' => ['admin', 'energy_auditor', 'genco_trader', 'grid_operator', 'renewable_dev']],
    'api.energy.telemetry' => 'public',
    'api.energy.billing.calculate' => ['roles' => ['admin', 'energy_manager', 'genco_trader']],
    'api.energy.invoices.show' => [
        'roles' => ['admin', 'energy_manager', 'genco_trader'],
        'params' => fn () => [
            'id' => UtilityConsolidatedInvoice::firstOrCreate(
                ['invoice_number' => 'INV-UTIL-TEST-001'],
                ['id' => (string) Str::uuid(), 'property_id' => 'PROP-TEST-01', 'billing_period' => '2026-10', 'total_consolidated_minor' => 100000, 'status' => 'ISSUED']
            )->id,
        ],
    ],
    'api.energy.invoices.issue' => [
        'roles' => ['admin', 'energy_manager', 'genco_trader'],
        'params' => fn () => [
            'id' => UtilityConsolidatedInvoice::firstOrCreate(
                ['invoice_number' => 'INV-UTIL-TEST-002'],
                ['id' => (string) Str::uuid(), 'property_id' => 'PROP-TEST-02', 'billing_period' => '2026-10', 'total_consolidated_minor' => 100000, 'status' => 'DRAFT']
            )->id,
        ],
    ],
    'api.energy.ppa.settle' => [
        'roles' => ['admin', 'energy_manager', 'genco_trader'],
        'params' => fn () => [
            'id' => PpaContract::firstOrCreate(
                ['contract_number' => 'PPA-TEST-001'],
                ['id' => (string) Str::uuid(), 'producer_entity_id' => 'ENT-01', 'grid_offtaker_id' => 'ENT-EGY-01', 'feed_in_tariff_per_kwh_minor' => 1000, 'status' => 'ACTIVE']
            )->id,
        ],
    ],

    // telecom-mvp
    'tlx.noc.alarms' => ['roles' => ['admin', 'noc_engineer', 'tlx_admin']],
    'tlx.datacenter.colocation' => ['roles' => ['admin', 'datacenter_ops', 'tlx_admin']],
    'tlx.billing.connectivity' => ['roles' => ['admin', 'telecom_billing', 'tlx_admin']],
    'tlx.billing.connectivity.detail' => [
        'roles' => ['admin', 'telecom_billing', 'tlx_admin'],
        'params' => fn () => [
            'id' => IotConnectivityInvoice::firstOrCreate(
                ['invoice_number' => 'INV-TLX-ROUTE-001'],
                [
                    'id' => (string) Str::uuid(),
                    'owner_entity_id' => 'ENT-LOGISTICS-01',
                    'billing_period' => '2026-10',
                    'active_device_count' => 1,
                    'total_consumed_mb' => 0.0,
                    'total_charge_minor' => 30000,
                    'status' => 'ISSUED',
                ]
            )->id,
        ],
    ],

    // media-mvp
    'med.mvp.dashboard' => ['roles' => ['admin', 'auditor', 'media_admin', 'rights_manager', 'studio_manager']],
    'med.mvp.studios' => ['roles' => ['admin', 'media_admin', 'studio_manager']],
    'med.mvp.licensing' => ['roles' => ['admin', 'media_admin', 'rights_manager']],
    'med.mvp.royalties.detail' => [
        'roles' => ['admin', 'media_admin', 'rights_manager'],
        'params' => fn () => [
            'id' => MedRoyaltyStatement::firstOrCreate(
                ['statement_number' => 'STMT-ROUTE-001'],
                [
                    'id' => (string) Str::uuid(),
                    'license_id' => MedIpLicense::firstOrCreate(
                        ['license_code' => 'LIC-ROUTE-001'],
                        [
                            'id' => (string) Str::uuid(),
                            'ip_id' => (string) Str::uuid(),
                            'licensee_entity_id' => 'PARTY-ROUTE-01',
                            'rights_type' => 'STREAMING',
                            'channel' => 'STREAMING',
                            'territory' => 'GLOBAL',
                            'start_date' => '2026-01-01',
                            'end_date' => '2026-12-31',
                            'royalty_rate_pct' => 10.0,
                            'status' => 'ACTIVE',
                        ]
                    )->id,
                    'billing_period' => '2026-10',
                    'gross_revenue_minor' => 1000000,
                    'total_distribution_fee_minor' => 100000,
                    'total_recouped_minor' => 0,
                    'total_royalty_distributed_minor' => 900000,
                    'status' => 'ISSUED',
                ]
            )->id,
        ],
    ],
    'mining.admin' => ['roles' => ['admin', 'mine_manager', 'mining_admin', 'site_manager']],
    'mining.dispatch.ui' => ['roles' => ['admin', 'dispatcher_mine', 'mine_manager', 'mining_admin', 'site_manager']],
    'mining.dispatch.assign' => ['roles' => ['admin', 'dispatcher_mine', 'mine_manager', 'mining_admin', 'site_manager']],
    'mining.dispatch.override' => [
        'roles' => ['admin', 'dispatcher_mine', 'mine_manager', 'mining_admin', 'site_manager'],
        'params' => function () {
            $cycle = MiningHaulCycle::firstOrCreate(
                ['cycle_code' => 'HC-ARCH-01'],
                [
                    'shift' => 'SHIFT_1',
                    'status' => HaulCycleStatus::ASSIGNED,
                    'cycle_date' => now()->toDateString(),
                ]
            );

            return ['cycle' => $cycle->id];
        },
    ],
    'mining.plans' => ['roles' => ['admin', 'mine_planner']],
    'mining.plans.store' => ['roles' => ['admin', 'mine_planner']],
    'mining.plans.replan' => [
        'roles' => ['admin', 'mine_planner'],
        'params' => function () {
            $site = MiningSite::firstOrCreate(
                ['site_code' => 'SITE-ARCH-01'],
                ['name' => 'Arch Site', 'commodity' => 'NICKEL', 'location' => 'Morowali', 'timezone' => 'Asia/Makassar', 'status' => 'ACTIVE']
            );
            $pit = MiningPit::firstOrCreate(
                ['pit_code' => 'PIT-ARCH-01'],
                ['site_id' => $site->id, 'name' => 'Arch Pit']
            );
            $plan = MiningPlan::firstOrCreate(
                ['plan_code' => 'MP-ARCH-01'],
                [
                    'site_id' => $site->id,
                    'pit_id' => $pit->id,
                    'period_month' => '2026-11',
                    'commodity' => 'NICKEL',
                    'target_tonnage_kg' => 1000000,
                    'target_grade_ppm' => 15000,
                    'status' => MinePlanStatus::ACTIVE,
                ]
            );

            return ['plan' => $plan->id];
        },
    ],
    'mining.equipment' => ['roles' => ['admin', 'mine_manager']],
    'mining.fuel' => ['roles' => ['admin', 'finance_officer', 'hse_officer']],
    'mining.fuel.dispense' => ['roles' => ['admin', 'finance_officer', 'hse_officer']],
    'mining.fuel.resolve' => [
        'roles' => ['admin', 'finance_officer', 'hse_officer'],
        'params' => function () {
            $site = MiningSite::firstOrCreate(
                ['site_code' => 'SITE-ARCH-01'],
                ['name' => 'Arch Site', 'commodity' => 'NICKEL', 'location' => 'Morowali', 'timezone' => 'Asia/Makassar', 'status' => 'ACTIVE']
            );
            $equipment = MiningEquipment::firstOrCreate(
                ['equipment_code' => 'EQ-ARCH-01'],
                ['site_id' => $site->id, 'type' => 'HAUL_TRUCK', 'model' => 'CAT 777D', 'capacity_ton' => 90.0, 'capacity_kg' => 90000, 'status' => 'available']
            );
            $case = MiningFuelAnomalyCase::firstOrCreate(
                ['case_number' => 'FAC-ARCH-01'],
                [
                    'site_id' => $site->id,
                    'contractor_party_ref' => 'ARCH-CONTRACTOR',
                    'equipment_id' => $equipment->id,
                    'shift_date' => now()->toDateString(),
                    'shift_code' => 'SHIFT_1',
                    'actual_fuel_ml' => 500000,
                    'baseline_fuel_ml' => 350000,
                    'excess_ml' => 150000,
                    'excess_bps' => 4280,
                    'held_amount_idr' => 2000000,
                    'status' => FuelAnomalyCaseStatus::DETECTED,
                ]
            );

            return ['case' => $case->id];
        },
    ],
    'mining.reports.export' => ['roles' => ['admin', 'finance_officer', 'mine_manager']],
    'mining.contractor.portal' => ['roles' => ['admin', 'contractor']],
    'mining.contractor.dispute' => ['roles' => ['admin', 'contractor']],
    'mining.hse' => ['roles' => ['admin', 'hse_officer', 'mine_manager']],
    'mining.hse.permit' => ['roles' => ['admin', 'hse_officer', 'mine_manager']],
    'mining.hse.scan-expiry' => ['roles' => ['admin', 'hse_officer', 'mine_manager']],
    'mining.hse.audit-permits' => ['roles' => ['admin', 'hse_officer', 'mine_manager']],
    'mining.hse.blasting-eval' => ['roles' => ['admin', 'hse_officer', 'mine_manager']],
    'mining.royalties' => ['roles' => ['admin', 'finance_officer', 'mining_admin', 'site_manager']],
    'mining.weighbridge' => ['roles' => ['admin', 'mine_manager', 'weighbridge_operator']],
    'mining.grade' => ['roles' => ['admin', 'metallurgist', 'mine_manager']],
    'mining.grade.blend' => ['roles' => ['admin', 'metallurgist', 'mine_manager']],
    'mining.grade.depletion' => ['roles' => ['admin', 'metallurgist', 'mine_manager']],
    'mining.royalty' => ['roles' => ['admin', 'mine_manager', 'royalty_officer']],
    'mining.royalty.adjust-price' => ['roles' => ['admin', 'mine_manager', 'royalty_officer']],
    'mining.reclamation' => ['roles' => ['admin', 'mine_manager']],
    'mining.reclamation.schedule' => ['roles' => ['admin', 'mine_manager']],
    'mining.reclamation.unwind' => ['roles' => ['admin', 'mine_manager']],
    'mining.offtake' => ['roles' => ['admin', 'mine_manager', 'offtake_manager']],
    'mining.offtake.nominate' => ['roles' => ['admin', 'mine_manager', 'offtake_manager']],
    'mining.offtake.settle-provisional' => ['roles' => ['admin', 'mine_manager', 'offtake_manager']],
    'mining.offtake.settle-final' => ['roles' => ['admin', 'mine_manager', 'offtake_manager']],
    'mining.offtaker.portal' => ['roles' => ['admin', 'offtaker']],
    'mining.offtaker.nominate' => ['roles' => ['admin', 'offtaker']],
    'mining.fase119.barge' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase119.barge.chain' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase119.barge.draft' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase119.barge.freight' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase119.barge.reconcile' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase119.blast' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase119.blast.detonate' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase119.blast.drill-correction' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase119.blast.fragmentation' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase119.blast.radius' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase119.blast.runs.store' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase119.blast.siren' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase119.blast.verify' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase119.drone.reconcile' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase119.drone.tin-volume' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase119.export-csv' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase119.geotech' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase119.geotech.bishop-fos' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase119.geotech.evacuate' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase119.geotech.investigate' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase119.geotech.remediate' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase119.geotech.resolve' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase119.geotech.telemetry' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase119.stopes.store' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase119.underground' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase119.underground.fan-optimize' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase119.underground.gas' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase119.ventilation.simulate' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.audit-metals-desk' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.byproduct' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.byproduct.dispatch' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.byproduct.recycle' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.export-csv' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.smelter' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.smelter.abort' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.smelter.allocate-joint-cost' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.smelter.assay' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.smelter.audit-chain' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.smelter.balance' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.smelter.carbon-tax' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.smelter.emissions' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.smelter.hilirisasi-pipeline' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.smelter.reconcile' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.smelter.runs.store' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.smelter.settle-final-assay' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.smelter.slag-basicity' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.smelter.tapping' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.trading' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.trading.contracts.store' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.trading.lme-contango' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.trading.lock-price' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.trading.margin-call' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.trading.mtm' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.trading.plan-layered-hedge' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.trading.settle' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.warehouse-receipt' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.warehouse-receipt.issue' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.warehouse-receipt.pledge-scf' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.warehouse-receipt.register-bappebti' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.warehouse-receipt.release' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.warehouse-receipt.scan-expiry' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'mining.fase121.warehouse-receipt.validate-integrity' => ['roles' => ['admin', 'dispatcher_mine', 'hse_officer', 'mine_manager', 'site_manager']],
    'venue.gate.mobile' => ['roles' => ['venue_staff']],
    'oto.charging' => ['roles' => ['admin', 'driver', 'ev_admin', 'station_operator']],
    'oto.fleet' => ['roles' => ['admin', 'fleet_admin', 'fleet_manager', 'lease_manager']],
    'oto.telematics.clear-grounding' => ['roles' => ['admin', 'mechanic']],
    'oto.sessions.show' => [
        'roles' => ['admin', 'driver', 'ev_operator', 'fleet_manager'],
        'params' => function () {
            $user = User::firstOrCreate(['email' => 'driver-route@example.com'], ['name' => 'Route Driver', 'password' => 'secret']);
            $vehicle = Vehicle::firstOrCreate(['vin' => 'VIN-ROUTE-01'], ['user_id' => $user->id, 'plate_number' => 'B 1111 EV', 'color' => 'White', 'odometer_km' => 1000, 'status' => 'active']);
            $station = EvStation::firstOrCreate(['station_code' => 'SPKLU-PLN-01'], ['name' => 'SPKLU Sudirman', 'location_type' => 'PUBLIC', 'address' => 'Sudirman', 'latitude' => -6.2, 'longitude' => 106.8, 'is_active' => true]);
            $charger = EvCharger::firstOrCreate(['charger_code' => 'CHG-CCS2-01'], ['station_id' => $station->id, 'type' => 'DC_FAST', 'max_kw' => 150, 'status' => 'available']);
            $session = EvSession::firstOrCreate(
                ['session_code' => 'SES-DYN-001'],
                [
                    'charger_id' => $charger->id,
                    'vehicle_id' => $vehicle->id,
                    'user_id' => $user->id,
                    'status' => 'CHARGING',
                    'deposit_held_idr' => 50000,
                    'started_at' => now(),
                ]
            );

            return ['session' => $session->id];
        },
        'setup' => function (User $user): void {
            if ($user->role === 'driver') {
                EvSession::where('session_code', 'SES-DYN-001')->update(['user_id' => $user->id]);
            }
        },
    ],

    // circular-marketplace (Lini 24, Fase 174)
    'circular.listings.index' => ['roles' => ['admin', 'material_seller']],
    'circular.listings.store' => ['roles' => ['admin', 'material_seller']],
    'circular.market.index' => ['roles' => ['admin', 'material_buyer']],
    'circular.orders.store' => ['roles' => ['admin', 'material_buyer']],
    'circular.auctions.bids.store' => ['roles' => ['admin', 'material_buyer']],
    'circular.auctions.index' => ['roles' => ['admin', 'circular_admin']],
    'circular.auctions.store' => ['roles' => ['admin', 'circular_admin']],
    'circular.auctions.close' => [
        'roles' => ['admin', 'circular_admin'],
        'params' => fn () => [
            'round' => CirAuctionRound::firstOrCreate(
                ['round_code' => 'CIR-RND-ROUTE-001'],
                [
                    'material_code' => 'SCRAP_FE',
                    'grade_code' => 'A',
                    'status' => 'open',
                    'cleared_volume_grams' => 0,
                ]
            )->id,
        ],
    ],
    'circular.orders.show' => [
        'roles' => ['admin', 'circular_admin', 'material_buyer', 'material_seller'],
        'params' => function () {
            $listing = CirListing::firstOrCreate(
                ['listing_code' => 'CIR-LST-ROUTE-001'],
                [
                    'seller_party_id' => 1,
                    'material_code' => 'SCRAP_FE',
                    'grade_code' => 'A',
                    'available_grams' => 5_000_000,
                    'unit_price_per_kg_idr' => 4_000,
                    'mode' => 'fixed',
                    'status' => 'active',
                ]
            );
            $order = CirOrder::firstOrCreate(
                ['order_number' => 'CIR-ORD-ROUTE-001'],
                [
                    'listing_id' => $listing->id,
                    'buyer_party_id' => 1,
                    'seller_party_id' => 1,
                    'material_code' => 'SCRAP_FE',
                    'grade_code' => 'A',
                    'ordered_grams' => 1_000_000,
                    'unit_price_per_kg_idr' => 4_000,
                    'escrow_amount_idr' => 4_000_000,
                    'refund_amount_idr' => 0,
                    'status' => 'in_transit',
                    'idempotency_key' => 'cir-route-order-001',
                ]
            );

            return ['order' => $order->id];
        },
        'setup' => function (User $user): void {
            if ($user->role === 'material_buyer') {
                CirOrder::where('order_number', 'CIR-ORD-ROUTE-001')->update(['buyer_party_id' => $user->id]);
            } elseif ($user->role === 'material_seller') {
                CirOrder::where('order_number', 'CIR-ORD-ROUTE-001')->update(['seller_party_id' => $user->id]);
            }
        },
    ],
    'circular.orders.receipt' => [
        'roles' => ['admin', 'circular_admin'],
        'params' => function () {
            $listing = CirListing::firstOrCreate(
                ['listing_code' => 'CIR-LST-ROUTE-001'],
                [
                    'seller_party_id' => 1,
                    'material_code' => 'SCRAP_FE',
                    'grade_code' => 'A',
                    'available_grams' => 5_000_000,
                    'unit_price_per_kg_idr' => 4_000,
                    'mode' => 'fixed',
                    'status' => 'active',
                ]
            );
            $order = CirOrder::firstOrCreate(
                ['order_number' => 'CIR-ORD-ROUTE-001'],
                [
                    'listing_id' => $listing->id,
                    'buyer_party_id' => 1,
                    'seller_party_id' => 1,
                    'material_code' => 'SCRAP_FE',
                    'grade_code' => 'A',
                    'ordered_grams' => 1_000_000,
                    'unit_price_per_kg_idr' => 4_000,
                    'escrow_amount_idr' => 4_000_000,
                    'refund_amount_idr' => 0,
                    'status' => 'in_transit',
                    'idempotency_key' => 'cir-route-order-001',
                ]
            );

            return ['order' => $order->id];
        },
    ],
    'circular.orders.settle' => [
        'roles' => ['admin', 'circular_admin'],
        'params' => function () {
            $listing = CirListing::firstOrCreate(
                ['listing_code' => 'CIR-LST-ROUTE-001'],
                [
                    'seller_party_id' => 1,
                    'material_code' => 'SCRAP_FE',
                    'grade_code' => 'A',
                    'available_grams' => 5_000_000,
                    'unit_price_per_kg_idr' => 4_000,
                    'mode' => 'fixed',
                    'status' => 'active',
                ]
            );
            $order = CirOrder::firstOrCreate(
                ['order_number' => 'CIR-ORD-ROUTE-001'],
                [
                    'listing_id' => $listing->id,
                    'buyer_party_id' => 1,
                    'seller_party_id' => 1,
                    'material_code' => 'SCRAP_FE',
                    'grade_code' => 'A',
                    'ordered_grams' => 1_000_000,
                    'unit_price_per_kg_idr' => 4_000,
                    'escrow_amount_idr' => 4_000_000,
                    'refund_amount_idr' => 0,
                    'status' => 'in_transit',
                    'idempotency_key' => 'cir-route-order-001',
                ]
            );

            return ['order' => $order->id];
        },
    ],
    'circular.bids.show' => [
        'roles' => ['admin', 'circular_admin', 'material_buyer', 'material_seller'],
        'params' => function () {
            $round = CirAuctionRound::firstOrCreate(
                ['round_code' => 'CIR-RND-ROUTE-001'],
                [
                    'material_code' => 'SCRAP_FE',
                    'grade_code' => 'A',
                    'status' => 'open',
                    'cleared_volume_grams' => 0,
                ]
            );
            $bid = CirBid::firstOrCreate(
                ['idempotency_key' => 'cir-route-bid-001'],
                [
                    'auction_round_id' => $round->id,
                    'buyer_party_id' => 1,
                    'quantity_grams' => 1_000_000,
                    'max_price_per_kg_idr' => 4_000,
                    'allocated_grams' => 0,
                    'status' => 'active',
                ]
            );

            return ['bid' => $bid->id];
        },
        'setup' => function (User $user): void {
            if ($user->role === 'material_buyer' || $user->role === 'material_seller') {
                CirBid::where('idempotency_key', 'cir-route-bid-001')->update(['buyer_party_id' => $user->id]);
            }
        },
    ],
    'port.index' => [
        'roles' => ['admin', 'port_operator', 'shipping_line_agent'],
    ],
    'port.berths' => [
        'roles' => ['admin', 'port_operator', 'shipping_line_agent'],
    ],
    'port.yard' => [
        'roles' => ['admin', 'port_operator', 'shipping_line_agent'],
    ],
    'port.billing' => [
        'roles' => ['admin', 'port_operator', 'shipping_line_agent'],
    ],
    'port.fleet.index' => [
        'roles' => ['admin', 'port_operator', 'shipping_line_agent'],
    ],
    'port.voyages.index' => [
        'roles' => ['admin', 'port_operator', 'shipping_line_agent'],
    ],
    'port.charter.index' => [
        'roles' => ['admin', 'port_operator', 'shipping_line_agent'],
    ],
    'port.services.index' => [
        'roles' => ['admin', 'port_operator', 'shipping_line_agent'],
    ],
    'port.simulate.tce' => [
        'roles' => ['admin', 'port_operator', 'shipping_line_agent'],
    ],
    'port.simulate.malacca' => [
        'roles' => ['admin', 'port_operator', 'shipping_line_agent'],
    ],
    'fashion.collections.index' => [
        'roles' => ['admin', 'fashion_buyer', 'merchandiser'],
    ],
    'fashion.collections.store' => [
        'roles' => ['admin', 'fashion_buyer', 'merchandiser'],
    ],
    'fashion.orders.allocate' => [
        'roles' => ['admin', 'fashion_buyer', 'merchandiser'],
    ],
    'core.ai.anomalies.index' => [
        'roles' => ['admin', 'risk_officer'],
    ],
    'core.ai.anomalies.screen' => [
        'roles' => ['admin', 'risk_officer'],
    ],
    'core.ai.quarantine.index' => [
        'roles' => ['admin', 'risk_officer', 'risk_manager'],
    ],
    'core.ai.quarantine.release' => [
        'roles' => ['admin', 'risk_manager'],
        'params' => function () {
            $q = CoreAiAnomalyQuarantine::firstOrCreate(
                ['transaction_ref' => 'TX-ARCH-ROUTE-001'],
                [
                    'customer_ref' => 'CUST-ARCH-001',
                    'amount_idr' => 1000000,
                    'risk_score' => 0.95,
                    'anomaly_reasons' => ['EXTREME_DEVIATION'],
                    'status' => 'QUARANTINED',
                    'quarantined_at' => now(),
                    'created_by' => 'Other Officer',
                ]
            );

            return ['quarantine' => $q->id];
        },
    ],
    'core.ai.stock-recommendations.index' => [
        'roles' => ['admin', 'planner'],
    ],
    'core.ai.stock-recommendations.store' => [
        'roles' => ['admin', 'planner'],
    ],
    'core.ai.stock-recommendations.approve' => [
        'roles' => ['admin', 'planner_manager'],
        'params' => function () {
            $rec = CoreAiStockRecommendation::firstOrCreate(
                ['recommendation_code' => 'REC-ARCH-001'],
                [
                    'item_code' => 'SKU-ARCH-001',
                    'item_name' => 'Item Arch',
                    'current_stock' => 5,
                    'calculated_rop' => 20,
                    'safety_stock' => 10,
                    'suggested_order_qty' => 50,
                    'seasonal_factor' => 1.0,
                    'estimated_cost_idr' => 15000000,
                    'requires_human_approval' => true,
                    'status' => 'PROPOSED',
                ]
            );

            return ['recommendation' => $rec->id];
        },
    ],
    'core.api.global-search' => ['roles' => ['admin', 'design_system_owner', 'manager', 'operator']],
    'core.search.analytics' => ['roles' => ['admin']],
    'core.search.diagnostics' => ['roles' => ['admin']],
    'core.search.reindex' => ['roles' => ['admin']],
    'core.task-inbox.action' => [
        'roles' => ['admin', 'design_system_owner', 'manager', 'operator'],
        'params' => function () {
            $task = CoreTask::firstOrCreate(
                ['id' => 'TASK-ARCH-ROUTE-001'],
                [
                    'module' => 'Core',
                    'title' => 'Task Test Route',
                    'source_entity_type' => 'test',
                    'source_entity_id' => '1',
                    'status' => 'pending',
                ]
            );

            return ['id' => $task->id];
        },
        'setup' => function ($user) {
            DB::table('core_tasks')
                ->where('id', 'TASK-ARCH-ROUTE-001')
                ->update(['assignee_user_id' => $user->id]);
        },
    ],
    'core.task-inbox.analytics' => ['roles' => ['admin', 'design_system_owner', 'manager', 'operator']],
    'core.task-inbox.batch' => ['roles' => ['admin', 'design_system_owner', 'manager', 'operator']],
    'core.task-inbox.index' => ['roles' => ['admin', 'design_system_owner', 'manager', 'operator']],
    'core.task-inbox.show' => [
        'roles' => ['admin', 'design_system_owner', 'manager', 'operator'],
        'params' => function () {
            $task = CoreTask::firstOrCreate(
                ['id' => 'TASK-ARCH-ROUTE-001'],
                [
                    'module' => 'Core',
                    'title' => 'Task Test Route',
                    'source_entity_type' => 'test',
                    'source_entity_id' => '1',
                    'status' => 'pending',
                ]
            );

            return ['id' => $task->id];
        },
        'setup' => function ($user) {
            DB::table('core_tasks')
                ->where('id', 'TASK-ARCH-ROUTE-001')
                ->update(['assignee_user_id' => $user->id]);
        },
    ],
    'trade_finance.clearing.index' => ['roles' => ['admin', 'tf_officer', 'importer', 'exporter']],
    'trade_finance.clearing.show' => [
        'roles' => ['admin', 'tf_officer', 'importer', 'exporter'],
        'params' => function () {
            return ['escrow' => routeRolesClearingEscrow()->id];
        },
        // Peran importir/eksportir hanya boleh melihat escrow miliknya: pengguna uji dijadikan pihak escrow fixture.
        'setup' => function ($user) {
            $column = $user->role === 'importer' ? 'importer_user_id' : ($user->role === 'exporter' ? 'exporter_user_id' : null);
            if ($column !== null) {
                DB::table('tf_clearing_escrows')->where('escrow_code', 'CLR-ROUTE-TEST')->update([$column => $user->id]);
            }
        },
    ],
    'trade_finance.clearing.deposit' => ['roles' => ['admin', 'tf_officer', 'importer']],
    'trade_finance.clearing.release' => [
        'roles' => ['admin', 'tf_officer'],
        'params' => function () {
            return ['escrow' => routeRolesClearingEscrow()->id];
        },
    ],
    'trade.cbam.index' => ['roles' => ['admin', 'compliance_officer']],
    'trade.cbam.declare' => ['roles' => ['admin', 'compliance_officer']],
    'syariah.murabahah.index' => ['roles' => ['admin', 'syariah_officer']],
    'syariah.murabahah.propose' => ['roles' => ['admin', 'syariah_officer']],
    'syariah.murabahah.approve-officer' => [
        'roles' => ['admin', 'syariah_officer'],
        'params' => fn () => ['contract' => routeRolesMurabahahContract()->id],
    ],
    'syariah.murabahah.purchase-asset' => [
        'roles' => ['admin', 'syariah_officer'],
        'params' => fn () => ['contract' => routeRolesMurabahahContract()->id],
    ],
    'syariah.murabahah.sign' => [
        'roles' => ['admin', 'syariah_officer'],
        'params' => fn () => ['contract' => routeRolesMurabahahContract()->id],
    ],
    'syariah.murabahah.tazir' => [
        'roles' => ['admin', 'syariah_officer'],
        'params' => fn () => ['contract' => routeRolesMurabahahContract()->id],
    ],
    'syariah.murabahah.pay-installment' => [
        'roles' => ['admin', 'syariah_officer', 'nasabah'],
        'params' => fn () => ['contract' => routeRolesMurabahahContract()->id],
        'setup' => function (User $user): void {
            if ($user->role === 'nasabah') {
                DB::table('syb_murabahah_financings')->where('contract_code', 'MRB-ROUTE-TEST')->update(['customer_user_id' => $user->id]);
            }
        },
    ],
    'syariah.review.index' => ['roles' => ['admin', 'dps_reviewer']],
    'syariah.review.approve' => [
        'roles' => ['admin', 'dps_reviewer'],
        'params' => fn () => ['contract' => routeRolesMurabahahContract()->id],
    ],
    'syariah.portal.index' => ['roles' => ['admin', 'syariah_officer', 'dps_reviewer', 'nasabah']],
    'syariah.portal.show' => [
        'roles' => ['admin', 'syariah_officer', 'dps_reviewer', 'nasabah'],
        'params' => fn () => ['contract' => routeRolesMurabahahContract()->id],
        'setup' => function (User $user): void {
            if ($user->role === 'nasabah') {
                DB::table('syb_murabahah_financings')->where('contract_code', 'MRB-ROUTE-TEST')->update(['customer_user_id' => $user->id]);
            }
        },
    ],
    // Syariah Lanjutan Fase 162 — Sukuk
    'syariah.sukuk.index' => ['roles' => ['admin', 'syariah_officer']],
    'syariah.sukuk.export' => ['roles' => ['admin', 'syariah_officer']],
    'syariah.sukuk.propose' => ['roles' => ['admin', 'syariah_officer']],
    'syariah.sukuk.show' => ['roles' => ['admin', 'syariah_officer'], 'params' => fn () => ['id' => 1]],
    'syariah.sukuk.issue' => ['roles' => ['admin', 'syariah_officer'], 'params' => fn () => ['id' => 1]],
    'syariah.sukuk.distribute-coupon' => ['roles' => ['admin', 'syariah_officer'], 'params' => fn () => ['id' => 1]],
    'syariah.sukuk.redeem' => ['roles' => ['admin', 'syariah_officer'], 'params' => fn () => ['id' => 1]],
    'syariah.sukuk.dps-approve' => ['roles' => ['admin', 'dps_reviewer', 'shariah_board'], 'params' => fn () => ['id' => 1]],

    // Syariah Lanjutan Fase 162 — Ijarah
    'syariah.ijarah.index' => ['roles' => ['admin', 'syariah_officer']],
    'syariah.ijarah.export' => ['roles' => ['admin', 'syariah_officer']],
    'syariah.ijarah.propose' => ['roles' => ['admin', 'syariah_officer']],
    'syariah.ijarah.show' => ['roles' => ['admin', 'syariah_officer'], 'params' => fn () => ['id' => 1]],
    'syariah.ijarah.activate' => ['roles' => ['admin', 'syariah_officer'], 'params' => fn () => ['id' => 1]],
    'syariah.ijarah.exercise-imbt' => ['roles' => ['admin', 'syariah_officer'], 'params' => fn () => ['id' => 1]],
    'syariah.ijarah.dps-approve' => ['roles' => ['admin', 'dps_reviewer', 'shariah_board'], 'params' => fn () => ['id' => 1]],
    'syariah.ijarah.pay-period' => ['roles' => ['admin', 'customer', 'nasabah', 'syariah_officer'], 'params' => fn () => ['id' => 1]],

    // Syariah Lanjutan Fase 162 — Wealth
    'syariah.wealth.index' => ['roles' => ['admin', 'wealth_advisor']],
    'syariah.wealth.export' => ['roles' => ['admin', 'wealth_advisor']],
    'syariah.wealth.instruments.store' => ['roles' => ['admin', 'wealth_advisor']],
    'syariah.wealth.portfolios.store' => ['roles' => ['admin', 'customer', 'wealth_advisor']],
    'syariah.wealth.portfolios.show' => ['roles' => ['admin', 'customer', 'wealth_advisor'], 'params' => fn () => ['id' => 1]],
    'syariah.wealth.orders.store' => ['roles' => ['admin', 'customer', 'wealth_advisor']],
    'syariah.wealth.purify-dividend' => ['roles' => ['admin', 'customer', 'wealth_advisor']],

    // Syariah Lanjutan Fase 162 — Zakat
    'syariah.zakat.index' => ['roles' => ['admin', 'amil']],
    'syariah.zakat.export' => ['roles' => ['admin', 'amil']],
    'syariah.zakat.assess' => ['roles' => ['admin', 'amil']],
    'syariah.zakat.distribute' => ['roles' => ['admin', 'amil']],
    'syariah.zakat.calculate' => ['roles' => ['admin', 'amil', 'customer', 'nasabah']],
    'syariah.zakat.certificates.show' => ['roles' => ['admin', 'amil', 'customer', 'nasabah'], 'params' => fn () => ['id' => 1]],

    // Syariah Lanjutan Fase 162 — Waqf
    'syariah.waqf.index' => ['roles' => ['admin', 'nazhir']],
    'syariah.waqf.receive-money' => ['roles' => ['admin', 'nazhir']],
    'syariah.waqf.withdraw-principal' => ['roles' => ['admin', 'nazhir'], 'params' => fn () => ['id' => 1]],
    'syariah.waqf.distribute-benefit' => ['roles' => ['admin', 'nazhir'], 'params' => fn () => ['id' => 1]],
    'syariah.waqf.certificates.show' => ['roles' => ['admin', 'nazhir'], 'params' => fn () => ['id' => 1]],

    // Syariah Lanjutan Fase 163 — BMT, Microfinance & Dampak Sosial
    'syariah.bmt.index' => ['roles' => ['admin', 'bmt_manager', 'syariah_officer']],
    'syariah.bmt.cooperatives.store' => ['roles' => ['admin', 'bmt_manager', 'syariah_officer']],
    'syariah.bmt.show' => ['roles' => ['admin', 'bmt_manager', 'syariah_officer'], 'params' => fn () => ['id' => 1]],
    'syariah.bmt.savings.open' => ['roles' => ['admin', 'bmt_manager', 'syariah_officer']],
    'syariah.bmt.savings.show' => ['roles' => ['admin', 'bmt_manager', 'syariah_officer'], 'params' => fn () => ['id' => 1]],
    'syariah.bmt.savings.deposit' => ['roles' => ['admin', 'bmt_manager', 'syariah_officer'], 'params' => fn () => ['id' => 1]],
    'syariah.bmt.savings.withdraw' => ['roles' => ['admin', 'bmt_manager', 'syariah_officer'], 'params' => fn () => ['id' => 1]],

    'syariah.micro.index' => ['roles' => ['admin', 'field_officer', 'syariah_officer']],
    'syariah.micro.group.store' => ['roles' => ['admin', 'field_officer', 'syariah_officer']],
    'syariah.micro.group.show' => ['roles' => ['admin', 'field_officer', 'syariah_officer'], 'params' => fn () => ['id' => 1]],
    'syariah.micro.gig.store' => ['roles' => ['admin', 'field_officer', 'syariah_officer']],
    'syariah.micro.gig.show' => ['roles' => ['admin', 'field_officer', 'syariah_officer'], 'params' => fn () => ['id' => 1]],
    'syariah.micro.agri.store' => ['roles' => ['admin', 'field_officer', 'syariah_officer']],
    'syariah.micro.agri.show' => ['roles' => ['admin', 'field_officer', 'syariah_officer'], 'params' => fn () => ['id' => 1]],
    'syariah.micro.agri.disburse' => ['roles' => ['admin', 'field_officer', 'syariah_officer'], 'params' => fn () => ['id' => 1]],
    'syariah.micro.restructure.index' => ['roles' => ['admin', 'field_officer', 'syariah_officer']],
    'syariah.micro.restructure.store' => ['roles' => ['admin', 'field_officer', 'syariah_officer']],
    'syariah.micro.calculator' => ['roles' => ['admin', 'field_officer', 'syariah_officer']],

    'syariah.social.index' => ['roles' => ['admin', 'syariah_officer']],
    'syariah.social.calculate' => ['roles' => ['admin', 'syariah_officer']],
    'syariah.social.graduations.index' => ['roles' => ['admin', 'syariah_officer']],
    'syariah.social.graduations.store' => ['roles' => ['admin', 'syariah_officer']],
    'syariah.social.graduations.verify' => ['roles' => ['admin', 'syariah_officer'], 'params' => fn () => ['id' => 1]],

    // 10. Perdagangan Internasional & SCF Syariah (Fase 164)
    'syariah.trade.index' => ['roles' => ['admin', 'syariah_officer', 'trade_officer']],
    'syariah.trade.lc.index' => ['roles' => ['admin', 'syariah_officer', 'trade_officer']],
    'syariah.trade.lc.show' => ['roles' => ['admin', 'syariah_officer', 'trade_officer'], 'params' => fn () => ['id' => 1]],
    'syariah.trade.salam.index' => ['roles' => ['admin', 'syariah_officer', 'trade_officer']],
    'syariah.trade.salam.show' => ['roles' => ['admin', 'syariah_officer', 'trade_officer'], 'params' => fn () => ['id' => 1]],
    'syariah.trade.scf.index' => ['roles' => ['admin', 'syariah_officer', 'trade_officer']],
    'syariah.trade.scf.show' => ['roles' => ['admin', 'syariah_officer', 'trade_officer'], 'params' => fn () => ['id' => 1]],
    'syariah.trade.tawarruq.index' => ['roles' => ['admin', 'syariah_officer', 'trade_officer']],
    'syariah.trade.custody.index' => ['roles' => ['admin', 'syariah_officer', 'trade_officer']],
    'syariah.trade.calculator' => ['roles' => ['admin', 'syariah_officer', 'trade_officer']],
    'syariah.ops.index' => ['roles' => ['admin', 'dps_board', 'risk_officer', 'syariah_officer']],
    'syariah.ops.npf.index' => ['roles' => ['admin', 'dps_board', 'risk_officer', 'syariah_officer']],
    'syariah.ops.npf.show' => ['roles' => ['admin', 'dps_board', 'risk_officer', 'syariah_officer'], 'params' => fn () => ['id' => 1]],
    'syariah.ops.restructuring.index' => ['roles' => ['admin', 'dps_board', 'risk_officer', 'syariah_officer']],
    'syariah.ops.restructuring.show' => ['roles' => ['admin', 'dps_board', 'risk_officer', 'syariah_officer'], 'params' => fn () => ['id' => 1]],
    'syariah.ops.restructuring.store' => ['roles' => ['admin', 'dps_board', 'risk_officer', 'syariah_officer']],
    'syariah.ops.restructuring.dps-review' => ['roles' => ['admin', 'dps_board', 'risk_officer', 'syariah_officer'], 'params' => fn () => ['id' => 1]],
    'syariah.ops.restructuring.execute' => ['roles' => ['admin', 'dps_board', 'risk_officer', 'syariah_officer'], 'params' => fn () => ['id' => 1]],
    'syariah.ops.wallet.index' => ['roles' => ['admin', 'dps_board', 'risk_officer', 'syariah_officer']],
    'syariah.ops.merchants.index' => ['roles' => ['admin', 'dps_board', 'risk_officer', 'syariah_officer']],
    'syariah.ops.dps.index' => ['roles' => ['admin', 'dps_board', 'risk_officer', 'syariah_officer']],
    'syariah.ops.incidents.index' => ['roles' => ['admin', 'dps_board', 'risk_officer', 'syariah_officer']],
    'syariah.ops.regulatory.lbbs' => ['roles' => ['admin', 'dps_board', 'risk_officer', 'syariah_officer']],
    'syariah.ops.regulatory.recovery' => ['roles' => ['admin', 'dps_board', 'risk_officer', 'syariah_officer']],
    'syariah.ops.regulatory.auctions.settle' => ['roles' => ['admin', 'dps_board', 'risk_officer', 'syariah_officer'], 'params' => fn () => ['id' => 1]],
    'forest.plots.index' => ['roles' => ['admin', 'forester', 'superadmin']],
    'forest.plots.show' => ['roles' => ['admin', 'forester', 'superadmin'], 'params' => fn () => ['id' => 1]],
    'forest.permits.index' => ['roles' => ['admin', 'forester', 'superadmin']],
    'forest.logs.index' => ['roles' => ['admin', 'forester', 'timber_inspector', 'superadmin']],
    'forest.sales.index' => ['roles' => ['admin', 'forester', 'timber_buyer', 'superadmin']],
    'forest.restoration.index' => ['roles' => ['admin', 'forester', 'esg_officer', 'superadmin']],
    'proservices.projects.index' => ['roles' => ['admin', 'project_manager', 'superadmin']],
    'proservices.timesheets.index' => ['roles' => ['admin', 'consultant']],
    'proservices.timesheets.show' => ['roles' => ['admin', 'consultant'], 'params' => fn () => ['id' => 1]],
    'proservices.portal.invoices' => ['roles' => ['admin', 'client']],
    'proservices.portal.projects.show' => ['roles' => ['admin', 'client'], 'params' => fn () => ['id' => 1]],
    'insurance.full.products.index' => ['roles' => ['admin', 'superadmin', 'underwriter', 'actuary', 'claims_adjuster', 'reinsurance_mgr']],
    'insurance.full.policies.index' => ['roles' => ['admin', 'superadmin', 'underwriter', 'actuary', 'claims_adjuster', 'reinsurance_mgr', 'customer']],
    'insurance.full.policies.show' => ['roles' => ['admin', 'superadmin', 'underwriter', 'actuary', 'claims_adjuster', 'reinsurance_mgr', 'customer'], 'params' => fn () => ['id' => 1]],
    'insurance.full.claims.index' => ['roles' => ['admin', 'superadmin', 'underwriter', 'actuary', 'claims_adjuster', 'reinsurance_mgr', 'customer']],
    'insurance.full.claims.show' => ['roles' => ['admin', 'superadmin', 'underwriter', 'actuary', 'claims_adjuster', 'reinsurance_mgr', 'customer'], 'params' => fn () => ['id' => 1]],
    'insurance.full.actuarial.index' => ['roles' => ['admin', 'superadmin', 'underwriter', 'actuary', 'claims_adjuster', 'reinsurance_mgr']],
    'insurance.reinsurance.treaties.index' => ['roles' => ['admin', 'superadmin', 'reinsurance_mgr']],
    'insurance.reinsurance.treaties.show' => ['roles' => ['admin', 'superadmin', 'reinsurance_mgr'], 'params' => fn () => ['id' => 1]],
    'insurance.reinsurance.treaties.store' => ['roles' => ['admin', 'superadmin', 'reinsurance_mgr']],
    'insurance.reinsurance.treaties.transition' => ['roles' => ['admin', 'superadmin', 'reinsurance_mgr'], 'params' => fn () => ['id' => 1]],
    'insurance.reinsurance.bordereaux.index' => ['roles' => ['admin', 'superadmin', 'reinsurance_mgr']],
    'insurance.reinsurance.bordereaux.show' => ['roles' => ['admin', 'superadmin', 'reinsurance_mgr'], 'params' => fn () => ['id' => 1]],
    'insurance.reinsurance.bordereaux.generate' => ['roles' => ['admin', 'superadmin', 'reinsurance_mgr']],
    'insurance.reinsurance.bordereaux.send' => ['roles' => ['admin', 'superadmin', 'reinsurance_mgr'], 'params' => fn () => ['id' => 1]],
    'insurance.reinsurance.bordereaux.export-csv' => ['roles' => ['admin', 'superadmin', 'reinsurance_mgr'], 'params' => fn () => ['id' => 1]],
    'insurance.reinsurance.account-current.index' => ['roles' => ['admin', 'superadmin', 'reinsurance_mgr']],
    'insurance.reinsurance.account-current.show' => ['roles' => ['admin', 'superadmin', 'reinsurance_mgr'], 'params' => fn () => ['id' => 1]],
    'insurance.reinsurance.account-current.generate' => ['roles' => ['admin', 'superadmin', 'reinsurance_mgr']],
    'insurance.reinsurance.account-current.settle' => ['roles' => ['admin', 'superadmin', 'reinsurance_mgr'], 'params' => fn () => ['id' => 1]],
    'insurance.capital.index' => ['roles' => ['admin', 'superadmin', 'actuary', 'cfo_view']],
    'insurance.capital.snapshot' => ['roles' => ['admin', 'superadmin', 'actuary', 'cfo_view']],
    'insurance.capital.simulate' => ['roles' => ['admin', 'superadmin', 'actuary', 'cfo_view']],
    'insurance.cat.index' => ['roles' => ['admin', 'superadmin', 'actuary']],
    'insurance.cat.run' => ['roles' => ['admin', 'superadmin', 'actuary']],
    'insurance.cat.show' => ['roles' => ['admin', 'superadmin', 'actuary'], 'params' => fn () => ['id' => 1]],
    'insurance.cat-bonds.index' => ['roles' => ['admin', 'superadmin', 'reinsurance_mgr']],
    'insurance.cat-bonds.show' => ['roles' => ['admin', 'superadmin', 'reinsurance_mgr'], 'params' => fn () => ['id' => 1]],
    'insurance.cat-bonds.store' => ['roles' => ['admin', 'superadmin', 'reinsurance_mgr']],
    'insurance.cat-bonds.coupon' => ['roles' => ['admin', 'superadmin', 'reinsurance_mgr'], 'params' => fn () => ['id' => 1]],
    'insurance.cat-bonds.trigger' => ['roles' => ['admin', 'superadmin', 'reinsurance_mgr'], 'params' => fn () => ['id' => 1]],
    'investor.cat-bonds.index' => ['roles' => ['admin', 'superadmin', 'investor', 'reinsurance_mgr']],
    'investor.cat-bonds.show' => ['roles' => ['admin', 'superadmin', 'investor', 'reinsurance_mgr'], 'params' => fn () => ['id' => 1]],
    // Embedded Insurance & Broker Marketplace (Fase 158)
    'insurance.embedded.matrix' => ['roles' => ['admin', 'superadmin', 'underwriter', 'insurance_admin', 'broker']],
    'insurance.embedded.policies' => ['roles' => ['admin', 'superadmin', 'underwriter', 'insurance_admin', 'broker']],
    'insurance.embedded.issue' => ['roles' => ['admin', 'superadmin', 'underwriter', 'insurance_admin', 'broker']],
    'insurance.brokers.index' => ['roles' => ['admin', 'superadmin', 'insurance_admin', 'broker']],
    'insurance.brokers.register' => ['roles' => ['admin', 'superadmin', 'insurance_admin', 'broker']],
    'insurance.brokers.commissions' => ['roles' => ['admin', 'superadmin', 'insurance_admin', 'broker']],
    'insurance.brokers.settle' => ['roles' => ['admin', 'superadmin', 'insurance_admin', 'broker'], 'params' => fn () => ['id' => 1]],
    'insurance.hub.dashboard' => ['roles' => ['admin', 'superadmin', 'customer', 'underwriter', 'broker']],
    'insurance.siu.index' => ['roles' => ['admin', 'superadmin', 'claims_adjuster', 'underwriter']],
    'insurance.siu.show' => ['roles' => ['admin', 'superadmin', 'claims_adjuster', 'underwriter'], 'params' => fn () => ['id' => 1]],
    'insurance.siu.decide' => ['roles' => ['admin', 'superadmin', 'claims_adjuster', 'underwriter'], 'params' => fn () => ['id' => 1]],
    // Life, Health & Wellness Insurance (Fase 159)
    'insurance.life.index' => ['roles' => ['admin', 'superadmin', 'underwriter', 'insurance_admin']],
    'insurance.life.actuarial' => ['roles' => ['admin', 'superadmin', 'underwriter', 'insurance_admin']],
    'insurance.life.show' => ['roles' => ['admin', 'superadmin', 'underwriter', 'insurance_admin'], 'params' => fn () => ['id' => 1]],
    'insurance.life.store' => ['roles' => ['admin', 'superadmin', 'underwriter', 'insurance_admin']],
    'insurance.life.underwrite' => ['roles' => ['admin', 'superadmin', 'underwriter', 'insurance_admin'], 'params' => fn () => ['id' => 1]],
    'insurance.life.claim' => ['roles' => ['admin', 'superadmin', 'claims_adjuster', 'underwriter']],
    'insurance.life.surrender' => ['roles' => ['admin', 'superadmin', 'underwriter', 'customer'], 'params' => fn () => ['id' => 1]],
    'insurance.health.index' => ['roles' => ['admin', 'superadmin', 'underwriter', 'insurance_admin']],
    'insurance.health.analytics' => ['roles' => ['admin', 'superadmin', 'underwriter', 'insurance_admin']],
    'insurance.health.gl.index' => ['roles' => ['admin', 'superadmin', 'claims_adjuster', 'underwriter']],
    'insurance.health.gl.request' => ['roles' => ['admin', 'superadmin', 'claims_adjuster', 'underwriter']],
    'insurance.health.claims.index' => ['roles' => ['admin', 'superadmin', 'claims_adjuster', 'underwriter']],
    'insurance.health.claims.adjudicate' => ['roles' => ['admin', 'superadmin', 'claims_adjuster', 'underwriter']],
    'insurance.health.claims.appeal' => ['roles' => ['admin', 'superadmin', 'claims_adjuster', 'underwriter'], 'params' => fn () => ['id' => 1]],
    'insurance.wellness.dashboard' => ['roles' => ['admin', 'superadmin', 'customer', 'underwriter']],
    'insurance.wellness.record' => ['roles' => ['admin', 'superadmin', 'customer', 'underwriter']],
    'insurance.unitlink.portfolio' => ['roles' => ['admin', 'superadmin', 'underwriter', 'investor']],
    'insurance.unitlink.analytics' => ['roles' => ['admin', 'superadmin', 'underwriter', 'investor']],
    'insurance.unitlink.valuate' => ['roles' => ['admin', 'superadmin', 'underwriter', 'actuary']],
    'insurance.unitlink.switch' => ['roles' => ['admin', 'superadmin', 'underwriter', 'customer']],
    // Takaful, Agri, Micro & Command Center (Fase 160)
    'insurance.takaful.index' => ['roles' => ['admin', 'superadmin', 'underwriter', 'insurance_admin']],
    'insurance.takaful.actuarial-board' => ['roles' => ['admin', 'superadmin', 'underwriter', 'insurance_admin']],
    'insurance.takaful.surplus' => ['roles' => ['admin', 'superadmin', 'underwriter', 'insurance_admin']],
    'insurance.takaful.store' => ['roles' => ['admin', 'superadmin', 'underwriter', 'insurance_admin']],
    'insurance.takaful.distribute' => ['roles' => ['admin', 'superadmin', 'underwriter', 'insurance_admin']],
    'insurance.agri.index' => ['roles' => ['admin', 'superadmin', 'underwriter', 'insurance_admin']],
    'insurance.agri.weather-stations' => ['roles' => ['admin', 'superadmin', 'underwriter', 'insurance_admin']],
    'insurance.agri.claims' => ['roles' => ['admin', 'superadmin', 'underwriter', 'insurance_admin']],
    'insurance.agri.show' => ['roles' => ['admin', 'superadmin', 'underwriter', 'insurance_admin'], 'params' => fn () => ['id' => 1]],
    'insurance.agri.store' => ['roles' => ['admin', 'superadmin', 'underwriter', 'insurance_admin']],
    'insurance.agri.settle' => ['roles' => ['admin', 'superadmin', 'claims_adjuster', 'underwriter']],
    'insurance.micro.index' => ['roles' => ['admin', 'superadmin', 'customer', 'underwriter']],
    'insurance.micro.analytics' => ['roles' => ['admin', 'superadmin', 'customer', 'underwriter']],
    'insurance.micro.store' => ['roles' => ['admin', 'superadmin', 'customer', 'underwriter']],
    'insurance.command.dashboard' => ['roles' => ['admin', 'superadmin', 'underwriter', 'insurance_admin']],
    'insurance.command.aml' => ['roles' => ['admin', 'superadmin', 'underwriter', 'insurance_admin']],
    'insurance.command.calendar' => ['roles' => ['admin', 'superadmin', 'underwriter', 'insurance_admin']],
    'insurance.command.stress-test' => ['roles' => ['admin', 'superadmin', 'underwriter', 'insurance_admin']],
    'insurance.command.snapshot' => ['roles' => ['admin', 'superadmin', 'underwriter', 'insurance_admin']],
    'insurance.command.regulatory' => ['roles' => ['admin', 'superadmin', 'underwriter', 'insurance_admin']],
    'insurance.command.grievance' => ['roles' => ['admin', 'superadmin', 'underwriter', 'insurance_admin']],
    'insurance.command.resolve-grievance' => ['roles' => ['admin', 'superadmin', 'underwriter', 'insurance_admin']],
    'edu.campus.institutions.index' => ['roles' => ['admin', 'superadmin', 'academic_admin', 'lecturer']],
    'edu.campus.courses.index' => ['roles' => ['admin', 'superadmin', 'academic_admin', 'lecturer']],
    'edu.campus.classes.index' => ['roles' => ['admin', 'superadmin', 'academic_admin', 'lecturer']],
    'edu.campus.krs.index' => ['roles' => ['admin', 'superadmin', 'academic_admin', 'lecturer', 'student']],
    'edu.campus.transcripts.index' => ['roles' => ['admin', 'superadmin', 'academic_admin', 'lecturer', 'student']],
    'edu.campus.tuition.index' => ['roles' => ['admin', 'superadmin', 'academic_admin', 'lecturer', 'student']],
    // hotel-fase-91
    'hotel.front_desk' => ['roles' => ['admin', 'front_desk', 'front_office', 'hotel_admin']],
    'hotel.front_desk.check_in' => ['roles' => ['admin', 'front_desk', 'front_office', 'hotel_admin']],
    'hotel.front_desk.walk' => ['roles' => ['admin', 'front_desk', 'front_office', 'hotel_admin']],
    'hotel.inventory' => ['roles' => ['admin', 'hotel_admin', 'revenue_mgr']],
    'hotel.rates' => ['roles' => ['admin', 'hotel_admin', 'revenue_mgr']],
    'hotel.rates.publish' => ['roles' => ['admin', 'hotel_admin', 'revenue_mgr']],
    'hotel.rates.simulator' => ['roles' => ['admin', 'hotel_admin', 'revenue_mgr']],
    'hotel.housekeeping.mobile' => ['roles' => ['admin', 'front_office', 'hotel_admin', 'housekeeping']],
    'hotel.housekeeping.tasks.status' => [
        'roles' => ['admin', 'front_office', 'hotel_admin', 'housekeeping'],
        'params' => fn () => ['taskId' => 1],
    ],
    'hotel.engineering' => ['roles' => ['admin', 'engineering', 'hotel_admin']],
    'hotel.engineering.work_orders' => ['roles' => ['admin', 'engineering', 'hotel_admin']],
    'hotel.engineering.compensate' => ['roles' => ['admin', 'engineering', 'hotel_admin']],
    'hotel.compliance.apoa' => ['roles' => ['admin', 'front_office', 'hotel_admin', 'hotel_gm']],
    'hotel.compliance.apoa.submit' => ['roles' => ['admin', 'front_office', 'hotel_admin', 'hotel_gm']],
    'hotel.energy' => ['roles' => ['admin', 'hotel_admin', 'hotel_gm']],
    'hotel.energy.scan' => ['roles' => ['admin', 'hotel_admin', 'hotel_gm']],
    'guest.stay' => ['roles' => ['admin', 'customer', 'guest']],
    'hotel.folio.split.show' => ['roles' => ['admin', 'front_desk', 'front_office', 'hotel_admin']],
    'hotel.folio.split.settle' => ['roles' => ['admin', 'front_desk', 'front_office', 'hotel_admin']],
    'hotel.city_ledger.index' => ['roles' => ['admin', 'front_desk', 'front_office', 'hotel_admin']],
    'hotel.city_ledger.aging' => ['roles' => ['admin', 'front_desk', 'front_office', 'hotel_admin']],
    'hotel.spa.appointments.index' => ['roles' => ['admin', 'front_desk', 'front_office', 'hotel_admin']],
    'hotel.spa.appointments.book' => ['roles' => ['admin', 'front_desk', 'front_office', 'hotel_admin']],
    'hotel.banquet.index' => ['roles' => ['admin', 'hotel_admin', 'hotel_gm', 'sales_mgr']],
    'hotel.banquet.approve' => ['roles' => ['admin', 'hotel_admin', 'hotel_gm', 'sales_mgr']],
    'hotel.banquet.production' => ['roles' => ['admin', 'hotel_admin', 'hotel_gm', 'sales_mgr']],
    'hotel.banquet.explode_bom' => ['roles' => ['admin', 'hotel_admin', 'hotel_gm', 'sales_mgr']],
    'hotel.timeshare.index' => ['roles' => ['admin', 'hotel_admin', 'hotel_gm', 'sales_mgr']],
    'hotel.timeshare.schedule' => ['roles' => ['admin', 'hotel_admin', 'hotel_gm', 'sales_mgr']],
    'hotel.timeshare.distribute' => ['roles' => ['admin', 'hotel_admin', 'hotel_gm', 'sales_mgr']],
    'hotel.timeshare.secondary' => ['roles' => ['admin', 'hotel_admin', 'hotel_gm', 'sales_mgr']],
    'hotel.timeshare.swap' => ['roles' => ['admin', 'hotel_admin', 'hotel_gm', 'sales_mgr']],
    'hotel.packages.index' => ['roles' => ['admin', 'hotel_admin', 'hotel_gm', 'sales_mgr']],
    'hotel.packages.book' => ['roles' => ['admin', 'hotel_admin', 'hotel_gm', 'sales_mgr']],
    'hotel.packages.release_milestone' => ['roles' => ['admin', 'hotel_admin', 'hotel_gm', 'sales_mgr']],
    'hotel.packages.escrow_audit' => ['roles' => ['admin', 'hotel_admin', 'hotel_gm', 'sales_mgr']],
    'hotel.packages.audit_booking' => ['roles' => ['admin', 'hotel_admin', 'hotel_gm', 'sales_mgr']],
    'hotel.loyalty.profile' => ['roles' => ['admin', 'customer', 'guest', 'hotel_admin']],
    'hotel.loyalty.accrue' => ['roles' => ['admin', 'customer', 'guest', 'hotel_admin']],
    'hotel.loyalty.score_churn' => ['roles' => ['admin', 'customer', 'guest', 'hotel_admin']],
    'hotel.travel_pass.dashboard' => ['roles' => ['admin', 'front_desk', 'guest', 'hotel_admin']],
    'hotel.travel_pass.award_booking' => ['roles' => ['admin', 'front_desk', 'guest', 'hotel_admin']],
    'hotel.travel_pass.alliance_exchange' => ['roles' => ['admin', 'front_desk', 'guest', 'hotel_admin']],
    'hotel.travel_pass.voucher_redeem' => ['roles' => ['admin', 'front_desk', 'guest', 'hotel_admin']],
    'hotel.travel_pass.breakage_audit' => ['roles' => ['admin', 'finance', 'hotel_admin']],
    'hotel.co_brand.index' => ['roles' => ['admin', 'finance', 'hotel_admin']],
    'hotel.co_brand.cashback' => ['roles' => ['admin', 'finance', 'hotel_admin']],
    'hotel.esg.index' => ['roles' => ['admin', 'finance', 'hotel_admin']],
    'hotel.esg.donate' => ['roles' => ['admin', 'finance', 'hotel_admin']],
    'hotel.travel_pass.personalization' => ['roles' => ['admin', 'hotel_admin', 'sales_mgr']],
    'hotel.travel_pass.corporate_allocate' => ['roles' => ['admin', 'hotel_admin', 'sales_mgr']],

    // Fase 114: MICE & Wedding Sales Engine
    'hotel.mice.pipeline' => ['roles' => ['admin', 'hotel_admin', 'sales_mgr']],
    'hotel.mice.proposal.create' => ['roles' => ['admin', 'hotel_admin', 'sales_mgr']],
    'hotel.mice.contract.convert' => ['roles' => ['admin', 'hotel_admin', 'sales_mgr']],
    'hotel.mice.calculator' => ['roles' => ['admin', 'hotel_admin', 'sales_mgr']],
    'hotel.mice.calculator.compute' => ['roles' => ['admin', 'hotel_admin', 'sales_mgr']],
    'hotel.mice.room-blocks' => ['roles' => ['admin', 'front_desk', 'hotel_admin']],
    'hotel.mice.release-blocks.trigger' => ['roles' => ['admin', 'front_desk', 'hotel_admin']],
    'hotel.mice.room-blocks.pickup' => ['roles' => ['admin', 'front_desk', 'hotel_admin']],
    'hotel.mice.exhibitions' => ['roles' => ['admin', 'front_desk', 'hotel_admin']],
    'hotel.mice.exhibitions.reserve' => ['roles' => ['admin', 'front_desk', 'hotel_admin']],
    'hotel.mice.banquets.beo' => ['roles' => ['admin', 'chef_banquet', 'hotel_admin', 'sales_mgr']],
    'hotel.mice.banquets.beo.generate' => ['roles' => ['admin', 'chef_banquet', 'hotel_admin', 'sales_mgr']],
    'hotel.mice.weddings' => ['roles' => ['admin', 'finance', 'guest', 'hotel_admin', 'sales_mgr']],
    'hotel.mice.weddings.registry' => ['roles' => ['admin', 'finance', 'guest', 'hotel_admin', 'sales_mgr']],
    'hotel.mice.weddings.pay-milestone' => ['roles' => ['admin', 'finance', 'guest', 'hotel_admin', 'sales_mgr']],
    'hotel.mice.reconciliation' => ['roles' => ['admin', 'finance', 'hotel_admin']],
    'hotel.mice.reconciliation.audit' => ['roles' => ['admin', 'finance', 'hotel_admin']],

    // Fase 177: Aviation Core
    'aviation.ops.index' => ['roles' => ['admin', 'ops_controller', 'superadmin']],
    'aviation.slots.index' => ['roles' => ['admin', 'station_manager', 'superadmin']],
    'aviation.slots.request' => ['roles' => ['admin', 'station_manager', 'superadmin']],
    'aviation.fleet.index' => ['roles' => ['admin', 'maintenance_planner', 'superadmin']],
    'aviation.dispatch.index' => ['roles' => ['admin', 'dispatcher', 'ops_controller', 'superadmin']],
    'aviation.dispatch.execute' => [
        'roles' => ['admin', 'dispatcher', 'ops_controller', 'superadmin'],
        'params' => function () {
            $operator = AviationOperator::firstOrCreate(['code' => 'GA'], ['name' => 'Garuda', 'country' => 'ID', 'status' => 'active']);
            $origin = AviationAirport::firstOrCreate(['iata_code' => 'CGK'], ['icao_code' => 'WIII', 'name' => 'Soekarno Hatta', 'city' => 'Jakarta', 'province' => 'DKI Jakarta', 'island' => 'Jawa', 'timezone' => 'Asia/Jakarta']);
            $destination = AviationAirport::firstOrCreate(['iata_code' => 'DPS'], ['icao_code' => 'WADD', 'name' => 'Ngurah Rai', 'city' => 'Denpasar', 'province' => 'Bali', 'island' => 'Bali', 'timezone' => 'Asia/Makassar']);
            $route = AviationRoute::firstOrCreate(
                ['flight_number' => 'GA-404'],
                [
                    'origin_airport_id' => $origin->id,
                    'destination_airport_id' => $destination->id,
                    'distance_km' => 980,
                    'scheduled_duration_minutes' => 110,
                    'base_fare_minor' => 120000000,
                    'currency' => 'IDR',
                    'status' => 'active',
                ]
            );
            $flight = AviationFlight::firstOrCreate(['flight_number' => 'GA-404'], [
                'operator_id' => $operator->id,
                'route_id' => $route->id,
                'origin_airport_id' => $origin->id,
                'destination_airport_id' => $destination->id,
                'departure_time' => now()->addHours(2),
                'arrival_time' => now()->addHours(4),
                'status' => 'planned',
            ]);

            return ['flight' => $flight->id];
        },
    ],
    'aviation.dispatch.depart' => [
        'roles' => ['admin', 'dispatcher', 'ops_controller', 'superadmin'],
        'params' => function () {
            $operator = AviationOperator::firstOrCreate(['code' => 'GA'], ['name' => 'Garuda', 'country' => 'ID', 'status' => 'active']);
            $origin = AviationAirport::firstOrCreate(['iata_code' => 'CGK'], ['icao_code' => 'WIII', 'name' => 'Soekarno Hatta', 'city' => 'Jakarta', 'province' => 'DKI Jakarta', 'island' => 'Jawa', 'timezone' => 'Asia/Jakarta']);
            $destination = AviationAirport::firstOrCreate(['iata_code' => 'DPS'], ['icao_code' => 'WADD', 'name' => 'Ngurah Rai', 'city' => 'Denpasar', 'province' => 'Bali', 'island' => 'Bali', 'timezone' => 'Asia/Makassar']);
            $route = AviationRoute::firstOrCreate(
                ['flight_number' => 'GA-404'],
                [
                    'origin_airport_id' => $origin->id,
                    'destination_airport_id' => $destination->id,
                    'distance_km' => 980,
                    'scheduled_duration_minutes' => 110,
                    'base_fare_minor' => 120000000,
                    'currency' => 'IDR',
                    'status' => 'active',
                ]
            );
            $flight = AviationFlight::firstOrCreate(['flight_number' => 'GA-404'], [
                'operator_id' => $operator->id,
                'route_id' => $route->id,
                'origin_airport_id' => $origin->id,
                'destination_airport_id' => $destination->id,
                'departure_time' => now()->addHours(2),
                'arrival_time' => now()->addHours(4),
                'status' => 'planned',
            ]);

            return ['flight' => $flight->id];
        },
    ],
    'aviation.cargo.index' => ['roles' => ['admin', 'cargo_agent', 'superadmin']],
    'aviation.cargo.receive' => ['roles' => ['admin', 'cargo_agent', 'superadmin']],
    'aviation.bookings.index' => ['roles' => ['admin', 'customer_care', 'superadmin']],
    'aviation.bookings.hold' => ['roles' => ['admin', 'customer_care', 'passenger', 'superadmin']],
    'aviation.bookings.confirm' => ['roles' => ['admin', 'customer_care', 'superadmin']],
    'aviation.bookings.refund' => [
        'roles' => ['admin', 'customer_care', 'superadmin'],
        'params' => function () {
            $operator = AviationOperator::firstOrCreate(['code' => 'GA'], ['name' => 'Garuda', 'country' => 'ID', 'status' => 'active']);
            $origin = AviationAirport::firstOrCreate(['iata_code' => 'CGK'], ['icao_code' => 'WIII', 'name' => 'Soekarno Hatta', 'city' => 'Jakarta', 'province' => 'DKI Jakarta', 'island' => 'Jawa', 'timezone' => 'Asia/Jakarta']);
            $destination = AviationAirport::firstOrCreate(['iata_code' => 'DPS'], ['icao_code' => 'WADD', 'name' => 'Ngurah Rai', 'city' => 'Denpasar', 'province' => 'Bali', 'island' => 'Bali', 'timezone' => 'Asia/Makassar']);
            $route = AviationRoute::firstOrCreate(
                ['flight_number' => 'GA-404'],
                [
                    'origin_airport_id' => $origin->id,
                    'destination_airport_id' => $destination->id,
                    'distance_km' => 980,
                    'scheduled_duration_minutes' => 110,
                    'base_fare_minor' => 120000000,
                    'currency' => 'IDR',
                    'status' => 'active',
                ]
            );
            $flight = AviationFlight::firstOrCreate(['flight_number' => 'GA-404'], [
                'operator_id' => $operator->id,
                'route_id' => $route->id,
                'origin_airport_id' => $origin->id,
                'destination_airport_id' => $destination->id,
                'departure_time' => now()->addHours(2),
                'arrival_time' => now()->addHours(4),
                'status' => 'planned',
            ]);
            $ticket = AviationTicket::firstOrCreate(['ticket_number' => 'TKT-TEST-ROUTE-01'], [
                'flight_id' => $flight->id,
                'passenger_party_id' => auth()->id() ?? 1,
                'cabin' => 'Y',
                'fare_basis' => 'STD',
                'status' => 'issued',
                'base_fare_minor' => 100000000,
                'tax_minor' => 10000000,
                'total_fare_minor' => 110000000,
                'pii_name_encrypted' => 'Test Passenger',
                'pii_blind_index' => hash('sha256', 'TEST PASSENGER'),
            ]);
            if (auth()->check()) {
                $ticket->passenger_party_id = auth()->id();
                $ticket->save();
            }

            return ['ticket' => $ticket->id];
        },
        'setup' => function ($user) {
            AviationTicket::where('ticket_number', 'TKT-TEST-ROUTE-01')->update(['passenger_party_id' => $user->id]);
        },
    ],
    'aviation.passenger.index' => ['roles' => ['admin', 'passenger', 'superadmin']],
    'aviation.passenger.ticket' => [
        'roles' => ['admin', 'passenger', 'superadmin'],
        'params' => function () {
            $operator = AviationOperator::firstOrCreate(['code' => 'GA'], ['name' => 'Garuda', 'country' => 'ID', 'status' => 'active']);
            $origin = AviationAirport::firstOrCreate(['iata_code' => 'CGK'], ['icao_code' => 'WIII', 'name' => 'Soekarno Hatta', 'city' => 'Jakarta', 'province' => 'DKI Jakarta', 'island' => 'Jawa', 'timezone' => 'Asia/Jakarta']);
            $destination = AviationAirport::firstOrCreate(['iata_code' => 'DPS'], ['icao_code' => 'WADD', 'name' => 'Ngurah Rai', 'city' => 'Denpasar', 'province' => 'Bali', 'island' => 'Bali', 'timezone' => 'Asia/Makassar']);
            $route = AviationRoute::firstOrCreate(
                ['flight_number' => 'GA-404'],
                [
                    'origin_airport_id' => $origin->id,
                    'destination_airport_id' => $destination->id,
                    'distance_km' => 980,
                    'scheduled_duration_minutes' => 110,
                    'base_fare_minor' => 120000000,
                    'currency' => 'IDR',
                    'status' => 'active',
                ]
            );
            $flight = AviationFlight::firstOrCreate(['flight_number' => 'GA-404'], [
                'operator_id' => $operator->id,
                'route_id' => $route->id,
                'origin_airport_id' => $origin->id,
                'destination_airport_id' => $destination->id,
                'departure_time' => now()->addHours(2),
                'arrival_time' => now()->addHours(4),
                'status' => 'planned',
            ]);
            $ticket = AviationTicket::firstOrCreate(['ticket_number' => 'TKT-TEST-ROUTE-01'], [
                'flight_id' => $flight->id,
                'passenger_party_id' => auth()->id() ?? 1,
                'cabin' => 'Y',
                'fare_basis' => 'STD',
                'status' => 'issued',
                'base_fare_minor' => 100000000,
                'tax_minor' => 10000000,
                'total_fare_minor' => 110000000,
                'pii_name_encrypted' => 'Test Passenger',
                'pii_blind_index' => hash('sha256', 'TEST PASSENGER'),
            ]);
            if (auth()->check()) {
                $ticket->passenger_party_id = auth()->id();
                $ticket->save();
            }

            return ['ticket' => $ticket->id];
        },
        'setup' => function ($user) {
            AviationTicket::where('ticket_number', 'TKT-TEST-ROUTE-01')->update(['passenger_party_id' => $user->id]);
        },
    ],
    'aviation.reports.index' => ['roles' => ['admin', 'customer_care', 'ops_controller', 'superadmin']],
    'aviation.reports.export' => ['roles' => ['admin', 'customer_care', 'ops_controller', 'superadmin']],

    // Fase 178: Yield, IRROPS, Codeshare & Loyalty
    'aviation.yield.index' => ['roles' => ['admin', 'ops_controller', 'superadmin']],
    'aviation.yield.optimize' => ['roles' => ['admin', 'ops_controller', 'superadmin']],
    'aviation.irrops.index' => ['roles' => ['admin', 'customer_care', 'ops_controller', 'superadmin']],
    'aviation.irrops.store' => ['roles' => ['admin', 'customer_care', 'ops_controller', 'superadmin']],
    'aviation.irrops.dispatch-care' => ['roles' => ['admin', 'customer_care', 'ops_controller', 'superadmin']],
    'aviation.irrops.reaccommodate' => ['roles' => ['admin', 'customer_care', 'ops_controller', 'superadmin']],
    'aviation.codeshare.index' => ['roles' => ['admin', 'ops_controller', 'superadmin']],
    'aviation.codeshare.store' => ['roles' => ['admin', 'ops_controller', 'superadmin']],
    'aviation.codeshare.activate' => [
        'roles' => ['admin', 'ops_controller', 'superadmin'],
        'params' => function () {
            $operator = AviationOperator::firstOrCreate(['code' => 'GA'], ['name' => 'Garuda', 'country' => 'ID', 'status' => 'active']);
            $agreement = AviationCodeshareAgreement::firstOrCreate(
                ['agreement_code' => 'CS-TEST-ROUTE-ACTIVATE'],
                [
                    'operating_airline_id' => $operator->id,
                    'marketing_airline_id' => $operator->id,
                    'agreement_type' => 'free_sale',
                    'revenue_split_bps' => 8500,
                    'proration_rule' => 'straight_rate',
                    'status' => 'proposed',
                    'valid_from' => '2026-01-01',
                    'valid_until' => '2026-12-31',
                ]
            );

            return ['id' => $agreement->id];
        },
    ],
    'aviation.loyalty.index' => ['roles' => ['admin', 'customer_care', 'superadmin']],
    'aviation.loyalty.enroll' => ['roles' => ['admin', 'customer_care', 'superadmin']],

    // Fase 237: Platform & DX Tooling
    'platform.dev.dashboard' => ['roles' => ['admin', 'superadmin']],
    'platform.dev.sandboxes.index' => ['roles' => ['admin', 'superadmin']],
    'platform.dev.sandboxes.show' => [
        'roles' => ['admin', 'superadmin'],
        'params' => function () {
            $owner = User::firstOrCreate(['email' => 'admin@platform-route-test.local'], ['name' => 'Admin User', 'password' => bcrypt('password'), 'role' => 'admin']);
            $sandbox = SandboxEnvironment::firstOrCreate(
                ['slug' => 'sbx-arch-test-01'],
                [
                    'name' => 'sbx-arch-test-01',
                    'slug' => 'sbx-arch-test-01',
                    'tier' => 'standard',
                    'status' => 'requested',
                    'owner_id' => $owner->id,
                    'idempotency_key' => 'idem-arch-01',
                    'expires_at' => now()->addDays(7),
                ]
            );

            return ['sandbox' => $sandbox->id];
        },
    ],
    'platform.dev.slos.index' => ['roles' => ['admin', 'superadmin']],
    'platform.dev.metrics.index' => ['roles' => ['admin', 'superadmin']],
    'platform.dev.trace.show' => [
        'roles' => ['admin', 'superadmin'],
        'params' => function () {
            return ['correlationId' => 'corr-arch-test-trace-id'];
        },
    ],
    'platform.dev.api.sandboxes.store' => ['roles' => ['admin', 'superadmin']],
    'platform.dev.api.sandboxes.provision' => [
        'roles' => ['admin', 'superadmin'],
        'params' => function () {
            $owner = User::firstOrCreate(['email' => 'admin@platform-route-test.local'], ['name' => 'Admin User', 'password' => bcrypt('password'), 'role' => 'admin']);
            $sandbox = SandboxEnvironment::firstOrCreate(
                ['slug' => 'sbx-arch-test-02'],
                [
                    'name' => 'sbx-arch-test-02',
                    'slug' => 'sbx-arch-test-02',
                    'tier' => 'standard',
                    'status' => 'requested',
                    'owner_id' => $owner->id,
                    'idempotency_key' => 'idem-arch-02',
                    'expires_at' => now()->addDays(7),
                ]
            );

            return ['sandbox' => $sandbox->id];
        },
    ],
    'platform.dev.api.sandboxes.destroy' => [
        'roles' => ['admin', 'superadmin'],
        'params' => function () {
            $owner = User::firstOrCreate(['email' => 'admin@platform-route-test.local'], ['name' => 'Admin User', 'password' => bcrypt('password'), 'role' => 'admin']);
            $sandbox = SandboxEnvironment::firstOrCreate(
                ['slug' => 'sbx-arch-test-03'],
                [
                    'name' => 'sbx-arch-test-03',
                    'slug' => 'sbx-arch-test-03',
                    'tier' => 'standard',
                    'status' => 'requested',
                    'owner_id' => $owner->id,
                    'idempotency_key' => 'idem-arch-03',
                    'expires_at' => now()->addDays(7),
                ]
            );

            return ['sandbox' => $sandbox->id];
        },
    ],
    'platform.dev.api.slos.index' => ['roles' => ['admin', 'superadmin']],
    'platform.dev.api.metrics.prometheus' => ['roles' => ['admin', 'superadmin']],
    'platform.dev.api.metrics.phase' => [
        'roles' => ['admin', 'superadmin'],
        'params' => function () {
            return ['phaseId' => 237];
        },
    ],

    // Fase 238: Change Management & Release Train
    'platform.change.index' => ['roles' => ['admin', 'superadmin']],
    'platform.change.show' => [
        'roles' => ['admin', 'superadmin'],
        'params' => function () {
            $owner = User::firstOrCreate(['email' => 'admin@platform-route-test.local'], ['name' => 'Admin User', 'password' => bcrypt('password'), 'role' => 'admin']);
            $cr = ChangeRequest::firstOrCreate(
                ['reference_number' => 'CR-ARCH-ROUTE-01'],
                [
                    'title' => 'Arch Test CR',
                    'requester_id' => $owner->id,
                    'target_module' => 'Core',
                    'change_type' => 'normal',
                    'status' => 'draft',
                    'risk_score' => 2500,
                    'risk_tier' => 'low',
                ]
            );

            return ['change' => $cr->id];
        },
    ],
    'platform.release.index' => ['roles' => ['admin', 'superadmin']],
    'platform.release.show' => [
        'roles' => ['admin', 'superadmin'],
        'params' => function () {
            $owner = User::firstOrCreate(['email' => 'admin@platform-route-test.local'], ['name' => 'Admin User', 'password' => bcrypt('password'), 'role' => 'admin']);
            $train = ReleaseTrain::firstOrCreate(
                ['train_code' => 'REL-ARCH-ROUTE-01'],
                [
                    'version' => 'v8.9.0',
                    'release_manager_id' => $owner->id,
                    'status' => 'open',
                    'freeze_at' => now()->addDays(5),
                    'scheduled_deploy_at' => now()->addDays(7),
                ]
            );

            return ['train' => $train->id];
        },
    ],
    'platform.backfills.index' => ['roles' => ['admin', 'superadmin']],
    'admin.simulation.runs.index' => ['roles' => ['sim_operator', 'admin', 'superadmin']],
    'admin.simulation.runs.store' => ['roles' => ['sim_operator', 'admin', 'superadmin']],
    'admin.simulation.diff' => ['roles' => ['sim_operator', 'admin', 'superadmin']],
    'admin.simulation.twins.index' => ['roles' => ['sim_operator', 'admin', 'superadmin']],
    'admin.simulation.twins.show' => [
        'roles' => ['sim_operator', 'admin', 'superadmin'],
        'params' => fn () => ['type' => 'vehicle', 'id' => 'VH-001'],
    ],
    'admin.simulation.export' => [
        'roles' => ['sim_operator', 'admin', 'superadmin'],
        'params' => fn () => ['type' => 'runs'],
    ],
    'admin.simulation.runs.show' => [
        'roles' => ['sim_operator', 'admin', 'superadmin'],
        'params' => function () {
            $run = SimRun::firstOrCreate(
                ['name' => 'route-role-test-run'],
                [
                    'virtual_now' => now(),
                    'sim_start_at' => now(),
                    'status' => 'draft',
                    'seed' => 42,
                ]
            );

            return ['run' => $run->id];
        },
    ],
    'admin.simulation.runs.pause' => [
        'roles' => ['sim_operator', 'admin', 'superadmin'],
        'params' => function () {
            $run = SimRun::firstOrCreate(
                ['name' => 'route-role-test-run'],
                [
                    'virtual_now' => now(),
                    'sim_start_at' => now(),
                    'status' => 'draft',
                    'seed' => 42,
                ]
            );

            return ['run' => $run->id];
        },
    ],
    'admin.simulation.runs.resume' => [
        'roles' => ['sim_operator', 'admin', 'superadmin'],
        'params' => function () {
            $run = SimRun::firstOrCreate(
                ['name' => 'route-role-test-run'],
                [
                    'virtual_now' => now(),
                    'sim_start_at' => now(),
                    'status' => 'draft',
                    'seed' => 42,
                ]
            );

            return ['run' => $run->id];
        },
    ],
    'admin.spine.index' => [
        'roles' => ['spine_operator', 'admin', 'superadmin'],
    ],
    'admin.spine.dlq.index' => [
        'roles' => ['spine_operator', 'admin', 'superadmin'],
    ],
    'admin.spine.dlq.export' => [
        'roles' => ['spine_operator', 'admin', 'superadmin'],
    ],
    'admin.spine.dlq.replay' => [
        'roles' => ['spine_operator', 'admin', 'superadmin'],
    ],
    'admin.spine.dlq.replay-batch' => [
        'roles' => ['spine_operator', 'admin', 'superadmin'],
    ],
    'admin.spine.dlq.discard' => [
        'roles' => ['spine_operator', 'admin', 'superadmin'],
    ],
    'admin.spine.dlq.discard-batch' => [
        'roles' => ['spine_operator', 'admin', 'superadmin'],
    ],
    'admin.spine.consumers.index' => [
        'roles' => ['spine_operator', 'admin', 'superadmin'],
    ],
    'admin.spine.consumers.replay' => [
        'roles' => ['spine_operator', 'admin', 'superadmin'],
    ],
    'admin.spine.consumers.export' => [
        'roles' => ['spine_operator', 'admin', 'superadmin'],
    ],
    'admin.spine.gaps.index' => [
        'roles' => ['spine_operator', 'admin', 'superadmin'],
    ],
    'admin.spine.gaps.scan' => [
        'roles' => ['spine_operator', 'admin', 'superadmin'],
    ],
    'admin.spine.gaps.resolve' => [
        'roles' => ['spine_operator', 'admin', 'superadmin'],
    ],
    'admin.spine.gaps.export' => [
        'roles' => ['spine_operator', 'admin', 'superadmin'],
    ],
    'admin.spine.schemas.index' => [
        'roles' => ['spine_operator', 'auditor', 'admin', 'superadmin'],
    ],
    'admin.spine.schemas.export' => [
        'roles' => ['spine_operator', 'auditor', 'admin', 'superadmin'],
    ],
    'admin.boundaries.map' => [
        'roles' => ['admin', 'superadmin', 'platform_architect'],
    ],
    'admin.boundaries.violations' => [
        'roles' => ['admin', 'superadmin', 'platform_architect'],
    ],
    'admin.boundaries.violations.show' => [
        'roles' => ['admin', 'superadmin', 'platform_architect'],
        'params' => function () {
            $v = CoreBoundaryViolation::firstOrCreate(
                ['rule_id' => 'A1', 'file_path' => 'tests/sample.php', 'signature' => 'route-roles-sample'],
                [
                    'category' => 'domain_import',
                    'line_number' => 10,
                    'client_module' => 'Core',
                    'owner_module' => 'Core',
                    'status' => 'detected',
                ]
            );

            return ['id' => $v->id];
        },
    ],
    'admin.boundaries.waivers' => [
        'roles' => ['admin', 'superadmin', 'platform_architect', 'auditor'],
    ],
    'admin.boundaries.waivers.extend' => [
        'roles' => ['admin', 'superadmin', 'platform_architect', 'auditor'],
        'params' => function () {
            $v = CoreBoundaryViolation::firstOrCreate(
                ['rule_id' => 'A2', 'file_path' => 'tests/waiver-sample.php', 'signature' => 'route-roles-waiver-sample'],
                [
                    'category' => 'table_prefix',
                    'line_number' => 10,
                    'client_module' => 'Core',
                    'owner_module' => 'Core',
                    'status' => 'waived',
                    'adr_anchor' => 'docs/DECISIONS.md#w8-d43',
                    'expires_at' => now()->addDays(30),
                ]
            );

            return ['id' => $v->id];
        },
    ],
    'admin.boundaries.export.violations' => [
        'roles' => ['admin', 'superadmin', 'platform_architect'],
    ],
    'admin.boundaries.export.waivers' => [
        'roles' => ['admin', 'superadmin', 'platform_architect'],
    ],
    'admin.boundaries.export.coverage' => [
        'roles' => ['admin', 'superadmin', 'platform_architect'],
    ],
    'admin.boundaries.export.table-ownership' => [
        'roles' => ['admin', 'superadmin', 'platform_architect'],
    ],
    'admin.architecture.modules' => [
        'roles' => ['admin', 'superadmin', 'platform_architect'],
    ],
    'admin.architecture.violations' => [
        'roles' => ['admin', 'superadmin', 'platform_architect'],
    ],
    'admin.architecture.violations.show' => [
        'roles' => ['admin', 'superadmin', 'platform_architect'],
        'params' => function () {
            $v = CoreBoundaryViolation::firstOrCreate(
                ['rule_id' => 'A1', 'file_path' => 'tests/sample.php', 'signature' => 'route-roles-sample'],
                [
                    'category' => 'domain_import',
                    'line_number' => 10,
                    'client_module' => 'Core',
                    'owner_module' => 'Core',
                    'status' => 'detected',
                ]
            );

            return ['id' => $v->id];
        },
    ],
    'admin.architecture.waivers' => [
        'roles' => ['admin', 'superadmin', 'platform_architect', 'auditor'],
    ],
    'admin.architecture.waivers.extend' => [
        'roles' => ['admin', 'superadmin', 'platform_architect', 'auditor'],
        'params' => function () {
            $v = CoreBoundaryViolation::firstOrCreate(
                ['rule_id' => 'A2', 'file_path' => 'tests/waiver-sample.php', 'signature' => 'route-roles-waiver-sample'],
                [
                    'category' => 'table_prefix',
                    'line_number' => 10,
                    'client_module' => 'Core',
                    'owner_module' => 'Core',
                    'status' => 'waived',
                    'adr_anchor' => 'docs/DECISIONS.md#w8-d43',
                    'expires_at' => now()->addDays(30),
                ]
            );

            return ['id' => $v->id];
        },
    ],
    'admin.api.clients' => [
        'roles' => ['admin', 'api_admin', 'superadmin'],
    ],
    'admin.api.usage' => [
        'roles' => ['admin', 'api_admin', 'auditor', 'superadmin'],
    ],
    'admin.api.edi' => [
        'roles' => ['admin', 'api_admin', 'superadmin'],
    ],
    'admin.api.imports' => [
        'roles' => ['admin', 'api_admin', 'superadmin'],
    ],
    'admin.api.webhooks' => [
        'roles' => ['admin', 'api_admin', 'superadmin'],
    ],
    'admin.api.retention' => [
        'roles' => ['admin', 'api_admin', 'auditor', 'platform_architect', 'superadmin'],
    ],
    'admin.tenancy.index' => [
        'roles' => ['admin', 'api_admin', 'platform_architect', 'superadmin'],
    ],
    'admin.tenancy.coverage' => [
        'roles' => ['admin', 'api_admin', 'security_officer', 'platform_architect', 'superadmin'],
    ],
    'developer.index' => [
        'roles' => ['admin', 'developer', 'api_admin', 'superadmin'],
    ],
    'admin.developer.index' => [
        'roles' => ['admin', 'developer', 'api_admin', 'superadmin'],
    ],
    'admin.api.exports.download' => [
        'roles' => ['admin', 'api_admin', 'superadmin'],
        'params' => fn () => ['export' => 1],
    ],
    'api.v2.bulk.export.download' => [
        'protection' => 'public',
        'params' => fn () => ['job' => 1],
    ],
    'api.v2.webhooks.index' => 'public',
    'api.v2.webhooks.store' => 'public',
    'api.v2.webhooks.rotate-secret' => [
        'protection' => 'public',
        'params' => fn () => ['id' => 1],
    ],
    'api.v2.edi.interchange' => 'public',
    'api.v2.bulk.import' => 'public',
    'api.v2.bulk.export' => 'public',
    'api.v2.resource.index' => [
        'protection' => 'public',
        'params' => fn () => ['resource' => 'purchase-orders'],
    ],
    'api.v2.resource.show' => [
        'protection' => 'public',
        'params' => fn () => ['resource' => 'purchase-orders', 'id' => '1'],
    ],
    'api.v2.resource.action' => [
        'protection' => 'public',
        'params' => fn () => ['resource' => 'purchase-orders', 'action' => 'cancel'],
    ],
];
