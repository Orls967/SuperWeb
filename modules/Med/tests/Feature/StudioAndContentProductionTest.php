<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Med\Application\Services\StudioAndContentProductionService;
use Modules\Med\Domain\Models\MedStudio;
use Modules\Med\Domain\Models\MedStudioBooking;

uses(RefreshDatabase::class);

beforeEach(function () {
    $accounts = [
        'med:talent_royalty_expense:IDR' => 'expense',
        'med:talent_royalty_payable:IDR' => 'liability',
    ];

    foreach ($accounts as $code => $kind) {
        LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'kind' => $kind,
                'asset_code' => 'IDR',
                'allow_negative' => true,
                'cached_balance' => '0',
                'name' => "Media {$code}",
            ]
        );
    }
});

test('(a) backend % talent = audited revenue * rate and triggers ledger accrual', function () {
    $service = app(StudioAndContentProductionService::class);

    $project = $service->createProject([
        'title' => 'Nusantara Sci-Fi Blockbuster',
        'genre' => 'MOVIE',
        'budget_limit_minor' => 1000000000000,
    ]);

    $contract = $service->registerTalentContract([
        'project_id' => $project->id,
        'talent_party_id' => 'TALENT-DIRECTOR-01',
        'role_name' => 'DIRECTOR',
        'upfront_fee_minor' => 50000000000,
        'backend_percentage' => 5.0, // 5%
    ]);

    expect($contract->calculated_royalty_minor)->toBe(0)
        ->and($contract->payout_status)->toBe('HOLD');

    // Box office audited revenue: 200 Miliar IDR (200,000,000,000 IDR = 20,000,000,000,000 minor)
    $auditedRevenue = 20000000000000;
    $updatedContracts = $service->auditRevenueAndComputeRoyalty($project->id, $auditedRevenue);

    // 5% of 20,000,000,000,000 = 1,000,000,000,000 minor
    expect($updatedContracts[0]['calculated_royalty_minor'])->toBe(1000000000000)
        ->and($updatedContracts[0]['payout_status'])->toBe('APPROVED');
});

test('(b) IP double-license teritori overlap ditolak dengan exception', function () {
    $service = app(StudioAndContentProductionService::class);

    $ip = $service->registerIpAsset([
        'title' => 'Garuda Superhero IP',
        'ip_type' => 'CHARACTER',
        'owner_entity_id' => 'MEDIA-HOLDING-CORP',
    ]);

    $license1 = $service->issueIpLicense([
        'ip_id' => $ip->id,
        'licensee_entity_id' => 'PARTNER-THEMEPARK-JKT',
        'channel' => 'VENUE',
        'territory' => 'ID',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'royalty_rate_pct' => 10.0,
    ]);

    expect($license1->status)->toBe('ACTIVE');

    // Overlapping license in same territory and channel should throw
    expect(fn () => $service->issueIpLicense([
        'ip_id' => $ip->id,
        'licensee_entity_id' => 'PARTNER-ANOTHER-PARK',
        'channel' => 'VENUE',
        'territory' => 'ID',
        'start_date' => '2026-06-01',
        'end_date' => '2027-05-31',
        'royalty_rate_pct' => 12.0,
    ]))->toThrow(RuntimeException::class, 'IP double-license conflict');
});

test('(c) studio booking bentrok jadwal ditolak sistem', function () {
    $service = app(StudioAndContentProductionService::class);

    $studio = $service->createStudio([
        'name' => 'Sound Stage Alpha (LED Virtual Production)',
        'facility_type' => 'VIRTUAL_PRODUCTION',
        'location_city' => 'Jakarta Selatan',
        'hourly_rate_minor' => 15000000,
        'full_day_rate_minor' => 120000000,
    ]);

    expect($studio)->toBeInstanceOf(MedStudio::class);

    $bkg1 = $service->bookStudio([
        'studio_id' => $studio->id,
        'client_entity_id' => 'PRODUCTION-HOUSE-A',
        'start_time' => '2026-11-10 09:00:00',
        'end_time' => '2026-11-10 17:00:00',
        'total_price_minor' => 120000000,
    ]);

    expect($bkg1)->toBeInstanceOf(MedStudioBooking::class)
        ->and($bkg1->status)->toBe('CONFIRMED');

    // Clashing booking attempt on same studio
    expect(fn () => $service->bookStudio([
        'studio_id' => $studio->id,
        'client_entity_id' => 'COMMERCIAL-AGENCY-B',
        'start_time' => '2026-11-10 14:00:00',
        'end_time' => '2026-11-10 20:00:00',
        'total_price_minor' => 90000000,
    ]))->toThrow(RuntimeException::class, 'Studio booking clash detected');
});

test('(d) total biaya proyek = crew + vendor + studio dan dapat dikapitalisasi sebagai aset', function () {
    $service = app(StudioAndContentProductionService::class);

    $project = $service->createProject([
        'title' => 'Web Series Season 1',
        'genre' => 'SERIES',
        'budget_limit_minor' => 500000000000,
    ]);

    $updatedProject = $service->recordProductionCosts(
        projectId: $project->id,
        crewMinor: 150000000000,
        vendorMinor: 100000000000,
        studioMinor: 50000000000
    );

    expect($updatedProject->total_production_cost_minor)->toBe(300000000000)
        ->and($updatedProject->phase)->toBe('POST');

    $capitalized = $service->completeAndCapitalizeProject($project->id, true);
    expect($capitalized->is_capitalized_as_asset)->toBeTrue()
        ->and($capitalized->phase)->toBe('COMPLETED');
});
