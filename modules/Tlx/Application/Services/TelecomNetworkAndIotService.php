<?php

declare(strict_types=1);

namespace Modules\Tlx\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Tlx\Domain\Models\IotConnectivityInvoice;
use Modules\Tlx\Domain\Models\IotDevice;
use Modules\Tlx\Domain\Models\NocNetworkTicket;
use Modules\Tlx\Domain\Models\TelecomLink;
use Modules\Tlx\Domain\Models\TelecomSite;
use RuntimeException;

class TelecomNetworkAndIotService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    public function createSite(array $params): TelecomSite
    {
        return TelecomSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => $params['site_code'],
            'site_name' => $params['site_name'],
            'site_type' => $params['site_type'],
            'region' => $params['region'],
            'latitude' => (float) ($params['latitude'] ?? null),
            'longitude' => (float) ($params['longitude'] ?? null),
            'status' => 'ACTIVE',
        ]);
    }

    public function createLink(array $params): TelecomLink
    {
        return TelecomLink::create([
            'id' => (string) Str::uuid(),
            'link_code' => $params['link_code'],
            'origin_site_id' => $params['origin_site_id'],
            'dest_site_id' => $params['dest_site_id'],
            'link_medium' => $params['link_medium'],
            'bandwidth_capacity_gbps' => (float) $params['bandwidth_capacity_gbps'],
            'allocated_bandwidth_gbps' => 0.0,
            'target_sla_uptime_pct' => (float) ($params['target_sla_uptime_pct'] ?? 99.90),
            'actual_sla_uptime_pct' => 100.00,
            'downtime_minutes_month' => 0,
            'status' => 'ACTIVE',
        ]);
    }

    public function allocateBandwidth(string $linkId, float $requestedGbps): TelecomLink
    {
        $link = TelecomLink::findOrFail($linkId);
        $newAllocated = $link->allocated_bandwidth_gbps + $requestedGbps;

        if ($newAllocated > $link->bandwidth_capacity_gbps) {
            throw new RuntimeException("Bandwidth capacity oversubscription rejected: requested total {$newAllocated}Gbps exceeds link capacity {$link->bandwidth_capacity_gbps}Gbps.");
        }

        $link->update(['allocated_bandwidth_gbps' => $newAllocated]);

        return $link;
    }

    public function recordLinkDowntime(string $linkId, int $downtimeMinutes): TelecomLink
    {
        $link = TelecomLink::findOrFail($linkId);
        $totalMinutesMonth = 30 * 24 * 60; // 43,200 minutes

        $newDowntime = $link->downtime_minutes_month + $downtimeMinutes;
        $uptimePct = max(0.0, (($totalMinutesMonth - $newDowntime) / $totalMinutesMonth) * 100.0);

        $link->update([
            'downtime_minutes_month' => $newDowntime,
            'actual_sla_uptime_pct' => round($uptimePct, 2),
        ]);

        return $link;
    }

    public function registerIotDevice(array $params): IotDevice
    {
        return IotDevice::create([
            'id' => (string) Str::uuid(),
            'device_uuid' => $params['device_uuid'] ?? (string) Str::uuid(),
            'device_type' => $params['device_type'],
            'owner_entity_id' => $params['owner_entity_id'],
            'active_sim_iccid' => $params['active_sim_iccid'] ?? '8962'.strtoupper(Str::random(16)),
            'data_quota_mb_monthly' => (float) ($params['data_quota_mb_monthly'] ?? 1000.0),
            'consumed_mb_monthly' => 0.0,
            'rate_per_mb_minor' => (int) ($params['rate_per_mb_minor'] ?? 50),
            'status' => 'ACTIVE',
        ]);
    }

    public function billIotConnectivity(string $ownerEntityId, string $billingPeriod): IotConnectivityInvoice
    {
        $devices = IotDevice::where('owner_entity_id', $ownerEntityId)->get();
        $deviceCount = $devices->count();
        $totalMb = (float) $devices->sum('consumed_mb_monthly');

        $totalCharge = 0;
        foreach ($devices as $d) {
            $totalCharge += (int) round($d->consumed_mb_monthly * $d->rate_per_mb_minor);
        }

        $invoiceNumber = 'IOT-INV-'.strtoupper(Str::random(8));

        return DB::transaction(function () use ($ownerEntityId, $billingPeriod, $deviceCount, $totalMb, $totalCharge, $invoiceNumber) {
            $tx = $this->ledgerService->post(new PostingDTO(
                type: 'TELECOM_IOT_CONNECTIVITY_BILLING',
                description: "IoT connectivity intercompany billing for {$ownerEntityId} ({$billingPeriod})",
                idempotencyKey: 'TLX-IOT-'.$invoiceNumber,
                entries: [
                    PostingEntryDTO::forCode('tlx:intercompany_receivable:IDR', 'IDR', $totalCharge),
                    PostingEntryDTO::forCode('tlx:iot_service_revenue:IDR', 'IDR', -$totalCharge),
                ],
                referenceType: 'IOT_INVOICE',
                referenceId: $invoiceNumber,
            ));

            return IotConnectivityInvoice::create([
                'id' => (string) Str::uuid(),
                'invoice_number' => $invoiceNumber,
                'owner_entity_id' => $ownerEntityId,
                'billing_period' => $billingPeriod,
                'active_device_count' => $deviceCount,
                'total_consumed_mb' => $totalMb,
                'total_charge_minor' => $totalCharge,
                'ledger_transaction_id' => $tx->id,
            ]);
        });
    }

    public function raiseNocAlarmTicket(array $params): NocNetworkTicket
    {
        $key = $params['idempotency_alarm_key'];

        $existing = NocNetworkTicket::where('idempotency_alarm_key', $key)->first();
        if ($existing) {
            return $existing; // Idempotent 1x ticket
        }

        return NocNetworkTicket::create([
            'id' => (string) Str::uuid(),
            'ticket_number' => 'NOC-'.strtoupper(Str::random(8)),
            'idempotency_alarm_key' => $key,
            'link_id' => $params['link_id'] ?? null,
            'site_id' => $params['site_id'] ?? null,
            'alarm_type' => $params['alarm_type'],
            'severity' => $params['severity'] ?? 'CRITICAL',
            'correlated_line_incident_id' => $params['correlated_line_incident_id'] ?? null,
            'status' => 'OPEN',
            'raised_at' => Carbon::now(),
        ]);
    }
}
