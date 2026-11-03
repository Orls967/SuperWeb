<?php

declare(strict_types=1);

namespace Tests\Acceptance\Media;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Med\Application\Services\IpLicensingService;
use Modules\Med\Domain\Exceptions\LicenseOverlapException;
use Modules\Med\Domain\Models\MedIpAsset;
use Tests\TestCase;

class IpLicensingAndIntervalIndexTest extends TestCase
{
    use RefreshDatabase;

    protected IpLicensingService $licensingService;

    protected MedIpAsset $testIp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->licensingService = app(IpLicensingService::class);

        $this->testIp = MedIpAsset::create([
            'id' => (string) Str::uuid(),
            'ip_code' => 'IP-LIC-TEST-01',
            'title' => 'Galactic Cinema',
            'ip_type' => 'SHOW_FORMAT',
            'owner_entity_id' => 'ENT-STUDIO-01',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_exclusive_license_in_territory_succeeds(): void
    {
        $license = $this->licensingService->grantLicense([
            'ip_id' => $this->testIp->id,
            'licensee_entity_id' => 'PARTY-OTT-ID',
            'rights_type' => 'STREAMING',
            'channel' => 'STREAMING',
            'territory' => 'ID',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_exclusive' => true,
            'minimum_guarantee_minor' => 100_000_000,
            'royalty_rate_pct' => 20.0,
            'idempotency_key' => 'LIC:EXCL:ID:01',
        ]);

        expect($license->status)->toBe('ACTIVE')
            ->and($license->is_exclusive)->toBeTrue()
            ->and($license->territory)->toBe('ID');
    }

    public function test_second_exclusive_license_overlapping_same_territory_is_rejected_by_interval_index(): void
    {
        $this->licensingService->grantLicense([
            'ip_id' => $this->testIp->id,
            'licensee_entity_id' => 'PARTY-OTT-ID',
            'rights_type' => 'STREAMING',
            'channel' => 'STREAMING',
            'territory' => 'ID',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_exclusive' => true,
            'minimum_guarantee_minor' => 100_000_000,
            'royalty_rate_pct' => 20.0,
            'idempotency_key' => 'LIC:EXCL:ID:02A',
        ]);

        $this->expectException(LicenseOverlapException::class);
        // Overlapping period in ID: 2026-06-01 to 2027-06-01
        $this->licensingService->grantLicense([
            'ip_id' => $this->testIp->id,
            'licensee_entity_id' => 'PARTY-COMPETITOR-ID',
            'rights_type' => 'STREAMING',
            'channel' => 'STREAMING',
            'territory' => 'ID',
            'start_date' => '2026-06-01',
            'end_date' => '2027-06-01',
            'is_exclusive' => true,
            'minimum_guarantee_minor' => 150_000_000,
            'royalty_rate_pct' => 25.0,
            'idempotency_key' => 'LIC:EXCL:ID:02B',
        ]);
    }

    public function test_second_exclusive_license_in_different_territory_succeeds(): void
    {
        $this->licensingService->grantLicense([
            'ip_id' => $this->testIp->id,
            'licensee_entity_id' => 'PARTY-OTT-ID',
            'rights_type' => 'STREAMING',
            'channel' => 'STREAMING',
            'territory' => 'ID',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_exclusive' => true,
            'minimum_guarantee_minor' => 100_000_000,
            'royalty_rate_pct' => 20.0,
            'idempotency_key' => 'LIC:EXCL:TERR:01',
        ]);

        // Same period, but territory is SG -> Allowed
        $licenseSG = $this->licensingService->grantLicense([
            'ip_id' => $this->testIp->id,
            'licensee_entity_id' => 'PARTY-OTT-SG',
            'rights_type' => 'STREAMING',
            'channel' => 'STREAMING',
            'territory' => 'SG',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_exclusive' => true,
            'minimum_guarantee_minor' => 80_000_000,
            'royalty_rate_pct' => 20.0,
            'idempotency_key' => 'LIC:EXCL:TERR:02',
        ]);

        expect($licenseSG->status)->toBe('ACTIVE')
            ->and($licenseSG->territory)->toBe('SG');
    }

    public function test_second_exclusive_license_with_different_rights_type_succeeds(): void
    {
        $this->licensingService->grantLicense([
            'ip_id' => $this->testIp->id,
            'licensee_entity_id' => 'PARTY-OTT-ID',
            'rights_type' => 'STREAMING',
            'channel' => 'STREAMING',
            'territory' => 'ID',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_exclusive' => true,
            'minimum_guarantee_minor' => 100_000_000,
            'royalty_rate_pct' => 20.0,
            'idempotency_key' => 'LIC:EXCL:RIGHTS:01',
        ]);

        // Same period and territory, but rights_type is THEATRICAL -> Allowed
        $licenseTheatrical = $this->licensingService->grantLicense([
            'ip_id' => $this->testIp->id,
            'licensee_entity_id' => 'PARTY-CINEMA-ID',
            'rights_type' => 'THEATRICAL',
            'channel' => 'THEATRICAL',
            'territory' => 'ID',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_exclusive' => true,
            'minimum_guarantee_minor' => 300_000_000,
            'royalty_rate_pct' => 35.0,
            'idempotency_key' => 'LIC:EXCL:RIGHTS:02',
        ]);

        expect($licenseTheatrical->status)->toBe('ACTIVE');
    }

    public function test_adjacent_exclusive_license_succeeds_without_overlap(): void
    {
        $this->licensingService->grantLicense([
            'ip_id' => $this->testIp->id,
            'licensee_entity_id' => 'PARTY-OTT-ID-1',
            'rights_type' => 'STREAMING',
            'channel' => 'STREAMING',
            'territory' => 'ID',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
            'is_exclusive' => true,
            'minimum_guarantee_minor' => 50_000_000,
            'royalty_rate_pct' => 20.0,
            'idempotency_key' => 'LIC:ADJ:01',
        ]);

        // Exactly adjacent: starts right at 2026-06-30
        $license2 = $this->licensingService->grantLicense([
            'ip_id' => $this->testIp->id,
            'licensee_entity_id' => 'PARTY-OTT-ID-2',
            'rights_type' => 'STREAMING',
            'channel' => 'STREAMING',
            'territory' => 'ID',
            'start_date' => '2026-06-30',
            'end_date' => '2026-12-31',
            'is_exclusive' => true,
            'minimum_guarantee_minor' => 60_000_000,
            'royalty_rate_pct' => 20.0,
            'idempotency_key' => 'LIC:ADJ:02',
        ]);

        expect($license2->status)->toBe('ACTIVE');
    }

    public function test_non_exclusive_license_overlapping_exclusive_license_is_rejected(): void
    {
        $this->licensingService->grantLicense([
            'ip_id' => $this->testIp->id,
            'licensee_entity_id' => 'PARTY-OTT-ID',
            'rights_type' => 'STREAMING',
            'channel' => 'STREAMING',
            'territory' => 'ID',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_exclusive' => true,
            'minimum_guarantee_minor' => 100_000_000,
            'royalty_rate_pct' => 20.0,
            'idempotency_key' => 'LIC:EXCL:PREV',
        ]);

        $this->expectException(LicenseOverlapException::class);
        // Non-exclusive attempted during exclusive window -> Rejected
        $this->licensingService->grantLicense([
            'ip_id' => $this->testIp->id,
            'licensee_entity_id' => 'PARTY-HOTEL-ID',
            'rights_type' => 'STREAMING',
            'channel' => 'HOTEL_IN_ROOM',
            'territory' => 'ID',
            'start_date' => '2026-03-01',
            'end_date' => '2026-09-01',
            'is_exclusive' => false,
            'minimum_guarantee_minor' => 20_000_000,
            'royalty_rate_pct' => 10.0,
            'idempotency_key' => 'LIC:NONEXCL:CLASH',
        ]);
    }

    public function test_multiple_non_exclusive_licenses_overlapping_each_other_succeed(): void
    {
        $lic1 = $this->licensingService->grantLicense([
            'ip_id' => $this->testIp->id,
            'licensee_entity_id' => 'PARTY-HOTEL-1',
            'rights_type' => 'STREAMING',
            'channel' => 'HOTEL_IN_ROOM',
            'territory' => 'ID',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_exclusive' => false,
            'minimum_guarantee_minor' => 15_000_000,
            'royalty_rate_pct' => 10.0,
            'idempotency_key' => 'LIC:MULTI:NONEXCL:01',
        ]);

        $lic2 = $this->licensingService->grantLicense([
            'ip_id' => $this->testIp->id,
            'licensee_entity_id' => 'PARTY-HOTEL-2',
            'rights_type' => 'STREAMING',
            'channel' => 'HOTEL_IN_ROOM',
            'territory' => 'ID',
            'start_date' => '2026-04-01',
            'end_date' => '2026-10-31',
            'is_exclusive' => false,
            'minimum_guarantee_minor' => 15_000_000,
            'royalty_rate_pct' => 10.0,
            'idempotency_key' => 'LIC:MULTI:NONEXCL:02',
        ]);

        expect($lic1->status)->toBe('ACTIVE')
            ->and($lic2->status)->toBe('ACTIVE');
    }

    public function test_license_with_invalid_date_interval_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->licensingService->grantLicense([
            'ip_id' => $this->testIp->id,
            'licensee_entity_id' => 'PARTY-INV',
            'rights_type' => 'STREAMING',
            'channel' => 'STREAMING',
            'territory' => 'ID',
            'start_date' => '2026-12-31',
            'end_date' => '2026-01-01', // End before start
            'is_exclusive' => false,
            'idempotency_key' => 'LIC:INV:DATES',
        ]);
    }

    public function test_license_interval_index_produces_verifiable_certificate(): void
    {
        $certificate = $this->licensingService->generateIntervalCertificate($this->testIp->id);

        expect($certificate)->toHaveKey('total_licenses')
            ->and($certificate)->toHaveKey('intervals')
            ->and($certificate)->toHaveKey('is_valid')
            ->and($certificate['is_valid'])->toBeTrue();
    }

    public function test_grant_license_is_idempotent_with_same_key(): void
    {
        $lic1 = $this->licensingService->grantLicense([
            'ip_id' => $this->testIp->id,
            'licensee_entity_id' => 'PARTY-IDEMP',
            'rights_type' => 'STREAMING',
            'channel' => 'STREAMING',
            'territory' => 'MY',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_exclusive' => true,
            'minimum_guarantee_minor' => 40_000_000,
            'royalty_rate_pct' => 15.0,
            'idempotency_key' => 'LIC:IDEMP:001',
        ]);

        $lic2 = $this->licensingService->grantLicense([
            'ip_id' => $this->testIp->id,
            'licensee_entity_id' => 'PARTY-IDEMP',
            'rights_type' => 'STREAMING',
            'channel' => 'STREAMING',
            'territory' => 'MY',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_exclusive' => true,
            'minimum_guarantee_minor' => 40_000_000,
            'royalty_rate_pct' => 15.0,
            'idempotency_key' => 'LIC:IDEMP:001',
        ]);

        expect($lic1->id)->toBe($lic2->id);
    }
}
