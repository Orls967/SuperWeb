<?php

declare(strict_types=1);

namespace Modules\Core\database\seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Enums\ViolationCategory;
use Modules\Core\Domain\Enums\ViolationStatus;
use Modules\Core\Domain\Models\CoreBoundaryViolation;
use Modules\Core\Domain\Models\CoreBoundaryViolationTransition;

/**
 * Seeder T1 Batas Modul & Kepemilikan Data (T-6 / W8-P4.1).
 *
 * Mengisi basis data skala T1 secara deterministik:
 * - >= 25 BoundaryViolation mencakup 7 status (detected, triaged, contract_designed, migrating, verified, closed, waived)
 * - >= 5 waived dengan anchor DECISIONS dan expires_at (>= 2 sudah expired)
 * - Riwayat transisi fakta append-only tercatat di core_boundary_violation_transitions
 */
class BoundariesT1Seeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::parse('2026-11-11 10:00:00');

        // 1. Bersihkan tabel agar idempoten
        DB::table('core_boundary_violation_transitions')->delete();
        DB::table('core_boundary_violations')->delete();

        // 1b. Sinkronkan pelanggaran nyata hasil scan ke core_boundary_violations
        Artisan::call('boundary:triage');

        // 2. Daftar 28 pelanggaran representatif lintas berbagai modul dan status
        $violationsData = [
            // Status: DETECTED (4)
            [
                'rule_id' => 'A2',
                'category' => ViolationCategory::TABLE_PREFIX,
                'file_path' => 'modules/Agri/database/migrations/2026_10_07_860100_create_ndvi_and_dao_tables.php',
                'line_number' => 41,
                'signature' => "Schema::create('gov_proposals')",
                'client_module' => 'Agri',
                'owner_module' => 'Governance',
                'status' => ViolationStatus::DETECTED,
            ],
            [
                'rule_id' => 'A2',
                'category' => ViolationCategory::TABLE_PREFIX,
                'file_path' => 'modules/CloudKitchen/database/migrations/2026_10_07_740100_create_resto_ck_tables.php',
                'line_number' => 14,
                'signature' => "Schema::create('resto_menus')",
                'client_module' => 'CloudKitchen',
                'owner_module' => 'Resto',
                'status' => ViolationStatus::DETECTED,
            ],
            [
                'rule_id' => 'A2',
                'category' => ViolationCategory::TABLE_PREFIX,
                'file_path' => 'modules/Hcm/database/migrations/2026_10_07_850100_create_bounty_gig_tables.php',
                'line_number' => 12,
                'signature' => "Schema::create('pay_payouts')",
                'client_module' => 'Hcm',
                'owner_module' => 'Payment',
                'status' => ViolationStatus::DETECTED,
            ],
            [
                'rule_id' => 'A1',
                'category' => ViolationCategory::DOMAIN_IMPORT,
                'file_path' => 'modules/Hotel/Domain/Services/HotelBanquetReservation.php',
                'line_number' => 22,
                'signature' => 'use Modules\Resto\Domain\Models\Menu',
                'client_module' => 'Hotel',
                'owner_module' => 'Resto',
                'status' => ViolationStatus::DETECTED,
            ],

            // Status: TRIAGED (4)
            [
                'rule_id' => 'A2',
                'category' => ViolationCategory::TABLE_PREFIX,
                'file_path' => 'modules/Agri/database/migrations/2026_10_07_860100_create_ndvi_and_dao_tables.php',
                'line_number' => 57,
                'signature' => "Schema::create('gov_votes')",
                'client_module' => 'Agri',
                'owner_module' => 'Governance',
                'status' => ViolationStatus::TRIAGED,
            ],
            [
                'rule_id' => 'A2',
                'category' => ViolationCategory::TABLE_PREFIX,
                'file_path' => 'modules/CloudKitchen/database/migrations/2026_10_07_740100_create_resto_ck_tables.php',
                'line_number' => 28,
                'signature' => "Schema::create('resto_orders')",
                'client_module' => 'CloudKitchen',
                'owner_module' => 'Resto',
                'status' => ViolationStatus::TRIAGED,
            ],
            [
                'rule_id' => 'A1',
                'category' => ViolationCategory::DOMAIN_IMPORT,
                'file_path' => 'modules/Mining/Application/Services/SmelterOreBooking.php',
                'line_number' => 15,
                'signature' => 'use Modules\Manufacturing\Domain\Models\Plant',
                'client_module' => 'Mining',
                'owner_module' => 'Manufacturing',
                'status' => ViolationStatus::TRIAGED,
            ],
            [
                'rule_id' => 'A2',
                'category' => ViolationCategory::TABLE_PREFIX,
                'file_path' => 'modules/Hcm/database/migrations/2026_10_07_850100_create_bounty_gig_tables.php',
                'line_number' => 28,
                'signature' => "Schema::create('pay_wallets')",
                'client_module' => 'Hcm',
                'owner_module' => 'Payment',
                'status' => ViolationStatus::TRIAGED,
            ],

            // Status: CONTRACT_DESIGNED (4)
            [
                'rule_id' => 'A1',
                'category' => ViolationCategory::DOMAIN_IMPORT,
                'file_path' => 'modules/Hospital/Domain/Services/EmergencyReferralService.php',
                'line_number' => 19,
                'signature' => 'use Modules\Med\Domain\Models\Prescription',
                'client_module' => 'Hospital',
                'owner_module' => 'Med',
                'status' => ViolationStatus::CONTRACT_DESIGNED,
                'contract_name' => 'Modules\Med\Contracts\PrescriptionReader',
                'adr_anchor' => 'docs/DECISIONS.md#w8-d44',
            ],
            [
                'rule_id' => 'A1',
                'category' => ViolationCategory::DOMAIN_IMPORT,
                'file_path' => 'modules/Aviation/Application/Services/GroundHandlingService.php',
                'line_number' => 31,
                'signature' => 'use Modules\Logistics\Domain\Models\CargoUnit',
                'client_module' => 'Aviation',
                'owner_module' => 'Logistics',
                'status' => ViolationStatus::CONTRACT_DESIGNED,
                'contract_name' => 'Modules\Logistics\Contracts\CargoUnitLookup',
                'adr_anchor' => 'docs/DECISIONS.md#w8-d41',
            ],
            [
                'rule_id' => 'A1',
                'category' => ViolationCategory::DOMAIN_IMPORT,
                'file_path' => 'modules/Retail/Application/Actions/ProcessReturnOrderAction.php',
                'line_number' => 25,
                'signature' => 'use Modules\Inventory\Domain\Models\StockItem',
                'client_module' => 'Retail',
                'owner_module' => 'Inventory',
                'status' => ViolationStatus::CONTRACT_DESIGNED,
                'contract_name' => 'Modules\Inventory\Contracts\StockAdjuster',
                'adr_anchor' => 'docs/DECISIONS.md#w8-d44',
            ],
            [
                'rule_id' => 'A1',
                'category' => ViolationCategory::DOMAIN_IMPORT,
                'file_path' => 'modules/Esg/Application/Services/EmissionOffsetService.php',
                'line_number' => 18,
                'signature' => 'use Modules\Forest\Domain\Models\ForestParcel',
                'client_module' => 'Esg',
                'owner_module' => 'Forest',
                'status' => ViolationStatus::CONTRACT_DESIGNED,
                'contract_name' => 'Modules\Forest\Contracts\ParcelCarbonEvaluator',
                'adr_anchor' => 'docs/DECISIONS.md#w8-d41',
            ],

            // Status: MIGRATING (3)
            [
                'rule_id' => 'A1',
                'category' => ViolationCategory::DOMAIN_IMPORT,
                'file_path' => 'modules/Proptech/Application/Actions/LeaseTenancyBillingAction.php',
                'line_number' => 14,
                'signature' => 'use Modules\Finance\Domain\Models\Invoice',
                'client_module' => 'Proptech',
                'owner_module' => 'Finance',
                'status' => ViolationStatus::MIGRATING,
                'contract_name' => 'Modules\Finance\Contracts\InvoiceGenerator',
            ],
            [
                'rule_id' => 'A1',
                'category' => ViolationCategory::DOMAIN_IMPORT,
                'file_path' => 'modules/Edu/Application/Services/StudentTuitionService.php',
                'line_number' => 29,
                'signature' => 'use Modules\Banking\Domain\Models\LedgerAccount',
                'client_module' => 'Edu',
                'owner_module' => 'Banking',
                'status' => ViolationStatus::MIGRATING,
                'contract_name' => 'Modules\Banking\Contracts\Ledger',
            ],
            [
                'rule_id' => 'A2',
                'category' => ViolationCategory::TABLE_PREFIX,
                'file_path' => 'modules/Hcm/database/migrations/2026_10_07_850100_create_bounty_gig_tables.php',
                'line_number' => 41,
                'signature' => "Schema::create('pay_transactions')",
                'client_module' => 'Hcm',
                'owner_module' => 'Payment',
                'status' => ViolationStatus::MIGRATING,
            ],

            // Status: VERIFIED (3)
            [
                'rule_id' => 'A1',
                'category' => ViolationCategory::DOMAIN_IMPORT,
                'file_path' => 'modules/AutoServe/Application/Listeners/CreatePredictiveMaintenanceBooking.php',
                'line_number' => 15,
                'signature' => 'use Modules\Core\Contracts\VehicleSnapshot',
                'client_module' => 'AutoServe',
                'owner_module' => 'Core',
                'status' => ViolationStatus::VERIFIED,
                'contract_name' => 'Modules\Core\Contracts\VehicleSnapshot',
            ],
            [
                'rule_id' => 'A1',
                'category' => ViolationCategory::DOMAIN_IMPORT,
                'file_path' => 'modules/Logistics/Application/Actions/DeliverVehicleByCarrierAction.php',
                'line_number' => 16,
                'signature' => 'use Modules\Core\Contracts\VehicleDirectory',
                'client_module' => 'Logistics',
                'owner_module' => 'Core',
                'status' => ViolationStatus::VERIFIED,
                'contract_name' => 'Modules\Core\Contracts\VehicleDirectory',
            ],
            [
                'rule_id' => 'A1',
                'category' => ViolationCategory::DOMAIN_IMPORT,
                'file_path' => 'modules/Mall/Application/Actions/CheckInVehicleAction.php',
                'line_number' => 14,
                'signature' => 'use Modules\Core\Contracts\VehicleModelResolver',
                'client_module' => 'Mall',
                'owner_module' => 'Core',
                'status' => ViolationStatus::VERIFIED,
                'contract_name' => 'Modules\Core\Contracts\VehicleModelResolver',
            ],

            // Status: CLOSED (4)
            [
                'rule_id' => 'A1',
                'category' => ViolationCategory::DOMAIN_IMPORT,
                'file_path' => 'modules/Store/Application/Actions/ListVehicleForSaleAction.php',
                'line_number' => 14,
                'signature' => 'use Modules\Core\Domain\Models\Vehicle',
                'client_module' => 'Store',
                'owner_module' => 'Core',
                'status' => ViolationStatus::CLOSED,
                'contract_name' => 'Modules\Core\Contracts\VehicleModelResolver',
            ],
            [
                'rule_id' => 'A1',
                'category' => ViolationCategory::DOMAIN_IMPORT,
                'file_path' => 'modules/Store/Application/Actions/PurchaseC2cVehicleAction.php',
                'line_number' => 15,
                'signature' => 'use Modules\Core\Domain\Models\Vehicle',
                'client_module' => 'Store',
                'owner_module' => 'Core',
                'status' => ViolationStatus::CLOSED,
                'contract_name' => 'Modules\Core\Contracts\VehicleDirectory',
            ],
            [
                'rule_id' => 'A1',
                'category' => ViolationCategory::DOMAIN_IMPORT,
                'file_path' => 'modules/Store/Http/Controllers/C2cController.php',
                'line_number' => 20,
                'signature' => 'use Modules\Core\Domain\Models\Vehicle',
                'client_module' => 'Store',
                'owner_module' => 'Core',
                'status' => ViolationStatus::CLOSED,
                'contract_name' => 'Modules\Core\Contracts\VehicleDirectory',
            ],
            [
                'rule_id' => 'A1',
                'category' => ViolationCategory::DOMAIN_IMPORT,
                'file_path' => 'modules/Mall/Application/Actions/RegisterParkingMemberAction.php',
                'line_number' => 13,
                'signature' => 'use Modules\Core\Domain\Models\Vehicle',
                'client_module' => 'Mall',
                'owner_module' => 'Core',
                'status' => ViolationStatus::CLOSED,
                'contract_name' => 'Modules\Core\Contracts\VehicleSnapshot',
            ],

            // Status: WAIVED (6 - including 2 expired)
            [
                'rule_id' => 'A2',
                'category' => ViolationCategory::TABLE_PREFIX,
                'file_path' => 'modules/Pricing/database/migrations/2026_10_07_810100_create_algorithmic_pricing_tables.php',
                'line_number' => 12,
                'signature' => "Schema::create('prc_frozen_quotes')",
                'client_module' => 'Pricing',
                'owner_module' => 'Procurement',
                'status' => ViolationStatus::WAIVED,
                'adr_anchor' => 'docs/DECISIONS.md#w8-d43',
                'waiver_reason' => 'Legacy algorithmic pricing quotes waiver prior to harmonized pric_ prefix',
                'waived_by' => 'lead-architect',
                'waived_at' => $now->copy()->subDays(10),
                'expires_at' => $now->copy()->addDays(50), // active
            ],
            [
                'rule_id' => 'A2',
                'category' => ViolationCategory::TABLE_PREFIX,
                'file_path' => 'modules/Pricing/database/migrations/2026_10_07_810100_create_algorithmic_pricing_tables.php',
                'line_number' => 25,
                'signature' => "Schema::create('prc_price_ticks')",
                'client_module' => 'Pricing',
                'owner_module' => 'Procurement',
                'status' => ViolationStatus::WAIVED,
                'adr_anchor' => 'docs/DECISIONS.md#w8-d43',
                'waiver_reason' => 'Legacy pricing ticks waiver prior to table partitioning',
                'waived_by' => 'lead-architect',
                'waived_at' => $now->copy()->subDays(10),
                'expires_at' => $now->copy()->addDays(60), // active
            ],
            [
                'rule_id' => 'A2',
                'category' => ViolationCategory::TABLE_PREFIX,
                'file_path' => 'modules/Vending/database/migrations/2026_10_07_820100_create_vending_core_tables.php',
                'line_number' => 14,
                'signature' => "Schema::create('ven_beverage_vending_machines')",
                'client_module' => 'Vending',
                'owner_module' => 'Venue',
                'status' => ViolationStatus::WAIVED,
                'adr_anchor' => 'docs/DECISIONS.md#w8-d43',
                'waiver_reason' => 'Vending machines legacy prefix waiver prior to vnd_ prefix harmonization',
                'waived_by' => 'lead-architect',
                'waived_at' => $now->copy()->subDays(15),
                'expires_at' => $now->copy()->addDays(75), // active
            ],
            [
                'rule_id' => 'A2',
                'category' => ViolationCategory::TABLE_PREFIX,
                'file_path' => 'modules/Vending/database/migrations/2026_10_07_820100_create_vending_core_tables.php',
                'line_number' => 28,
                'signature' => "Schema::create('ven_beverage_sales_transactions')",
                'client_module' => 'Vending',
                'owner_module' => 'Venue',
                'status' => ViolationStatus::WAIVED,
                'adr_anchor' => 'docs/DECISIONS.md#w8-d43',
                'waiver_reason' => 'Vending transactions prefix waiver pending ledger migration',
                'waived_by' => 'lead-architect',
                'waived_at' => $now->copy()->subDays(20),
                'expires_at' => $now->copy()->addDays(40), // active
            ],
            // Expired waiver 1
            [
                'rule_id' => 'A2',
                'category' => ViolationCategory::TABLE_PREFIX,
                'file_path' => 'modules/Pricing/database/migrations/2026_10_07_810100_create_algorithmic_pricing_tables.php',
                'line_number' => 45,
                'signature' => "Schema::create('prc_quote_rate_limits')",
                'client_module' => 'Pricing',
                'owner_module' => 'Procurement',
                'status' => ViolationStatus::WAIVED,
                'adr_anchor' => 'docs/DECISIONS.md#w8-d43',
                'waiver_reason' => 'Temporary quote rate limit waiver (expired)',
                'waived_by' => 'lead-architect',
                'waived_at' => $now->copy()->subDays(95),
                'expires_at' => $now->copy()->subDays(5), // expired
            ],
            // Expired waiver 2
            [
                'rule_id' => 'A2',
                'category' => ViolationCategory::TABLE_PREFIX,
                'file_path' => 'modules/Vending/database/migrations/2026_10_07_820100_create_vending_core_tables.php',
                'line_number' => 60,
                'signature' => "Schema::create('ven_beverage_alert_logs')",
                'client_module' => 'Vending',
                'owner_module' => 'Venue',
                'status' => ViolationStatus::WAIVED,
                'adr_anchor' => 'docs/DECISIONS.md#w8-d43',
                'waiver_reason' => 'Alert logs prefix temporary exemption (expired)',
                'waived_by' => 'lead-architect',
                'waived_at' => $now->copy()->subDays(92),
                'expires_at' => $now->copy()->subDays(2), // expired
            ],
        ];

        // 3. Masukkan data dan riwayat transisi State Machine
        foreach ($violationsData as $item) {
            $status = $item['status'];
            unset($item['status']);

            $violation = CoreBoundaryViolation::updateOrCreate(
                [
                    'rule_id' => $item['rule_id'],
                    'file_path' => $item['file_path'],
                    'signature' => $item['signature'],
                ],
                array_merge($item, [
                    'status' => $status->value,
                ])
            );

            // Catat transisi awal
            CoreBoundaryViolationTransition::create([
                'violation_id' => $violation->id,
                'actor' => $violation->triaged_by ?? $violation->waived_by ?? 'system:detector',
                'from_status' => ViolationStatus::DETECTED->value,
                'to_status' => $status->value,
                'reason' => 'T1 seeding initial state transition',
                'occurred_at' => $now,
            ]);
        }
    }
}
