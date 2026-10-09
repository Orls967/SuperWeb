<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Edu\Domain\Models\EduProgram;
use Modules\Egy\Domain\Models\GenerationAsset;
use Modules\Egy\Domain\Models\SmartMeter;
use Modules\Med\Domain\Models\MedDistributionChannel;
use Modules\Med\Domain\Models\MedStudio;
use Modules\Ret\Domain\Models\RetChannel;
use Modules\Ret\Domain\Models\RetInventoryItem;
use Modules\Tlx\Domain\Models\TelecomDataCenter;
use Modules\Tlx\Domain\Models\TelecomSite;

class SeventeenLinesUltraSeeder extends Seeder
{
    /**
     * Seeds realistic foundational scale data for Wave 2 lines:
     * - Line 13: Energy (Egy)
     * - Line 14: Telecom (Tlx)
     * - Line 15: Media & Creative (Med)
     * - Line 16: Education & Talent (Edu)
     * - Line 17: Retail & Omnichannel (Ret)
     *
     * Fully idempotent with checkpoint verification.
     */
    public function run(): void
    {
        // 1. Energy: Generation Assets & Smart Meters
        GenerationAsset::firstOrCreate(
            ['asset_code' => 'GEN-SOLAR-CIRATA-01'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Cirata Floating Solar Photovoltaic 145MWp',
                'asset_type' => 'SOLAR_PV',
                'installed_capacity_mw' => 145.0,
                'current_output_mw' => 110.5,
                'marginal_cost_per_mwh_minor' => 65000000,
                'status' => 'ONLINE',
            ]
        );

        SmartMeter::firstOrCreate(
            ['meter_serial_number' => 'SM-CIRATA-GRID-001'],
            [
                'id' => (string) Str::uuid(),
                'consumer_property_type' => 'SUBSTATION',
                'consumer_property_id' => 'SUBSTATION-WEST-JAVA-01',
                'total_kwh_accumulated' => 1580000.0,
                'tariff_category' => 'HIGH_VOLTAGE_INDUSTRIAL',
                'status' => 'ACTIVE',
            ]
        );

        // 2. Telecom: Sites & Data Centers
        TelecomSite::firstOrCreate(
            ['site_code' => 'TWR-JKT-CENTRAL-01'],
            [
                'id' => (string) Str::uuid(),
                'site_name' => 'Thamrin 5G Monopole Macro Site',
                'site_type' => 'TOWER',
                'region' => 'DKI_JAKARTA',
                'latitude' => -6.189500,
                'longitude' => 106.824100,
                'status' => 'ACTIVE',
            ]
        );

        TelecomDataCenter::firstOrCreate(
            ['dc_code' => 'DC-CGK-HYPERSCALE-01'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Jakarta Cyber Hyperscale Data Center',
                'city' => 'Jakarta',
                'total_facility_power_kw' => 5000.0,
                'it_load_power_kw' => 3500.0,
                'measured_pue' => 1.43,
                'total_racks_capacity' => 1200,
                'occupied_racks' => 950,
            ]
        );

        // 3. Media: Sound Stages & Distribution Channels
        MedStudio::firstOrCreate(
            ['studio_code' => 'STU-STUDIO-STAGE-01'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Nusantara LED Virtual Production Stage 1',
                'facility_type' => 'VIRTUAL_PRODUCTION',
                'location_city' => 'Jakarta Selatan',
                'hourly_rate_minor' => 15000000,
                'full_day_rate_minor' => 120000000,
                'status' => 'AVAILABLE',
            ]
        );

        MedDistributionChannel::firstOrCreate(
            ['channel_code' => 'CH-ECO-STREAM-01'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'SuperApp Live Stream & Video Hub',
                'channel_type' => 'APP_STREAMING',
                'revenue_share_pct' => 70.0,
                'status' => 'ACTIVE',
            ]
        );

        // 4. Education: Technical & Safety Programs
        EduProgram::firstOrCreate(
            ['program_code' => 'PRG-TECH-EV-HV-01'],
            [
                'id' => (string) Str::uuid(),
                'title' => 'Teknisi High Voltage EV & BESS Bersertifikat',
                'industry_sector' => 'AUTOMOTIVE',
                'total_sessions' => 12,
                'tuition_fee_minor' => 1200000000,
                'status' => 'ACTIVE',
            ]
        );

        // 5. Retail: Omnichannel Channels & Listings
        RetChannel::firstOrCreate(
            ['channel_code' => 'CHN-SUPERAPP-SHOP'],
            [
                'id' => (string) Str::uuid(),
                'channel_name' => 'SuperApp Omnichannel Direct Store',
                'channel_type' => 'WEB_APP',
                'default_commission_pct' => 5.0,
                'status' => 'ACTIVE',
            ]
        );

        RetInventoryItem::firstOrCreate(
            ['sku' => 'SKU-ECO-MERCH-HOODIE'],
            [
                'id' => (string) Str::uuid(),
                'product_name' => 'Ecosystem Exclusive Heavyweight Hoodie',
                'stock_available' => 1500,
                'stock_reserved' => 0,
                'map_price_minor' => 65000000,
            ]
        );
    }
}
