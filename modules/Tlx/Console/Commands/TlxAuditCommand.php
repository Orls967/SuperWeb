<?php

declare(strict_types=1);

namespace Modules\Tlx\Console\Commands;

use Illuminate\Console\Command;
use Modules\Tlx\Domain\Models\ColocationContract;
use Modules\Tlx\Domain\Models\DcPowerReading;
use Modules\Tlx\Domain\Models\InterconnectSettlement;
use Modules\Tlx\Domain\Models\IotConnectivityInvoice;
use Modules\Tlx\Domain\Models\IotDevice;
use Modules\Tlx\Domain\Models\NetworkElement;
use Modules\Tlx\Domain\Models\NocAlarm;
use Modules\Tlx\Domain\Models\SimSubscription;
use Modules\Tlx\Domain\Models\TelecomCdr;
use Modules\Tlx\Domain\Models\TelecomDataCenter;
use Modules\Tlx\Domain\Models\TelecomLink;
use Modules\Tlx\Domain\Models\TelecomSite;

class TlxAuditCommand extends Command
{
    protected $signature = 'tlx:audit';

    protected $description = 'Audit dua-sumber modul Tlx: integritas registry IoT, rating CDR, kapasitas colocation DC, PUE, dan korelasi alarm topologi';

    public function handle(): int
    {
        $this->info('Starting Telecom & Data Center Infrastructure Audit (tlx:audit)...');

        $siteCount = TelecomSite::count();
        $linkCount = TelecomLink::count();
        $simCount = SimSubscription::count();
        $deviceCount = IotDevice::count();
        $dcCount = TelecomDataCenter::count();
        $contractCount = ColocationContract::count();
        $settlementCount = InterconnectSettlement::count();

        $discrepancies = 0;

        // 1. Verify rack capacity vs limit (Preserves TlxAuditCorruptionFixture)
        $overCapacityDcs = TelecomDataCenter::whereColumn('occupied_racks', '>', 'total_racks_capacity')->count();
        if ($overCapacityDcs > 0) {
            $this->warn("Found {$overCapacityDcs} data centers exceeding total rack capacity.");
            $discrepancies += $overCapacityDcs;
        }

        // 2. Active devices in invoices vs active devices in single registry
        $invoices = IotConnectivityInvoice::all();
        foreach ($invoices as $inv) {
            $actualActiveCount = IotDevice::where('owner_entity_id', $inv->owner_entity_id)
                ->where('status', 'ACTIVE')
                ->count();
            if ($inv->active_device_count !== $actualActiveCount) {
                $this->warn("Invoice {$inv->invoice_number}: active device count mismatch (invoice: {$inv->active_device_count}, registry: {$actualActiveCount}).");
                $discrepancies++;
            }
        }

        // 3. CDR rating integrity: rated_bytes must not be less than transferred bytes
        $invalidCdrs = TelecomCdr::whereColumn('rated_bytes', '<', 'bytes_transferred')->count();
        if ($invalidCdrs > 0) {
            $this->warn("Found {$invalidCdrs} records with CDR rating discrepancy (rated < transferred).");
            $discrepancies += $invalidCdrs;
        }

        // 4. DC occupied racks vs active colocation contracts sum
        $dcs = TelecomDataCenter::all();
        foreach ($dcs as $dc) {
            $hasContracts = ColocationContract::where('dc_id', $dc->id)->exists();
            if ($hasContracts) {
                $contractRacks = (int) ColocationContract::where('dc_id', $dc->id)
                    ->where('port_status', 'UP')
                    ->sum('rack_units_allocated');
                if ($contractRacks !== (int) $dc->occupied_racks) {
                    $this->warn("DC {$dc->dc_code}: DC occupied rack discrepancy (DC occupied: {$dc->occupied_racks}, active contracts: {$contractRacks}).");
                    $discrepancies++;
                }
            }
        }

        // 5. PUE reported vs dynamic measured PUE
        foreach ($dcs as $dc) {
            $readings = DcPowerReading::where('dc_id', $dc->id)->get();
            if ($readings->isNotEmpty()) {
                $totalFacility = $readings->sum('total_facility_energy_kwh');
                $totalIt = $readings->sum('it_equipment_energy_kwh');
                if ($totalIt > 0) {
                    $calculatedPue = (float) round($totalFacility / $totalIt, 2);
                    if (abs($calculatedPue - (float) $dc->measured_pue) > 0.05) {
                        $this->warn("DC {$dc->dc_code}: PUE calculation discrepancy (reported: {$dc->measured_pue}, calculated: {$calculatedPue}).");
                        $discrepancies++;
                    }
                }
            }
        }

        // 6. Invalid topology alarm suppression without down ancestor
        $suppressedAlarms = NocAlarm::where('is_suppressed', true)->get();
        foreach ($suppressedAlarms as $al) {
            $node = NetworkElement::find($al->element_id);
            if ($node && $node->parent_element_id === null && $al->suppressed_by_element_id === null) {
                $this->warn("Invalid topology alarm suppression for alarm [{$al->alarm_code}]: root element has no parent or down ancestor.");
                $discrepancies++;
            }
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Telecom Sites', $siteCount],
                ['Network Links & Slices', $linkCount],
                ['Active SIM Subscriptions', $simCount],
                ['IoT Connected Devices', $deviceCount],
                ['Data Center Facilities', $dcCount],
                ['Colocation Contracts', $contractCount],
                ['Interconnect Settlements', $settlementCount],
                ['Discrepancies', $discrepancies],
            ]
        );

        if ($discrepancies > 0) {
            $this->error("Tlx audit FAILED with {$discrepancies} discrepancies detected!");

            return self::FAILURE;
        }

        $this->info('Tlx audit PASSED with 0 discrepancy.');

        return self::SUCCESS;
    }
}
