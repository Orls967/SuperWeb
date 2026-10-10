<?php

declare(strict_types=1);

namespace Modules\Asset\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Asset\Application\Services\AssetService;
use Modules\Asset\Domain\Enums\AssetCategoryCode;
use Modules\Asset\Domain\Models\Asset;
use Modules\Asset\Domain\Models\AssetCategory;
use Modules\Asset\Domain\Models\AssetLocation;

class AssetSeeder extends Seeder
{
    public function run(AssetService $service): void
    {
        // 30.1 — kategori default PSAK 16 (simulasi)
        $service->ensureDefaultCategories();

        // Hirarki lokasi contoh: entity → site → area (30.2)
        $head = AssetLocation::firstOrCreate(
            ['code' => 'AST-ENTITY'],
            ['name' => 'Grup Holding', 'level' => 'entity', 'legal_entity_id' => null],
        );

        $site = AssetLocation::firstOrCreate(
            ['code' => 'AST-SITE-HQ'],
            ['name' => 'Kantor Pusat Banjarmasin', 'level' => 'site', 'parent_id' => $head->id],
        );

        AssetLocation::firstOrCreate(
            ['code' => 'AST-AREA-WH'],
            ['name' => 'Gudang Logistik Utama', 'level' => 'area', 'parent_id' => $site->id],
        );

        // Contoh aset tunggal agar register tidak kosong pada demo.
        if (Asset::count() === 0) {
            $category = AssetCategory::where('code', AssetCategoryCode::Building->value)->firstOrFail();

            $service->register([
                'name' => 'Gudang Logistik Utama',
                'description' => 'Bangunan gudang pusat distribusi (aset contoh seed).',
                'category_id' => $category->id,
                'location_id' => $site->id,
                'acquisition_cost_idr' => 4_000_000_000,
                'landed_cost_idr' => 100_000_000,
                'source_type' => 'direct',
                'acquired_at' => now()->subYears(2)->toDateString(),
            ], null, false);
        }

        // Kapitalisasi idempoten semua aset non-legacy agar subledger == ledger.
        foreach (Asset::query()
            ->where('source_type', '!=', 'legacy_backfill')
            ->get() as $seededAsset) {
            $service->ensureCapitalized($seededAsset);
        }
    }
}
