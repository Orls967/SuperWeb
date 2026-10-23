<?php

declare(strict_types=1);

namespace Tests\Feature;

use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Application\Services\TwelveLinesComplianceCalendarService;
use Modules\Core\Domain\Models\CoreComplianceObligation;
use Tests\TestCase;

class TwelveLinesComplianceCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_compliance_calendar_flags_expiring_permits(): void
    {
        $refDate = new DateTimeImmutable('2026-10-22');

        // Expiring in 15 days (alert lead: 30) -> WARNING_EXPIRING_SOON
        CoreComplianceObligation::query()->create([
            'permit_code' => 'PERMIT-MINING-ESDM-001',
            'domain_code' => 'MINING',
            'permit_name' => 'ESDM Concession License',
            'issuing_authority' => 'Kementerian ESDM',
            'expiration_date' => $refDate->modify('+15 days')->format('Y-m-d'),
            'alert_lead_days' => 30,
            'status' => 'VALID',
        ]);

        // Expired yesterday -> CRITICAL_EXPIRED
        CoreComplianceObligation::query()->create([
            'permit_code' => 'PERMIT-HOTEL-FIRE-002',
            'domain_code' => 'HOTEL',
            'permit_name' => 'Fire Safety Certification',
            'issuing_authority' => 'Dinas Damkar Bali',
            'expiration_date' => $refDate->modify('-1 day')->format('Y-m-d'),
            'alert_lead_days' => 30,
            'status' => 'VALID',
        ]);

        $service = app(TwelveLinesComplianceCalendarService::class);
        $alerts = $service->checkExpiringPermits($refDate);

        $this->assertCount(2, $alerts);
        $this->assertSame('CRITICAL_EXPIRED', $alerts[1]['level']);
        $this->assertSame('WARNING_EXPIRING_SOON', $alerts[0]['level']);
        $this->assertSame(15, $alerts[0]['days_left']);
    }
}
