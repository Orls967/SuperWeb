<?php

declare(strict_types=1);

namespace Modules\Tlx\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Tlx\Application\Services\TelecomNetworkAndIotService;
use RuntimeException;
use Tests\TestCase;

class TelecomNetworkAndIotTest extends TestCase
{
    use RefreshDatabase;

    protected TelecomNetworkAndIotService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TelecomNetworkAndIotService::class);

        $accounts = [
            'tlx:intercompany_receivable:IDR' => 'asset',
            'tlx:iot_service_revenue:IDR' => 'revenue',
        ];

        foreach ($accounts as $code => $kind) {
            LedgerAccount::create([
                'code' => $code,
                'name' => "Telecom {$code}",
                'asset_code' => 'IDR',
                'kind' => $kind,
                'allow_negative' => true,
                'cached_balance' => '0',
            ]);
        }
    }

    public function test_130_2_and_130_6_a_bandwidth_oversubscription_rejected(): void
    {
        $siteA = $this->service->createSite([
            'site_code' => 'POP-JKT-01',
            'site_name' => 'Cyber 1 Building POP',
            'site_type' => 'POP',
            'region' => 'DKI_JAKARTA',
        ]);

        $siteB = $this->service->createSite([
            'site_code' => 'DC-CBT-01',
            'site_name' => 'Cibitung Hyperscale DC',
            'site_type' => 'DATA_CENTER',
            'region' => 'WEST_JAVA',
        ]);

        $link = $this->service->createLink([
            'link_code' => 'FBR-JKT-CBT-01',
            'origin_site_id' => $siteA->id,
            'dest_site_id' => $siteB->id,
            'link_medium' => 'FIBER',
            'bandwidth_capacity_gbps' => 100.0,
        ]);

        // Allocate 75 Gbps -> success
        $this->service->allocateBandwidth($link->id, 75.0);

        // Attempt to allocate another 35 Gbps (total 110 > 100) -> must throw exception!
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Bandwidth capacity oversubscription rejected');
        $this->service->allocateBandwidth($link->id, 35.0);
    }

    public function test_130_2_and_130_6_b_sla_uptime_calculation(): void
    {
        $siteA = $this->service->createSite(['site_code' => 'TWR-BDG-01', 'site_name' => 'Bandung Dago Tower', 'site_type' => 'TOWER', 'region' => 'WEST_JAVA']);
        $siteB = $this->service->createSite(['site_code' => 'POP-BDG-01', 'site_name' => 'Bandung POP Central', 'site_type' => 'POP', 'region' => 'WEST_JAVA']);

        $link = $this->service->createLink([
            'link_code' => 'MW-BDG-01',
            'origin_site_id' => $siteA->id,
            'dest_site_id' => $siteB->id,
            'link_medium' => 'MICROWAVE',
            'bandwidth_capacity_gbps' => 10.0,
        ]);

        // 432 minutes of downtime in a month (43,200 mins total) -> 99.00% uptime
        $updated = $this->service->recordLinkDowntime($link->id, 432);
        $this->assertEquals(99.00, $updated->actual_sla_uptime_pct);
    }

    public function test_130_3_and_130_6_c_iot_connectivity_billing_ledger_entry(): void
    {
        // Register 2 devices for Mining line
        $dev1 = $this->service->registerIotDevice([
            'device_type' => 'MINING_SENSOR',
            'owner_entity_id' => 'ENTITY-MINING-KALTIM-01',
            'rate_per_mb_minor' => 100, // 100 IDR/MB
        ]);
        $dev1->update(['consumed_mb_monthly' => 500.0]); // 50,000 IDR

        $dev2 = $this->service->registerIotDevice([
            'device_type' => 'TELEMATICS',
            'owner_entity_id' => 'ENTITY-MINING-KALTIM-01',
            'rate_per_mb_minor' => 100,
        ]);
        $dev2->update(['consumed_mb_monthly' => 1500.0]); // 150,000 IDR

        $invoice = $this->service->billIotConnectivity('ENTITY-MINING-KALTIM-01', '2026-10');

        $this->assertEquals(2, $invoice->active_device_count);
        $this->assertEquals(2000.0, $invoice->total_consumed_mb);
        $this->assertEquals(200000, $invoice->total_charge_minor);

        $tx = LedgerTransaction::with('entries')->findOrFail($invoice->ledger_transaction_id);
        $sum = $tx->entries->sum('amount_minor');
        $this->assertEquals(0, $sum);
        $this->assertEquals(2, $tx->entries->count());
    }

    public function test_130_5_and_130_6_d_noc_alarm_ticket_idempotent(): void
    {
        $alarmKey = 'ALARM:FBR-JKT-CBT-01:LOS:2026-10-08-0300';

        $ticket1 = $this->service->raiseNocAlarmTicket([
            'idempotency_alarm_key' => $alarmKey,
            'alarm_type' => 'LINK_DOWN',
            'severity' => 'CRITICAL',
            'correlated_line_incident_id' => 'INC-EV-CHARGER-OFFLINE-99',
        ]);

        $ticket2 = $this->service->raiseNocAlarmTicket([
            'idempotency_alarm_key' => $alarmKey,
            'alarm_type' => 'LINK_DOWN',
            'severity' => 'CRITICAL',
        ]);

        $this->assertEquals($ticket1->id, $ticket2->id);
        $this->assertEquals($ticket1->ticket_number, $ticket2->ticket_number);
    }
}
