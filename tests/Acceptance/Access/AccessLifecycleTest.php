<?php

declare(strict_types=1);

namespace Tests\Acceptance\Access;

use App\Models\User;
use App\Quality\Audit\Fixtures\AccessAuditCorruptionFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Application\Access\AbacPolicyService;
use Modules\Core\Application\Access\AccessGrantService;
use Modules\Core\Application\Access\IdentityGraphService;
use Modules\Core\Application\Access\VendorExposureService;
use Modules\Core\Domain\Models\CoreAccessGrant;
use Modules\Core\Domain\Models\CoreAccessGrantTransition;
use Modules\Core\Domain\Models\CoreAccessRecertificationCampaign;
use Modules\Core\Domain\Models\CoreAccessRecertificationItem;
use Modules\Core\Domain\Models\CoreTenant;
use Tests\TestCase;

class AccessLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private CoreTenant $tenantA;

    private CoreTenant $tenantB;

    private User $adminUser;

    private User $officerUser;

    private User $standardUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = CoreTenant::firstOrCreate(
            ['code' => 'TENANT-188-A'],
            ['id' => '0193188a-0001-7000-8000-000000000001', 'name' => 'Tenant Alpha Corp', 'tier' => 'PLATINUM', 'status' => 'active']
        );

        $this->tenantB = CoreTenant::firstOrCreate(
            ['code' => 'TENANT-188-B'],
            ['id' => '0193188b-0002-7000-8000-000000000002', 'name' => 'Tenant Beta Ltd', 'tier' => 'GOLD', 'status' => 'active']
        );

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
            'name' => 'System Admin',
            'email' => 'admin@superweb.test',
        ]);

        $this->officerUser = User::factory()->create([
            'role' => 'security_officer',
            'name' => 'Security Officer',
            'email' => 'security@superweb.test',
        ]);

        $this->standardUser = User::factory()->create([
            'role' => 'staff',
            'name' => 'Standard Staff',
            'email' => 'staff@superweb.test',
        ]);
    }

    /**
     * Skenario 188.1: RBAC + ABAC Multi-Dimensional Scoping.
     */
    public function test_188_1_abac_and_multi_dimensional_scoping(): void
    {
        $policyService = app(AbacPolicyService::class);

        // 1. Buat kebijakan ABAC berdimensi
        $policy = $policyService->createPolicy([
            'policy_code' => 'POL-WH-OPS-01',
            'name' => 'Warehouse Site Operations Policy',
            'target_resource' => 'wms:inventory',
            'effect' => 'ALLOW',
            'dimensions' => [
                'entity_id' => 'ENT-ALPHA',
                'region_id' => 'ID-JKT',
                'site_id' => ['WH-NORTH-01', 'WH-NORTH-02'],
                'valid_from' => now()->subDay()->toIso8601String(),
                'valid_to' => now()->addDays(7)->toIso8601String(),
            ],
            'is_active' => true,
        ]);

        // Buat aturan penolakan eksplisit (DENY) untuk sub-zona karantina
        $policyService->createPolicy([
            'policy_code' => 'POL-WH-DENY-QUARANTINE',
            'name' => 'Quarantine Zone Deny Override',
            'target_resource' => 'wms:inventory',
            'effect' => 'DENY',
            'dimensions' => [
                'site_id' => 'WH-NORTH-QUARANTINE',
            ],
            'is_active' => true,
        ]);

        // Uji evaluasi konteks cocok -> ALLOW
        $evalAllowed = $policyService->evaluateAccess(
            user: $this->standardUser,
            resource: 'wms:inventory',
            action: 'transfer',
            contextDimensions: [
                'entity_id' => 'ENT-ALPHA',
                'region_id' => 'ID-JKT',
                'site_id' => 'WH-NORTH-01',
            ]
        );
        $this->assertTrue($evalAllowed['allowed']);
        $this->assertEquals('ALLOW', $evalAllowed['decision']);

        // Uji evaluasi dimensi mismatch (wilayah salah) -> DENY
        $evalRegionDenied = $policyService->evaluateAccess(
            user: $this->standardUser,
            resource: 'wms:inventory',
            action: 'transfer',
            contextDimensions: [
                'entity_id' => 'ENT-ALPHA',
                'region_id' => 'ID-SBY', // Mismatch
                'site_id' => 'WH-NORTH-01',
            ]
        );
        $this->assertFalse($evalRegionDenied['allowed']);
        $this->assertEquals('DENY_DEFAULT', $evalRegionDenied['decision']);

        // Uji Deny-Overrides (site karantina) -> DENY
        $evalQuarantineDenied = $policyService->evaluateAccess(
            user: $this->standardUser,
            resource: 'wms:inventory',
            action: 'transfer',
            contextDimensions: [
                'entity_id' => 'ENT-ALPHA',
                'region_id' => 'ID-JKT',
                'site_id' => 'WH-NORTH-QUARANTINE',
            ]
        );
        $this->assertFalse($evalQuarantineDenied['allowed']);
        $this->assertEquals('DENY', $evalQuarantineDenied['decision']);
    }

    /**
     * Skenario 188.2: State Machine Siklus Hidup Access Grant 8 Status.
     */
    public function test_188_2_access_grant_state_machine_8_statuses(): void
    {
        $grantService = app(AccessGrantService::class);

        // 1. Pengajuan: requested
        $grant = $grantService->requestGrant([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->standardUser->id,
            'resource' => 'procurement:po',
            'action' => 'approve',
            'scope_dimensions' => ['entity_id' => 'ENT-ALPHA', 'limit_idr' => 10000000000],
            'valid_from' => now(),
            'valid_to' => now()->addDays(30),
            'reason' => 'Quarterly budget approval authority',
        ]);
        $this->assertEquals('requested', $grant->status);

        // 2. Pre-flight ABAC: policy_checked
        $grant = $grantService->checkPolicy($grant);
        $this->assertEquals('policy_checked', $grant->status);

        // 3. Approval pejabat: approved
        $grant = $grantService->approveGrant($grant, approver: $this->adminUser, notes: 'Approved for Q4');
        $this->assertEquals('approved', $grant->status);

        // 4. Provisi teknis: provisioned
        $grant = $grantService->provisionGrant($grant);
        $this->assertEquals('provisioned', $grant->status);

        // 5. Aktivasi sesi: active
        $grant = $grantService->activateGrant($grant);
        $this->assertEquals('active', $grant->status);

        // 6. Suspensi darurat: suspended
        $grant = $grantService->suspendGrant($grant, actor: $this->officerUser, reason: 'Temporary security audit');
        $this->assertEquals('suspended', $grant->status);

        // 7. Pemulihan: active kembali
        $grant = $grantService->unsuspendGrant($grant, actor: $this->officerUser, reason: 'Audit completed clear');
        $this->assertEquals('active', $grant->status);

        // 8. Pencabutan permanen: revoked
        $grant = $grantService->revokeGrant($grant, actor: $this->adminUser, reason: 'Role transferred to another division');
        $this->assertEquals('revoked', $grant->status);
        $this->assertNotNull($grant->revoked_at);

        // Verifikasi audit log imutabel append-only
        $transitions = CoreAccessGrantTransition::where('grant_id', $grant->id)->orderBy('id')->get();
        $this->assertCount(7, $transitions);
        $expectedStatusPath = [
            'policy_checked',
            'approved',
            'provisioned',
            'active',
            'suspended',
            'active',
            'revoked',
        ];
        $this->assertEquals($expectedStatusPath, $transitions->pluck('to_status')->all());
    }

    /**
     * Skenario 188.3: Pencabutan Hak Akses Sub-Detik & Invalidation Cache Versioning.
     */
    public function test_188_3_subsecond_revocation_and_grant_versioning(): void
    {
        $grantService = app(AccessGrantService::class);

        $grant = $grantService->requestGrant([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->standardUser->id,
            'resource' => 'finance:ledger',
            'action' => 'post',
            'scope_dimensions' => ['entity_id' => 'ENT-ALPHA'],
            'valid_from' => now()->subHour(),
            'valid_to' => now()->addDays(5),
            'reason' => 'Daily closing access',
        ]);
        $grant = $grantService->checkPolicy($grant);
        $grant = $grantService->approveGrant($grant, $this->adminUser);
        $grant = $grantService->provisionGrant($grant);
        $grant = $grantService->activateGrant($grant);

        $initialVersion = $grant->grant_version;
        $this->assertEquals(1, $initialVersion);

        // Sesi membawa token dengan versi 1 -> Akses Valid
        $isValidBefore = $grantService->isGrantActiveAndValid($grant->id, presentedVersion: 1);
        $this->assertTrue($isValidBefore);

        // Eksekusi pencabutan segera
        $grantService->revokeGrant($grant, actor: $this->officerUser, reason: 'Emergency credential compromised');

        // Versi cache naik seketika ke 2
        $cachedVersion = Cache::get("access_grant_version:{$grant->id}");
        $this->assertEquals(2, $cachedVersion);

        // Sesi lama yang masih membawa versi 1 seketika ditolak (sub-second)
        $isValidAfter = $grantService->isGrantActiveAndValid($grant->id, presentedVersion: 1);
        $this->assertFalse($isValidAfter);
    }

    /**
     * Skenario 188.4: Graf Klaster Identitas Multi-Pihak & Penegakan Persetujuan Ganda.
     */
    public function test_188_4_weighted_identity_graph_and_consent_enforcement(): void
    {
        $graphService = app(IdentityGraphService::class);

        $profileHotel = [
            'nik' => '3171012345670008',
            'email' => 'tamu.vip@example.com',
            'phone' => '+6281122334455',
            'name_dob' => 'ANDI WIJAYA:1990-03-21',
        ];

        $profileHospital = [
            'nik' => '3171012345670008', // Identik
            'email' => 'tamu.vip@example.com', // Identik
            'phone' => '081122334455', // Normalisasi cocok
            'name_dob' => 'Andi Wijaya:1990-03-21',
        ];

        // 1. Tautkan tanpa persetujuan pihak kedua -> BLOCKED_NO_CONSENT
        $linkPending = $graphService->linkProfiles(
            tenantId: $this->tenantA->id,
            subjectType: 'CUSTOMER',
            sourceEntityId: 101, // ID Profil Hotel
            targetEntityId: 202, // ID Profil RS
            sourceAttributes: $profileHotel,
            targetAttributes: $profileHospital,
            sourceConsent: true,
            targetConsent: false // Pasien belum memberi izin
        );
        $this->assertEquals('BLOCKED_NO_CONSENT', $linkPending->status);
        $this->assertFalse($linkPending->is_active);

        // Profil target hanya terlihat sebagai profil bayangan ter-anonimisasi
        $sharedProfile = $graphService->getConsolidatedProfile($linkPending->id);
        $this->assertTrue($sharedProfile['is_anonymized']);
        $this->assertEquals('SHADOW_PROFILE', $sharedProfile['name']);

        // 2. Berikan persetujuan ganda -> LINKED aktif
        $linkActive = $graphService->updateConsent($linkPending->id, targetConsent: true);
        $this->assertEquals('LINKED', $linkActive->status);
        $this->assertTrue($linkActive->is_active);

        // Profil kini dapat diakses secara penuh
        $sharedProfileActive = $graphService->getConsolidatedProfile($linkActive->id);
        $this->assertFalse($sharedProfileActive['is_anonymized']);
        $this->assertEquals('ANDI WIJAYA:1990-03-21', $sharedProfileActive['name']);
    }

    /**
     * Skenario 188.5: Agregasi Eksposur Kredit Grup Vendor Lintas Lini & Blokir PO.
     */
    public function test_188_5_vendor_group_credit_exposure_and_po_limit_block(): void
    {
        $vendorService = app(VendorExposureService::class);

        $clusterCode = 'CLUST-MEGA-SUPPLIER';
        $groupLimitIdr = 50000000000; // Limit IDR 500 Juta

        // 1. Simulasikan tranche liabilitas lintas modul (Procurement, Logistics, CloudKitchen)
        $tranches = [
            ['module' => 'Procurement', 'reference' => 'PO-001', 'amount_idr' => 25000000000, 'status' => 'OPEN'],
            ['module' => 'Logistics', 'reference' => 'GR-002', 'amount_idr' => 15000000000, 'status' => 'UNINVOICED'],
            ['module' => 'CloudKitchen', 'reference' => 'DEP-003', 'amount_idr' => 5000000000, 'status' => 'HELD'],
        ];

        // Total: 250M + 150M + 50M = 450 Juta (Limit 500 Juta -> Masih Aman)
        $snapshot = $vendorService->calculateAndSaveExposure($clusterCode, $tranches, $groupLimitIdr);
        $this->assertEquals(45000000000, $snapshot->total_exposure_idr);
        $this->assertFalse($snapshot->is_limit_breached);

        // Permohonan PO baru sebesar IDR 40 Juta -> Disetujui (Total 490 Juta <= 500 Juta)
        $canIssuePo1 = $vendorService->canIssueNewTranche($clusterCode, additionalAmountIdr: 4000000000);
        $this->assertTrue($canIssuePo1['allowed']);

        // Permohonan PO baru sebesar IDR 60 Juta -> Ditolak (Total 510 Juta > 500 Juta)
        $canIssuePo2 = $vendorService->canIssueNewTranche($clusterCode, additionalAmountIdr: 6000000000);
        $this->assertFalse($canIssuePo2['allowed']);
        $this->assertEquals('EXPOSURE_LIMIT_EXCEEDED', $canIssuePo2['reason']);
    }

    /**
     * Skenario 188.6: Probe Harian Isolasi Multi-Tenant & Kampanye Re-Sertifikasi.
     */
    public function test_188_6_continuous_multi_tenancy_probe_and_recertification_sweep(): void
    {
        // 1. Jalankan probe isolasi multi-tenant harian
        $exitCodeProbe = Artisan::call('access:tenant-probe', ['--samples' => 10]);
        $this->assertEquals(0, $exitCodeProbe);

        // 2. Buat kampanye re-sertifikasi dengan tenggat waktu kemarin
        $campaign = CoreAccessRecertificationCampaign::create([
            'campaign_code' => 'CAMP-2026-Q4-AUDIT',
            'title' => 'Q4 Mandatory Access Recertification',
            'deadline' => now()->subDay(), // Sudah lewat tenggat
            'status' => 'IN_PROGRESS',
        ]);

        $grantToRevoke = CoreAccessGrant::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->standardUser->id,
            'grant_code' => 'GRT-PENDING-RECERT',
            'resource' => 'asset:equipment',
            'action' => 'dispose',
            'status' => 'active',
            'valid_from' => now()->subMonth(),
            'valid_to' => now()->addMonth(),
            'grant_version' => 1,
        ]);

        $recertItem = CoreAccessRecertificationItem::create([
            'campaign_id' => $campaign->id,
            'grant_id' => $grantToRevoke->id,
            'response' => 'PENDING',
        ]);

        // Jalankan sweep re-sertifikasi otomatis
        $exitCodeSweep = Artisan::call('access:recert-campaign', ['--sweep' => true]);
        $this->assertEquals(0, $exitCodeSweep);

        // Grant yang tidak dijawab hingga tenggat waktu otomatis dicabut (revoked)
        $grantToRevoke->refresh();
        $this->assertEquals('revoked', $grantToRevoke->status);
        $this->assertNotNull($grantToRevoke->revoked_at);

        $recertItem->refresh();
        $this->assertEquals('AUTO_REVOKED', $recertItem->response);
    }

    /**
     * Skenario 188.7: Audit Tata Kelola Akses Bersih vs Fixture Korupsi.
     */
    public function test_188_7_access_audit_clean_and_corruption_fixture(): void
    {
        // 1. Audit dalam kondisi data bersih -> Exit Code 0
        $exitClean = Artisan::call('access:audit');
        $this->assertEquals(0, $exitClean);

        // 2. Terapkan fixture korupsi sabotase (grant active tanpa riwayat approved)
        $fixture = new AccessAuditCorruptionFixture;
        $corruptId = $fixture->inject();

        // Audit mendeteksi korupsi -> Exit Code 1
        $exitCorrupt = Artisan::call('access:audit');
        $this->assertEquals(1, $exitCorrupt);

        // Pulihkan fixture -> Audit kembali bersih
        $fixture->restore($corruptId);
        $exitRestored = Artisan::call('access:audit');
        $this->assertEquals(0, $exitRestored);
    }
}
