<?php

declare(strict_types=1);

namespace Tests\Feature\Agency;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agency\Application\Services\AgencyService;
use Modules\Agency\Domain\Models\Agent;
use Tests\TestCase;

/**
 * Pengujian Fase 46: CRM leads, onboarding/sertifikasi agen, tiering & gamifikasi,
 * APM keagenan merek, sanksi kepatuhan, fraud self-referral, dan analitik agen.
 */
class AgencyEcosystemTest extends TestCase
{
    use RefreshDatabase;

    private AgencyService $agency;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->agency = app(AgencyService::class);
        $this->admin = User::where('role', 'admin')->firstOrFail();
    }

    private function makeActiveAgent(string $code): Agent
    {
        $agent = $this->agency->registerAgent([
            'code' => $code,
            'name' => "Agen {$code}",
            'kind' => 'sales_agent',
        ]);

        return $this->agency->transition($agent, 'active');
    }

    public function test_crm_leads_creation_activity_and_conversion(): void
    {
        $agent = $this->makeActiveAgent('AG-LEAD-1');

        $lead = $this->agency->createLead($agent, [
            'name' => 'Prospek B2B Armada PT Maju',
            'phone' => '081234567890',
            'email' => 'prospek@maju.test',
            'category' => 'vehicle_store',
            'estimated_value_idr' => 250_000_000,
        ]);

        $this->assertSame('new', $lead->status);
        $this->assertSame($agent->id, $lead->agent_id);

        $activity = $this->agency->recordLeadActivity($lead, 'meeting', 'Presentasi penawaran unit armada');
        $this->assertSame($lead->id, $activity->lead_id);
        $this->assertCount(1, $lead->activities);

        $converted = $this->agency->convertLead($lead, 'ORD-FLEET-001');
        $this->assertSame('converted', $converted->status);
        $this->assertSame('ORD-FLEET-001', $converted->converted_order_id);

        $agent->refresh();
        $this->assertSame(1, (int) $agent->total_deals_count);
        $this->assertSame(250_000_000, (int) $agent->total_sales_volume_idr);
    }

    public function test_agent_certification_and_validity(): void
    {
        $agent = $this->makeActiveAgent('AG-CERT-1');

        $cert = $this->agency->addCertification($agent, [
            'type' => 'property_license',
            'license_number' => 'LIC/AREBI/2026/088',
            'issuing_body' => 'AREBI',
            'issued_at' => now()->subMonths(2)->toDateString(),
            'expires_at' => now()->addYear()->toDateString(),
            'status' => 'verified',
        ]);

        $this->assertTrue($cert->isValidAt());
        $this->assertTrue($cert->isValidAt(now()->toDateString()));
        $this->assertFalse($cert->isValidAt(now()->addYears(2)->toDateString()));
    }

    public function test_agent_tier_evaluation_and_leaderboard(): void
    {
        $this->agency->saveTier('GOLD', 'Gold Tier', 5, 500_000_000, 1.25, ['vip_lounge', 'exclusive_rates']);
        $this->agency->saveTier('SILVER', 'Silver Tier', 2, 200_000_000, 1.10, ['priority_support']);
        $this->agency->saveTier('BRONZE', 'Bronze Tier', 0, 0, 1.00);

        $agent = $this->makeActiveAgent('AG-TIER-1');
        $agent->total_deals_count = 6;
        $agent->total_sales_volume_idr = 600_000_000;
        $agent->save();

        $tier = $this->agency->evaluateTier($agent);
        $this->assertSame('GOLD', $tier);
        $this->assertSame('GOLD', $agent->fresh()->tier_code);

        $leaderboard = $this->agency->getLeaderboard(5);
        $this->assertTrue($leaderboard->contains('code', 'AG-TIER-1'));
    }

    public function test_brand_agency_apm_registration(): void
    {
        $agent = $this->makeActiveAgent('AG-APM-1');

        $apm = $this->agency->registerBrandAgency($agent, [
            'brand_name' => 'Apex Supercars',
            'principal_country' => 'IT',
            'has_import_rights' => true,
            'has_warranty_service' => true,
            'service_network_ref' => 'AutoServe SCBD Hub',
            'effective_from' => now()->subDay()->toDateString(),
        ]);

        $this->assertSame('Apex Supercars', $apm->brand_name);
        $this->assertTrue($apm->has_import_rights);
        $this->assertTrue($apm->has_warranty_service);
    }

    public function test_compliance_incident_and_commission_freeze(): void
    {
        $agent = $this->makeActiveAgent('AG-COMPL-1');

        $incident = $this->agency->reportIncident(
            $agent,
            'unauthorized_discount',
            'high',
            'commission_freeze',
            'Memberikan cashback tunai tanpa persetujuan kantor pusat'
        );

        $this->assertSame('active', $incident->status);
        $this->assertSame('commission_freeze', $incident->sanction);
        $this->assertSame('suspended', $agent->fresh()->status);
        $this->assertFalse($agent->fresh()->canEarn());

        $appealed = $this->agency->appealIncident($incident, 'Kesalahan persepsi promosi regional');
        $this->assertSame('appealed', $appealed->status);
        $this->assertSame('Kesalahan persepsi promosi regional', $appealed->appeal_notes);
    }

    public function test_fraud_check_detects_self_referral(): void
    {
        $agent = $this->makeActiveAgent('AG-FRAUD-1');

        $fraudSelf = $this->agency->checkFraud($agent, 'self_referral', [
            'agent_phone' => '081299998888',
            'customer_phone' => '081299998888',
        ]);

        $this->assertSame('blocked', $fraudSelf->decision);
        $this->assertSame(95, $fraudSelf->risk_score);
        $this->assertStringContainsString('Self-referral', $fraudSelf->reason);

        $fraudSpike = $this->agency->checkFraud($agent, 'commission_spike', [
            'current_amount' => 100_000_000,
            'avg_amount' => 10_000_000,
        ]);

        $this->assertSame('review', $fraudSpike->decision);
        $this->assertSame(75, $fraudSpike->risk_score);
    }

    public function test_agent_analytics_and_roi(): void
    {
        $agent = $this->makeActiveAgent('AG-ANALYTICS-1');
        $agent->total_sales_volume_idr = 500_000_000;
        $agent->total_deals_count = 5;
        $agent->save();

        $analytics = $this->agency->calculateAnalytics($agent);
        $this->assertSame('AG-ANALYTICS-1', $analytics['agent_code']);
        $this->assertSame(500_000_000, $analytics['total_sales_idr']);
        $this->assertSame(5, $analytics['total_deals']);
    }
}
