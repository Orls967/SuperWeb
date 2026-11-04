<?php

declare(strict_types=1);

namespace Tests\Acceptance\ProServices;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Contracts\ClockInterface;
use Modules\Hcm\Contracts\EmployeeDirectory;
use Modules\ProServices\Application\Services\ProServicesProjectService;
use Modules\ProServices\Domain\Models\ProServicesProject;
use Modules\ProServices\Domain\Models\ProServicesTimesheet;
use Modules\Shared\Domain\Algorithms\Revenue\CostToCostRecognition;
use Tests\TestCase;

final class ProServicesAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists('Modules\\ProServices\\Database\\Seeders\\ProServicesRbacSeeder')) {
            Artisan::call('db:seed', ['--class' => 'Modules\\ProServices\\Database\\Seeders\\ProServicesRbacSeeder']);
        }
        Artisan::call('db:seed', ['--class' => 'LedgerAccountsSeeder']);
    }

    public function test_akses_kontrol_akses_berbasis_peran_untuk_rute_proservices(): void
    {
        $pmUser = User::factory()->create(['role' => 'project_manager']);
        $consultantUser = User::factory()->create(['role' => 'consultant']);
        $clientUser = User::factory()->create(['role' => 'client']);
        $unauthUser = User::factory()->create(['role' => 'guest_role']);

        // PM route
        $this->actingAs($pmUser)->get('/proservices/projects')->assertStatus(200);
        $this->actingAs($unauthUser)->get('/proservices/projects')->assertStatus(403);

        // Consultant route
        $this->actingAs($consultantUser)->get('/proservices/timesheets')->assertStatus(200);
        $this->actingAs($unauthUser)->get('/proservices/timesheets')->assertStatus(403);

        // Client route
        $this->actingAs($clientUser)->get('/proservices/portal/invoices')->assertStatus(200);
        $this->actingAs($unauthUser)->get('/proservices/portal/invoices')->assertStatus(403);
    }

    public function test_alur_utama_end_to_end_posting_ledger_idempotensi(): void
    {
        $service = app(ProServicesProjectService::class);

        // Setup Project Fixed Fee
        $project = $service->createProject([
            'project_code' => 'PRJ-2026-001',
            'client_party_id' => 101,
            'contract_reference' => 'CTR-PSV-001',
            'billing_type' => 'fixed_fee',
            'total_contract_idr' => 1000000000,
            'estimated_cost_idr' => 800000000,
        ]);

        // Add Member via K12
        $member = $service->addProjectMember($project->id, [
            'employee_id' => 1,
            'role_name' => 'Lead Consultant',
            'hourly_rate_idr' => 500000,
        ]);

        // Submit Timesheet
        $ts = $service->submitTimesheet($project->id, $member->id, [
            'work_date' => '2026-11-05',
            'hours_worked' => 8,
            'description' => 'Architecture initial review',
        ]);

        // Approve Timesheet
        $service->approveTimesheet($ts->id, 99);

        // Fixed Fee Revenue Recognition P1 (Cost 200m from est 800m)
        $rec1 = $service->recognizeRevenueFixedFee($project->id, 200000000, 800000000, 'IDEMP-REC-001');
        $this->assertSame(250000000, $rec1->cumulative_recognized_idr);
        $this->assertSame(250000000, $rec1->period_recognized_idr);

        // Invoice Bill with Idempotency Key
        $inv1 = $service->createInvoice($project->id, 200000000, 'IDEMP-INV-001');
        $this->assertSame(200000000, $inv1->subtotal_idr);

        // Repeat invoice with same key -> returns same record without duplicate billing
        $invDuplicate = $service->createInvoice($project->id, 200000000, 'IDEMP-INV-001');
        $this->assertSame($inv1->id, $invDuplicate->id);

        // Without key -> 422
        $this->expectException(\InvalidArgumentException::class);
        $service->createInvoice($project->id, 200000000, '');
    }

    public function test_aturan_bisnis_timesheet_belum_disetujui_dan_reversal(): void
    {
        $service = app(ProServicesProjectService::class);
        $project = $service->createProject([
            'project_code' => 'PRJ-TM-001',
            'client_party_id' => 102,
            'contract_reference' => 'CTR-PSV-002',
            'billing_type' => 'time_and_materials',
            'total_contract_idr' => 500000000,
            'estimated_cost_idr' => 400000000,
        ]);

        $member = $service->addProjectMember($project->id, [
            'employee_id' => 2,
            'role_name' => 'Engineer',
            'hourly_rate_idr' => 250000,
        ]);

        $ts = $service->submitTimesheet($project->id, $member->id, [
            'work_date' => '2026-11-05',
            'hours_worked' => 10,
            'description' => 'Unapproved draft work',
        ]);

        // Attempt to bill unapproved timesheets -> 0 billable
        $billable = $service->getBillableHours($project->id);
        $this->assertSame(0, $billable);

        // Reversal timesheet test (append-only)
        $reversal = $service->reverseTimesheet($ts->id, 'Input salah jam');
        $this->assertTrue($reversal->is_reversal);
        $this->assertSame($ts->id, $reversal->reversed_timesheet_id);
    }

    public function test_idor_proteksi_data_klien_dan_konsultan(): void
    {
        $user1 = User::factory()->create(['role' => 'consultant']);
        $user2 = User::factory()->create(['role' => 'consultant']);
        $client1 = User::factory()->create(['role' => 'client']);
        $client2 = User::factory()->create(['role' => 'client']);

        $service = app(ProServicesProjectService::class);
        $project = $service->createProject([
            'project_code' => 'PRJ-IDOR-001',
            'client_party_id' => $client1->id,
            'contract_reference' => 'CTR-PSV-IDOR',
            'billing_type' => 'fixed_fee',
            'total_contract_idr' => 100000000,
            'estimated_cost_idr' => 80000000,
        ]);

        $member1 = $service->addProjectMember($project->id, [
            'employee_id' => $user1->id,
            'role_name' => 'Consultant A',
            'hourly_rate_idr' => 300000,
        ]);

        $ts1 = $service->submitTimesheet($project->id, $member1->id, [
            'work_date' => '2026-11-05',
            'hours_worked' => 4,
            'description' => 'Consultant 1 work',
        ]);

        // User2 trying to view User1's timesheet -> 403
        $this->actingAs($user2)->get("/proservices/timesheets/{$ts1->id}")->assertStatus(403);
        $this->actingAs($user1)->get("/proservices/timesheets/{$ts1->id}")->assertStatus(200);

        // Client2 trying to view Client1's project -> 403
        $this->actingAs($client2)->get("/proservices/portal/projects/{$project->id}")->assertStatus(403);
        $this->actingAs($client1)->get("/proservices/portal/projects/{$project->id}")->assertStatus(200);
    }

    public function test_audit_dua_sumber_bersih_exit_0_dan_fixture_exit_nonzero(): void
    {
        // Clean run
        $exitClean = Artisan::call('psv:audit');
        $this->assertSame(0, $exitClean);

        // Fixture corrupted run
        $fixture = new \App\Quality\Audit\Fixtures\ProServicesAuditCorruptionFixture();
        $fixture->corrupt();
        $exitCorrupt = Artisan::call('psv:audit');
        $this->assertNotSame(0, $exitCorrupt);
    }
}
