<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Security\BlindIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Domain\Models\OutboxSubscription;
use Modules\Hcm\Application\Services\HcmService;
use Modules\Hcm\Domain\Models\Employee;
use Modules\Integration\Application\Services\ApiIntegrationQualityGatesService;
use Modules\Integration\Application\Services\IntegrationService;
use Modules\Integration\Application\Services\PlatformEconomyService;
use Modules\Integration\Domain\Models\WebhookSubscription;
use Modules\Logistics\Domain\Models\WebhookEndpoint;
use Modules\Mall\Domain\Models\Tenant;
use Modules\Party\Domain\Models\LegalEntity;
use Modules\Party\Domain\Models\Party;
use Modules\Plm\Application\Services\PlmService;
use Modules\Plm\Domain\Models\LabNotebook;
use Tests\TestCase;

class SensitiveDataEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_nik_is_encrypted_and_searchable_via_blind_index(): void
    {
        $hcmService = app(HcmService::class);
        $rawNik = '3171012345670001';

        $employee = $hcmService->registerEmployee([
            'employee_number' => 'EMP-SEC-01',
            'name' => 'John Doe',
            'nik' => $rawNik,
            'basic_salary_idr' => 7000000,
            'allowances_idr' => 1500000,
            'bank_name' => 'Bank Mandiri',
            'bank_account_number' => '1230009876541',
        ]);

        // 1. Raw DB value must be ciphertext != plaintext
        $rawDb = DB::table('hcm_employees')->where('id', $employee->id)->first();
        $this->assertNotSame($rawNik, $rawDb->nik);
        $this->assertSame($rawNik, Crypt::decryptString($rawDb->nik));

        // 2. Blind index must match HMAC-SHA256
        $expectedBidx = BlindIndex::forNik($rawNik);
        $this->assertSame($expectedBidx, $rawDb->nik_bidx);

        // 3. Eloquent model transparently decrypts
        $loaded = Employee::find($employee->id);
        $this->assertSame($rawNik, $loaded->nik);

        // 4. Exact-match search via blind index works with formatted/unformatted variations
        $found1 = Employee::findByNik('3171012345670001');
        $this->assertNotNull($found1);
        $this->assertSame($employee->id, $found1->id);

        $found2 = Employee::findByNik('31.71.01-234567-0001');
        $this->assertNotNull($found2);
        $this->assertSame($employee->id, $found2->id);

        $found3 = Employee::findByNik(' 31 71 01 234567 0001 ');
        $this->assertNotNull($found3);
        $this->assertSame($employee->id, $found3->id);
    }

    public function test_mall_tenant_npwp_is_encrypted_and_searchable_via_blind_index(): void
    {
        $rawNpwp = '01.234.567.8-901.000';

        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'company_name' => 'PT Kopi Nusantara',
            'brand_name' => 'Kopi Nusantara',
            'pic_name' => 'Budi',
            'pic_phone' => '08123456789',
            'pic_email' => 'budi@kopinusantara.test',
            'npwp' => $rawNpwp,
        ]);

        $rawDb = DB::table('mall_tenants')->where('id', $tenant->id)->first();
        $this->assertNotSame($rawNpwp, $rawDb->npwp);
        $this->assertSame($rawNpwp, Crypt::decryptString($rawDb->npwp));

        $expectedBidx = BlindIndex::forNpwp($rawNpwp);
        $this->assertSame($expectedBidx, $rawDb->npwp_bidx);

        $found = Tenant::findByNpwp('012345678901000');
        $this->assertNotNull($found);
        $this->assertSame($tenant->id, $found->id);
    }

    public function test_party_and_legal_entity_pii_encryption_and_blind_index(): void
    {
        $npwp = '09.876.543.2-109.000';
        $nik = '3201015505900002';

        $legalEntity = LegalEntity::create([
            'name' => 'PT Sumber Makmur',
            'npwp' => $npwp,
        ]);

        $rawEntityDb = DB::table('pty_legal_entities')->where('id', $legalEntity->id)->first();
        $this->assertNotSame($npwp, $rawEntityDb->npwp);
        $this->assertSame(BlindIndex::forNpwp($npwp), $rawEntityDb->npwp_bidx);

        $foundEntity = LegalEntity::findByNpwp('098765432109000');
        $this->assertNotNull($foundEntity);
        $this->assertSame($legalEntity->id, $foundEntity->id);

        $party = Party::create([
            'name' => 'Siti Rahma',
            'name_normalized' => 'siti rahma',
            'nik' => $nik,
            'npwp' => $npwp,
        ]);

        $rawPartyDb = DB::table('pty_parties')->where('id', $party->id)->first();
        $this->assertNotSame($nik, $rawPartyDb->nik);
        $this->assertNotSame($npwp, $rawPartyDb->npwp);
        $this->assertSame(BlindIndex::forNik($nik), $rawPartyDb->nik_bidx);
        $this->assertSame(BlindIndex::forNpwp($npwp), $rawPartyDb->npwp_bidx);

        $foundPartyByNik = Party::findByNik('32.01.01-550590-0002');
        $this->assertNotNull($foundPartyByNik);
        $this->assertSame($party->id, $foundPartyByNik->id);

        $foundPartyByNpwp = Party::findByNpwp($npwp);
        $this->assertNotNull($foundPartyByNpwp);
        $this->assertSame($party->id, $foundPartyByNpwp->id);
    }

    public function test_plm_lab_notebook_formula_is_encrypted(): void
    {
        $plmService = app(PlmService::class);
        $project = $plmService->createProject(['name' => 'Secret Fuel Blend']);
        $secretFormula = 'PolymerX=40%;CatalystY=10%;SolventZ=50%';

        $notebook = $plmService->recordLabExperiment($project, 'EXP-SEC-99', 'Advanced Catalyst', $secretFormula);

        $rawDb = DB::table('plm_lab_notebooks')->where('id', $notebook->id)->first();
        $this->assertNotSame($secretFormula, $rawDb->formula_payload_encrypted);
        $this->assertNotSame(base64_encode($secretFormula), $rawDb->formula_payload_encrypted);

        $loaded = LabNotebook::find($notebook->id);
        $this->assertSame($secretFormula, $loaded->formula_payload_encrypted);
    }

    public function test_webhook_secrets_are_encrypted_in_database(): void
    {
        // 1. Integration WebhookSubscription
        $intgService = app(IntegrationService::class);
        $sub = $intgService->registerWebhook('PTN-TEST', 'order.paid', 'https://example.com/hook', 'my-super-secret-key-12345');

        $rawSub = DB::table('intg_webhook_subscriptions')->where('id', $sub->id)->first();
        $this->assertNotSame('my-super-secret-key-12345', $rawSub->secret_key);

        $loadedSub = WebhookSubscription::find($sub->id);
        $this->assertSame('my-super-secret-key-12345', $loadedSub->secret_key);

        // 2. Logistics WebhookEndpoint
        $endpoint = WebhookEndpoint::create([
            'url' => 'https://logistics.partner.test/webhook',
            'secret' => 'logistics-endpoint-secret-key',
            'events' => ['shipment.created'],
            'is_active' => true,
        ]);

        $rawEndpoint = DB::table('lgx_webhook_endpoints')->where('id', $endpoint->id)->first();
        $this->assertNotSame('logistics-endpoint-secret-key', $rawEndpoint->secret);

        $loadedEndpoint = WebhookEndpoint::find($endpoint->id);
        $this->assertSame('logistics-endpoint-secret-key', $loadedEndpoint->secret);

        // 3. Core OutboxSubscription
        $outbox = OutboxSubscription::create([
            'name' => 'Audit Subscriber',
            'target' => 'https://core.events.test/webhook',
            'secret' => 'core-outbox-secret-999',
            'events' => ['*'],
            'is_active' => true,
        ]);

        $rawOutbox = DB::table('core_outbox_subscriptions')->where('id', $outbox->id)->first();
        $this->assertNotSame('core-outbox-secret-999', $rawOutbox->secret);

        $loadedOutbox = OutboxSubscription::find($outbox->id);
        $this->assertSame('core-outbox-secret-999', $loadedOutbox->secret);
    }

    public function test_developer_and_partner_api_keys_are_one_way_hashed_in_database(): void
    {
        $platform = app(PlatformEconomyService::class);
        $dev = $platform->registerDeveloper('Key Test Dev', 'keytest@dev.test', 'PRO');

        $rawDev = DB::table('pe_developer_accounts')->where('id', $dev->id)->first();
        $this->assertSame(64, strlen($rawDev->api_key));
        $this->assertSame(hash('sha256', $dev->raw_api_key), $rawDev->api_key);
        $this->assertNotSame($dev->raw_api_key, $rawDev->api_key);

        $qualityService = app(ApiIntegrationQualityGatesService::class);
        $qualityService->registerPartner('PRT-KEY-01', 'Partner Key Corp');
        $qualityService->recordCertificationResults('PRT-KEY-01', true, true, true);
        $enabled = $qualityService->issueProductionEnablement('PRT-KEY-01', now()->addYear()->toDateString());

        $rawCert = DB::table('plt_api_partner_certifications')->where('partner_code', 'PRT-KEY-01')->first();
        $this->assertSame(64, strlen($rawCert->production_api_key));
        $this->assertSame(hash('sha256', $enabled->raw_api_key), $rawCert->production_api_key);
        $this->assertNotSame($enabled->raw_api_key, $rawCert->production_api_key);
    }
}
