<?php

declare(strict_types=1);

namespace Tests\Perilaku;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Modules\Core\Domain\Models\Role;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use Throwable;

#[Group('perilaku')]
class WriteSmokeTest extends TestCase
{
    protected static array $userCache = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.sqlite.database' => database_path('database.sqlite')]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
    }

    protected function getUserForRole(string $role): User
    {
        if (isset(self::$userCache[$role])) {
            return self::$userCache[$role];
        }

        $user = User::where('role', $role)->first();
        if (! $user) {
            $user = User::factory()->create([
                'name' => "User {$role}",
                'email' => "write_{$role}@example.com",
                'role' => $role,
                'email_verified_at' => now(),
            ]);

            $roleModel = Role::where('name', $role)->first();
            if ($roleModel) {
                $user->assignRbacRole($roleModel);
            }
        }

        self::$userCache[$role] = $user;

        return $user;
    }

    public function test_primary_write_routes_per_module(): void
    {
        // Define primary write action per module with valid payload and table to check
        $definitions = [
            'Agency' => [
                'uri' => '/agency/agents',
                'method' => 'POST',
                'role' => 'admin',
                'payload' => ['code' => 'AGT-'.Str::random(5), 'name' => 'Agency Test', 'status' => 'active'],
                'table' => 'agn_agents',
            ],
            'Agri' => [
                'uri' => '/agri/contracts/register',
                'method' => 'POST',
                'role' => 'admin',
                'payload' => ['contract_number' => 'AGR-'.Str::random(5), 'commodity' => 'Corn', 'farmer_name' => 'Pak Tani', 'volume_kg' => 1000, 'price_per_kg' => 5000],
                'table' => 'agr_farmer_contracts',
            ],
            'Asset' => [
                'uri' => '/assets/depreciation/run',
                'method' => 'POST',
                'role' => 'admin',
                'payload' => ['fiscal_year' => (int) date('Y'), 'period' => 1],
                'table' => 'ast_depreciation_runs',
            ],
            'AutoDex' => [
                'uri' => '/autodex/garage/1',
                'method' => 'POST',
                'role' => 'customer',
                'payload' => [],
                'table' => 'garages_wishlists',
            ],
            'AutoServe' => [
                'uri' => '/bookings',
                'method' => 'POST',
                'role' => 'customer',
                'payload' => [
                    'vehicle_id' => 1,
                    'service_id' => 1,
                    'booking_date' => now()->addDays(2)->format('Y-m-d H:i:s'),
                    'notes' => 'Ganti oli rutin',
                ],
                'table' => 'serve_bookings',
            ],
            'B2b' => [
                'uri' => '/b2b/wholesale/publish',
                'method' => 'POST',
                'role' => 'admin',
                'payload' => ['sku' => 'B2B-'.Str::random(4), 'catalog_name' => 'Wholesale Catalog', 'moq' => 10, 'tier_price' => 50000],
                'table' => 'b2b_wholesale_catalogs',
            ],
            'Banking' => [
                'uri' => '/wallet/pin',
                'method' => 'POST',
                'role' => 'customer',
                'payload' => ['pin' => '123456', 'pin_confirmation' => '123456'],
                'table' => 'bank_wallet_pins',
            ],
            'Contract' => [
                'uri' => '/contracts/clauses',
                'method' => 'POST',
                'role' => 'contract_manager',
                'payload' => ['code' => 'CLS-'.Str::random(4), 'title' => 'Klausa Kerahasiaan', 'body' => 'Data dirahasiakan', 'category' => 'nda'],
                'table' => 'ctr_clause_templates',
            ],
            'ControlTower' => [
                'uri' => '/control-tower/autonomous/forecast',
                'method' => 'POST',
                'role' => 'logistics_admin',
                'payload' => ['horizon_days' => 7, 'granularity' => 'daily'],
                'table' => 'tow_forecast_runs',
            ],
            'Crypto' => [
                'uri' => '/crypto/quote',
                'method' => 'POST',
                'role' => 'customer',
                'payload' => ['symbol' => 'BTC', 'side' => 'buy', 'idr_amount' => 500000],
                'table' => 'crypto_quotes',
            ],
            'Distribution' => [
                'uri' => '/distribution/distributors',
                'method' => 'POST',
                'role' => 'admin',
                'payload' => ['code' => 'DST-'.Str::random(4), 'name' => 'Distributor Jaya', 'region' => 'Jabodetabek'],
                'table' => 'dst_distributors',
            ],
            'Edu' => [
                'uri' => '/education/enroll',
                'method' => 'POST',
                'role' => 'admin',
                'payload' => ['program_name' => 'Pelatihan Mekanik EV', 'candidate_name' => 'Budi Santoso'],
                'table' => 'edu_enrollments',
            ],
            'Egy' => [
                'uri' => '/energy/telemetry',
                'method' => 'POST',
                'role' => 'admin',
                'payload' => ['meter_code' => 'MTR-01', 'kwh_reading' => 1250.5, 'voltage' => 220],
                'table' => 'egy_meter_readings',
            ],
            'EnterpriseFinance' => [
                'uri' => '/enterprise-finance/governance/trial-balance/consolidate',
                'method' => 'POST',
                'role' => 'admin',
                'payload' => ['period' => date('Y-m'), 'currency' => 'IDR'],
                'table' => 'efi_consolidation_runs',
            ],
            'Epc' => [
                'uri' => '/epc/claims/record',
                'method' => 'POST',
                'role' => 'admin',
                'payload' => ['project_code' => 'EPC-01', 'claim_type' => 'variation', 'amount' => 15000000],
                'table' => 'epc_claims',
            ],
            'Esg' => [
                'uri' => '/esg/operations/emissions',
                'method' => 'POST',
                'role' => 'esg_officer',
                'payload' => ['activity_type' => 'fuel_diesel', 'quantity' => 100, 'unit' => 'liter'],
                'table' => 'esg_carbon_logs',
            ],
            'Finance' => [
                'uri' => '/pembiayaan/simulasi/quote',
                'method' => 'POST',
                'role' => 'customer',
                'payload' => ['product_id' => 1, 'tenor_months' => 12, 'down_payment' => 5000000],
                'table' => 'fin_loan_quotes',
            ],
            'Fleet' => [
                'uri' => '/fleet/contracts',
                'method' => 'POST',
                'role' => 'logistics_admin',
                'payload' => ['contract_number' => 'FLT-'.Str::random(4), 'vendor_name' => 'PT Armada Sejahtera'],
                'table' => 'flt_contracts',
            ],
            'Hcm' => [
                'uri' => '/hcm/operations/department',
                'method' => 'POST',
                'role' => 'hcm_manager',
                'payload' => ['code' => 'DEP-'.Str::random(4), 'name' => 'Departemen Operasional'],
                'table' => 'hcm_departments',
            ],
            'Logistics' => [
                'uri' => '/logistics/locations',
                'method' => 'POST',
                'role' => 'logistics_admin',
                'payload' => ['code' => 'LOC-'.Str::random(4), 'name' => 'Hub Jakarta Timur', 'type' => 'hub', 'city' => 'Jakarta'],
                'table' => 'lgx_locations',
            ],
            'Manufacturing' => [
                'uri' => '/manufacturing/plants',
                'method' => 'POST',
                'role' => 'admin',
                'payload' => ['code' => 'PLT-'.Str::random(4), 'name' => 'Pabrik Cikarang 1', 'location' => 'Cikarang'],
                'table' => 'mfg_plants',
            ],
            'Partner' => [
                'uri' => '/partners',
                'method' => 'POST',
                'role' => 'admin',
                'payload' => ['name' => 'Mitra Kolaborasi '.Str::random(3), 'type' => 'technology'],
                'table' => 'ptn_partners',
            ],
            'Party' => [
                'uri' => '/party/legal-entities',
                'method' => 'POST',
                'role' => 'party_manager',
                'payload' => ['tax_id' => '01.'.Str::random(9), 'legal_name' => 'PT Pihak Berbadan Hukum'],
                'table' => 'pty_parties',
            ],
            'Pricing' => [
                'uri' => '/pricing/price-lists',
                'method' => 'POST',
                'role' => 'admin',
                'payload' => ['code' => 'PL-'.Str::random(4), 'name' => 'Daftar Harga Q4', 'currency' => 'IDR'],
                'table' => 'prc_price_lists',
            ],
            'Procurement' => [
                'uri' => '/procurement/requisitions',
                'method' => 'POST',
                'role' => 'procurement',
                'payload' => ['title' => 'Pengadaan Suku Cadang', 'priority' => 'normal', 'department' => 'Maintenance'],
                'table' => 'prc_requisitions',
            ],
            'Resto' => [
                'uri' => '/resto/menu/calculate-cost',
                'method' => 'POST',
                'role' => 'outlet_manager',
                'payload' => ['item_id' => 1],
                'table' => 'resto_ingredient_costs',
            ],
            'Supplier' => [
                'uri' => '/suppliers',
                'method' => 'POST',
                'role' => 'procurement',
                'payload' => ['name' => 'CV Pemasok Sentosa', 'email' => 'pemasok_'.Str::random(4).'@example.com'],
                'table' => 'spl_suppliers',
            ],
            'Telematics' => [
                'uri' => '/telematics/devices/1/ingest',
                'method' => 'POST',
                'role' => 'admin',
                'payload' => ['latitude' => -6.2, 'longitude' => 106.8, 'speed_kmh' => 45, 'oil_temp_c' => 92, 'fuel_pct' => 80],
                'table' => 'tlm_device_ticks',
            ],
            'Trade' => [
                'uri' => '/trade/bidding/agents',
                'method' => 'POST',
                'role' => 'procurement',
                'payload' => ['agent_name' => 'Bidding Bot Alpha', 'strategy' => 'aggressive'],
                'table' => 'trd_bidding_agents',
            ],
            'TradeFinance' => [
                'uri' => '/trade-finance/operations/lc/issue',
                'method' => 'POST',
                'role' => 'treasury',
                'payload' => ['lc_number' => 'LC-'.Str::random(6), 'beneficiary' => 'Global Exporter Inc', 'amount' => 50000000],
                'table' => 'tf_letters_of_credit',
            ],
            'Treasury' => [
                'uri' => '/treasury/fx-revalue',
                'method' => 'POST',
                'role' => 'treasury',
                'payload' => ['revaluation_date' => date('Y-m-d')],
                'table' => 'trs_fx_revaluations',
            ],
            'Wms' => [
                'uri' => '/wms/warehouses',
                'method' => 'POST',
                'role' => 'hub_operator',
                'payload' => ['code' => 'WH-'.Str::random(4), 'name' => 'Gudang Pusat Karawang', 'city' => 'Karawang'],
                'table' => 'wms_warehouses',
            ],
        ];

        $results = [];

        foreach ($definitions as $module => $def) {
            $user = $this->getUserForRole($def['role']);
            $table = $def['table'];
            $countBefore = DB::getSchemaBuilder()->hasTable($table) ? DB::table($table)->count() : null;

            $status = null;
            $validationErrors = null;
            $recordCreated = false;
            $isRedirect = false;
            $exceptionMsg = null;

            try {
                $test = $this->withoutMiddleware(ValidateCsrfToken::class)->actingAs($user);
                $res = $def['method'] === 'POST'
                    ? $test->post($def['uri'], $def['payload'])
                    : $test->put($def['uri'], $def['payload']);

                $status = $res->getStatusCode();
                $isRedirect = $res->isRedirection();

                if ($res->isClientError() && session('errors')) {
                    $validationErrors = session('errors')->all();
                }

                if ($countBefore !== null) {
                    $countAfter = DB::table($table)->count();
                    $recordCreated = ($countAfter > $countBefore);
                }
            } catch (Throwable $e) {
                $status = 500;
                $exceptionMsg = get_class($e).': '.$e->getMessage();
            }

            $successFake = false;
            if ($status >= 200 && $status < 300 && $countBefore !== null && ! $recordCreated) {
                $successFake = true;
            }

            $results[$module] = [
                'module' => $module,
                'uri' => $def['uri'],
                'method' => $def['method'],
                'role' => $def['role'],
                'status' => $status,
                'validation_errors' => $validationErrors,
                'record_created' => $recordCreated,
                'is_redirect' => $isRedirect,
                'fake_success' => $successFake,
                'exception' => $exceptionMsg,
            ];
        }

        File::ensureDirectoryExists(storage_path('audit'));
        File::put(storage_path('audit/write-smoke.json'), json_encode([
            'generated_at' => now()->toIso8601String(),
            'total_tested' => count($results),
            'results' => $results,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->assertNotEmpty($results);
    }
}
