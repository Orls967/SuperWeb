<?php

declare(strict_types=1);

namespace Tests\Keamanan\R3;

use App\Models\User;
use App\Security\BlindIndex;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Core\database\seeders\RbacSeeder;
use Modules\Integration\Application\Services\IntegrationService;
use Modules\Mall\Domain\Enums\TenantCategory;
use Modules\Mall\Domain\Models\Tenant;
use Modules\Party\Domain\Enums\PartyType;
use Modules\Party\Domain\Models\Party;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('keamanan')]
class R3VerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RbacSeeder::class, LedgerAccountsSeeder::class]);
    }

    /**
     * R3.1: Route Authorization Matrix with Real Database Fixtures.
     * Verifies that authorized roles return successful status (200/302) with real record,
     * while unauthorized roles receive 403 Forbidden (not 404).
     */
    public function test_r3_1_route_authorization_with_real_fixtures(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);

        $tenant = Tenant::create([
            'company_name' => 'PT Kopi Kenangan',
            'brand_name' => 'Kopi Kenangan',
            'category' => TenantCategory::FNB,
            'is_active' => true,
            'pic_name' => 'Budi',
            'pic_phone' => '08123456789',
            'pic_email' => 'budi@kopikenangan.test',
        ]);

        // 1. Authorized role accessing tenant details returns 200 (NOT 404)
        $authResponse = $this->actingAs($admin)->get(route('mall.tenants.show', $tenant));
        $this->assertEquals(200, $authResponse->status(), 'Authorized admin accessing real tenant must return 200 OK');

        // 2. Unauthorized role accessing tenant details returns 403 Forbidden
        $unauthResponse = $this->actingAs($customer)->get(route('mall.tenants.show', $tenant));
        $this->assertEquals(403, $unauthResponse->status(), 'Unauthorized customer accessing tenant must return 403 Forbidden');
    }

    /**
     * R3.2: Encryption and Blind Index Verification.
     * Raw DB ciphertext != plaintext; blind index matches deterministic query;
     * different pepper produces non-matching blind index.
     */
    public function test_r3_2_encryption_and_blind_index_behavior(): void
    {
        $rawNik = '3171012345678901';
        $rawNpwp = '012345678901234';

        $party = Party::create([
            'name' => 'PT Test Enkripsi',
            'name_normalized' => 'pt test enkripsi',
            'type' => PartyType::Company,
            'nik' => $rawNik,
            'npwp' => $rawNpwp,
        ]);

        // Inspect raw database values directly via PDO
        $rawRow = DB::table('pty_parties')->where('id', $party->id)->first();

        // 1. Raw DB values are ciphertexts (not plaintext)
        $this->assertNotEquals($rawNik, $rawRow->nik, 'Raw database NIK must be ciphertext');
        $this->assertNotEquals($rawNpwp, $rawRow->npwp, 'Raw database NPWP must be ciphertext');
        $this->assertNotEmpty($rawRow->nik_bidx, 'Blind index column must be populated');

        // 2. Blind index matches deterministic lookup
        $computedBidx = BlindIndex::forNik($rawNik);
        $this->assertEquals($computedBidx, $rawRow->nik_bidx, 'Blind index must match deterministic search token');

        // 3. Different pepper produces different blind index
        $differentPepperBidx = hash_hmac('sha256', $rawNik, 'different-test-pepper-999');
        $this->assertNotEquals($differentPepperBidx, $rawRow->nik_bidx, 'Different pepper must produce different blind index hash');
    }

    /**
     * R3.2: Salary Authorization across 3 roles.
     * Only admin and hcm_manager can view salary; operator is strictly denied.
     */
    public function test_r3_2_salary_authorization_three_roles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRbacRole('admin');

        $hcmManager = User::factory()->create(['role' => 'hcm_manager']);
        $hcmManager->assignRbacRole('hcm_manager');

        $operator = User::factory()->create(['role' => 'operator']);
        $operator->assignRbacRole('operator');

        $this->assertTrue(Gate::forUser($admin)->allows('view-any-salary'), 'Admin must have view-any-salary gate');
        $this->assertTrue(Gate::forUser($hcmManager)->allows('view-any-salary'), 'HCM Manager must have view-any-salary gate');
        $this->assertFalse(Gate::forUser($operator)->allows('view-any-salary'), 'Operator must NOT have view-any-salary gate');
    }

    /**
     * R3.2: API Key Database Scan.
     * Scans database tables to verify raw API secret key is never stored in plaintext.
     */
    public function test_r3_2_api_key_not_stored_plaintext_in_database(): void
    {
        $service = app(IntegrationService::class);
        $result = $service->registerApiClient('Secret Audit Client', 'PLATINUM');
        $rawKey = $result['raw_api_key'];

        // Scan integration api clients table
        $clientInDb = DB::table('intg_api_clients')->where('client_id', $result['client']->client_id)->first();

        $this->assertNotEquals($rawKey, $clientInDb->api_key_hash, 'Database must store hashed key, not raw API key');
        $this->assertEquals(hash('sha256', $rawKey), $clientInDb->api_key_hash, 'Stored hash must match SHA-256 of raw key');

        // Scan all text columns in SQLite database to confirm rawKey nowhere exists in plaintext
        $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        foreach ($tables as $table) {
            $tableName = $table->name;
            $rows = DB::table($tableName)->get();
            foreach ($rows as $row) {
                foreach ((array) $row as $col => $val) {
                    if (is_string($val) && strlen($val) > 10) {
                        $this->assertStringNotContainsString(
                            $rawKey,
                            $val,
                            "Raw API key must not exist plaintext in table {$tableName}.{$col}"
                        );
                    }
                }
            }
        }
    }

    /**
     * R3.4: API Client Rate Limiting (Limit 3 -> 4th request 429 + Retry-After + JSON).
     * Another client is unaffected.
     */
    public function test_r3_4_api_client_rate_limiting_and_isolation(): void
    {
        $service = app(IntegrationService::class);

        // Client 1 with limit 3 per minute
        $reg1 = $service->registerApiClient('Client One', 'CUSTOM');
        $reg1['client']->update(['rate_limit_per_minute' => 3]);
        $key1 = $reg1['raw_api_key'];

        // Client 2 with standard limit
        $reg2 = $service->registerApiClient('Client Two', 'SILVER');
        $key2 = $reg2['raw_api_key'];

        // 3 successful requests for Client 1
        for ($i = 0; $i < 3; $i++) {
            $res = $this->withHeaders(['X-API-Key' => $key1])->getJson(route('api.v1.integration.ping'));
            $res->assertOk();
        }

        // 4th request for Client 1 receives 429 Too Many Requests
        $overflow = $this->withHeaders(['X-API-Key' => $key1])->getJson(route('api.v1.integration.ping'));
        $overflow->assertStatus(429);
        $this->assertTrue($overflow->headers->has('Retry-After'));
        $overflow->assertJsonStructure(['message', 'client_id', 'limit_per_minute', 'retry_after']);

        // Client 2 is isolated and unaffected
        $resClient2 = $this->withHeaders(['X-API-Key' => $key2])->getJson(route('api.v1.integration.ping'));
        $resClient2->assertOk();
    }
}
