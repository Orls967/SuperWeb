<?php

declare(strict_types=1);

namespace Modules\Party\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Party\Application\Actions\ApproveKycDocumentAction;
use Modules\Party\Application\Actions\SubmitKycDocumentAction;
use Modules\Party\Application\Services\CreditScoringService;
use Modules\Party\Application\Services\PartyService;
use Modules\Party\Application\Services\SanctionScreeningService;
use Modules\Party\Domain\Enums\KybStatus;
use Modules\Party\Domain\Enums\KycDocumentStatus;
use Modules\Party\Domain\Enums\KycDocumentType;
use Modules\Party\Domain\Enums\PartyRoleType;
use Modules\Party\Domain\Enums\PartyStatus;
use Modules\Party\Domain\Enums\PartyType;
use Modules\Party\Domain\Enums\SanctionCheckStatus;
use Modules\Party\Domain\Models\Party;
use Modules\Party\Exceptions\DuplicatePartyException;
use Modules\Party\Exceptions\InvalidKycTransitionException;
use Modules\Party\Exceptions\InvalidPartyTransitionException;
use Tests\TestCase;

class PartyTest extends TestCase
{
    use RefreshDatabase;

    private PartyService $partyService;

    private SanctionScreeningService $screeningService;

    private CreditScoringService $creditService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->partyService = app(PartyService::class);
        $this->screeningService = app(SanctionScreeningService::class);
        $this->creditService = app(CreditScoringService::class);
    }

    // =========================================================================
    // (a) Happy Path: Lifecycle Party, KYC Submission, Approval, Transition
    // =========================================================================

    public function test_happy_path_party_creation_and_initial_role(): void
    {
        $party = $this->partyService->create([
            'type' => PartyType::Company->value,
            'name' => 'PT Harapan Bangsa Makmur',
            'short_name' => 'HBM',
            'npwp' => '01.234.567.8-999.000',
            'nib' => '9120009990001',
            'role' => PartyRoleType::Supplier->value,
            'credit_limit_idr' => 50_000_000,
        ]);

        $this->assertDatabaseHas('pty_parties', [
            'id' => $party->id,
            'name' => 'PT Harapan Bangsa Makmur',
            'status' => 'pending',
            'kyb_status' => 'pending',
        ]);

        $this->assertTrue($party->hasRole(PartyRoleType::Supplier->value));
        $this->assertNotNull($party->creditProfile);
        $this->assertEquals(50_000_000, $party->creditProfile->credit_limit_idr);
        $this->assertEquals(50_000_000, $party->creditProfile->availableCredit());
    }

    public function test_happy_path_kyc_document_submission_and_approval_flow(): void
    {
        $party = $this->partyService->create([
            'name' => 'PT Mitra Sejati Logistik',
            'npwp' => '02.345.678.9-888.000',
        ]);

        // Screen party so it has a clear sanction check
        $this->screeningService->screen($party, 'onboarding');

        // Submit KYC Document
        $submitAction = app(SubmitKycDocumentAction::class);
        $doc = $submitAction->execute($party, [
            'document_type' => KycDocumentType::Nib->value,
            'document_number' => 'NIB-2026-999',
            'issuer' => 'BKPM/OSS',
            'expires_at' => now()->addYear()->toDateString(),
        ]);

        $party->refresh();
        $this->assertEquals(KycDocumentStatus::Pending, $doc->status);
        $this->assertEquals(KybStatus::InReview, $party->kyb_status);

        // Approve KYC Document
        $approveAction = app(ApproveKycDocumentAction::class);
        $approvedDoc = $approveAction->execute($doc, 'Admin AutoServe');

        $party->refresh();
        $this->assertEquals(KycDocumentStatus::Approved, $approvedDoc->status);
        $this->assertEquals(KybStatus::Verified, $party->kyb_status);
        $this->assertEquals(PartyStatus::Verified, $party->status);
    }

    // =========================================================================
    // (b) Validasi: NPWP Duplikat Hard Block & Invalid Transitions
    // =========================================================================

    public function test_duplicate_npwp_throws_duplicate_party_exception(): void
    {
        $this->partyService->create([
            'name' => 'PT Pertama Sejahtera',
            'npwp' => '01.111.222.3-444.000',
        ]);

        $this->expectException(DuplicatePartyException::class);

        $this->partyService->create([
            'name' => 'PT Cabang Kedua',
            'npwp' => '01.111.222.3-444.000', // same NPWP
        ]);
    }

    public function test_invalid_party_status_transition_is_blocked(): void
    {
        $party = $this->partyService->create([
            'name' => 'PT Transisi Test',
            'npwp' => '01.222.333.4-555.000',
        ]);

        // Transition pending -> blacklisted
        $this->partyService->transitionStatus($party, PartyStatus::Blacklisted);

        // Transitioning from blacklisted is disallowed by domain rules
        $this->expectException(InvalidPartyTransitionException::class);
        $this->partyService->transitionStatus($party, PartyStatus::Verified);
    }

    public function test_approving_non_pending_kyc_document_throws_exception(): void
    {
        $party = $this->partyService->create(['name' => 'PT KYC Strict', 'npwp' => '01.333.444.5-666.000']);
        $doc = app(SubmitKycDocumentAction::class)->execute($party, [
            'document_type' => KycDocumentType::Npwp->value,
            'document_number' => '01.333.444.5-666.000',
        ]);

        $approveAction = app(ApproveKycDocumentAction::class);
        $approveAction->execute($doc, 'Admin');

        // Trying to approve again
        $this->expectException(InvalidKycTransitionException::class);
        $approveAction->execute($doc->fresh(), 'Admin');
    }

    // =========================================================================
    // (c) Idempotensi: addRole, screening 24h, backfill-links
    // =========================================================================

    public function test_add_role_is_idempotent(): void
    {
        $party = $this->partyService->create(['name' => 'PT Multi Role Corp', 'npwp' => '01.444.555.6-777.000']);

        $role1 = $this->partyService->addRole($party, PartyRoleType::Carrier);
        $role2 = $this->partyService->addRole($party, PartyRoleType::Carrier);

        $this->assertEquals($role1->id, $role2->id);
        $this->assertEquals(1, $party->roles()->where('role', PartyRoleType::Carrier->value)->count());
    }

    public function test_sanctions_screening_is_idempotent_within_24_hours(): void
    {
        $party = $this->partyService->create(['name' => 'PT Bersih Aman Damai', 'npwp' => '01.555.666.7-888.000']);

        $check1 = $this->screeningService->screen($party, 'onboarding');
        $check2 = $this->screeningService->screen($party, 'onboarding');

        $this->assertEquals($check1->id, $check2->id);
        $this->assertEquals(1, $party->sanctionsChecks()->count());
    }

    public function test_backfill_party_links_command_is_idempotent(): void
    {
        $party = $this->partyService->create(['name' => 'PT Fast Cargo Nusantara', 'npwp' => '01.666.777.8-999.000']);

        // Insert carrier without party_id
        $carrierId = DB::table('lgx_carriers')->insertGetId([
            'code' => 'CR-FAST',
            'name' => 'PT Fast Cargo Nusantara',
            'mode' => 'trucking',
            'payment_terms_days' => 30,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('party:backfill-links')->assertSuccessful();

        $linkedPartyId = DB::table('lgx_carriers')->where('id', $carrierId)->value('party_id');
        $this->assertEquals($party->id, $linkedPartyId);

        // Run again: remains linked and exits 0 cleanly
        $this->artisan('party:backfill-links')->assertSuccessful();
    }

    // =========================================================================
    // (d) Merge & Reversal: Party Merge dengan Audit Trail
    // =========================================================================

    public function test_merge_transfers_roles_and_is_reversible(): void
    {
        $source = $this->partyService->create([
            'name' => 'PT Sumber Lama',
            'npwp' => '01.777.888.9-000.000',
            'role' => PartyRoleType::Supplier->value,
        ]);
        $target = $this->partyService->create([
            'name' => 'PT Sumber Konsolidasi',
            'npwp' => '01.888.999.0-111.000',
            'role' => PartyRoleType::Carrier->value,
        ]);

        // Merge source into target
        $this->partyService->merge(
            source: $source,
            target: $target,
            rule: 'manual',
            reason: 'Akuisisi korporasi',
            performer: 'SuperAdmin'
        );

        $source->refresh();
        $target->refresh();

        $this->assertFalse($source->is_active);
        $this->assertEquals($target->id, $source->merged_into_id);
        $this->assertTrue($target->hasRole(PartyRoleType::Supplier->value));
        $this->assertTrue($target->hasRole(PartyRoleType::Carrier->value));

        // Verify merge log
        $mergeLog = DB::table('pty_merge_logs')->where('source_party_id', $source->id)->first();
        $this->assertNotNull($mergeLog);
        $this->assertFalse((bool) $mergeLog->reversed);

        // Reverse Merge
        $this->partyService->reverseMerge($mergeLog->id, 'Koreksi administratif');

        $source->refresh();
        $this->assertTrue($source->is_active);
        $this->assertNull($source->merged_into_id);

        $updatedLog = DB::table('pty_merge_logs')->where('id', $mergeLog->id)->first();
        $this->assertTrue((bool) $updatedLog->reversed);
    }

    // =========================================================================
    // (e) Edge Case: Sanctions Hit Detection & Credit Scoring Formula
    // =========================================================================

    public function test_sanctions_screening_detects_hit_and_flags_status(): void
    {
        DB::table('pty_sanctions_lists')->insert([
            'source' => 'test_un',
            'entry_type' => 'entity',
            'name' => 'Omega Black Transnational Syndicate',
            'name_normalized' => 'omega black transnational syndicate',
            'identifier' => 'TAX-TEST-999',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $suspectParty = $this->partyService->create([
            'name' => 'Omega Black Transnational Syndicate Ltd',
            'npwp' => 'TAX-TEST-999',
        ]);

        $check = $this->screeningService->screen($suspectParty, 'onboarding');

        $this->assertTrue(in_array($check->status, [SanctionCheckStatus::Hit, SanctionCheckStatus::ManualReview]));
        $this->assertNotEmpty($check->hits);
        $this->assertGreaterThan(70.0, $check->match_score);
    }

    public function test_credit_scoring_calculates_correct_score_and_tier(): void
    {
        $party = $this->partyService->create([
            'name' => 'PT Prima Unggul Nilai',
            'npwp' => '01.999.000.1-222.000',
            'role' => PartyRoleType::Tenant->value,
            'credit_limit_idr' => 100_000_000,
        ]);

        // Screen clean (+15)
        $this->screeningService->screen($party, 'onboarding');

        // Add role (+5)
        $this->partyService->addRole($party, PartyRoleType::Supplier);

        // Submit & approve 1 KYC doc (+5)
        $doc = app(SubmitKycDocumentAction::class)->execute($party, ['document_type' => KycDocumentType::Siup->value]);
        app(ApproveKycDocumentAction::class)->execute($doc, 'Admin');

        // Recalculate credit score
        $profile = $this->creditService->score($party->fresh());

        $this->assertGreaterThan(50, $profile->internal_score);
        $this->assertNotNull($profile->risk_tier);
        $this->assertEquals(100_000_000, $profile->availableCredit());
    }

    // =========================================================================
    // Controller & HTTP Endpoints Test (Auth & RBAC)
    // =========================================================================

    public function test_authenticated_admin_can_access_party_directory_and_views(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $party = $this->partyService->create([
            'name' => 'PT Akses Controller Sejahtera',
            'npwp' => '01.000.111.2-333.000',
        ]);

        $this->actingAs($admin)
            ->get(route('party.index'))
            ->assertOk()
            ->assertSee('PT Akses Controller Sejahtera');

        $this->actingAs($admin)
            ->get(route('party.show', $party))
            ->assertOk()
            ->assertSee('360° Profile');

        $this->actingAs($admin)
            ->get(route('party.legal-entities'))
            ->assertOk()
            ->assertSee('Legal Entities');
    }

    public function test_unauthorized_user_cannot_access_party_module(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)
            ->get(route('party.index'))
            ->assertForbidden();
    }
}
