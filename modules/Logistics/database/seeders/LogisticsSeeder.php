<?php

declare(strict_types=1);

namespace Modules\Logistics\database\seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\AutoDex\Domain\Models\Brand;
use Modules\AutoDex\Domain\Models\Car;
use Modules\Banking\Application\Actions\SetPinAction;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Core\Contracts\AcquiresVehicle;
use Modules\Logistics\Domain\Enums\FleetStatus;
use Modules\Logistics\Domain\Enums\TrailerType;
use Modules\Logistics\Domain\Enums\TruckType;
use Modules\Logistics\Domain\Models\Aircraft;
use Modules\Logistics\Domain\Models\Container;
use Modules\Logistics\Domain\Models\Driver;
use Modules\Logistics\Domain\Models\HubOperator;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Trailer;
use Modules\Logistics\Domain\Models\Truck;
use Modules\Logistics\Domain\Models\Vessel;
use Modules\Logistics\Domain\ValueObjects\Iso6346Validator;

class LogisticsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Jaringan (Lokasi & Lanes)
        $this->call(LogisticsNetworkSeeder::class);

        if (! LedgerAccount::where('code', 'clearing:external:IDR')->exists()) {
            $this->call(BankingSeeder::class);
        }

        $setPin = app(SetPinAction::class);
        $topUp = app(TopUpAction::class);
        $acquirer = app(AcquiresVehicle::class);

        $locations = Location::all()->keyBy('code');
        $hubBdj = $locations['HUB-BDJ'];
        $hubBjb = $locations['HUB-BJB'];
        $hubPky = $locations['HUB-PKY'];
        $hubBpn = $locations['HUB-BPN'];
        $portBdj = $locations['PORT-IDBDJ'];
        $portSub = $locations['PORT-IDSUB'];
        $portTpp = $locations['PORT-IDTPP'];
        $airpBdj = $locations['AIRP-BDJ'];

        // 2. Akun Demo Pengguna
        // 2a. 1 Logistics Admin
        $admin = User::firstOrCreate(
            ['email' => 'logistics.admin@autoserve.test'],
            [
                'name' => 'Logistics Super Admin',
                'phone' => '081299000001',
                'role' => 'logistics_admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $admin->walletAccount('IDR');
        $setPin->execute($admin, '123456');
        $topUp->execute($admin, '50000000', 'seed_topup_logistics_admin');

        // 2b. 3 Dispatchers
        for ($i = 1; $i <= 3; $i++) {
            $disp = User::firstOrCreate(
                ['email' => sprintf('dispatcher%02d@autoserve.test', $i)],
                [
                    'name' => sprintf('Dispatcher Operasional %02d', $i),
                    'phone' => sprintf('08129910000%d', $i),
                    'role' => 'dispatcher',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );
            $disp->walletAccount('IDR');
            $setPin->execute($disp, '123456');
        }

        // 2c. 4 Hub Operators
        $hubOps = [
            ['hub.bdj@autoserve.test', 'Operator Hub Banjarmasin', '081299200001', $hubBdj->id],
            ['hub.bjb@autoserve.test', 'Operator Hub Banjarbaru', '081299200002', $hubBjb->id],
            ['hub.pky@autoserve.test', 'Operator Hub Palangkaraya', '081299200003', $hubPky->id],
            ['hub.bpn@autoserve.test', 'Operator Hub Balikpapan', '081299200004', $hubBpn->id],
        ];
        foreach ($hubOps as [$email, $name, $phone, $hubId]) {
            $op = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'phone' => $phone,
                    'role' => 'hub_operator',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );
            $op->walletAccount('IDR');
            $setPin->execute($op, '123456');

            HubOperator::firstOrCreate(
                ['user_id' => $op->id],
                ['hub_id' => $hubId, 'is_active' => true]
            );
        }

        // 2d. 12 Drivers
        $driverHubs = [
            $hubBdj, $hubBdj, $hubBdj, $hubBdj, $hubBdj, $hubBdj,
            $hubBjb, $hubBjb,
            $hubPky, $hubPky,
            $hubBpn, $hubBpn,
        ];
        for ($i = 1; $i <= 12; $i++) {
            $drvUser = User::firstOrCreate(
                ['email' => sprintf('driver%02d@autoserve.test', $i)],
                [
                    'name' => sprintf('Driver Ekspedisi %02d', $i),
                    'phone' => sprintf('0812993000%02d', $i),
                    'role' => 'driver',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );
            $drvUser->walletAccount('IDR');
            $setPin->execute($drvUser, '123456');

            // 4 driver SIM B2 Umum, 8 driver SIM B1 Umum
            $licClass = $i <= 4 ? 'SIM B2 Umum' : 'SIM B1 Umum';
            $assignedHub = $driverHubs[$i - 1];

            Driver::updateOrCreate(
                ['user_id' => $drvUser->id],
                [
                    'driver_number' => sprintf('DRV-BDJ-%03d', $i),
                    'license_class' => $licClass,
                    'license_expiry' => now()->addYears(2)->addMonths($i),
                    'home_hub_id' => $assignedHub->id,
                    'status' => 'available',
                    'daily_driving_minutes' => ($i % 3) * 60, // variasi realistis 0 - 120 menit
                    'continuous_driving_minutes' => ($i % 2) * 45,
                ]
            );
        }

        // 2e. 5 Shippers (Pelanggan Bisnis B2B)
        $shippers = [
            ['shipper01@autoserve.test', 'PT Borneo Agro Mandiri', '081299400001'],
            ['shipper02@autoserve.test', 'PT Intan Banjar Mineral', '081299400002'],
            ['shipper03@autoserve.test', 'CV Sasirangan Kalsel Lestari', '081299400003'],
            ['shipper04@autoserve.test', 'PT Samudera Perdana Raya', '081299400004'],
            ['shipper05@autoserve.test', 'CV Mitra Kuliner Nusantara', '081299400005'],
        ];
        foreach ($shippers as [$email, $name, $phone]) {
            $shp = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'phone' => $phone,
                    'role' => 'shipper',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );
            $shp->walletAccount('IDR');
            $setPin->execute($shp, '123456');
            $topUp->execute($shp, '50000000', 'seed_topup_shipper_'.$email);
        }

        // 3. Armada Truk: 30 Unit (Terhubung ke Vehicle Passport Core)
        $commercialBrand = Brand::firstOrCreate(
            ['slug' => 'isuzu-commercial'],
            ['name' => 'Isuzu Commercial', 'country' => 'Japan', 'category' => 'other', 'is_active' => true]
        );

        $truckModels = [
            TruckType::CDE->value => Car::firstOrCreate(
                ['slug' => 'isuzu-elf-nlr-cde'],
                ['brand_id' => $commercialBrand->id, 'model' => 'Elf NLR 55 T (CDE)', 'year_start' => 2022, 'body_type' => 'Box Truck', 'fuel_type' => 'diesel', 'price_idr' => 380000000, 'is_active' => true]
            ),
            TruckType::CDD->value => Car::firstOrCreate(
                ['slug' => 'isuzu-elf-nmr-cdd'],
                ['brand_id' => $commercialBrand->id, 'model' => 'Elf NMR 71 T (CDD)', 'year_start' => 2022, 'body_type' => 'Box Truck', 'fuel_type' => 'diesel', 'price_idr' => 460000000, 'is_active' => true]
            ),
            TruckType::FUSO->value => Car::firstOrCreate(
                ['slug' => 'isuzu-giga-fvr-fuso'],
                ['brand_id' => $commercialBrand->id, 'model' => 'Giga FVR 34 P (Fuso Heavy)', 'year_start' => 2023, 'body_type' => 'Wingbox Truck', 'fuel_type' => 'diesel', 'price_idr' => 780000000, 'is_active' => true]
            ),
            TruckType::TRONTON->value => Car::firstOrCreate(
                ['slug' => 'isuzu-giga-gxz-tronton'],
                ['brand_id' => $commercialBrand->id, 'model' => 'Giga GXZ 60 K (Tronton Wingbox)', 'year_start' => 2023, 'body_type' => 'Wingbox Heavy', 'fuel_type' => 'diesel', 'price_idr' => 1100000000, 'is_active' => true]
            ),
            TruckType::TRACTOR_HEAD->value => Car::firstOrCreate(
                ['slug' => 'isuzu-giga-gvr-tractor'],
                ['brand_id' => $commercialBrand->id, 'model' => 'Giga GVR 34 J (Tractor Head)', 'year_start' => 2023, 'body_type' => 'Prime Mover', 'fuel_type' => 'diesel', 'price_idr' => 1250000000, 'is_active' => true]
            ),
            TruckType::CAR_CARRIER->value => Car::firstOrCreate(
                ['slug' => 'isuzu-giga-car-carrier'],
                ['brand_id' => $commercialBrand->id, 'model' => 'Giga FTR Car Carrier 6-Cars', 'year_start' => 2022, 'body_type' => 'Car Carrier', 'fuel_type' => 'diesel', 'price_idr' => 950000000, 'is_active' => true]
            ),
            TruckType::REEFER->value => Car::firstOrCreate(
                ['slug' => 'isuzu-elf-nmr-reefer'],
                ['brand_id' => $commercialBrand->id, 'model' => 'Elf NMR 71 Cold Chain Reefer', 'year_start' => 2023, 'body_type' => 'Reefer Truck', 'fuel_type' => 'diesel', 'price_idr' => 580000000, 'is_active' => true]
            ),
        ];

        // 30 Truk: 6 CDE, 8 CDD, 6 Fuso, 4 Tronton, 3 Tractor Head, 1 Car Carrier, 2 Reefer
        $truckPlan = [
            TruckType::CDE, TruckType::CDE, TruckType::CDE, TruckType::CDE, TruckType::CDE, TruckType::CDE,
            TruckType::CDD, TruckType::CDD, TruckType::CDD, TruckType::CDD, TruckType::CDD, TruckType::CDD, TruckType::CDD, TruckType::CDD,
            TruckType::FUSO, TruckType::FUSO, TruckType::FUSO, TruckType::FUSO, TruckType::FUSO, TruckType::FUSO,
            TruckType::TRONTON, TruckType::TRONTON, TruckType::TRONTON, TruckType::TRONTON,
            TruckType::TRACTOR_HEAD, TruckType::TRACTOR_HEAD, TruckType::TRACTOR_HEAD,
            TruckType::CAR_CARRIER,
            TruckType::REEFER, TruckType::REEFER,
        ];

        $hubCycle = [$hubBdj, $hubBjb, $hubPky, $hubBpn];

        foreach ($truckPlan as $idx => $tType) {
            $num = $idx + 1;
            $plateNumber = sprintf('DA %04d SX', 8000 + $num);
            $carModel = $truckModels[$tType->value];

            $vehicle = $acquirer->handle(
                user: $admin,
                car: $carModel,
                plateNumber: $plateNumber,
                color: 'White/Blue Sari Ranah',
                vin: sprintf('MHISZLGX2026%06d', $num),
                odometerKm: $num * 5000
            );

            Truck::updateOrCreate(
                ['plate_number' => $plateNumber],
                [
                    'vehicle_id' => $vehicle->id,
                    'type' => $tType,
                    'payload_kg' => $tType->defaultPayloadKg(),
                    'volume_dm3' => $tType->defaultVolumeDm3(),
                    'required_license' => $tType->requiredLicense(),
                    'service_interval_m' => 10_000_000,
                    'odometer_m' => $num * 5_000_000,
                    'status' => FleetStatus::AVAILABLE,
                    'current_location_id' => $hubCycle[$idx % 4]->id,
                ]
            );
        }

        // 4. Armada Trailer: 8 Unit
        $trailerDefs = [
            ['TRL-FB-20-001', TrailerType::FLATBED_20, 24_000, $portBdj->id],
            ['TRL-FB-20-002', TrailerType::FLATBED_20, 24_000, $portSub->id],
            ['TRL-FB-40-001', TrailerType::FLATBED_40, 36_000, $portBdj->id],
            ['TRL-FB-40-002', TrailerType::FLATBED_40, 36_000, $portTpp->id],
            ['TRL-SK-20-001', TrailerType::SKELETAL_20, 30_000, $portBdj->id],
            ['TRL-SK-20-002', TrailerType::SKELETAL_20, 30_000, $portSub->id],
            ['TRL-SK-40-001', TrailerType::SKELETAL_40, 34_000, $portBdj->id],
            ['TRL-RF-40-001', TrailerType::REEFER, 32_000, $portBdj->id],
        ];
        foreach ($trailerDefs as [$code, $type, $payload, $locId]) {
            Trailer::updateOrCreate(
                ['code' => $code],
                [
                    'type' => $type,
                    'payload_kg' => $payload,
                    'status' => FleetStatus::AVAILABLE,
                    'current_location_id' => $locId,
                ]
            );
        }

        // 5. Armada Kapal Kargo: 4 Unit (Check Digit IMO Valid)
        $vessels = [
            ['9074729', 'KM Sari Ranah Barito', 'ID', 650, 80, 12_500, $portBdj->id],
            ['9241061', 'KM Sari Ranah Martapura', 'ID', 720, 100, 14_000, $portSub->id],
            ['9315800', 'KM Sari Ranah Mahakam', 'ID', 850, 120, 16_500, $portTpp->id],
            ['9181106', 'KM Sari Ranah Kahayan', 'ID', 500, 60, 10_000, $portBdj->id],
        ];
        foreach ($vessels as [$imo, $name, $flag, $teu, $reefer, $dwt, $locId]) {
            Vessel::updateOrCreate(
                ['imo_number' => $imo],
                [
                    'name' => $name,
                    'flag' => $flag,
                    'teu_capacity' => $teu,
                    'reefer_plugs' => $reefer,
                    'dwt_tonnes' => $dwt,
                    'status' => FleetStatus::AVAILABLE,
                    'current_location_id' => $locId,
                ]
            );
        }

        // 6. Armada Pesawat Freighter: 2 Unit
        $aircrafts = [
            ['PK-SRA', 'B737-800BCF (Freighter)', 23_900, 12, $airpBdj->id],
            ['PK-SRB', 'A321P2F (Freighter)', 27_000, 14, $locations['AIRP-CGK']->id],
        ];
        foreach ($aircrafts as [$reg, $type, $payload, $uldPos, $locId]) {
            Aircraft::updateOrCreate(
                ['registration' => $reg],
                [
                    'type' => $type,
                    'max_payload_kg' => $payload,
                    'uld_positions' => $uldPos,
                    'status' => FleetStatus::AVAILABLE,
                    'current_location_id' => $locId,
                ]
            );
        }

        // 7. Armada Kontainer: 300 Unit (Validasi ISO 6346)
        $containerLocations = [
            $locations['DEP-BDJ']->id,
            $locations['PORT-IDBDJ']->id,
            $locations['CFS-BDJ']->id,
            $locations['DEP-SUB']->id,
            $locations['PORT-IDSUB']->id,
            $locations['DEP-TPP']->id,
        ];

        $sizeTypes = ['22G1', '42G1', '45G1', '22R1', '45R1'];
        $tareSpecs = [
            '22G1' => [2_200, 30_480],
            '42G1' => [3_750, 32_500],
            '45G1' => [3_900, 32_500],
            '22R1' => [3_050, 30_480],
            '45R1' => [4_800, 34_000],
        ];

        for ($c = 1; $c <= 300; $c++) {
            $serial = 100000 + $c;
            $cntNumber = Iso6346Validator::generate('SRX', 'U', $serial);
            $st = $sizeTypes[$c % 5];
            [$tare, $gross] = $tareSpecs[$st];

            Container::updateOrCreate(
                ['container_number' => $cntNumber],
                [
                    'size_type' => $st,
                    'tare_kg' => $tare,
                    'max_gross_kg' => $gross,
                    'status' => FleetStatus::AVAILABLE,
                    'current_location_id' => $containerLocations[$c % count($containerLocations)],
                ]
            );
        }
    }
}
