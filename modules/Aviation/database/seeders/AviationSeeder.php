<?php

declare(strict_types=1);

namespace Modules\Aviation\database\seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Aviation\Domain\Enums\AviationCabin;
use Modules\Aviation\Domain\Enums\CargoConsignmentStatus;
use Modules\Aviation\Domain\Enums\FareClass;
use Modules\Aviation\Domain\Enums\FlightStatus;
use Modules\Aviation\Domain\Enums\MaintenanceVisitStatus;
use Modules\Aviation\Domain\Enums\SlotRequestStatus;
use Modules\Aviation\Domain\Enums\TicketStatus;
use Modules\Aviation\Domain\Models\AviationAircraft;
use Modules\Aviation\Domain\Models\AviationAirport;
use Modules\Aviation\Domain\Models\AviationCargoConsignment;
use Modules\Aviation\Domain\Models\AviationExternalFactor;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Domain\Models\AviationMaintenanceVisit;
use Modules\Aviation\Domain\Models\AviationOperator;
use Modules\Aviation\Domain\Models\AviationRoute;
use Modules\Aviation\Domain\Models\AviationSeatInventory;
use Modules\Aviation\Domain\Models\AviationSlot;
use Modules\Aviation\Domain\Models\AviationTicket;

/**
 * Seeder Deterministik T1 Modul Aviation (Fase 177).
 * Menghasilkan 3 operator, 40 bandara (5 pulau), 60 armada pesawat,
 * ratusan rute, slot, penerbangan terjadwal, dan skenario erupsi bandara.
 */
class AviationSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Operator Penerbangan (3 Airlines)
        $operators = [
            ['code' => 'GA', 'name' => 'Garuda Indonesia', 'country' => 'ID', 'status' => 'active'],
            ['code' => 'QG', 'name' => 'Citilink Indonesia', 'country' => 'ID', 'status' => 'active'],
            ['code' => 'SJ', 'name' => 'Sriwijaya Air', 'country' => 'ID', 'status' => 'active'],
        ];

        $operatorModels = [];
        foreach ($operators as $op) {
            $operatorModels[$op['code']] = AviationOperator::firstOrCreate(
                ['code' => $op['code']],
                $op
            );
        }

        // 2. 40 Bandara di 6 Kawasan Kepulauan Indonesia
        $airportsData = [
            // Jawa (9)
            ['iata' => 'CGK', 'icao' => 'WIII', 'name' => 'Soekarno-Hatta Int', 'city' => 'Tangerang', 'province' => 'Banten', 'island' => 'Jawa', 'tz' => 'Asia/Jakarta', 'slot' => true, 'cap' => 72],
            ['iata' => 'HLP', 'icao' => 'WIHH', 'name' => 'Halim Perdanakusuma', 'city' => 'Jakarta Timur', 'province' => 'DKI Jakarta', 'island' => 'Jawa', 'tz' => 'Asia/Jakarta', 'slot' => true, 'cap' => 24],
            ['iata' => 'SUB', 'icao' => 'WARR', 'name' => 'Juanda International', 'city' => 'Sidoarjo', 'province' => 'Jawa Timur', 'island' => 'Jawa', 'tz' => 'Asia/Jakarta', 'slot' => true, 'cap' => 36],
            ['iata' => 'YIA', 'icao' => 'WAHI', 'name' => 'Yogyakarta International', 'city' => 'Kulon Progo', 'province' => 'DI Yogyakarta', 'island' => 'Jawa', 'tz' => 'Asia/Jakarta', 'slot' => true, 'cap' => 28],
            ['iata' => 'SRG', 'icao' => 'WAHS', 'name' => 'Jenderal Ahmad Yani', 'city' => 'Semarang', 'province' => 'Jawa Tengah', 'island' => 'Jawa', 'tz' => 'Asia/Jakarta', 'slot' => false, 'cap' => 20],
            ['iata' => 'SOC', 'icao' => 'WAHQ', 'name' => 'Adi Soemarmo', 'city' => 'Surakarta', 'province' => 'Jawa Tengah', 'island' => 'Jawa', 'tz' => 'Asia/Jakarta', 'slot' => false, 'cap' => 16],
            ['iata' => 'BDO', 'icao' => 'WICC', 'name' => 'Husein Sastranegara', 'city' => 'Bandung', 'province' => 'Jawa Barat', 'island' => 'Jawa', 'tz' => 'Asia/Jakarta', 'slot' => false, 'cap' => 12],
            ['iata' => 'MLG', 'icao' => 'WARA', 'name' => 'Abdul Rachman Saleh', 'city' => 'Malang', 'province' => 'Jawa Timur', 'island' => 'Jawa', 'tz' => 'Asia/Jakarta', 'slot' => false, 'cap' => 10],
            ['iata' => 'BWX', 'icao' => 'WADY', 'name' => 'Banyuwangi International', 'city' => 'Banyuwangi', 'province' => 'Jawa Timur', 'island' => 'Jawa', 'tz' => 'Asia/Jakarta', 'slot' => false, 'cap' => 8],

            // Bali & Nusa Tenggara (5)
            ['iata' => 'DPS', 'icao' => 'WADD', 'name' => 'I Gusti Ngurah Rai', 'city' => 'Badung', 'province' => 'Bali', 'island' => 'Bali', 'tz' => 'Asia/Makassar', 'slot' => true, 'cap' => 34],
            ['iata' => 'LOP', 'icao' => 'WADL', 'name' => 'Zainuddin Abdul Madjid', 'city' => 'Praya', 'province' => 'Nusa Tenggara Barat', 'island' => 'Lombok', 'tz' => 'Asia/Makassar', 'slot' => false, 'cap' => 16],
            ['iata' => 'KOE', 'icao' => 'WATT', 'name' => 'El Tari', 'city' => 'Kupang', 'province' => 'Nusa Tenggara Timur', 'island' => 'Timor', 'tz' => 'Asia/Makassar', 'slot' => false, 'cap' => 14],
            ['iata' => 'LBJ', 'icao' => 'WATO', 'name' => 'Komodo International', 'city' => 'Labuan Bajo', 'province' => 'Nusa Tenggara Timur', 'island' => 'Flores', 'tz' => 'Asia/Makassar', 'slot' => false, 'cap' => 12],
            ['iata' => 'MOF', 'icao' => 'WATC', 'name' => 'Frans Seda', 'city' => 'Maumere', 'province' => 'Nusa Tenggara Timur', 'island' => 'Flores', 'tz' => 'Asia/Makassar', 'slot' => false, 'cap' => 6],

            // Sumatera (10)
            ['iata' => 'KNO', 'icao' => 'WIMM', 'name' => 'Kualanamu International', 'city' => 'Deli Serdang', 'province' => 'Sumatera Utara', 'island' => 'Sumatera', 'tz' => 'Asia/Jakarta', 'slot' => true, 'cap' => 32],
            ['iata' => 'BTJ', 'icao' => 'WITT', 'name' => 'Sultan Iskandar Muda', 'city' => 'Banda Aceh', 'province' => 'Aceh', 'island' => 'Sumatera', 'tz' => 'Asia/Jakarta', 'slot' => false, 'cap' => 12],
            ['iata' => 'PDG', 'icao' => 'WIPT', 'name' => 'Minangkabau International', 'city' => 'Padang Pariaman', 'province' => 'Sumatera Barat', 'island' => 'Sumatera', 'tz' => 'Asia/Jakarta', 'slot' => false, 'cap' => 18],
            ['iata' => 'PKU', 'icao' => 'WIBB', 'name' => 'Sultan Syarif Kasim II', 'city' => 'Pekanbaru', 'province' => 'Riau', 'island' => 'Sumatera', 'tz' => 'Asia/Jakarta', 'slot' => false, 'cap' => 16],
            ['iata' => 'BTH', 'icao' => 'WIDD', 'name' => 'Hang Nadim', 'city' => 'Batam', 'province' => 'Kepulauan Riau', 'island' => 'Sumatera', 'tz' => 'Asia/Jakarta', 'slot' => true, 'cap' => 24],
            ['iata' => 'PLM', 'icao' => 'WIPP', 'name' => 'Sultan Mahmud Badaruddin II', 'city' => 'Palembang', 'province' => 'Sumatera Selatan', 'island' => 'Sumatera', 'tz' => 'Asia/Jakarta', 'slot' => false, 'cap' => 18],
            ['iata' => 'TKG', 'icao' => 'WILL', 'name' => 'Radin Inten II', 'city' => 'Bandar Lampung', 'province' => 'Lampung', 'island' => 'Sumatera', 'tz' => 'Asia/Jakarta', 'slot' => false, 'cap' => 14],
            ['iata' => 'DJB', 'icao' => 'WIJJ', 'name' => 'Sultan Thaha', 'city' => 'Jambi', 'province' => 'Jambi', 'island' => 'Sumatera', 'tz' => 'Asia/Jakarta', 'slot' => false, 'cap' => 10],
            ['iata' => 'PGK', 'icao' => 'WIPK', 'name' => 'Depati Amir', 'city' => 'Pangkal Pinang', 'province' => 'Bangka Belitung', 'island' => 'Bangka', 'tz' => 'Asia/Jakarta', 'slot' => false, 'cap' => 10],
            ['iata' => 'TJQ', 'icao' => 'WIKD', 'name' => 'H.A.S. Hanandjoeddin', 'city' => 'Tanjung Pandan', 'province' => 'Bangka Belitung', 'island' => 'Belitung', 'tz' => 'Asia/Jakarta', 'slot' => false, 'cap' => 8],

            // Kalimantan (7)
            ['iata' => 'BPN', 'icao' => 'WALL', 'name' => 'Sultan Aji Muhammad Sulaiman Sepinggan', 'city' => 'Balikpapan', 'province' => 'Kalimantan Timur', 'island' => 'Kalimantan', 'tz' => 'Asia/Makassar', 'slot' => true, 'cap' => 24],
            ['iata' => 'AAP', 'icao' => 'WALS', 'name' => 'Aji Pangeran Tumenggung Pranoto', 'city' => 'Samarinda', 'province' => 'Kalimantan Timur', 'island' => 'Kalimantan', 'tz' => 'Asia/Makassar', 'slot' => false, 'cap' => 12],
            ['iata' => 'BDJ', 'icao' => 'WAOO', 'name' => 'Syamsudin Noor', 'city' => 'Banjarbaru', 'province' => 'Kalimantan Selatan', 'island' => 'Kalimantan', 'tz' => 'Asia/Makassar', 'slot' => false, 'cap' => 16],
            ['iata' => 'PNK', 'icao' => 'WIOO', 'name' => 'Supadio', 'city' => 'Kubu Raya', 'province' => 'Kalimantan Barat', 'island' => 'Kalimantan', 'tz' => 'Asia/Jakarta', 'slot' => false, 'cap' => 16],
            ['iata' => 'PKY', 'icao' => 'WAGG', 'name' => 'Tjilik Riwut', 'city' => 'Palangka Raya', 'province' => 'Kalimantan Tengah', 'island' => 'Kalimantan', 'tz' => 'Asia/Jakarta', 'slot' => false, 'cap' => 10],
            ['iata' => 'TRK', 'icao' => 'WALR', 'name' => 'Juwata', 'city' => 'Tarakan', 'province' => 'Kalimantan Utara', 'island' => 'Kalimantan', 'tz' => 'Asia/Makassar', 'slot' => false, 'cap' => 10],
            ['iata' => 'BEJ', 'icao' => 'WAQT', 'name' => 'Kalimarau', 'city' => 'Berau', 'province' => 'Kalimantan Timur', 'island' => 'Kalimantan', 'tz' => 'Asia/Makassar', 'slot' => false, 'cap' => 8],

            // Sulawesi (5)
            ['iata' => 'UPG', 'icao' => 'WAAA', 'name' => 'Sultan Hasanuddin', 'city' => 'Maros', 'province' => 'Sulawesi Selatan', 'island' => 'Sulawesi', 'tz' => 'Asia/Makassar', 'slot' => true, 'cap' => 36],
            ['iata' => 'MDC', 'icao' => 'WAMM', 'name' => 'Sam Ratulangi', 'city' => 'Manado', 'province' => 'Sulawesi Utara', 'island' => 'Sulawesi', 'tz' => 'Asia/Makassar', 'slot' => false, 'cap' => 18],
            ['iata' => 'PLW', 'icao' => 'WAFF', 'name' => 'Mutiara SIS Al-Jufrie', 'city' => 'Palu', 'province' => 'Sulawesi Tengah', 'island' => 'Sulawesi', 'tz' => 'Asia/Makassar', 'slot' => false, 'cap' => 12],
            ['iata' => 'KDI', 'icao' => 'WAWW', 'name' => 'Haluoleo', 'city' => 'Kendari', 'province' => 'Sulawesi Tenggara', 'island' => 'Sulawesi', 'tz' => 'Asia/Makassar', 'slot' => false, 'cap' => 10],
            ['iata' => 'GTO', 'icao' => 'WAGL', 'name' => 'Jalaluddin', 'city' => 'Gorontalo', 'province' => 'Gorontalo', 'island' => 'Sulawesi', 'tz' => 'Asia/Makassar', 'slot' => false, 'cap' => 8],

            // Maluku & Papua (4)
            ['iata' => 'AMQ', 'icao' => 'WAPP', 'name' => 'Pattimura', 'city' => 'Ambon', 'province' => 'Maluku', 'island' => 'Maluku', 'tz' => 'Asia/Jayapura', 'slot' => false, 'cap' => 12],
            ['iata' => 'TTE', 'icao' => 'WAEE', 'name' => 'Sultan Babullah', 'city' => 'Ternate', 'province' => 'Maluku Utara', 'island' => 'Maluku', 'tz' => 'Asia/Jayapura', 'slot' => false, 'cap' => 8],
            ['iata' => 'DJJ', 'icao' => 'WAJJ', 'name' => 'Sentani', 'city' => 'Jayapura', 'province' => 'Papua', 'island' => 'Papua', 'tz' => 'Asia/Jayapura', 'slot' => false, 'cap' => 16],
            ['iata' => 'SOQ', 'icao' => 'WASS', 'name' => 'Domine Eduard Osok', 'city' => 'Sorong', 'province' => 'Papua Barat Daya', 'island' => 'Papua', 'tz' => 'Asia/Jayapura', 'slot' => false, 'cap' => 10],
        ];

        $airportModels = [];
        foreach ($airportsData as $ad) {
            $airportModels[$ad['iata']] = AviationAirport::firstOrCreate(
                ['iata_code' => $ad['iata']],
                [
                    'icao_code' => $ad['icao'],
                    'name' => $ad['name'],
                    'city' => $ad['city'],
                    'province' => $ad['province'],
                    'island' => $ad['island'],
                    'timezone' => $ad['tz'],
                    'slot_coordinated' => $ad['slot'],
                    'hourly_capacity' => $ad['cap'],
                    'status' => 'active',
                ]
            );
        }

        // 3. 60 Pesawat Terbang (Armada GA, QG, SJ)
        $aircraftModels = [];
        $modelsCatalog = [
            ['type' => 'B738', 'name' => 'Boeing 737-800NG', 'y' => 150, 'j' => 12, 'mtow' => 79000, 'oew' => 41400, 'cg_fwd' => 8600, 'cg_aft' => 9600, 'fuel' => 20800],
            ['type' => 'A320', 'name' => 'Airbus A320-200', 'y' => 180, 'j' => 0, 'mtow' => 77000, 'oew' => 42600, 'cg_fwd' => 8500, 'cg_aft' => 9500, 'fuel' => 19000],
            ['type' => 'AT76', 'name' => 'ATR 72-600', 'y' => 72, 'j' => 0, 'mtow' => 23000, 'oew' => 13500, 'cg_fwd' => 6500, 'cg_aft' => 7400, 'fuel' => 5000],
        ];

        $letters = range('A', 'Z');
        $aircraftCounter = 0;

        foreach ($operators as $opIdx => $op) {
            $opModel = $operatorModels[$op['code']];
            for ($i = 0; $i < 20; $i++) {
                $aircraftCounter++;
                $reg = sprintf('PK-%s%s%s', $op['code'][0], $letters[$opIdx], $letters[$i]);
                $spec = $modelsCatalog[$i % count($modelsCatalog)];

                $aircraftModels[] = AviationAircraft::firstOrCreate(
                    ['registration_mark' => $reg],
                    [
                        'icao_type' => $spec['type'],
                        'model_name' => $spec['name'],
                        'operator_id' => $opModel->id,
                        'cabin_y_capacity' => $spec['y'],
                        'cabin_j_capacity' => $spec['j'],
                        'mtow_kg' => $spec['mtow'],
                        'oew_kg' => $spec['oew'],
                        'cg_forward_limit_mm' => $spec['cg_fwd'],
                        'cg_aft_limit_mm' => $spec['cg_aft'],
                        'fuel_capacity_kg' => $spec['fuel'],
                        'total_flight_minutes' => rand(600, 24000),
                        'total_flight_cycles' => rand(30, 1200),
                        'airworthiness_expires_at' => now()->addMonths(12)->toDateString(),
                        'a_check_due_minutes' => 36000,
                        'a_check_current_minutes' => rand(2000, 30000),
                        'status' => $i === 0 ? 'maintenance' : ($i === 1 ? 'grounded' : 'active'),
                    ]
                );
            }
        }

        // 4. Rute Penerbangan Utama Antar Kota
        $routesPairs = [
            ['orig' => 'CGK', 'dest' => 'DPS', 'num' => 'GA-404', 'km' => 980, 'dur' => 110, 'fare' => 150000000],
            ['orig' => 'DPS', 'dest' => 'CGK', 'num' => 'GA-405', 'km' => 980, 'dur' => 110, 'fare' => 150000000],
            ['orig' => 'CGK', 'dest' => 'SUB', 'num' => 'GA-308', 'km' => 690, 'dur' => 90, 'fare' => 110000000],
            ['orig' => 'SUB', 'dest' => 'CGK', 'num' => 'GA-309', 'km' => 690, 'dur' => 90, 'fare' => 110000000],
            ['orig' => 'CGK', 'dest' => 'UPG', 'num' => 'GA-610', 'km' => 1400, 'dur' => 140, 'fare' => 180000000],
            ['orig' => 'CGK', 'dest' => 'KNO', 'num' => 'GA-188', 'km' => 1420, 'dur' => 140, 'fare' => 175000000],
            ['orig' => 'CGK', 'dest' => 'BPN', 'num' => 'GA-512', 'km' => 1250, 'dur' => 125, 'fare' => 160000000],
            ['orig' => 'SUB', 'dest' => 'DPS', 'num' => 'QG-650', 'km' => 310, 'dur' => 55, 'fare' => 65000000],
            ['orig' => 'DPS', 'dest' => 'LBJ', 'num' => 'GA-7026', 'km' => 520, 'dur' => 70, 'fare' => 95000000],
            ['orig' => 'UPG', 'dest' => 'MDC', 'num' => 'SJ-580', 'km' => 960, 'dur' => 105, 'fare' => 125000000],
            ['orig' => 'CGK', 'dest' => 'YIA', 'num' => 'QG-210', 'km' => 450, 'dur' => 70, 'fare' => 75000000],
            ['orig' => 'UPG', 'dest' => 'AMQ', 'num' => 'GA-640', 'km' => 990, 'dur' => 110, 'fare' => 135000000],
            ['orig' => 'UPG', 'dest' => 'DJJ', 'num' => 'GA-656', 'km' => 2200, 'dur' => 210, 'fare' => 290000000],
        ];

        $routeModels = [];
        foreach ($routesPairs as $rp) {
            $routeModels[$rp['num']] = AviationRoute::firstOrCreate(
                ['flight_number' => $rp['num']],
                [
                    'origin_airport_id' => $airportModels[$rp['orig']]->id,
                    'destination_airport_id' => $airportModels[$rp['dest']]->id,
                    'distance_km' => $rp['km'],
                    'scheduled_duration_minutes' => $rp['dur'],
                    'base_fare_minor' => $rp['fare'],
                    'currency' => 'IDR',
                    'status' => 'active',
                ]
            );
        }

        // 5. Slot Musiman (W26) di Bandara Slot Coordinated
        foreach (['CGK', 'DPS', 'SUB', 'KNO'] as $iata) {
            $airport = $airportModels[$iata];
            for ($hour = 6; $hour <= 21; $hour += 3) {
                AviationSlot::firstOrCreate(
                    [
                        'airport_id' => $airport->id,
                        'operator_id' => $operatorModels['GA']->id,
                        'season_code' => 'W26',
                        'time_window_start' => sprintf('%02d:00', $hour),
                    ],
                    [
                        'day_of_week' => 1,
                        'time_window_end' => sprintf('%02d:59', $hour),
                        'is_historic' => true,
                        'usage_rate_bps' => 8800,
                        'status' => 'confirmed',
                    ]
                );
            }
        }

        // 6. Faktor Eksternal Deterministik: Erupsi Gunung Agung di DPS (Z8, ADR-0051)
        AviationExternalFactor::firstOrCreate(
            [
                'factor_key' => 'ash_cloud_density_dps',
                'effective_from' => Carbon::parse('2026-11-10 00:00:00'),
            ],
            [
                'factor_value' => 'SEVERE_GROUND_STOP',
                'source' => 'VONA-AGUNG-2026-NOV',
                'label' => 'regulasi',
            ]
        );
        AviationExternalFactor::firstOrCreate(
            [
                'factor_key' => 'avtur_price_cgk_minor',
                'effective_from' => Carbon::parse('2026-11-01 00:00:00'),
            ],
            [
                'factor_value' => '1520000',
                'source' => 'PERTAMINA_AVIATION',
                'label' => 'asumsi_simulasi',
            ]
        );

        // 7. Jadwal Penerbangan Deterministik (Flights) dengan variasi status
        $baseDate = Carbon::parse('2026-11-08 06:00:00');
        $flightStatuses = [
            FlightStatus::PLANNED->value,
            FlightStatus::SLOT_SECURED->value,
            FlightStatus::AIRWORTHY_CLEARED->value,
            FlightStatus::BOARDING->value,
            FlightStatus::DEPARTED->value,
            FlightStatus::ARRIVED->value,
            FlightStatus::CLOSED->value,
            FlightStatus::CANCELLED->value,
        ];

        $flightModels = [];
        $idx = 0;
        foreach ($routesPairs as $rp) {
            $route = $routeModels[$rp['num']];
            $operatorCode = substr($rp['num'], 0, 2);
            $operator = $operatorModels[$operatorCode];
            $aircraft = $aircraftModels[$idx % count($aircraftModels)];

            $flightTime = (clone $baseDate)->addHours($idx * 2);
            $status = $flightStatuses[$idx % count($flightStatuses)];

            if ($rp['orig'] === 'DPS' || $rp['dest'] === 'DPS') {
                if ($idx % 2 === 0) {
                    $status = FlightStatus::CANCELLED->value;
                }
            }

            $flight = AviationFlight::firstOrCreate(
                ['flight_number' => $rp['num']],
                [
                    'operator_id' => $operator->id,
                    'aircraft_id' => $aircraft->id,
                    'route_id' => $route->id,
                    'origin_airport_id' => $route->origin_airport_id,
                    'destination_airport_id' => $route->destination_airport_id,
                    'departure_time' => $flightTime,
                    'arrival_time' => (clone $flightTime)->addMinutes($route->scheduled_duration_minutes),
                    'status' => $status,
                    'fuel_burn_kg' => $route->distance_km * 4,
                ]
            );

            $flightModels[] = $flight;

            // Inisialisasi Inventaris Kursi
            AviationSeatInventory::firstOrCreate(
                ['flight_id' => $flight->id, 'cabin' => AviationCabin::Y->value],
                [
                    'total_capacity' => $aircraft->cabin_y_capacity,
                    'held_seats' => 0,
                    'booked_seats' => 15,
                    'oversell_limit_seats' => 0,
                ]
            );
            if ($aircraft->cabin_j_capacity > 0) {
                AviationSeatInventory::firstOrCreate(
                    ['flight_id' => $flight->id, 'cabin' => AviationCabin::J->value],
                    [
                        'total_capacity' => $aircraft->cabin_j_capacity,
                        'held_seats' => 0,
                        'booked_seats' => 2,
                        'oversell_limit_seats' => 0,
                    ]
                );
            }

            // Buat Tiket Contoh
            AviationTicket::firstOrCreate(
                ['ticket_number' => 'TKT-'.$flight->flight_number.'-001'],
                [
                    'flight_id' => $flight->id,
                    'passenger_party_id' => 1,
                    'cabin' => AviationCabin::Y->value,
                    'fare_basis' => 'STD',
                    'seat_assignment' => '12A',
                    'base_fare_minor' => $route->base_fare_minor,
                    'tax_minor' => (int) ($route->base_fare_minor * 0.11),
                    'total_fare_minor' => (int) ($route->base_fare_minor * 1.11),
                    'currency' => 'IDR',
                    'status' => TicketStatus::ISSUED->value,
                    'pii_name_encrypted' => 'Budi Santoso',
                    'pii_blind_index' => hash('sha256', 'BUDI SANTOSO'),
                ]
            );

            // Buat Kargo Contoh
            AviationCargoConsignment::firstOrCreate(
                ['consignment_number' => 'AWB-'.$flight->flight_number.'-99'],
                [
                    'flight_id' => $flight->id,
                    'weight_g' => 450000,
                    'volume_mm3' => 2400000000,
                    'nature_of_goods' => 'Spareparts and Perishables',
                    'is_dg' => false,
                    'status' => CargoConsignmentStatus::ACCEPTED->value,
                    'custody_hash_chain' => hash('sha256', 'AWB-'.$flight->flight_number.'-99'.'GENESIS'.'CGK'),
                ]
            );

            $idx++;
        }

        // 8. Kunjungan Perawatan (Maintenance Visits)
        foreach ($aircraftModels as $acIdx => $ac) {
            if ($acIdx < 5) {
                AviationMaintenanceVisit::firstOrCreate(
                    ['aircraft_id' => $ac->id, 'visit_type' => 'A_CHECK'],
                    [
                        'scheduled_start' => now()->subDays($acIdx * 5),
                        'scheduled_end' => now()->subDays($acIdx * 5)->addDays(2),
                        'status' => MaintenanceVisitStatus::RELEASED_TO_SERVICE->value,
                        'release_certificate_number' => 'CERT-'.$ac->registration_mark,
                    ]
                );
            }
        }
    }
}
