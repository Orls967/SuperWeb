<?php

declare(strict_types=1);

namespace Modules\Contract\tests\Feature;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Contract\Application\Actions\ApproveAndSignContractVersionAction;
use Modules\Contract\Application\Actions\AttachContractClauseFromTemplateAction;
use Modules\Contract\Application\Actions\CreateMasterContractWithHashAction;
use Modules\Contract\Application\Actions\ResolveContractDisputeArbitrationAction;
use Modules\Contract\Application\Actions\VerifyContractHashChainIntegrityAction;
use Modules\Contract\Domain\Models\ClauseTemplate;
use Modules\Contract\Domain\Models\Contract;
use Modules\Contract\Domain\Models\ContractClause;
use Tests\TestCase;

class ContractIntegrityAndArbitrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    private function createSampleContract(): Contract
    {
        $legalEntityId = (string) Str::uuid();
        DB::table('pty_legal_entities')->insert([
            'id' => $legalEntityId,
            'name' => 'PT Procurement Bersama',
            'entity_type' => 'company',
            'functional_currency' => 'IDR',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return app(CreateMasterContractWithHashAction::class)->execute([
            'legal_entity_id' => $legalEntityId,
            'title' => 'Master Procurement Agreement',
            'contract_type' => 'purchase',
            'total_value_idr' => 1000000000,
        ]);
    }

    public function test_1_verify_valid_contract_hash_chain_integrity(): void
    {
        $contract = $this->createSampleContract();

        // Sign contract to add version 2
        app(ApproveAndSignContractVersionAction::class)->execute([
            'contract_id' => $contract->id,
            'signer_name' => 'Direktur Utama',
        ]);

        $action = app(VerifyContractHashChainIntegrityAction::class);
        $result = $action->execute(['contract_id' => $contract->id]);

        $this->assertTrue($result['is_valid']);
        $this->assertSame(2, $result['versions_verified']);
        $this->assertEmpty($result['errors']);
        $this->assertSame($contract->fresh()->current_hash, $result['latest_hash']);
    }

    public function test_2_detect_tampered_contract_version_body_in_hash_chain(): void
    {
        $contract = $this->createSampleContract();

        // Simulate malicious DB modification bypassing Eloquent events
        DB::table('ctr_contract_versions')
            ->where('contract_id', $contract->id)
            ->where('sequence', 1)
            ->update(['body' => 'TAMPERED MALICIOUS BODY CONTENT']);

        $action = app(VerifyContractHashChainIntegrityAction::class);
        $result = $action->execute(['contract_id' => $contract->id]);

        $this->assertFalse($result['is_valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('cryptographic tampering detected', $result['errors'][0]);
    }

    public function test_3_attach_clause_from_template_and_render_variables(): void
    {
        $contract = $this->createSampleContract();

        $template = ClauseTemplate::create([
            'id' => (string) Str::uuid(),
            'code' => 'CONFIDENTIALITY_NDA_V1',
            'title' => 'Klausul Kerahasiaan Informasi Non-Disclosure',
            'category' => 'confidentiality',
            'body_template' => 'Penerima informasi wajib menjaga kerahasiaan selama {{duration_years}} tahun dengan denda pelanggaran Rp {{penalty_idr}}.',
            'version' => 1,
            'is_standard' => true,
            'is_active' => true,
        ]);

        $action = app(AttachContractClauseFromTemplateAction::class);
        $clause = $action->execute([
            'contract_id' => $contract->id,
            'clause_template_id' => $template->id,
            'variables' => [
                'duration_years' => '5',
                'penalty_idr' => '500.000.000',
            ],
            'is_negotiated' => true,
        ]);

        $this->assertInstanceOf(ContractClause::class, $clause);
        $this->assertSame(1, $clause->display_order);
        $this->assertTrue($clause->is_negotiated);
        $this->assertStringContainsString('selama 5 tahun dengan denda pelanggaran Rp 500.000.000', $clause->body);

        // Version chain should have been appended
        $this->assertDatabaseHas('ctr_contract_versions', [
            'contract_id' => $contract->id,
            'change_type' => 'clause_attached',
            'sequence' => 2,
        ]);
    }

    public function test_4_attach_multiple_clauses_maintains_display_order(): void
    {
        $contract = $this->createSampleContract();

        $tpl1 = ClauseTemplate::create([
            'id' => (string) Str::uuid(),
            'code' => 'IP_RIGHTS_V1',
            'title' => 'Hak Kekayaan Intelektual',
            'category' => 'ip',
            'body_template' => 'Seluruh hak cipta dimiliki oleh Pemberi Lisensi.',
            'version' => 1,
            'is_standard' => true,
            'is_active' => true,
        ]);

        $tpl2 = ClauseTemplate::create([
            'id' => (string) Str::uuid(),
            'code' => 'FORCE_MAJEURE_V1',
            'title' => 'Keadaan Kahar (Force Majeure)',
            'category' => 'compliance',
            'body_template' => 'Pihak dibebaskan dari kewajiban akibat bencana alam skala nasional.',
            'version' => 1,
            'is_standard' => true,
            'is_active' => true,
        ]);

        $action = app(AttachContractClauseFromTemplateAction::class);
        $clause1 = $action->execute([
            'contract_id' => $contract->id,
            'clause_template_id' => $tpl1->id,
        ]);

        $clause2 = $action->execute([
            'contract_id' => $contract->id,
            'clause_template_id' => $tpl2->id,
        ]);

        $this->assertSame(1, $clause1->display_order);
        $this->assertSame(2, $clause2->display_order);
    }

    public function test_5_resolve_dispute_via_arbitration_without_financial_award(): void
    {
        $contract = $this->createSampleContract();
        $action = app(ResolveContractDisputeArbitrationAction::class);

        $result = $action->execute([
            'contract_id' => $contract->id,
            'arbitration_forum' => 'BANI Arbitration Centre Jakarta',
            'settlement_terms' => 'Parties agree to delivery timeline extension without damages.',
            'resolution_status' => 'settled_amicably',
            'arbitrator_name' => 'Prof. Dr. Bagir Manan',
        ]);

        $this->assertSame('settled_amicably', $result['status']);
        $this->assertSame(0, $result['award_minor']);
        $this->assertNotEmpty($result['resolution_hash']);
        $this->assertSame('BANI Arbitration Centre Jakarta', $contract->fresh()->dispute_forum);
    }

    public function test_6_resolve_dispute_via_arbitration_with_award_and_ledger(): void
    {
        $contract = $this->createSampleContract();
        $action = app(ResolveContractDisputeArbitrationAction::class);

        $result = $action->execute([
            'contract_id' => $contract->id,
            'arbitration_forum' => 'Singapore International Arbitration Centre (SIAC)',
            'settlement_terms' => 'Respondent ordered to pay liquidated damages of IDR 75,000,000.',
            'arbitration_award_minor' => 75000000,
            'resolution_status' => 'arbitration_award_rendered',
            'arbitrator_name' => 'Michael Hwang SC',
        ]);

        $this->assertSame(75000000, $result['award_minor']);
        $this->assertSame('arbitration_award_rendered', $result['status']);

        $this->assertDatabaseHas('bank_ledger_transactions', [
            'type' => 'ctr_payment',
            'reference_id' => $contract->contract_number,
        ]);
    }
}
