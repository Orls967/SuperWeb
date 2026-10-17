<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Feature;

use App\Models\User;
use App\Quality\Audit\Fixtures\HspAuditCorruptionFixture;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Hospital\database\seeders\HospitalDemoSeeder;
use Modules\Hospital\Domain\Models\ClinicalTrial;
use Tests\TestCase;

class HospitalVerticalSliceHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            LedgerAccountsSeeder::class,
            HospitalDemoSeeder::class,
        ]);
    }

    public function test_a_authorized_user_can_access_hospital_index_and_bill_milestone(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);
        $trial = ClinicalTrial::firstOrFail();

        // Access index
        $response = $this->actingAs($doctor)->get(route('hospital.index'));
        $response->assertOk();
        $response->assertSeeText('Hospital, EMR & Clinical Trial Operations');
        $response->assertSee($trial->title);

        // Bill milestone
        $billResponse = $this->actingAs($doctor)->post(route('hospital.trials.bill', $trial), [
            'milestone_name' => 'Enrollment Milestone 1',
            'amount_idr' => 50000000,
        ]);

        $billResponse->assertRedirect(route('hospital.index'));
        $billResponse->assertSessionHas('success');

        // Check ledger transaction created
        $this->assertDatabaseHas('bank_ledger_transactions', [
            'type' => 'CLINICAL_TRIAL_MILESTONE',
            'reference_type' => 'CLINICAL_TRIAL',
            'reference_id' => (string) $trial->id,
        ]);
    }

    public function test_b_unauthorized_role_is_forbidden_403(): void
    {
        $nurse = User::factory()->create(['role' => 'customer']);
        $trial = ClinicalTrial::firstOrFail();

        $response = $this->actingAs($nurse)->get(route('hospital.index'));
        $response->assertForbidden();

        $postResponse = $this->actingAs($nurse)->post(route('hospital.trials.bill', $trial), [
            'milestone_name' => 'Unauthorized Bill',
            'amount_idr' => 1000000,
        ]);
        $postResponse->assertForbidden();
    }

    public function test_c_unauthenticated_user_is_redirected_to_login_302(): void
    {
        $response = $this->get(route('hospital.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_d_two_source_hospital_audit_passes_cleanly(): void
    {
        $exit = Artisan::call('hsp:audit');
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Hospital audit PASSED with 0 discrepancy', Artisan::output());
    }

    public function test_e_hsp_audit_fails_when_data_is_corrupted(): void
    {
        $fixture = new HspAuditCorruptionFixture;
        $expectedError = $fixture->corrupt();

        $exit = Artisan::call('hsp:audit');
        $this->assertSame(1, $exit);
        $this->assertStringContainsString($expectedError, Artisan::output());
    }
}
