<?php

declare(strict_types=1);

namespace Modules\Contract\tests\Feature;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Contract\Application\Actions\ApproveAndSignContractVersionAction;
use Modules\Contract\Application\Actions\CreateMasterContractWithHashAction;
use Modules\Contract\Application\Actions\RenewOrTerminateContractAction;
use Modules\Contract\Domain\Enums\ContractStatus;
use Modules\Contract\Domain\Models\Contract;
use Modules\Contract\Domain\Models\ContractVersion;
use RuntimeException;
use Tests\TestCase;

class ContractLifecycleAndSigningTest extends TestCase
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
            'name' => 'PT AutoServe Indonesia',
            'entity_type' => 'company',
            'functional_currency' => 'IDR',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $action = app(CreateMasterContractWithHashAction::class);

        return $action->execute([
            'legal_entity_id' => $legalEntityId,
            'title' => 'Software License & SLA Agreement',
            'contract_type' => 'service',
            'total_value_idr' => 250000000,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'body' => 'Agreement between Provider and Client for cloud services.',
            'created_by' => 'alice@company.com',
        ]);
    }

    public function test_1_create_master_contract_with_genesis_hash(): void
    {
        $contract = $this->createSampleContract();

        $this->assertInstanceOf(Contract::class, $contract);
        $this->assertEquals(ContractStatus::Draft, $contract->status);
        $this->assertNotEmpty($contract->current_hash);

        $version = ContractVersion::where('contract_id', $contract->id)->first();
        $this->assertNotNull($version);
        $this->assertSame(1, $version->sequence);
        $this->assertSame(ContractVersion::GENESIS_HASH, $version->prev_hash);
        $this->assertSame($contract->current_hash, $version->hash);
    }

    public function test_2_approve_and_sign_contract_version(): void
    {
        $contract = $this->createSampleContract();
        $action = app(ApproveAndSignContractVersionAction::class);

        $signedContract = $action->execute([
            'contract_id' => $contract->id,
            'signer_name' => 'Budi Santoso',
            'signer_title' => 'President Director',
        ]);

        $this->assertEquals(ContractStatus::Active, $signedContract->status);
        $this->assertNotNull($signedContract->signed_at);
        $this->assertNotNull($signedContract->activated_at);

        $latestVersion = ContractVersion::where('contract_id', $contract->id)->latest('sequence')->first();
        $this->assertSame(2, $latestVersion->sequence);
        $this->assertSame('signature_ratification', $latestVersion->change_type);
        $this->assertSame($signedContract->current_hash, $latestVersion->hash);
    }

    public function test_3_contract_version_immutability_exception_on_update(): void
    {
        $contract = $this->createSampleContract();
        $version = ContractVersion::where('contract_id', $contract->id)->first();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ContractVersion is append-only and cannot be modified');

        $version->update(['body' => 'Tampered body content!']);
    }

    public function test_4_contract_version_immutability_exception_on_delete(): void
    {
        $contract = $this->createSampleContract();
        $version = ContractVersion::where('contract_id', $contract->id)->first();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ContractVersion is append-only and cannot be deleted');

        $version->delete();
    }

    public function test_5_renew_active_contract_extends_end_date(): void
    {
        $contract = $this->createSampleContract();
        $prevEndDate = $contract->end_date;

        $action = app(RenewOrTerminateContractAction::class);
        $renewed = $action->execute([
            'contract_id' => $contract->id,
            'mode' => 'renew',
            'renewal_months' => 6,
            'reason' => 'Annual performance milestone achieved',
        ]);

        $this->assertEquals(ContractStatus::Active, $renewed->status);
        $this->assertGreaterThan($prevEndDate, $renewed->end_date);

        $version = ContractVersion::where('contract_id', $contract->id)->latest('sequence')->first();
        $this->assertSame('contract_renewal', $version->change_type);
    }

    public function test_6_terminate_active_contract_with_reason(): void
    {
        $contract = $this->createSampleContract();

        $action = app(RenewOrTerminateContractAction::class);
        $terminated = $action->execute([
            'contract_id' => $contract->id,
            'mode' => 'terminate',
            'reason' => 'Mutual consent under Clause 14.2',
        ]);

        $this->assertEquals(ContractStatus::Terminated, $terminated->status);
        $this->assertNotNull($terminated->terminated_at);
        $this->assertSame('Mutual consent under Clause 14.2', $terminated->termination_reason);

        $version = ContractVersion::where('contract_id', $contract->id)->latest('sequence')->first();
        $this->assertSame('contract_termination', $version->change_type);
    }
}
