<?php

declare(strict_types=1);

namespace Modules\Egy\Console\Commands;

use Illuminate\Console\Command;
use Modules\Banking\Domain\Models\LedgerEntry;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Egy\Domain\Models\BessArbitrageRun;
use Modules\Egy\Domain\Models\GridNode;
use Modules\Egy\Domain\Models\MeterReading;
use Modules\Egy\Domain\Models\PpaContract;
use Modules\Egy\Domain\Models\RenewableEnergyCertificate;
use Modules\Egy\Domain\Models\SmartMeter;
use Modules\Egy\Domain\Models\UtilityConsolidatedInvoice;
use Modules\Shared\Domain\Numerics\MeterDelta;

class EgyAuditCommand extends Command
{
    protected $signature = 'egy:audit';

    protected $description = 'Audit jaringan energi: beban trafo, telemetri smart meter, kontrak PPA, faktur konsolidasi, eliminasi intercompany, dan rekonsiliasi ledger dua sumber';

    public function handle(): int
    {
        $this->info('Starting Smart Grid & Energy Operations Audit (egy:audit)...');

        $nodeCount = GridNode::count();
        $meterCount = SmartMeter::count();
        $readingCount = MeterReading::count();
        $invoiceCount = UtilityConsolidatedInvoice::count();
        $ppaCount = PpaContract::count();
        $recCount = RenewableEnergyCertificate::count();
        $bessCount = BessArbitrageRun::count();

        $discrepancies = 0;

        // 1. Check severe transformer overload
        $overloadedNodes = GridNode::whereColumn('current_load_mw', '>', 'peak_capacity_mw')->count();
        if ($overloadedNodes > 0) {
            $this->warn("Found {$overloadedNodes} transformers exceeding maximum design capacity.");
            $discrepancies += $overloadedNodes;
        }

        // 2. Check negative energy in RECs
        $invalidRecs = RenewableEnergyCertificate::where('energy_mwh', '<=', 0)->count();
        if ($invalidRecs > 0) {
            $this->warn("Found {$invalidRecs} RECs with non-positive MWh value.");
            $discrepancies += $invalidRecs;
        }

        // 3. Factual Meter Reading Register vs Delta Integrity
        $meters = SmartMeter::with('readings')->get();
        foreach ($meters as $meter) {
            $readings = $meter->readings()->orderBy('reading_at', 'asc')->get();
            $prev = null;
            foreach ($readings as $r) {
                if ($r->interval_status === 'RESET') {
                    $expectedDelta = (int) $r->cumulative_wh;
                    if ((int) $r->delta_wh !== $expectedDelta) {
                        $this->error("Tampered delta on meter [{$meter->meter_serial_number}] at {$r->reading_at}: recorded {$r->delta_wh} Wh, expected {$expectedDelta} Wh.");
                        $discrepancies++;
                    }
                } elseif ($prev === null) {
                    if ((int) $r->delta_wh !== 0 && (int) $r->delta_wh !== (int) $r->cumulative_wh) {
                        $this->error("Tampered delta on meter [{$meter->meter_serial_number}] at {$r->reading_at}: recorded {$r->delta_wh} Wh, expected 0 or {$r->cumulative_wh} Wh.");
                        $discrepancies++;
                    }
                } else {
                    $prevWh = (int) $prev->cumulative_wh;
                    $currWh = (int) $r->cumulative_wh;
                    $modulus = $meter->modulus !== null ? (int) $meter->modulus : null;
                    $expectedDelta = MeterDelta::calculate($prevWh, $currWh, $modulus);

                    if ((int) $r->delta_wh !== $expectedDelta) {
                        $this->error("Tampered delta on meter [{$meter->meter_serial_number}] at {$r->reading_at}: recorded {$r->delta_wh} Wh, expected {$expectedDelta} Wh.");
                        $discrepancies++;
                    }
                }

                $prev = $r;
            }
        }

        // 4. Consolidated Invoices vs Lines Integrity
        $invoices = UtilityConsolidatedInvoice::with('lines')->get();
        foreach ($invoices as $inv) {
            $linesTotal = (int) $inv->lines()->sum('subtotal_minor');
            if ($linesTotal !== (int) $inv->total_consolidated_minor) {
                $this->error("Invoice [{$inv->invoice_number}] line sum ({$linesTotal}) does not match total ({$inv->total_consolidated_minor}).");
                $discrepancies++;
            }

            $linesWh = (int) $inv->lines()->sum('wh_consumed');
            if ($linesWh !== (int) $inv->total_wh) {
                $this->error("Invoice [{$inv->invoice_number}] line Wh ({$linesWh}) does not match total Wh ({$inv->total_wh}).");
                $discrepancies++;
            }

            // 5. Two-source audit: Invoices vs General Ledger Postings
            if ($inv->status === 'ISSUED' && $inv->total_consolidated_minor > 0) {
                $tx = LedgerTransaction::where('reference_type', 'EGY_UTILITY_INVOICE')
                    ->where('reference_id', $inv->invoice_number)
                    ->first();

                if (! $tx) {
                    $this->error("Missing ledger transaction for issued invoice [{$inv->invoice_number}].");
                    $discrepancies++;
                } else {
                    $arEntry = $tx->entries()->whereHas('account', fn ($q) => $q->where('code', "ar:egy:property:{$inv->property_id}:IDR"))->first();
                    if (! $arEntry) {
                        $this->error("Missing AR entry for invoice [{$inv->invoice_number}].");
                        $discrepancies++;
                    } else {
                        $postedMinor = (int) abs((float) $arEntry->amount);
                        if ($postedMinor !== (int) $inv->total_consolidated_minor) {
                            $this->error("Tampered ledger entry for invoice [{$inv->invoice_number}]: posted {$postedMinor}, invoice {$inv->total_consolidated_minor}.");
                            $discrepancies++;
                        }
                    }
                }
            }
        }

        // 6. Intercompany Mirror & Elimination Equilibrium Check
        $icAr = (float) LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'ar:ic:producer:egy:IDR'))->sum('amount');
        $icAp = (float) LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'ap:ic:egy:producer:IDR'))->sum('amount');

        if (abs($icAr + $icAp) > 0.001) {
            $this->error("Intercompany AR ({$icAr}) and AP ({$icAp}) do not eliminate to zero! Net diff: ".($icAr + $icAp));
            $discrepancies++;
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Grid Transformer Nodes', $nodeCount],
                ['Smart Meters', $meterCount],
                ['Telemetry Readings', $readingCount],
                ['Consolidated Invoices', $invoiceCount],
                ['PPA Contracts', $ppaCount],
                ['Renewable Energy Certificates', $recCount],
                ['BESS Arbitrage Runs', $bessCount],
                ['Integrity Discrepancies', $discrepancies],
            ]
        );

        if ($discrepancies > 0) {
            $this->error("Energy audit FAILED with {$discrepancies} stability discrepancies detected!");

            return self::FAILURE;
        }

        $this->info('Energy audit PASSED with 0 discrepancy.');

        return self::SUCCESS;
    }
}
