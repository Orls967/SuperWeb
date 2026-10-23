<?php

declare(strict_types=1);

namespace Modules\Party\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Party\Application\Actions\RegisterLegalEntityPartyAction;
use Modules\Party\Application\Actions\RevokePartyCredentialAction;
use Modules\Party\Application\Actions\UpdatePartyRiskRatingAction;
use Modules\Party\Application\Actions\VerifySanctionScreeningResultAction;
use Modules\Party\Domain\Enums\KybStatus;
use Modules\Party\Domain\Enums\KycDocumentStatus;
use Modules\Party\Domain\Enums\KycDocumentType;
use Modules\Party\Domain\Enums\PartyStatus;
use Modules\Party\Domain\Enums\RiskTier;
use Modules\Party\Domain\Enums\SanctionCheckStatus;
use Modules\Party\Domain\Models\KycDocument;
use Modules\Party\Domain\Models\LegalEntity;
use Modules\Party\Domain\Models\Party;
use Tests\TestCase;

class PartyExtendedLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_1_register_legal_entity_and_party_company(): void
    {
        $action = app(RegisterLegalEntityPartyAction::class);
        $result = $action->execute([
            'name' => 'PT Semen Perkasa Nusantara',
            'short_name' => 'SPN Corp',
            'entity_type' => 'company',
            'functional_currency' => 'IDR',
            'initial_credit_limit_idr' => 500000000,
            'internal_score' => 820,
        ]);

        $this->assertInstanceOf(LegalEntity::class, $result['legal_entity']);
        $this->assertInstanceOf(Party::class, $result['party']);
        $this->assertSame('PT Semen Perkasa Nusantara', $result['legal_entity']->name);
        $this->assertSame(PartyStatus::Verified, $result['party']->status);
        $this->assertSame(KybStatus::Verified, $result['party']->kyb_status);
        $this->assertSame(500000000, $result['credit_profile']->credit_limit_idr);
    }

    public function test_2_verify_sanction_screening_clear_result(): void
    {
        $reg = app(RegisterLegalEntityPartyAction::class)->execute([
            'name' => 'PT Bersih Aman Makmur',
        ]);
        $party = $reg['party'];

        $action = app(VerifySanctionScreeningResultAction::class);
        $check = $action->execute([
            'party_id' => $party->id,
            'hits' => [],
            'match_score' => 0.0,
            'trigger' => 'onboarding',
        ]);

        $this->assertSame(SanctionCheckStatus::Clear, $check->status);
        $this->assertTrue($check->isClear());
        $this->assertSame(PartyStatus::Verified, $party->fresh()->status);
    }

    public function test_3_verify_sanction_screening_hit_suspends_party(): void
    {
        $reg = app(RegisterLegalEntityPartyAction::class)->execute([
            'name' => 'PT High Risk Sanctioned Co',
        ]);
        $party = $reg['party'];

        $action = app(VerifySanctionScreeningResultAction::class);
        $check = $action->execute([
            'party_id' => $party->id,
            'hits' => [
                ['list' => 'UN_CONSOLIDATED', 'entry_id' => 'UN-9921', 'reason' => 'Designated entity'],
            ],
            'match_score' => 0.95,
            'trigger' => 'transaction_monitoring',
        ]);

        $this->assertSame(SanctionCheckStatus::Hit, $check->status);
        $this->assertSame(PartyStatus::Suspended, $party->fresh()->status);
    }

    public function test_4_update_party_risk_rating_and_credit_limit(): void
    {
        $reg = app(RegisterLegalEntityPartyAction::class)->execute([
            'name' => 'PT Mitra Sejahtera Sentosa',
        ]);
        $party = $reg['party'];

        $action = app(UpdatePartyRiskRatingAction::class);
        $profile = $action->execute([
            'party_id' => $party->id,
            'risk_tier' => RiskTier::Medium,
            'internal_score' => 640,
            'credit_limit_idr' => 250000000,
        ]);

        $this->assertSame(RiskTier::Medium, $profile->risk_tier);
        $this->assertSame(640, $profile->internal_score);
        $this->assertSame(250000000, $profile->credit_limit_idr);
    }

    public function test_5_update_party_risk_rating_to_blacklisted_escalates_party(): void
    {
        $reg = app(RegisterLegalEntityPartyAction::class)->execute([
            'name' => 'PT Bad Faith Operator',
        ]);
        $party = $reg['party'];

        $action = app(UpdatePartyRiskRatingAction::class);
        $profile = $action->execute([
            'party_id' => $party->id,
            'risk_tier' => RiskTier::Blacklisted,
            'internal_score' => 200,
        ]);

        $this->assertSame(RiskTier::Blacklisted, $profile->risk_tier);
        $this->assertSame(PartyStatus::Blacklisted, $party->fresh()->status);
    }

    public function test_6_revoke_party_credential_downgrades_kyb_status(): void
    {
        $reg = app(RegisterLegalEntityPartyAction::class)->execute([
            'name' => 'PT Valid Documents First',
        ]);
        $party = $reg['party'];

        // Create an approved KYC Document
        $doc = KycDocument::create([
            'id' => (string) Str::uuid(),
            'party_id' => $party->id,
            'document_type' => KycDocumentType::Nib,
            'document_number' => 'NIB-88129031',
            'status' => KycDocumentStatus::Approved,
        ]);

        $action = app(RevokePartyCredentialAction::class);
        $revoked = $action->execute([
            'party_id' => $party->id,
            'document_id' => $doc->id,
            'reason' => 'Forged business registry document detected during audit',
        ]);

        $this->assertSame(KycDocumentStatus::Rejected, $revoked->status);
        $this->assertSame('Forged business registry document detected during audit', $revoked->rejection_reason);
        $this->assertSame(KybStatus::InReview, $party->fresh()->kyb_status);
    }
}
