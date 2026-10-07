<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Edu\Application\Services\TalentPipelineAndWorkforceService;
use Modules\Edu\Domain\Models\EduContingentContract;
use Modules\Edu\Domain\Models\EduHeadhunterContract;

uses(RefreshDatabase::class);

beforeEach(function () {
    $accounts = [
        'edu:headhunter_expense:IDR' => 'expense',
        'edu:headhunter_payable:IDR' => 'liability',
    ];

    foreach ($accounts as $code => $kind) {
        LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'kind' => $kind,
                'asset_code' => 'IDR',
                'allow_negative' => true,
                'cached_balance' => '0',
                'name' => "Education {$code}",
            ]
        );
    }
});

test('(a) matching deterministik: dua run identik menghasilkan skor & ranking persis sama', function () {
    $service = app(TalentPipelineAndWorkforceService::class);

    $service->registerTalent([
        'candidate_id' => 'CAND-01',
        'full_name' => 'Budi Santoso',
        'city' => 'Jakarta',
        'skills' => ['AUTOSERVE_EV_HV_TECH', 'DIAGNOSTIC_CANBUS'],
        'salary_expectation_minor' => 1200000000,
        'years_experience' => 4,
    ]);

    $service->registerTalent([
        'candidate_id' => 'CAND-02',
        'full_name' => 'Siti Rahma',
        'city' => 'Jakarta',
        'skills' => ['AUTOSERVE_EV_HV_TECH', 'BATTERY_THERMAL', 'DIAGNOSTIC_CANBUS'],
        'salary_expectation_minor' => 1500000000,
        'years_experience' => 6,
    ]);

    $service->registerTalent([
        'candidate_id' => 'CAND-03',
        'full_name' => 'Agus Pratama',
        'city' => 'Surabaya',
        'skills' => ['HSE_K3_MINING'],
        'salary_expectation_minor' => 1000000000,
        'years_experience' => 2,
    ]);

    $job = $service->postJob([
        'job_code' => 'JOB-EV-LEAD',
        'title' => 'Senior EV Powertrain Specialist',
        'hiring_entity_id' => 'AUTOSERVE-HQ',
        'location_city' => 'Jakarta',
        'required_skills' => ['AUTOSERVE_EV_HV_TECH', 'DIAGNOSTIC_CANBUS'],
        'salary_budget_minor' => 1600000000,
    ]);

    $run1 = $service->matchCandidatesForJob($job->job_code);
    $run2 = $service->matchCandidatesForJob($job->job_code);

    expect($run1)->toBe($run2)
        ->and($run1[0]['candidate_id'])->toBe('CAND-01')
        ->and($run1[0]['match_score'])->toBe(100.0); // 60 (skills) + 20 (loc) + 20 (salary)
});

test('(b) agency fee hold sampai garansi lewat dan sukses rilis payout setelahnya', function () {
    $service = app(TalentPipelineAndWorkforceService::class);

    $contract = $service->recordHeadhunterPlacement([
        'headhunter_agency_id' => 'AGENCY-TALENT-SEARCH-01',
        'candidate_id' => 'CAND-02',
        'hiring_entity_id' => 'MINING-OPS-CORP',
        'candidate_first_month_salary_minor' => 2500000000,
        'fee_percentage' => 20.0,
        'warranty_days' => 90,
        'hired_date' => '2026-01-01',
    ]);

    expect($contract)->toBeInstanceOf(EduHeadhunterContract::class)
        ->and($contract->fee_amount_minor)->toBe(500000000)
        ->and($contract->payout_status)->toBe('HOLD');

    // Attempt release during warranty (e.g. Day 45) -> Rejected
    expect(fn () => $service->releaseHeadhunterPayoutIfWarrantyPassed($contract->placement_code, Carbon::parse('2026-02-15')))
        ->toThrow(RuntimeException::class, 'Warranty period active');

    // Release after warranty ends (e.g. Day 95) -> Payout Released
    $released = $service->releaseHeadhunterPayoutIfWarrantyPassed($contract->placement_code, Carbon::parse('2026-04-10'));
    expect($released->payout_status)->toBe('RELEASED');
});

test('(c) contingent workforce timesheet melebihi durasi kontrak ditolak', function () {
    $service = app(TalentPipelineAndWorkforceService::class);

    $contract = $service->createContingentContract([
        'worker_id' => 'GIG-WORKER-99',
        'client_entity_id' => 'VENUE-STADIUM-JAKARTA',
        'project_code' => 'CONCERT-SAFETY-PATROL',
        'contract_max_hours' => 40,
        'hourly_rate_minor' => 15000000,
        'valid_until' => '2026-11-30',
    ]);

    expect($contract)->toBeInstanceOf(EduContingentContract::class);

    // Render 30 hours -> Success
    $c1 = $service->logContingentTimesheet($contract->contract_code, 30);
    expect($c1->hours_rendered)->toBe(30);

    // Render 15 more hours (total 45 > 40 max) -> Exception
    expect(fn () => $service->logContingentTimesheet($contract->contract_code, 15))
        ->toThrow(RuntimeException::class, 'Timesheet exceeds maximum contracted hours');
});

test('(d) transfer antar entitas tak ganda hitung payroll', function () {
    $service = app(TalentPipelineAndWorkforceService::class);

    $xfer = $service->processInternalTransfer([
        'employee_id' => 'EMP-WAIT-05',
        'from_entity_id' => 'RESTO-CHAIN-BALI',
        'to_entity_id' => 'EDU-ACADEMY-SURABAYA',
        'effective_date' => '2026-10-01',
        'base_salary_minor' => 850000000,
    ]);

    // Old entity tries to process payroll -> gets 0
    $oldEntityPayroll = $service->processEntityPayroll($xfer->transfer_code, 'RESTO-CHAIN-BALI');
    expect($oldEntityPayroll)->toBe(0);

    // New entity processes payroll -> gets full salary
    $newEntityPayroll = $service->processEntityPayroll($xfer->transfer_code, 'EDU-ACADEMY-SURABAYA');
    expect($newEntityPayroll)->toBe(850000000);

    // New entity tries to run payroll again -> idempotent 0 (no duplicate)
    $repeatPayroll = $service->processEntityPayroll($xfer->transfer_code, 'EDU-ACADEMY-SURABAYA');
    expect($repeatPayroll)->toBe(0);
});
