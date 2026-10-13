<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Med\Application\Services\StudioAndContentProductionService;

uses(RefreshDatabase::class);

test('issueIpLicense rejects channel > 50 chars with InvalidArgumentException', function () {
    $service = app(StudioAndContentProductionService::class);
    $ip = $service->registerIpAsset([
        'title' => 'Test IP',
        'ip_type' => 'CHARACTER',
        'owner_entity_id' => 'ENTITY-1',
    ]);

    expect(fn () => $service->issueIpLicense([
        'ip_id' => $ip->id,
        'licensee_entity_id' => 'LICENSEE-1',
        'channel' => str_repeat('A', 51), // 51 chars > 50
        'territory' => 'ID',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'royalty_rate_pct' => 10.0,
    ]))->toThrow(InvalidArgumentException::class, 'Channel exceeds maximum length of 50 characters.');
});

test('issueIpLicense accepts channel <= 50 chars', function () {
    $service = app(StudioAndContentProductionService::class);
    $ip = $service->registerIpAsset([
        'title' => 'Test IP 2',
        'ip_type' => 'CHARACTER',
        'owner_entity_id' => 'ENTITY-1',
    ]);

    $license = $service->issueIpLicense([
        'ip_id' => $ip->id,
        'licensee_entity_id' => 'LICENSEE-1',
        'channel' => str_repeat('A', 50), // 50 chars
        'territory' => 'ID',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'royalty_rate_pct' => 10.0,
    ]);

    expect($license->channel)->toBe(str_repeat('A', 50));
});

test('issueIpLicense rejects territory > 50 chars with InvalidArgumentException', function () {
    $service = app(StudioAndContentProductionService::class);
    $ip = $service->registerIpAsset([
        'title' => 'Test IP 3',
        'ip_type' => 'CHARACTER',
        'owner_entity_id' => 'ENTITY-1',
    ]);

    expect(fn () => $service->issueIpLicense([
        'ip_id' => $ip->id,
        'licensee_entity_id' => 'LICENSEE-1',
        'channel' => 'VENUE',
        'territory' => str_repeat('B', 51), // 51 chars > 50
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'royalty_rate_pct' => 10.0,
    ]))->toThrow(InvalidArgumentException::class, 'Territory exceeds maximum length of 50 characters.');
});

test('issueIpLicense accepts territory <= 50 chars', function () {
    $service = app(StudioAndContentProductionService::class);
    $ip = $service->registerIpAsset([
        'title' => 'Test IP 4',
        'ip_type' => 'CHARACTER',
        'owner_entity_id' => 'ENTITY-1',
    ]);

    $license = $service->issueIpLicense([
        'ip_id' => $ip->id,
        'licensee_entity_id' => 'LICENSEE-1',
        'channel' => 'VENUE',
        'territory' => str_repeat('B', 50), // 50 chars
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'royalty_rate_pct' => 10.0,
    ]);

    expect($license->territory)->toBe(str_repeat('B', 50));
});

test('issueIpLicense rejects ip_id > 64 chars with InvalidArgumentException', function () {
    $service = app(StudioAndContentProductionService::class);

    expect(fn () => $service->issueIpLicense([
        'ip_id' => str_repeat('C', 65), // 65 chars > 64
        'licensee_entity_id' => 'LICENSEE-1',
        'channel' => 'VENUE',
        'territory' => 'ID',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'royalty_rate_pct' => 10.0,
    ]))->toThrow(InvalidArgumentException::class, 'IP ID exceeds maximum length of 64 characters.');
});
