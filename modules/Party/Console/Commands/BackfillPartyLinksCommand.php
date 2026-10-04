<?php

declare(strict_types=1);

namespace Modules\Party\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent backfill: link existing Carriers, ShipperAccounts, Tenants, Suppliers
 * to their corresponding Party records by matching on name.
 * Safe to run multiple times — only fills NULL party_id rows.
 */
class BackfillPartyLinksCommand extends Command
{
    protected $signature = 'party:backfill-links {--dry-run : Show what would be linked without committing}';

    protected $description = 'Backfill party_id links on existing module tables (idempotent)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $total = 0;

        // 1. Carriers: match on name
        $carriers = DB::table('lgx_carriers')->whereNull('party_id')->get();
        foreach ($carriers as $carrier) {
            $norm = strtolower(trim($carrier->name));
            $party = DB::table('pty_parties')->where('name_normalized', $norm)->where('is_active', true)->first();
            if ($party) {
                $total++;
                $this->line("  [lgx_carriers] {$carrier->id} ({$carrier->name}) → party {$party->id}");
                if (! $dryRun) {
                    DB::table('lgx_carriers')->where('id', $carrier->id)->update(['party_id' => $party->id]);
                }
            }
        }

        // 2. Mall Tenants: match on company_name or brand_name
        $tenants = DB::table('mall_tenants')->whereNull('party_id')->get();
        foreach ($tenants as $tenant) {
            $normCompany = strtolower(trim($tenant->company_name));
            $normBrand = strtolower(trim($tenant->brand_name));
            $party = DB::table('pty_parties')
                ->where('is_active', true)
                ->where(function ($q) use ($normCompany, $normBrand) {
                    $q->where('name_normalized', $normCompany)
                        ->orWhere('name_normalized', $normBrand);
                })
                ->first();
            if ($party) {
                $total++;
                $this->line("  [mall_tenants] {$tenant->id} ({$tenant->brand_name}) → party {$party->id}");
                if (! $dryRun) {
                    DB::table('mall_tenants')->where('id', $tenant->id)->update(['party_id' => $party->id]);
                }
            }
        }

        // 3. Resto Suppliers: match on name
        $suppliers = DB::table('resto_suppliers')->whereNull('party_id')->get();
        foreach ($suppliers as $supplier) {
            $norm = strtolower(trim($supplier->name));
            $party = DB::table('pty_parties')->where('name_normalized', $norm)->where('is_active', true)->first();
            if ($party) {
                $total++;
                $this->line("  [resto_suppliers] {$supplier->id} ({$supplier->name}) → party {$party->id}");
                if (! $dryRun) {
                    DB::table('resto_suppliers')->where('id', $supplier->id)->update(['party_id' => $party->id]);
                }
            }
        }

        $prefix = $dryRun ? '[DRY-RUN] Would link' : 'Linked';
        $this->info("{$prefix} {$total} rows to party records.");

        return self::SUCCESS;
    }
}
