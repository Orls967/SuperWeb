<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Car;
use Illuminate\Database\Seeder;

class AutoDexSeeder extends Seeder
{
    public function run(): void
    {
        // ============================================================
        // JDM (Japanese Domestic Market)
        // ============================================================
        $toyota = Brand::create(['name' => 'Toyota', 'country' => 'Japan', 'category' => 'jdm', 'description' => 'Produsen mobil terbesar di dunia asal Jepang']);
        $honda = Brand::create(['name' => 'Honda', 'country' => 'Japan', 'category' => 'jdm', 'description' => 'Inovator mesin VTEC dan mobil efisien']);
        $nissan = Brand::create(['name' => 'Nissan', 'country' => 'Japan', 'category' => 'jdm', 'description' => 'Pembuat GT-R dan pioneer EV Leaf']);
        $mazda = Brand::create(['name' => 'Mazda', 'country' => 'Japan', 'category' => 'jdm', 'description' => 'Kodo design dan mesin SkyActiv']);
        $subaru = Brand::create(['name' => 'Subaru', 'country' => 'Japan', 'category' => 'jdm', 'description' => 'Spesialis boxer engine dan AWD']);
        $mitsubishi = Brand::create(['name' => 'Mitsubishi', 'country' => 'Japan', 'category' => 'jdm', 'description' => 'Legenda rally Lancer Evolution']);
        $suzuki = Brand::create(['name' => 'Suzuki', 'country' => 'Japan', 'category' => 'jdm', 'description' => 'Raja city car dan off-road Jimny']);
        $daihatsu = Brand::create(['name' => 'Daihatsu', 'country' => 'Japan', 'category' => 'jdm', 'description' => 'Spesialis kendaraan kompak untuk pasar Asia']);
        $lexus = Brand::create(['name' => 'Lexus', 'country' => 'Japan', 'category' => 'jdm', 'description' => 'Divisi luxury Toyota']);

        // Toyota cars
        $this->createCars($toyota->id, [
            ['model' => 'GR Supra', 'year_start' => 2019, 'body_type' => 'Coupe', 'fuel_type' => 'gasoline', 'engine' => '3.0L Twin-Scroll Turbo I6', 'horsepower' => 382, 'torque_nm' => 500, 'transmission' => 'AT 8-Speed', 'drivetrain' => 'RWD', 'top_speed_kmh' => 250, 'zero_to_100' => 4.1, 'price_idr' => 2_100_000_000, 'description' => 'Legenda sports car Toyota yang terlahir kembali dengan kolaborasi BMW'],
            ['model' => 'GR86', 'year_start' => 2021, 'body_type' => 'Coupe', 'fuel_type' => 'gasoline', 'engine' => '2.4L Boxer NA', 'horsepower' => 228, 'torque_nm' => 250, 'transmission' => 'MT 6-Speed / AT 6-Speed', 'drivetrain' => 'RWD', 'top_speed_kmh' => 226, 'zero_to_100' => 6.3, 'price_idr' => 820_000_000, 'description' => 'Pure driving pleasure, lightweight RWD coupe'],
            ['model' => 'Camry', 'year_start' => 2024, 'body_type' => 'Sedan', 'fuel_type' => 'hybrid', 'engine' => '2.5L Dynamic Force + HEV', 'horsepower' => 225, 'torque_nm' => 221, 'transmission' => 'eCVT', 'drivetrain' => 'FWD', 'top_speed_kmh' => 210, 'zero_to_100' => 7.2, 'price_idr' => 790_000_000, 'description' => 'Best-selling sedan kelas menengah kini full hybrid'],
            ['model' => 'Avanza', 'year_start' => 2022, 'body_type' => 'MPV', 'fuel_type' => 'gasoline', 'engine' => '1.5L Dual VVT-i', 'horsepower' => 106, 'torque_nm' => 138, 'transmission' => 'CVT', 'drivetrain' => 'FWD', 'top_speed_kmh' => 175, 'zero_to_100' => 12.5, 'price_idr' => 240_000_000, 'description' => 'Raja MPV Indonesia, andalan keluarga'],
            ['model' => 'Fortuner', 'year_start' => 2020, 'body_type' => 'SUV', 'fuel_type' => 'diesel', 'engine' => '2.8L D-4D Turbo Diesel', 'horsepower' => 204, 'torque_nm' => 500, 'transmission' => 'AT 6-Speed', 'drivetrain' => '4WD', 'top_speed_kmh' => 195, 'zero_to_100' => 9.8, 'price_idr' => 640_000_000, 'description' => 'SUV tangguh untuk segala medan'],
            ['model' => 'Land Cruiser 300', 'year_start' => 2021, 'body_type' => 'SUV', 'fuel_type' => 'diesel', 'engine' => '3.3L V6 Twin-Turbo Diesel', 'horsepower' => 309, 'torque_nm' => 700, 'transmission' => 'AT 10-Speed', 'drivetrain' => '4WD', 'top_speed_kmh' => 210, 'zero_to_100' => 6.7, 'price_idr' => 2_900_000_000, 'description' => 'Ikon SUV premium off-road legendaris'],
            ['model' => 'Yaris Cross', 'year_start' => 2023, 'body_type' => 'SUV', 'fuel_type' => 'hybrid', 'engine' => '1.5L Hybrid', 'horsepower' => 116, 'torque_nm' => 185, 'transmission' => 'eCVT', 'drivetrain' => 'FWD', 'top_speed_kmh' => 170, 'zero_to_100' => 11.2, 'price_idr' => 390_000_000, 'description' => 'Compact SUV hybrid stylish'],
        ]);

        // Honda cars
        $this->createCars($honda->id, [
            ['model' => 'Civic Type R', 'year_start' => 2022, 'body_type' => 'Hatchback', 'fuel_type' => 'gasoline', 'engine' => '2.0L VTEC Turbo', 'horsepower' => 329, 'torque_nm' => 420, 'transmission' => 'MT 6-Speed', 'drivetrain' => 'FWD', 'top_speed_kmh' => 275, 'zero_to_100' => 5.4, 'price_idr' => 1_399_000_000, 'description' => 'Hot hatch terkencang dari Honda, Nürburgring record holder'],
            ['model' => 'Civic RS', 'year_start' => 2022, 'body_type' => 'Sedan', 'fuel_type' => 'gasoline', 'engine' => '1.5L VTEC Turbo', 'horsepower' => 178, 'torque_nm' => 240, 'transmission' => 'CVT', 'drivetrain' => 'FWD', 'top_speed_kmh' => 210, 'zero_to_100' => 8.2, 'price_idr' => 570_000_000, 'description' => 'Sedan sporty generasi ke-11 dengan desain agresif'],
            ['model' => 'HR-V', 'year_start' => 2022, 'body_type' => 'SUV', 'fuel_type' => 'gasoline', 'engine' => '1.5L i-VTEC', 'horsepower' => 121, 'torque_nm' => 145, 'transmission' => 'CVT', 'drivetrain' => 'FWD', 'top_speed_kmh' => 180, 'zero_to_100' => 11.5, 'price_idr' => 395_000_000, 'description' => 'Compact SUV dengan kabin paling lega di kelasnya'],
            ['model' => 'CR-V', 'year_start' => 2023, 'body_type' => 'SUV', 'fuel_type' => 'hybrid', 'engine' => '2.0L i-MMD Hybrid', 'horsepower' => 204, 'torque_nm' => 335, 'transmission' => 'eCVT', 'drivetrain' => 'AWD', 'top_speed_kmh' => 195, 'zero_to_100' => 8.0, 'price_idr' => 750_000_000, 'description' => 'SUV medium hybrid dengan fitur Honda Sensing lengkap'],
            ['model' => 'Brio', 'year_start' => 2023, 'body_type' => 'Hatchback', 'fuel_type' => 'gasoline', 'engine' => '1.2L i-VTEC', 'horsepower' => 90, 'torque_nm' => 110, 'transmission' => 'CVT', 'drivetrain' => 'FWD', 'top_speed_kmh' => 165, 'zero_to_100' => 13.0, 'price_idr' => 165_000_000, 'description' => 'City car terlaris di Indonesia'],
            ['model' => 'WR-V', 'year_start' => 2023, 'body_type' => 'SUV', 'fuel_type' => 'gasoline', 'engine' => '1.5L i-VTEC', 'horsepower' => 121, 'torque_nm' => 145, 'transmission' => 'CVT', 'drivetrain' => 'FWD', 'top_speed_kmh' => 175, 'zero_to_100' => 11.0, 'price_idr' => 285_000_000, 'description' => 'Small SUV entry level yang gagah'],
        ]);

        // Nissan cars
        $this->createCars($nissan->id, [
            ['model' => 'GT-R Nismo', 'year_start' => 2020, 'body_type' => 'Coupe', 'fuel_type' => 'gasoline', 'engine' => '3.8L Twin-Turbo V6 VR38DETT', 'horsepower' => 600, 'torque_nm' => 652, 'transmission' => 'DCT 6-Speed', 'drivetrain' => 'AWD', 'top_speed_kmh' => 315, 'zero_to_100' => 2.5, 'price_idr' => 5_500_000_000, 'description' => 'Godzilla - supercar killer Jepang'],
            ['model' => 'Z', 'year_start' => 2023, 'body_type' => 'Coupe', 'fuel_type' => 'gasoline', 'engine' => '3.0L Twin-Turbo V6', 'horsepower' => 400, 'torque_nm' => 475, 'transmission' => 'AT 9-Speed / MT 6-Speed', 'drivetrain' => 'RWD', 'top_speed_kmh' => 250, 'zero_to_100' => 4.0, 'price_idr' => 1_800_000_000, 'description' => 'Penerus legenda Fairlady Z'],
            ['model' => 'Kicks e-Power', 'year_start' => 2022, 'body_type' => 'SUV', 'fuel_type' => 'hybrid', 'engine' => '1.2L e-POWER', 'horsepower' => 129, 'torque_nm' => 260, 'transmission' => 'Single Speed', 'drivetrain' => 'FWD', 'top_speed_kmh' => 167, 'zero_to_100' => 10.2, 'price_idr' => 360_000_000, 'description' => 'SUV kompak dengan teknologi e-Power'],
            ['model' => 'Leaf', 'year_start' => 2019, 'body_type' => 'Hatchback', 'fuel_type' => 'electric', 'engine' => 'Single Motor', 'horsepower' => 214, 'torque_nm' => 340, 'transmission' => 'Single Speed', 'drivetrain' => 'FWD', 'top_speed_kmh' => 157, 'zero_to_100' => 7.4, 'range_km' => 385, 'battery_kwh' => 62, 'price_idr' => 740_000_000, 'description' => 'Pioneer EV massal paling laris di dunia'],
        ]);

        // Mazda cars
        $this->createCars($mazda->id, [
            ['model' => 'MX-5 Miata', 'year_start' => 2019, 'body_type' => 'Roadster', 'fuel_type' => 'gasoline', 'engine' => '2.0L SkyActiv-G', 'horsepower' => 184, 'torque_nm' => 205, 'transmission' => 'MT 6-Speed', 'drivetrain' => 'RWD', 'top_speed_kmh' => 219, 'zero_to_100' => 6.5, 'price_idr' => 850_000_000, 'description' => 'Roadster terlaris sepanjang masa, pure driving fun'],
            ['model' => 'CX-5', 'year_start' => 2022, 'body_type' => 'SUV', 'fuel_type' => 'gasoline', 'engine' => '2.5L SkyActiv-G', 'horsepower' => 187, 'torque_nm' => 252, 'transmission' => 'AT 6-Speed', 'drivetrain' => 'AWD', 'top_speed_kmh' => 195, 'zero_to_100' => 8.8, 'price_idr' => 580_000_000, 'description' => 'SUV premium dengan Kodo design yang elegan'],
            ['model' => 'CX-60', 'year_start' => 2023, 'body_type' => 'SUV', 'fuel_type' => 'diesel', 'engine' => '3.3L SkyActiv-D Turbo Diesel I6', 'horsepower' => 254, 'torque_nm' => 550, 'transmission' => 'AT 8-Speed', 'drivetrain' => 'RWD', 'top_speed_kmh' => 219, 'zero_to_100' => 7.3, 'price_idr' => 900_000_000, 'description' => 'SUV mewah pertama Mazda dengan platform longitudinal'],
            ['model' => '3', 'year_start' => 2019, 'body_type' => 'Hatchback', 'fuel_type' => 'gasoline', 'engine' => '2.0L SkyActiv-G', 'horsepower' => 153, 'torque_nm' => 200, 'transmission' => 'AT 6-Speed', 'drivetrain' => 'FWD', 'top_speed_kmh' => 210, 'zero_to_100' => 8.3, 'price_idr' => 520_000_000, 'description' => 'Hatchback premium dengan desain paling cantik di kelasnya'],
        ]);

        // Subaru
        $this->createCars($subaru->id, [
            ['model' => 'WRX', 'year_start' => 2022, 'body_type' => 'Sedan', 'fuel_type' => 'gasoline', 'engine' => '2.4L Boxer Turbo', 'horsepower' => 271, 'torque_nm' => 350, 'transmission' => 'CVT / MT 6-Speed', 'drivetrain' => 'AWD', 'top_speed_kmh' => 240, 'zero_to_100' => 5.5, 'price_idr' => 920_000_000, 'description' => 'Rally-bred AWD sedan turbo boxer'],
            ['model' => 'BRZ', 'year_start' => 2022, 'body_type' => 'Coupe', 'fuel_type' => 'gasoline', 'engine' => '2.4L Boxer NA', 'horsepower' => 228, 'torque_nm' => 249, 'transmission' => 'MT 6-Speed / AT 6-Speed', 'drivetrain' => 'RWD', 'top_speed_kmh' => 226, 'zero_to_100' => 6.3, 'price_idr' => 870_000_000, 'description' => 'Kembaran GR86, lightweight coupe sejati'],
        ]);

        // Mitsubishi
        $this->createCars($mitsubishi->id, [
            ['model' => 'Xpander', 'year_start' => 2022, 'body_type' => 'MPV', 'fuel_type' => 'gasoline', 'engine' => '1.5L MIVEC', 'horsepower' => 105, 'torque_nm' => 141, 'transmission' => 'CVT', 'drivetrain' => 'FWD', 'top_speed_kmh' => 175, 'zero_to_100' => 13.0, 'price_idr' => 270_000_000, 'description' => 'MPV crossover terlaris, rival Avanza'],
            ['model' => 'Pajero Sport', 'year_start' => 2021, 'body_type' => 'SUV', 'fuel_type' => 'diesel', 'engine' => '2.4L DI-D Turbo Diesel', 'horsepower' => 181, 'torque_nm' => 430, 'transmission' => 'AT 8-Speed', 'drivetrain' => '4WD', 'top_speed_kmh' => 190, 'zero_to_100' => 10.1, 'price_idr' => 580_000_000, 'description' => 'SUV tangguh dengan Super Select 4WD-II'],
            ['model' => 'Outlander PHEV', 'year_start' => 2023, 'body_type' => 'SUV', 'fuel_type' => 'hybrid', 'engine' => '2.4L PHEV AWD', 'horsepower' => 248, 'torque_nm' => 450, 'transmission' => 'Single Speed', 'drivetrain' => 'AWD', 'top_speed_kmh' => 170, 'zero_to_100' => 7.9, 'range_km' => 87, 'battery_kwh' => 20, 'price_idr' => 1_200_000_000, 'description' => 'SUV plug-in hybrid terlaris di dunia'],
        ]);

        // Suzuki
        $this->createCars($suzuki->id, [
            ['model' => 'Jimny 5-Door', 'year_start' => 2024, 'body_type' => 'SUV', 'fuel_type' => 'gasoline', 'engine' => '1.5L K15B', 'horsepower' => 105, 'torque_nm' => 138, 'transmission' => 'AT 4-Speed', 'drivetrain' => '4WD', 'top_speed_kmh' => 145, 'zero_to_100' => 14.0, 'price_idr' => 420_000_000, 'description' => 'Ikon off-road mini yang sangat dicari'],
            ['model' => 'Swift Sport', 'year_start' => 2020, 'body_type' => 'Hatchback', 'fuel_type' => 'gasoline', 'engine' => '1.4L BoosterJet Turbo', 'horsepower' => 140, 'torque_nm' => 230, 'transmission' => 'MT 6-Speed', 'drivetrain' => 'FWD', 'top_speed_kmh' => 210, 'zero_to_100' => 8.1, 'price_idr' => 310_000_000, 'description' => 'Hot hatch ringan dan lincah'],
            ['model' => 'Ertiga Hybrid', 'year_start' => 2023, 'body_type' => 'MPV', 'fuel_type' => 'hybrid', 'engine' => '1.5L Smart Hybrid', 'horsepower' => 105, 'torque_nm' => 138, 'transmission' => 'AT 6-Speed', 'drivetrain' => 'FWD', 'top_speed_kmh' => 170, 'zero_to_100' => 12.0, 'price_idr' => 280_000_000, 'description' => 'MPV hybrid terjangkau untuk keluarga'],
        ]);

        // Daihatsu
        $this->createCars($daihatsu->id, [
            ['model' => 'Rocky', 'year_start' => 2022, 'body_type' => 'SUV', 'fuel_type' => 'gasoline', 'engine' => '1.0L Turbo', 'horsepower' => 98, 'torque_nm' => 140, 'transmission' => 'CVT', 'drivetrain' => 'FWD', 'top_speed_kmh' => 170, 'zero_to_100' => 12.5, 'price_idr' => 220_000_000, 'description' => 'Compact SUV turbo terjangkau'],
            ['model' => 'Terios', 'year_start' => 2021, 'body_type' => 'SUV', 'fuel_type' => 'gasoline', 'engine' => '1.5L Dual VVT-i', 'horsepower' => 104, 'torque_nm' => 136, 'transmission' => 'AT 4-Speed', 'drivetrain' => 'FWD', 'top_speed_kmh' => 165, 'zero_to_100' => 13.2, 'price_idr' => 230_000_000, 'description' => 'SUV keluarga 7-seater entry level'],
        ]);

        // Lexus
        $this->createCars($lexus->id, [
            ['model' => 'LC 500', 'year_start' => 2021, 'body_type' => 'Coupe', 'fuel_type' => 'gasoline', 'engine' => '5.0L V8 NA', 'horsepower' => 471, 'torque_nm' => 540, 'transmission' => 'AT 10-Speed', 'drivetrain' => 'RWD', 'top_speed_kmh' => 270, 'zero_to_100' => 4.4, 'price_idr' => 3_200_000_000, 'description' => 'Grand tourer mewah dengan suara V8 NA menggelegar'],
            ['model' => 'RX 500h', 'year_start' => 2023, 'body_type' => 'SUV', 'fuel_type' => 'hybrid', 'engine' => '2.4L Turbo Hybrid', 'horsepower' => 371, 'torque_nm' => 460, 'transmission' => 'AT 6-Speed', 'drivetrain' => 'AWD', 'top_speed_kmh' => 220, 'zero_to_100' => 6.2, 'price_idr' => 1_850_000_000, 'description' => 'SUV luxury hybrid performance terbaru'],
        ]);

        // ============================================================
        // USDM (US Domestic Market)
        // ============================================================
        $ford = Brand::create(['name' => 'Ford', 'country' => 'USA', 'category' => 'usdm', 'description' => 'Raksasa otomotif Amerika, pembuat Mustang']);
        $chevrolet = Brand::create(['name' => 'Chevrolet', 'country' => 'USA', 'category' => 'usdm', 'description' => 'Ikon Amerika dengan Corvette dan Camaro']);
        $dodge = Brand::create(['name' => 'Dodge', 'country' => 'USA', 'category' => 'usdm', 'description' => 'Muscle car bertenaga besar']);
        $tesla = Brand::create(['name' => 'Tesla', 'country' => 'USA', 'category' => 'ev', 'description' => 'Pelopor revolusi mobil listrik global']);
        $jeep = Brand::create(['name' => 'Jeep', 'country' => 'USA', 'category' => 'usdm', 'description' => 'Legenda off-road sejak Perang Dunia II']);

        // Ford
        $this->createCars($ford->id, [
            ['model' => 'Mustang GT', 'year_start' => 2024, 'body_type' => 'Coupe', 'fuel_type' => 'gasoline', 'engine' => '5.0L Coyote V8', 'horsepower' => 480, 'torque_nm' => 569, 'transmission' => 'MT 6-Speed / AT 10-Speed', 'drivetrain' => 'RWD', 'top_speed_kmh' => 250, 'zero_to_100' => 4.2, 'price_idr' => 1_600_000_000, 'description' => 'Pony car Amerika yang ikonik, V8 NA terakhir'],
            ['model' => 'Ranger Raptor', 'year_start' => 2023, 'body_type' => 'Pickup', 'fuel_type' => 'diesel', 'engine' => '3.0L V6 EcoBoost', 'horsepower' => 392, 'torque_nm' => 583, 'transmission' => 'AT 10-Speed', 'drivetrain' => '4WD', 'top_speed_kmh' => 180, 'zero_to_100' => 5.8, 'price_idr' => 960_000_000, 'description' => 'Pickup performa tinggi untuk off-road ekstrem'],
            ['model' => 'Mustang Mach-E', 'year_start' => 2021, 'body_type' => 'SUV', 'fuel_type' => 'electric', 'engine' => 'Dual Motor AWD', 'horsepower' => 480, 'torque_nm' => 860, 'transmission' => 'Single Speed', 'drivetrain' => 'AWD', 'top_speed_kmh' => 200, 'zero_to_100' => 3.5, 'range_km' => 502, 'battery_kwh' => 91, 'price_idr' => 1_500_000_000, 'description' => 'SUV listrik performa tinggi berjiwa Mustang'],
        ]);

        // Chevrolet
        $this->createCars($chevrolet->id, [
            ['model' => 'Corvette Stingray', 'year_start' => 2020, 'body_type' => 'Coupe', 'fuel_type' => 'gasoline', 'engine' => '6.2L LT2 V8', 'horsepower' => 495, 'torque_nm' => 637, 'transmission' => 'DCT 8-Speed', 'drivetrain' => 'RWD', 'top_speed_kmh' => 312, 'zero_to_100' => 2.9, 'price_idr' => 2_500_000_000, 'description' => 'Mid-engine American supercar dengan harga rasional'],
            ['model' => 'Camaro ZL1', 'year_start' => 2019, 'year_end' => 2024, 'body_type' => 'Coupe', 'fuel_type' => 'gasoline', 'engine' => '6.2L Supercharged V8', 'horsepower' => 650, 'torque_nm' => 881, 'transmission' => 'MT 6-Speed / AT 10-Speed', 'drivetrain' => 'RWD', 'top_speed_kmh' => 318, 'zero_to_100' => 3.5, 'price_idr' => 2_200_000_000, 'description' => 'Muscle car supercharged ganas'],
        ]);

        // Dodge
        $this->createCars($dodge->id, [
            ['model' => 'Challenger SRT Hellcat', 'year_start' => 2019, 'year_end' => 2023, 'body_type' => 'Coupe', 'fuel_type' => 'gasoline', 'engine' => '6.2L Supercharged HEMI V8', 'horsepower' => 717, 'torque_nm' => 881, 'transmission' => 'AT 8-Speed', 'drivetrain' => 'RWD', 'top_speed_kmh' => 328, 'zero_to_100' => 3.4, 'price_idr' => 3_500_000_000, 'description' => 'Muscle car paling gahar dengan suara HEMI yang menggelegar'],
            ['model' => 'Charger Daytona EV', 'year_start' => 2024, 'body_type' => 'Sedan', 'fuel_type' => 'electric', 'engine' => 'Banshee Dual Motor', 'horsepower' => 670, 'torque_nm' => 868, 'transmission' => 'Single Speed', 'drivetrain' => 'AWD', 'top_speed_kmh' => 260, 'zero_to_100' => 3.3, 'range_km' => 510, 'battery_kwh' => 100, 'price_idr' => 2_800_000_000, 'description' => 'Muscle car listrik pertama dengan exhaust sintetis'],
        ]);

        // Tesla
        $this->createCars($tesla->id, [
            ['model' => 'Model 3 Performance', 'year_start' => 2024, 'body_type' => 'Sedan', 'fuel_type' => 'electric', 'engine' => 'Dual Motor AWD', 'horsepower' => 510, 'torque_nm' => 550, 'transmission' => 'Single Speed', 'drivetrain' => 'AWD', 'top_speed_kmh' => 262, 'zero_to_100' => 3.1, 'range_km' => 528, 'battery_kwh' => 79, 'price_idr' => 1_200_000_000, 'description' => 'Sedan listrik performa tinggi terlaris di dunia'],
            ['model' => 'Model Y Long Range', 'year_start' => 2023, 'body_type' => 'SUV', 'fuel_type' => 'electric', 'engine' => 'Dual Motor AWD', 'horsepower' => 384, 'torque_nm' => 493, 'transmission' => 'Single Speed', 'drivetrain' => 'AWD', 'top_speed_kmh' => 217, 'zero_to_100' => 5.0, 'range_km' => 533, 'battery_kwh' => 75, 'price_idr' => 850_000_000, 'description' => 'SUV listrik terlaris di dunia 2023-2024'],
            ['model' => 'Model S Plaid', 'year_start' => 2021, 'body_type' => 'Sedan', 'fuel_type' => 'electric', 'engine' => 'Tri Motor AWD', 'horsepower' => 1020, 'torque_nm' => 1420, 'transmission' => 'Single Speed', 'drivetrain' => 'AWD', 'top_speed_kmh' => 322, 'zero_to_100' => 1.99, 'range_km' => 600, 'battery_kwh' => 100, 'price_idr' => 3_500_000_000, 'description' => 'Sedan produksi tercepat di dunia, 0-100 di bawah 2 detik'],
            ['model' => 'Cybertruck', 'year_start' => 2024, 'body_type' => 'Pickup', 'fuel_type' => 'electric', 'engine' => 'Tri Motor AWD', 'horsepower' => 845, 'torque_nm' => 1138, 'transmission' => 'Single Speed', 'drivetrain' => 'AWD', 'top_speed_kmh' => 209, 'zero_to_100' => 2.6, 'range_km' => 547, 'battery_kwh' => 123, 'price_idr' => 2_000_000_000, 'description' => 'Pickup futuristik dengan exoskeleton stainless steel'],
        ]);

        // Jeep
        $this->createCars($jeep->id, [
            ['model' => 'Wrangler Rubicon', 'year_start' => 2020, 'body_type' => 'SUV', 'fuel_type' => 'gasoline', 'engine' => '3.6L Pentastar V6', 'horsepower' => 285, 'torque_nm' => 353, 'transmission' => 'AT 8-Speed', 'drivetrain' => '4WD', 'top_speed_kmh' => 175, 'zero_to_100' => 7.5, 'price_idr' => 1_500_000_000, 'description' => 'Off-roader sejati dengan kemampuan mendaki batu'],
        ]);

        // ============================================================
        // EURO (European Market)
        // ============================================================
        $bmw = Brand::create(['name' => 'BMW', 'country' => 'Germany', 'category' => 'euro', 'description' => 'The Ultimate Driving Machine']);
        $mercedes = Brand::create(['name' => 'Mercedes-Benz', 'country' => 'Germany', 'category' => 'euro', 'description' => 'Das Beste oder Nichts']);
        $porsche = Brand::create(['name' => 'Porsche', 'country' => 'Germany', 'category' => 'euro', 'description' => 'Pembuat 911 legendaris']);
        $vw = Brand::create(['name' => 'Volkswagen', 'country' => 'Germany', 'category' => 'euro', 'description' => 'Mobil rakyat Jerman yang go global']);
        $volvo = Brand::create(['name' => 'Volvo', 'country' => 'Sweden', 'category' => 'euro', 'description' => 'Pionir keselamatan otomotif dari Swedia']);

        // BMW
        $this->createCars($bmw->id, [
            ['model' => 'M3 Competition', 'year_start' => 2021, 'body_type' => 'Sedan', 'fuel_type' => 'gasoline', 'engine' => '3.0L S58 Twin-Turbo I6', 'horsepower' => 510, 'torque_nm' => 650, 'transmission' => 'AT 8-Speed', 'drivetrain' => 'RWD', 'top_speed_kmh' => 290, 'zero_to_100' => 3.4, 'price_idr' => 2_300_000_000, 'description' => 'Sport sedan definitif dari Bayern'],
            ['model' => 'M4 CSL', 'year_start' => 2022, 'body_type' => 'Coupe', 'fuel_type' => 'gasoline', 'engine' => '3.0L S58 Twin-Turbo I6', 'horsepower' => 550, 'torque_nm' => 650, 'transmission' => 'AT 8-Speed', 'drivetrain' => 'RWD', 'top_speed_kmh' => 307, 'zero_to_100' => 3.2, 'price_idr' => 3_500_000_000, 'description' => 'Competition Sport Lightweight, track-focused M4'],
            ['model' => 'i4 M50', 'year_start' => 2022, 'body_type' => 'Sedan', 'fuel_type' => 'electric', 'engine' => 'Dual Motor AWD', 'horsepower' => 544, 'torque_nm' => 795, 'transmission' => 'Single Speed', 'drivetrain' => 'AWD', 'top_speed_kmh' => 225, 'zero_to_100' => 3.9, 'range_km' => 520, 'battery_kwh' => 84, 'price_idr' => 1_800_000_000, 'description' => 'Gran Coupe listrik pertama dari BMW M'],
            ['model' => '320i', 'year_start' => 2023, 'body_type' => 'Sedan', 'fuel_type' => 'gasoline', 'engine' => '2.0L TwinPower Turbo I4', 'horsepower' => 184, 'torque_nm' => 300, 'transmission' => 'AT 8-Speed', 'drivetrain' => 'RWD', 'top_speed_kmh' => 235, 'zero_to_100' => 7.1, 'price_idr' => 900_000_000, 'description' => 'Entry sedan premium paling fun to drive'],
        ]);

        // Mercedes-Benz
        $this->createCars($mercedes->id, [
            ['model' => 'AMG GT 63 S', 'year_start' => 2023, 'body_type' => 'Coupe', 'fuel_type' => 'gasoline', 'engine' => '4.0L V8 Biturbo + EQ Boost', 'horsepower' => 639, 'torque_nm' => 900, 'transmission' => 'AMG SPEEDSHIFT MCT 9G', 'drivetrain' => 'AWD', 'top_speed_kmh' => 315, 'zero_to_100' => 3.2, 'price_idr' => 5_500_000_000, 'description' => 'Super sport dari Affalterbach'],
            ['model' => 'C 300 AMG Line', 'year_start' => 2022, 'body_type' => 'Sedan', 'fuel_type' => 'gasoline', 'engine' => '2.0L Turbo + Mild Hybrid', 'horsepower' => 258, 'torque_nm' => 400, 'transmission' => 'AT 9G-TRONIC', 'drivetrain' => 'RWD', 'top_speed_kmh' => 250, 'zero_to_100' => 5.9, 'price_idr' => 1_050_000_000, 'description' => 'Baby S-Class dengan teknologi flagship'],
            ['model' => 'EQS 580', 'year_start' => 2022, 'body_type' => 'Sedan', 'fuel_type' => 'electric', 'engine' => 'Dual Motor AWD', 'horsepower' => 523, 'torque_nm' => 855, 'transmission' => 'Single Speed', 'drivetrain' => 'AWD', 'top_speed_kmh' => 210, 'zero_to_100' => 4.3, 'range_km' => 770, 'battery_kwh' => 108, 'price_idr' => 3_800_000_000, 'description' => 'S-Class versi listrik, range terpanjang EV luxury'],
            ['model' => 'G 63 AMG', 'year_start' => 2019, 'body_type' => 'SUV', 'fuel_type' => 'gasoline', 'engine' => '4.0L V8 Biturbo', 'horsepower' => 585, 'torque_nm' => 850, 'transmission' => 'AMG SPEEDSHIFT 9G', 'drivetrain' => '4WD', 'top_speed_kmh' => 220, 'zero_to_100' => 4.5, 'price_idr' => 6_500_000_000, 'description' => 'SUV kotak paling ikonik dan paling mahal dari Mercedes'],
        ]);

        // Porsche
        $this->createCars($porsche->id, [
            ['model' => '911 GT3 RS', 'year_start' => 2023, 'body_type' => 'Coupe', 'fuel_type' => 'gasoline', 'engine' => '4.0L Flat-6 NA', 'horsepower' => 518, 'torque_nm' => 465, 'transmission' => 'PDK 7-Speed', 'drivetrain' => 'RWD', 'top_speed_kmh' => 296, 'zero_to_100' => 3.2, 'price_idr' => 12_000_000_000, 'description' => 'Track weapon dengan aerodinamika aktif ekstrem'],
            ['model' => 'Cayman GT4 RS', 'year_start' => 2022, 'body_type' => 'Coupe', 'fuel_type' => 'gasoline', 'engine' => '4.0L Flat-6 NA', 'horsepower' => 493, 'torque_nm' => 450, 'transmission' => 'PDK 7-Speed', 'drivetrain' => 'RWD', 'top_speed_kmh' => 315, 'zero_to_100' => 3.4, 'price_idr' => 7_500_000_000, 'description' => 'Mid-engine Porsche terbaik sepanjang masa'],
            ['model' => 'Taycan Turbo S', 'year_start' => 2020, 'body_type' => 'Sedan', 'fuel_type' => 'electric', 'engine' => 'Dual Motor Performance Plus', 'horsepower' => 761, 'torque_nm' => 1050, 'transmission' => 'AT 2-Speed', 'drivetrain' => 'AWD', 'top_speed_kmh' => 260, 'zero_to_100' => 2.8, 'range_km' => 484, 'battery_kwh' => 93, 'price_idr' => 5_200_000_000, 'description' => 'EV performa tinggi yang tetap berasa Porsche'],
        ]);

        // Volkswagen
        $this->createCars($vw->id, [
            ['model' => 'Golf R', 'year_start' => 2022, 'body_type' => 'Hatchback', 'fuel_type' => 'gasoline', 'engine' => '2.0L TSI EA888 Turbo', 'horsepower' => 320, 'torque_nm' => 420, 'transmission' => 'DSG 7-Speed', 'drivetrain' => 'AWD', 'top_speed_kmh' => 270, 'zero_to_100' => 4.7, 'price_idr' => 1_100_000_000, 'description' => 'Hot hatch premium AWD terbaik'],
            ['model' => 'ID.4 Pro', 'year_start' => 2023, 'body_type' => 'SUV', 'fuel_type' => 'electric', 'engine' => 'Single Motor RWD', 'horsepower' => 286, 'torque_nm' => 310, 'transmission' => 'Single Speed', 'drivetrain' => 'RWD', 'top_speed_kmh' => 160, 'zero_to_100' => 6.7, 'range_km' => 529, 'battery_kwh' => 82, 'price_idr' => 800_000_000, 'description' => 'SUV listrik VW untuk pasar massal'],
        ]);

        // Volvo
        $this->createCars($volvo->id, [
            ['model' => 'XC60 Recharge', 'year_start' => 2022, 'body_type' => 'SUV', 'fuel_type' => 'hybrid', 'engine' => '2.0L Turbo+Supercharged PHEV', 'horsepower' => 455, 'torque_nm' => 709, 'transmission' => 'AT 8-Speed', 'drivetrain' => 'AWD', 'top_speed_kmh' => 180, 'zero_to_100' => 4.8, 'price_idr' => 1_350_000_000, 'description' => 'SUV medium tersaman, kini PHEV bertenaga besar'],
            ['model' => 'EX30', 'year_start' => 2024, 'body_type' => 'SUV', 'fuel_type' => 'electric', 'engine' => 'Single Motor', 'horsepower' => 272, 'torque_nm' => 343, 'transmission' => 'Single Speed', 'drivetrain' => 'FWD', 'top_speed_kmh' => 180, 'zero_to_100' => 5.3, 'range_km' => 476, 'battery_kwh' => 69, 'price_idr' => 700_000_000, 'description' => 'Compact electric SUV dari Volvo yang minimalis'],
        ]);

        // ============================================================
        // KOREAN
        // ============================================================
        $hyundai = Brand::create(['name' => 'Hyundai', 'country' => 'South Korea', 'category' => 'korean', 'description' => 'Dari murah ke premium, kini pemain global utama']);
        $kia = Brand::create(['name' => 'Kia', 'country' => 'South Korea', 'category' => 'korean', 'description' => 'Movement that inspires']);
        $genesis = Brand::create(['name' => 'Genesis', 'country' => 'South Korea', 'category' => 'korean', 'description' => 'Divisi luxury Hyundai yang menyaingi Jerman']);

        // Hyundai
        $this->createCars($hyundai->id, [
            ['model' => 'IONIQ 5 N', 'year_start' => 2024, 'body_type' => 'SUV', 'fuel_type' => 'electric', 'engine' => 'Dual Motor AWD', 'horsepower' => 641, 'torque_nm' => 770, 'transmission' => 'Single Speed', 'drivetrain' => 'AWD', 'top_speed_kmh' => 260, 'zero_to_100' => 3.4, 'range_km' => 448, 'battery_kwh' => 84, 'price_idr' => 1_500_000_000, 'description' => 'EV performa gila dengan N e-shift virtual gear'],
            ['model' => 'IONIQ 6', 'year_start' => 2023, 'body_type' => 'Sedan', 'fuel_type' => 'electric', 'engine' => 'Dual Motor AWD', 'horsepower' => 320, 'torque_nm' => 605, 'transmission' => 'Single Speed', 'drivetrain' => 'AWD', 'top_speed_kmh' => 185, 'zero_to_100' => 5.1, 'range_km' => 614, 'battery_kwh' => 77, 'price_idr' => 850_000_000, 'description' => 'Sedan listrik paling aerodinamis, Cd 0.21'],
            ['model' => 'Creta', 'year_start' => 2022, 'body_type' => 'SUV', 'fuel_type' => 'gasoline', 'engine' => '1.5L Smartstream', 'horsepower' => 115, 'torque_nm' => 144, 'transmission' => 'CVT', 'drivetrain' => 'FWD', 'top_speed_kmh' => 175, 'zero_to_100' => 12.0, 'price_idr' => 310_000_000, 'description' => 'Compact SUV stylish untuk urban'],
            ['model' => 'Stargazer', 'year_start' => 2023, 'body_type' => 'MPV', 'fuel_type' => 'gasoline', 'engine' => '1.5L Smartstream', 'horsepower' => 115, 'torque_nm' => 144, 'transmission' => 'CVT', 'drivetrain' => 'FWD', 'top_speed_kmh' => 170, 'zero_to_100' => 13.0, 'price_idr' => 265_000_000, 'description' => 'MPV futuristik penantang Avanza/Xpander'],
        ]);

        // Kia
        $this->createCars($kia->id, [
            ['model' => 'EV6 GT', 'year_start' => 2023, 'body_type' => 'SUV', 'fuel_type' => 'electric', 'engine' => 'Dual Motor AWD', 'horsepower' => 585, 'torque_nm' => 740, 'transmission' => 'Single Speed', 'drivetrain' => 'AWD', 'top_speed_kmh' => 260, 'zero_to_100' => 3.5, 'range_km' => 424, 'battery_kwh' => 77, 'price_idr' => 1_350_000_000, 'description' => 'EV crossover kencang, platform E-GMP'],
            ['model' => 'EV9', 'year_start' => 2024, 'body_type' => 'SUV', 'fuel_type' => 'electric', 'engine' => 'Dual Motor AWD', 'horsepower' => 379, 'torque_nm' => 700, 'transmission' => 'Single Speed', 'drivetrain' => 'AWD', 'top_speed_kmh' => 200, 'zero_to_100' => 5.3, 'range_km' => 541, 'battery_kwh' => 99, 'price_idr' => 1_600_000_000, 'description' => 'SUV listrik 7-seater besar dari Kia'],
            ['model' => 'Seltos', 'year_start' => 2023, 'body_type' => 'SUV', 'fuel_type' => 'gasoline', 'engine' => '1.5L Smartstream', 'horsepower' => 115, 'torque_nm' => 144, 'transmission' => 'CVT', 'drivetrain' => 'FWD', 'top_speed_kmh' => 175, 'zero_to_100' => 12.0, 'price_idr' => 320_000_000, 'description' => 'Compact SUV fitur melimpah harga bersaing'],
        ]);

        // Genesis
        $this->createCars($genesis->id, [
            ['model' => 'G70', 'year_start' => 2022, 'body_type' => 'Sedan', 'fuel_type' => 'gasoline', 'engine' => '3.3L Twin-Turbo V6', 'horsepower' => 370, 'torque_nm' => 510, 'transmission' => 'AT 8-Speed', 'drivetrain' => 'RWD', 'top_speed_kmh' => 270, 'zero_to_100' => 4.7, 'price_idr' => 1_100_000_000, 'description' => 'Sports sedan Korea yang menyaingi BMW 3 Series'],
            ['model' => 'GV60', 'year_start' => 2023, 'body_type' => 'SUV', 'fuel_type' => 'electric', 'engine' => 'Dual Motor AWD', 'horsepower' => 429, 'torque_nm' => 605, 'transmission' => 'Single Speed', 'drivetrain' => 'AWD', 'top_speed_kmh' => 235, 'zero_to_100' => 4.0, 'range_km' => 466, 'battery_kwh' => 77, 'price_idr' => 1_200_000_000, 'description' => 'Luxury EV crossover dari Genesis'],
        ]);

        // ============================================================
        // CHINESE & EV
        // ============================================================
        $byd = Brand::create(['name' => 'BYD', 'country' => 'China', 'category' => 'ev', 'description' => 'Build Your Dreams - Raja EV global dari China, didukung Warren Buffett']);
        $wuling = Brand::create(['name' => 'Wuling', 'country' => 'China', 'category' => 'chinese', 'description' => 'Mobil rakyat China yang sukses besar di Indonesia']);
        $chery = Brand::create(['name' => 'Chery', 'country' => 'China', 'category' => 'chinese', 'description' => 'Pabrikan China dengan teknologi mesin canggih']);
        $neta = Brand::create(['name' => 'Neta', 'country' => 'China', 'category' => 'ev', 'description' => 'Brand EV muda dari Hozon Auto']);

        // ============================================================
        // BYD - WAJIB ADA BYD ATTO 1 DENGAN SPESIFIKASI LENGKAP
        // ============================================================
        $this->createCars($byd->id, [
            // *** BYD ATTO 1 - EKSPLISIT SESUAI PERMINTAAN ***
            [
                'model' => 'Atto 1',
                'year_start' => 2024,
                'body_type' => 'Hatchback',
                'fuel_type' => 'electric',
                'engine' => 'Single Motor Front-Mounted',
                'horsepower' => 95,
                'torque_nm' => 180,
                'transmission' => 'Single Speed Reduction Gear',
                'drivetrain' => 'FWD',
                'top_speed_kmh' => 130,
                'zero_to_100' => 12.3,
                'range_km' => 310,
                'battery_kwh' => 38,
                'price_idr' => 285_000_000,
                'description' => 'City car listrik paling terjangkau dari BYD. Menggunakan Blade Battery LFP yang aman dan tahan lama. Desain kompak cocok untuk perkotaan dengan fitur lengkap termasuk V2L (Vehicle-to-Load) untuk mengisi daya perangkat elektronik.',
            ],
            ['model' => 'Atto 3', 'year_start' => 2023, 'body_type' => 'SUV', 'fuel_type' => 'electric', 'engine' => 'Single Motor Front-Mounted', 'horsepower' => 201, 'torque_nm' => 310, 'transmission' => 'Single Speed', 'drivetrain' => 'FWD', 'top_speed_kmh' => 160, 'zero_to_100' => 7.3, 'range_km' => 410, 'battery_kwh' => 60, 'price_idr' => 520_000_000, 'description' => 'Compact EV SUV dengan desain unik dan Blade Battery'],
            ['model' => 'Seal', 'year_start' => 2023, 'body_type' => 'Sedan', 'fuel_type' => 'electric', 'engine' => 'Dual Motor AWD', 'horsepower' => 530, 'torque_nm' => 670, 'transmission' => 'Single Speed', 'drivetrain' => 'AWD', 'top_speed_kmh' => 180, 'zero_to_100' => 3.8, 'range_km' => 570, 'battery_kwh' => 82, 'price_idr' => 750_000_000, 'description' => 'Sedan listrik premium rival Tesla Model 3'],
            ['model' => 'Dolphin', 'year_start' => 2023, 'body_type' => 'Hatchback', 'fuel_type' => 'electric', 'engine' => 'Single Motor', 'horsepower' => 150, 'torque_nm' => 290, 'transmission' => 'Single Speed', 'drivetrain' => 'FWD', 'top_speed_kmh' => 160, 'zero_to_100' => 7.5, 'range_km' => 427, 'battery_kwh' => 60, 'price_idr' => 420_000_000, 'description' => 'Hatchback listrik fun to drive dan terjangkau'],
            ['model' => 'Han EV', 'year_start' => 2022, 'body_type' => 'Sedan', 'fuel_type' => 'electric', 'engine' => 'Dual Motor AWD', 'horsepower' => 517, 'torque_nm' => 700, 'transmission' => 'Single Speed', 'drivetrain' => 'AWD', 'top_speed_kmh' => 180, 'zero_to_100' => 3.9, 'range_km' => 610, 'battery_kwh' => 85, 'price_idr' => 900_000_000, 'description' => 'Flagship sedan listrik BYD dengan kemewahan tinggi'],
            ['model' => 'Tang EV', 'year_start' => 2022, 'body_type' => 'SUV', 'fuel_type' => 'electric', 'engine' => 'Dual Motor AWD', 'horsepower' => 517, 'torque_nm' => 680, 'transmission' => 'Single Speed', 'drivetrain' => 'AWD', 'top_speed_kmh' => 180, 'zero_to_100' => 4.4, 'range_km' => 530, 'battery_kwh' => 108, 'price_idr' => 1_100_000_000, 'description' => 'SUV listrik 7-seater flagship dari BYD'],
        ]);

        // Wuling
        $this->createCars($wuling->id, [
            ['model' => 'Air EV', 'year_start' => 2022, 'body_type' => 'Hatchback', 'fuel_type' => 'electric', 'engine' => 'Single Motor', 'horsepower' => 50, 'torque_nm' => 110, 'transmission' => 'Single Speed', 'drivetrain' => 'FWD', 'top_speed_kmh' => 100, 'zero_to_100' => 15.0, 'range_km' => 300, 'battery_kwh' => 27, 'price_idr' => 238_000_000, 'description' => 'Mobil listrik termurah di Indonesia, GJAW best seller'],
            ['model' => 'BinguoEV', 'year_start' => 2024, 'body_type' => 'Hatchback', 'fuel_type' => 'electric', 'engine' => 'Single Motor', 'horsepower' => 68, 'torque_nm' => 150, 'transmission' => 'Single Speed', 'drivetrain' => 'FWD', 'top_speed_kmh' => 120, 'zero_to_100' => 14.0, 'range_km' => 333, 'battery_kwh' => 31, 'price_idr' => 270_000_000, 'description' => 'Penerus Air EV dengan spesifikasi lebih baik'],
            ['model' => 'Almaz RS Hybrid', 'year_start' => 2023, 'body_type' => 'SUV', 'fuel_type' => 'hybrid', 'engine' => '1.5L Turbo Hybrid', 'horsepower' => 180, 'torque_nm' => 350, 'transmission' => 'DHT', 'drivetrain' => 'FWD', 'top_speed_kmh' => 180, 'zero_to_100' => 9.0, 'price_idr' => 380_000_000, 'description' => 'SUV hybrid murah dengan fitur ADAS lengkap'],
        ]);

        // Chery
        $this->createCars($chery->id, [
            ['model' => 'Omoda 5', 'year_start' => 2024, 'body_type' => 'SUV', 'fuel_type' => 'gasoline', 'engine' => '1.5L Turbo', 'horsepower' => 147, 'torque_nm' => 210, 'transmission' => 'CVT', 'drivetrain' => 'FWD', 'top_speed_kmh' => 185, 'zero_to_100' => 9.5, 'price_idr' => 340_000_000, 'description' => 'Compact SUV China desain sporty, fitur melimpah'],
            ['model' => 'Tiggo 7 Pro', 'year_start' => 2023, 'body_type' => 'SUV', 'fuel_type' => 'gasoline', 'engine' => '1.5L Turbo', 'horsepower' => 147, 'torque_nm' => 210, 'transmission' => 'CVT', 'drivetrain' => 'FWD', 'top_speed_kmh' => 185, 'zero_to_100' => 9.5, 'price_idr' => 380_000_000, 'description' => 'SUV medium China value-for-money terbaik'],
        ]);

        // Neta
        $this->createCars($neta->id, [
            ['model' => 'V-II', 'year_start' => 2024, 'body_type' => 'SUV', 'fuel_type' => 'electric', 'engine' => 'Single Motor', 'horsepower' => 120, 'torque_nm' => 150, 'transmission' => 'Single Speed', 'drivetrain' => 'FWD', 'top_speed_kmh' => 120, 'zero_to_100' => 12.0, 'range_km' => 401, 'battery_kwh' => 40, 'price_idr' => 310_000_000, 'description' => 'SUV listrik murah dari Hozon Auto'],
        ]);
    }

    /**
     * Helper: batch create cars for a brand
     */
    private function createCars(int $brandId, array $cars): void
    {
        foreach ($cars as $carData) {
            $carData['brand_id'] = $brandId;
            Car::create($carData);
        }
    }
}
