<?php

declare(strict_types=1);

namespace Modules\Asset\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Asset\Application\Services\AssetService;
use Modules\Asset\Domain\Enums\AssetEventType;
use Modules\Asset\Domain\Enums\AssetStatus;
use Modules\Asset\Domain\Models\AssetCategory;

/**
 * 30.6 Konsolidasi aset lama → modul Asset (idempoten, backfill `asset_id`).
 *
 * Menghubungkan Mall\Asset, armada Logistics (Truck/Trailer/Vessel/Aircraft/Container)
 * ke aset `ast_assets` tanpa menyentuh modul lain (hanya query tabel).
 */
class BackfillAssetLinksCommand extends Command
{
    protected $signature = 'ast:backfill-links {--dry-run : Tampilkan rencana tanpa menyimpan}';

    protected $description = 'Backfill asset_id pada data aset lama (idempoten, aman diulang)';

    public function handle(AssetService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $service->ensureDefaultCategories();
        $total = 0;

        // 1. Mall\Asset → kategori equipment
        if (DB::getSchemaBuilder()->hasTable('mall_assets')) {
            $category = AssetCategory::where('code', 'equipment')->firstOrFail();
            $rows = DB::table('mall_assets')->whereNull('asset_id')->get(['id', 'name', 'asset_tag', 'status']);

            foreach ($rows as $row) {
                $total++;
                $this->line("  [mall_assets] {$row->id} ({$row->name})");
                if (! $dryRun) {
                    $this->linkLegacy('mall_asset', (int) $row->id, $row->name, (string) $row->asset_tag, $category, $service, $row->status === 'decommissioned');
                }
            }
        }

        // 2. Armada Logistics → kategori vehicle
        $fleet = [
            'lgx_trucks' => 'Truk',
            'lgx_trailers' => 'Trailer',
            'lgx_vessels' => 'Kapal',
            'lgx_aircraft' => 'Pesawat',
            'lgx_containers' => 'Kontainer',
        ];

        foreach ($fleet as $table => $label) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            $columns = Schema::hasColumn($table, 'asset_id') ? ['id'] : null;
            if ($columns === null) {
                continue;
            }

            $rows = DB::table($table)->whereNull('asset_id')->get(['id']);

            foreach ($rows as $row) {
                $total++;
                $this->line("  [{$table}] {$row->id}");
                if (! $dryRun) {
                    $name = DB::table($table)->where('id', $row->id)->value('plate_number')
                        ?? DB::table($table)->where('id', $row->id)->value('imo_number')
                        ?? DB::table($table)->where('id', $row->id)->value('name')
                        ?? "{$label} #{$row->id}";

                    $this->linkLegacy($table, (int) $row->id, (string) $name, '', $category = AssetCategory::where('code', 'vehicle')->firstOrFail(), $service, false, $label);
                }
            }
        }

        $this->info($dryRun ? "Dry-run: {$total} baris akan ditautkan." : "✓ {$total} aset lama ditautkan (idempoten).");

        return self::SUCCESS;
    }

    private function linkLegacy(
        string $sourceType,
        int $sourceId,
        string $name,
        string $tag,
        AssetCategory $category,
        AssetService $service,
        bool $disposed,
        string $brand = ''
    ): void {
        $asset = $service->register([
            'name' => $name,
            'category_id' => $category->id,
            'source_type' => 'legacy_backfill',
            'source_id' => $sourceId,
            'acquisition_cost_idr' => 0,
        ], null, false); // tidak posting ledger (data legacy, biaya sudah tercatat di modul asal)

        if ($disposed) {
            $asset->update(['status' => AssetStatus::Disposed]);
        }

        DB::table($this->targetTable($sourceType))->where('id', $sourceId)->update(['asset_id' => $asset->id]);

        $service->recordEvent($asset, AssetEventType::Acquisition, [
            'legacy_source' => $sourceType,
            'legacy_id' => $sourceId,
            'note' => 'Tautan aset lama (backfill idempoten)',
        ], 'ast:backfill-links');
    }

    private function targetTable(string $sourceType): string
    {
        return $sourceType === 'mall_asset' ? 'mall_assets' : $sourceType;
    }
}
