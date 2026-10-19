<?php

declare(strict_types=1);
use App\Models\User;
use Illuminate\Support\Str;
use Modules\AutoDex\Domain\Models\Brand;
use Modules\AutoDex\Domain\Models\Car;
use Modules\AutoServe\Domain\Enums\EstimateStatus;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\AutoServe\Domain\Models\Estimate;
use Modules\AutoServe\Domain\Models\Service;
use Modules\AutoServe\Domain\Models\Sparepart;
use Modules\CloudKitchen\Domain\Models\CateringSubscription;
use Modules\CloudKitchen\Domain\Models\CloudKitchen;
use Modules\Core\Domain\Models\AuditLog;
use Modules\Core\Domain\Models\CoreAiAnomalyQuarantine;
use Modules\Core\Domain\Models\CoreMobileDevice;
use Modules\Core\Domain\Models\Role;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Crypto\Domain\Enums\AlertCondition;
use Modules\Crypto\Domain\Models\CryptoAlert;
use Modules\Crypto\Domain\Models\CryptoAsset;
use Modules\Crypto\Domain\Models\CryptoPriceTick;
use Modules\Distribution\Domain\Models\Distributor;
use Modules\Ev\Domain\Models\EvCharger;
use Modules\Ev\Domain\Models\EvSession;
use Modules\Ev\Domain\Models\EvStation;
use Modules\Finance\Domain\Models\Loan;
use Modules\Fleet\Domain\Models\FleetContract;
use Modules\Hospital\Domain\Models\ClinicalTrial;
use Modules\Hotel\database\seeders\HotelDemoSeeder;
use Modules\Hotel\Domain\Models\HotelReservation;
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
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Partner\Domain\Models\Partner;
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
use Modules\Telematics\Domain\Models\TelematicsDevice;
use Modules\Vending\Domain\Models\VendingUnit;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\EntertainmentVenue;
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
    'admin.group-dashboard' => ['roles' => ['admin']],
    'admin.health.index' => ['roles' => ['admin']],
    'admin.health.run' => ['roles' => ['admin']],
    'admin.ledger.adjust' => ['roles' => ['admin']],
    'admin.ledger.freeze' => ['roles' => ['admin']],
    'admin.ledger.index' => ['roles' => ['admin']],
    'admin.ledger.show' => ['roles' => ['admin']],
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
    'admin.rbac.toggle-permission' => ['roles' => ['admin']],
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
    'agency.agents.transition' => ['roles' => ['admin', 'procurement']],
    'agency.attributions.store' => ['roles' => ['admin', 'procurement']],
    'agency.brands.store' => ['roles' => ['admin', 'procurement']],
    'agency.certifications.store' => ['roles' => ['admin', 'procurement']],
    'agency.compliance.store' => ['roles' => ['admin', 'procurement']],
    'agency.fraud.store' => ['roles' => ['admin', 'procurement']],
    'agency.index' => ['roles' => ['admin', 'agent', 'auditor', 'procurement']],
    'agency.leads.convert' => ['roles' => ['admin', 'procurement']],
    'agency.leads.store' => ['roles' => ['admin', 'procurement']],
    'agency.payouts.approve' => ['roles' => ['admin', 'procurement']],
    'agency.payouts.store' => ['roles' => ['admin', 'procurement']],
    'agency.schemes.store' => ['roles' => ['admin', 'procurement']],
    'agency.show' => ['roles' => ['admin', 'agent', 'auditor', 'procurement']],
    'agri.index' => ['roles' => ['admin', 'farmer', 'procurement']],
    'api.global-search' => 'auth',
    'api.v1.integration.ping' => 'public',
    'api.v1.logistics.quotes.store' => ['roles' => ['admin', 'customer', 'dispatcher', 'logistics_admin', 'shipper']],
    'api.v1.logistics.shipments.index' => ['roles' => ['admin', 'auditor', 'dispatcher', 'driver', 'hub_operator', 'logistics_admin', 'shipper']],
    'api.v1.logistics.shipments.show' => ['roles' => ['admin', 'auditor', 'dispatcher', 'driver', 'hub_operator', 'logistics_admin', 'shipper'], 'params' => fn () => ['tracking_number' => $logisticsSeedHelper()['shipment']->tracking_number]],
    'api.v1.logistics.shipments.store' => ['roles' => ['admin', 'dispatcher', 'logistics_admin', 'shipper']],
    'api.v1.logistics.tracking.show' => 'public',
    'asset.audit' => ['roles' => ['admin', 'asset_manager']],
    'asset.checkin' => ['roles' => ['admin', 'asset_manager']],
    'asset.checkout' => ['roles' => ['admin', 'asset_manager']],
    'asset.create' => ['roles' => ['admin', 'asset_manager']],
    'asset.depreciate' => ['roles' => ['admin', 'asset_manager']],
    'asset.depreciation.index' => ['roles' => ['admin', 'asset_manager']],
    'asset.depreciation.run' => ['roles' => ['admin', 'asset_manager']],
    'asset.disposal.finalize' => ['roles' => ['admin', 'asset_manager']],
    'asset.disposal.request' => ['roles' => ['admin', 'asset_manager']],
    'asset.index' => ['roles' => ['admin', 'asset_manager']],
    'asset.insurance.store' => ['roles' => ['admin', 'asset_manager']],
    'asset.leases.pay' => ['roles' => ['admin', 'asset_manager']],
    'asset.leases.store' => ['roles' => ['admin', 'asset_manager']],
    'asset.move' => ['roles' => ['admin', 'asset_manager']],
    'asset.move.approve' => ['roles' => ['admin', 'asset_manager']],
    'asset.revaluation.apply' => ['roles' => ['admin', 'asset_manager']],
    'asset.revaluation.request' => ['roles' => ['admin', 'asset_manager']],
    'asset.scan' => ['roles' => ['admin', 'asset_manager']],
    'asset.scan.form' => ['roles' => ['admin', 'asset_manager']],
    'asset.show' => ['roles' => ['admin', 'asset_manager']],
    'asset.store' => ['roles' => ['admin', 'asset_manager']],
    'asset.verify-chain' => ['roles' => ['admin', 'asset_manager']],
    'asset.work-orders.complete' => ['roles' => ['admin', 'asset_manager']],
    'asset.work-orders.store' => ['roles' => ['admin', 'asset_manager']],
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
    'cloud_kitchen.index' => ['roles' => ['admin', 'customer', 'kitchen', 'outlet_manager']],
    'cloud_kitchen.subscriptions.dispatch' => [
        'roles' => ['admin', 'customer', 'kitchen', 'outlet_manager'],
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
    'contract.advance.pay' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.amend' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.append-version' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.attachment.destroy' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.attachment.store' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.clauses.create' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.clauses.edit' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.clauses.index' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.clauses.store' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.clauses.update' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.create' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.edit' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.escalation.apply' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.escalation.preview' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.index' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.milestone.complete' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.milestone.store' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.obligations' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.party.add' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.party.remove' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.reports' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.risk.rescore' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.schedule.build' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.schedule.pay' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.schedule.penalty.pay' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.schedule.penalty.show' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.schedule.penalty.waiver' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.schedule.penalty.waiver.apply' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.show' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.sign-party' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.store' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.submit-approval' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.templates.create' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.templates.index' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.templates.store' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.transition' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.update' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.usage.sync' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.version-diff' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'contract.versions' => ['roles' => ['admin', 'contract_manager', 'legal']],
    'control_tower.index' => ['roles' => ['admin', 'logistics_admin', 'planner', 'auditor']],
    'control_tower.promises' => ['roles' => ['admin', 'logistics_admin', 'planner', 'auditor']],
    'crypto.alerts.destroy' => ['roles' => ['admin', 'customer', 'treasury'], 'params' => fn () => ['alert' => $financeCryptoSeedHelper()['alert']->id], 'setup' => $financeCryptoCustomerSetup],
    'crypto.alerts.index' => ['roles' => ['admin', 'auditor', 'customer', 'treasury']],
    'crypto.alerts.store' => ['roles' => ['admin', 'customer', 'treasury']],
    'crypto.api.chart' => 'public',
    'crypto.api.prices' => 'public',
    'crypto.market.index' => 'public',
    'crypto.market.show' => 'public',
    'crypto.portfolio.index' => ['roles' => ['admin', 'auditor', 'customer', 'treasury']],
    'crypto.trade.execute' => ['roles' => ['admin', 'customer', 'treasury']],
    'crypto.trade.quote' => ['roles' => ['admin', 'customer', 'treasury']],
    'dashboard' => 'auth',
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
    'distribution.show' => ['roles' => ['admin', 'auditor', 'distributor', 'procurement'], 'params' => $distributorParamHelper],
    'distribution.sweep' => ['roles' => ['admin', 'procurement']],
    'distribution.targets.store' => ['roles' => ['admin', 'procurement']],
    'distribution.territories.store' => ['roles' => ['admin', 'procurement']],
    'distribution.tier.evaluate' => ['roles' => ['admin', 'procurement'], 'params' => $distributorParamHelper],
    'distribution.transition' => ['roles' => ['admin', 'procurement'], 'params' => $distributorParamHelper],
    'enterprise_finance.budgets' => ['roles' => ['admin', 'auditor', 'treasury']],
    'enterprise_finance.index' => ['roles' => ['admin', 'auditor', 'treasury']],
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
    'hcm.index' => ['roles' => ['admin', 'hcm_manager']],
    'hospital.index' => ['roles' => ['admin', 'doctor', 'hospital_admin']],
    'hospital.trials.bill' => [
        'roles' => ['admin', 'doctor', 'hospital_admin'],
        'params' => fn () => ['trial' => ClinicalTrial::firstOrCreate(['trial_code' => 'TRL-CARDIO-01'], ['title' => 'Trial Card', 'phase' => 'PHASE_3', 'sponsor_name' => 'Novartis', 'target_subjects' => 10, 'status' => 'RECRUITING'])->id],
    ],
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
    'international.index' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'international.jvs' => ['roles' => ['admin', 'auditor', 'legal', 'treasury']],
    'login' => 'public',
    'logistics.carriers.index' => ['roles' => ['admin', 'auditor', 'dispatcher', 'logistics_admin', 'procurement', 'treasury']],
    'logistics.carriers.pay' => ['roles' => ['admin', 'logistics_admin', 'treasury'], 'params' => function () use ($logisticsSeedHelper) {
        $d = $logisticsSeedHelper();

        return ['carrier' => $d['carrier']->id];
    }],
    'logistics.carriers.store' => ['roles' => ['admin', 'logistics_admin', 'procurement']],
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
    'logistics.autonomous.index' => ['roles' => ['admin', 'auditor', 'dispatcher', 'hub_operator', 'logistics_admin']],
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
    'manufacturing.boms.store' => ['roles' => ['admin', 'planner']],
    'manufacturing.costing.index' => ['roles' => ['admin', 'auditor', 'planner']],
    'manufacturing.costing.versions.approve' => ['roles' => ['admin', 'planner']],
    'manufacturing.costing.versions.submit' => ['roles' => ['admin', 'planner']],
    'manufacturing.formulas.approve' => ['roles' => ['admin', 'qc_inspector']],
    'manufacturing.formulas.store' => ['roles' => ['admin', 'planner']],
    'manufacturing.formulas.submit' => ['roles' => ['admin', 'planner']],
    'manufacturing.index' => ['roles' => ['admin', 'operator', 'planner', 'qc_inspector']],
    'manufacturing.maintenance.incidents.close' => ['roles' => ['admin', 'asset_manager', 'operator', 'planner']],
    'manufacturing.maintenance.incidents.store' => ['roles' => ['admin', 'asset_manager', 'operator', 'planner']],
    'manufacturing.maintenance.index' => ['roles' => ['admin', 'asset_manager', 'auditor', 'operator', 'planner']],
    'manufacturing.maintenance.oee.compute' => ['roles' => ['admin', 'asset_manager', 'operator', 'planner']],
    'manufacturing.maintenance.orders.store' => ['roles' => ['admin', 'asset_manager', 'operator', 'planner']],
    'manufacturing.maintenance.orders.transition' => ['roles' => ['admin', 'asset_manager', 'operator', 'planner']],
    'manufacturing.maintenance.parts.store' => ['roles' => ['admin', 'asset_manager', 'operator', 'planner']],
    'manufacturing.maintenance.permits.approve' => ['roles' => ['admin', 'asset_manager', 'operator', 'planner']],
    'manufacturing.maintenance.permits.store' => ['roles' => ['admin', 'asset_manager', 'operator', 'planner']],
    'manufacturing.maintenance.sensors.store' => ['roles' => ['admin', 'asset_manager', 'operator', 'planner']],
    'manufacturing.materials.store' => ['roles' => ['admin', 'planner']],
    'manufacturing.planning.balances.store' => ['roles' => ['admin', 'planner']],
    'manufacturing.planning.forecast.activate' => ['roles' => ['admin', 'planner']],
    'manufacturing.planning.forecast.store' => ['roles' => ['admin', 'planner']],
    'manufacturing.planning.index' => ['roles' => ['admin', 'planner']],
    'manufacturing.planning.mps.store' => ['roles' => ['admin', 'planner']],
    'manufacturing.planning.mrp.run' => ['roles' => ['admin', 'planner']],
    'manufacturing.planning.orders.firm' => ['roles' => ['admin', 'planner']],
    'manufacturing.planning.params.store' => ['roles' => ['admin', 'planner']],
    'manufacturing.planning.receipts.store' => ['roles' => ['admin', 'planner']],
    'manufacturing.planning.runs.propose' => ['roles' => ['admin', 'planner']],
    'manufacturing.plants.store' => ['roles' => ['admin', 'planner']],
    'manufacturing.production.downtimes.end' => ['roles' => ['admin', 'operator', 'planner', 'qc_inspector']],
    'manufacturing.production.downtimes.store' => ['roles' => ['admin', 'operator', 'planner', 'qc_inspector']],
    'manufacturing.production.index' => ['roles' => ['admin', 'operator', 'planner', 'qc_inspector']],
    'manufacturing.production.operations.finish' => ['roles' => ['admin', 'operator', 'planner', 'qc_inspector']],
    'manufacturing.production.operations.start' => ['roles' => ['admin', 'operator', 'planner', 'qc_inspector']],
    'manufacturing.production.orders.invariants' => ['roles' => ['admin', 'operator', 'planner', 'qc_inspector']],
    'manufacturing.production.orders.issue' => ['roles' => ['admin', 'operator', 'planner', 'qc_inspector']],
    'manufacturing.production.orders.receive-fg' => ['roles' => ['admin', 'operator', 'planner', 'qc_inspector']],
    'manufacturing.production.orders.scrap' => ['roles' => ['admin', 'operator', 'planner', 'qc_inspector']],
    'manufacturing.production.orders.store' => ['roles' => ['admin', 'planner']],
    'manufacturing.production.orders.transition' => ['roles' => ['admin', 'operator', 'planner', 'qc_inspector']],
    'manufacturing.quality.capas.complete' => ['roles' => ['admin', 'planner', 'qc_inspector']],
    'manufacturing.quality.capas.store' => ['roles' => ['admin', 'planner', 'qc_inspector']],
    'manufacturing.quality.index' => ['roles' => ['admin', 'auditor', 'planner', 'qc_inspector']],
    'manufacturing.quality.inspections.approve' => ['roles' => ['admin', 'planner', 'qc_inspector']],
    'manufacturing.quality.inspections.store' => ['roles' => ['admin', 'planner', 'qc_inspector']],
    'manufacturing.quality.inspections.waiver' => ['roles' => ['admin', 'planner', 'qc_inspector']],
    'manufacturing.quality.lots.certificates' => ['roles' => ['admin', 'planner', 'qc_inspector']],
    'manufacturing.quality.lots.recall' => ['roles' => ['admin', 'planner', 'qc_inspector']],
    'manufacturing.quality.lots.release' => ['roles' => ['admin', 'planner', 'qc_inspector']],
    'manufacturing.quality.lots.trace' => ['roles' => ['admin', 'auditor', 'planner', 'qc_inspector']],
    'manufacturing.quality.ncrs.store' => ['roles' => ['admin', 'planner', 'qc_inspector']],
    'manufacturing.quality.plans.store' => ['roles' => ['admin', 'planner', 'qc_inspector']],
    'manufacturing.quality.recalls.complete' => ['roles' => ['admin', 'planner', 'qc_inspector']],
    'manufacturing.quality.recalls.notify' => ['roles' => ['admin', 'planner', 'qc_inspector']],
    'manufacturing.routings.store' => ['roles' => ['admin', 'planner']],
    'manufacturing.workers.store' => ['roles' => ['admin', 'planner']],
    'notifications.index' => 'auth',
    'notifications.markAllRead' => 'auth',
    'notifications.markAllReadJson' => 'auth',
    'notifications.read' => 'auth',
    'notifications.recent' => 'auth',
    'partners.index' => ['roles' => ['admin', 'auditor', 'procurement']],
    'partners.show' => ['roles' => ['admin', 'auditor', 'procurement'], 'params' => $partnerParamHelper],
    'partners.store' => ['roles' => ['admin', 'procurement']],
    'partners.transition' => ['roles' => ['admin', 'procurement'], 'params' => $partnerParamHelper],
    'party.create' => ['roles' => ['admin', 'logistics_admin', 'party_manager']],
    'party.index' => ['roles' => ['admin', 'logistics_admin', 'party_manager']],
    'party.kyc.approve' => ['roles' => ['admin', 'logistics_admin', 'party_manager']],
    'party.kyc.reject' => ['roles' => ['admin', 'logistics_admin', 'party_manager']],
    'party.kyc.submit' => ['roles' => ['admin', 'logistics_admin', 'party_manager']],
    'party.legal-entities' => ['roles' => ['admin', 'logistics_admin', 'party_manager']],
    'party.legal-entities.store' => ['roles' => ['admin', 'logistics_admin', 'party_manager']],
    'party.rescore' => ['roles' => ['admin', 'logistics_admin', 'party_manager']],
    'party.screen' => ['roles' => ['admin', 'logistics_admin', 'party_manager']],
    'party.show' => ['roles' => ['admin', 'logistics_admin', 'party_manager']],
    'party.store' => ['roles' => ['admin', 'logistics_admin', 'party_manager']],
    'passport.show' => 'public',
    'password.confirm' => 'auth',
    'password.email' => 'public',
    'password.request' => 'public',
    'password.reset' => 'public',
    'password.store' => 'public',
    'password.update' => 'auth',
    'plm.index' => ['roles' => ['admin', 'rnd_specialist']],
    'pricing.analytics.compute' => ['roles' => ['admin', 'procurement']],
    'pricing.claims.settle' => ['roles' => ['admin', 'procurement']],
    'pricing.claims.validate' => ['roles' => ['admin', 'procurement']],
    'pricing.discounts.store' => ['roles' => ['admin', 'procurement']],
    'pricing.index' => ['roles' => ['admin', 'auditor', 'procurement']],
    'pricing.margin-policies.store' => ['roles' => ['admin', 'procurement']],
    'pricing.overrides.decide' => ['roles' => ['admin', 'procurement']],
    'pricing.overrides.store' => ['roles' => ['admin', 'procurement']],
    'pricing.price-lists.activate' => ['roles' => ['admin', 'procurement']],
    'pricing.price-lists.items.store' => ['roles' => ['admin', 'procurement']],
    'pricing.price-lists.store' => ['roles' => ['admin', 'procurement']],
    'pricing.promotions.claims.store' => ['roles' => ['admin', 'procurement']],
    'pricing.promotions.store' => ['roles' => ['admin', 'procurement']],
    'procurement.dashboard' => ['roles' => ['admin', 'procurement']],
    'procurement.pos.cancel' => ['roles' => ['admin', 'procurement']],
    'procurement.pos.close' => ['roles' => ['admin', 'procurement']],
    'procurement.pos.import-profile' => ['roles' => ['admin', 'procurement']],
    'procurement.pos.inbound' => ['roles' => ['admin', 'procurement']],
    'procurement.pos.revise' => ['roles' => ['admin', 'procurement']],
    'procurement.pos.show' => ['roles' => ['admin', 'procurement', 'supplier']],
    'procurement.pos.store' => ['roles' => ['admin', 'procurement']],
    'procurement.quotes.store' => ['roles' => ['admin', 'procurement', 'supplier']],
    'procurement.requisitions.approve' => ['roles' => ['admin', 'procurement']],
    'procurement.requisitions.show' => ['roles' => ['admin', 'procurement', 'supplier']],
    'procurement.requisitions.store' => ['roles' => ['admin', 'procurement']],
    'procurement.rfqs.award' => ['roles' => ['admin', 'procurement']],
    'procurement.rfqs.show' => ['roles' => ['admin', 'procurement', 'supplier']],
    'procurement.rfqs.store' => ['roles' => ['admin', 'procurement']],
    'procurement.tenders.award' => ['roles' => ['admin', 'procurement']],
    'procurement.tenders.evaluate' => ['roles' => ['admin', 'procurement']],
    'procurement.tenders.open' => ['roles' => ['admin', 'procurement']],
    'procurement.tenders.seal' => ['roles' => ['admin', 'procurement', 'supplier']],
    'procurement.tenders.show' => ['roles' => ['admin', 'procurement', 'supplier']],
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
    ],
    'resto.outlets.edit' => [
        'roles' => ['admin', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['outlet' => Outlet::first()?->id ?? 1];
        },
    ],
    'resto.outlets.index' => ['roles' => ['admin', 'auditor', 'outlet_manager']],
    'resto.outlets.show' => [
        'roles' => ['admin', 'auditor', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['outlet' => Outlet::first()?->id ?? 1];
        },
    ],
    'resto.outlets.store' => ['roles' => ['admin', 'outlet_manager']],
    'resto.outlets.update' => [
        'roles' => ['admin', 'outlet_manager'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['outlet' => Outlet::first()?->id ?? 1];
        },
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
    ],
    'resto.purchases.edit' => [
        'roles' => ['admin', 'outlet_manager', 'procurement'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['purchase' => PurchaseOrder::first()?->id ?? 1];
        },
    ],
    'resto.purchases.index' => ['roles' => ['admin', 'auditor', 'outlet_manager', 'procurement']],
    'resto.purchases.pay' => [
        'roles' => ['admin', 'outlet_manager', 'procurement'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['purchase' => PurchaseOrder::first()?->id ?? 1];
        },
    ],
    'resto.purchases.receive' => [
        'roles' => ['admin', 'outlet_manager', 'procurement'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['purchase' => PurchaseOrder::first()?->id ?? 1];
        },
    ],
    'resto.purchases.show' => [
        'roles' => ['admin', 'auditor', 'outlet_manager', 'procurement'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['purchase' => PurchaseOrder::first()?->id ?? 1];
        },
    ],
    'resto.purchases.store' => ['roles' => ['admin', 'outlet_manager', 'procurement']],
    'resto.purchases.update' => [
        'roles' => ['admin', 'outlet_manager', 'procurement'],
        'params' => function () use ($restoSeedHelper): array {
            $restoSeedHelper();

            return ['purchase' => PurchaseOrder::first()?->id ?? 1];
        },
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
    'storage.local' => 'public',
    'storage.local.upload' => 'signed',
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
    'store.products.show' => 'public',
    'supplier.certifications.store' => ['roles' => ['admin', 'party_manager', 'procurement']],
    'supplier.create' => ['roles' => ['admin', 'party_manager', 'procurement']],
    'supplier.index' => ['roles' => ['admin', 'party_manager', 'procurement']],
    'supplier.items.store' => ['roles' => ['admin', 'party_manager', 'procurement']],
    'supplier.portal.asn.ship' => [
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
    'supplier.qualifications.approve' => ['roles' => ['admin', 'party_manager', 'procurement']],
    'supplier.qualifications.store' => ['roles' => ['admin', 'party_manager', 'procurement']],
    'supplier.scan-risks' => ['roles' => ['admin', 'party_manager', 'procurement']],
    'supplier.scorecards.store' => ['roles' => ['admin', 'party_manager', 'procurement']],
    'supplier.show' => ['roles' => ['admin', 'party_manager', 'procurement']],
    'supplier.store' => ['roles' => ['admin', 'party_manager', 'procurement']],
    'supplier.transition' => ['roles' => ['admin', 'party_manager', 'procurement']],
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
    'track.show' => 'public',
    'trade.index' => ['roles' => ['admin', 'auditor', 'procurement', 'treasury']],
    'trade_finance.index' => ['roles' => ['admin', 'auditor', 'procurement', 'treasury']],
    'trade_finance.lcs' => ['roles' => ['admin', 'auditor', 'procurement', 'treasury']],
    'treasury.index' => ['roles' => ['admin', 'auditor', 'treasury']],
    'verification.notice' => 'auth',
    'verification.send' => 'auth',
    'verification.verify' => 'auth',
    'vending.index' => ['roles' => ['admin', 'vending_operator', 'store_manager']],
    'vending.units.dispense' => [
        'roles' => ['admin', 'vending_operator', 'store_manager'],
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
    'wealth.index' => ['roles' => ['admin', 'customer', 'investor', 'treasury', 'wealth_manager']],
    'wealth.profiles.invest' => [
        'roles' => ['admin', 'customer', 'investor', 'treasury', 'wealth_manager'],
        'params' => function () {
            $prof = WmProfile::firstOrCreate(['user_id' => 1], ['risk_profile' => 'moderate', 'monthly_spend_baseline_idr' => 10000000, 'emergency_fund_target_idr' => 20000000, 'auto_invest_enabled' => true]);

            return ['profile' => $prof->id];
        },
    ],
    'wms.cycle-counts.apply' => ['roles' => ['admin']],
    'wms.cycle-counts.store' => ['roles' => ['admin', 'hub_operator']],
    'wms.cycle-counts.submit' => ['roles' => ['admin', 'hub_operator']],
    'wms.dock.store' => ['roles' => ['admin', 'hub_operator']],
    'wms.index' => ['roles' => ['admin', 'auditor', 'hub_operator', 'procurement']],
    'wms.pick' => ['roles' => ['admin', 'hub_operator']],
    'wms.putaway' => ['roles' => ['admin', 'hub_operator']],
    'wms.replenishments.compute' => ['roles' => ['admin', 'hub_operator']],
    'wms.transfers.receive' => ['roles' => ['admin', 'hub_operator']],
    'wms.transfers.ship' => ['roles' => ['admin', 'hub_operator']],
    'wms.transfers.store' => ['roles' => ['admin', 'hub_operator']],
    'wms.warehouses.store' => ['roles' => ['admin', 'hub_operator']],
    'wms.waves.release' => ['roles' => ['admin', 'hub_operator']],
    'wms.waves.store' => ['roles' => ['admin', 'hub_operator']],
];
