<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('spareparts')) {
            return;
        }

        $spareparts = DB::table('spareparts')->get();

        foreach ($spareparts as $sparepart) {
            $existing = DB::table('store_products')
                ->where('productable_type', 'serve_sparepart')
                ->where('productable_id', $sparepart->id)
                ->first();

            if ($existing) {
                continue;
            }

            $sku = $sparepart->code ?: 'PART-'.$sparepart->id;
            // Ensure unique SKU
            if (DB::table('store_products')->where('sku', $sku)->exists()) {
                $sku .= '-'.Str::random(4);
            }

            $slug = Str::slug($sparepart->name.'-'.$sku);
            if (DB::table('store_products')->where('slug', $slug)->exists()) {
                $slug .= '-'.Str::random(4);
            }

            $productId = DB::table('store_products')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'productable_type' => 'serve_sparepart',
                'productable_id' => $sparepart->id,
                'category_id' => null,
                'sku' => $sku,
                'name' => $sparepart->name,
                'slug' => $slug,
                'description' => "Sparepart bengkel: {$sparepart->name} ({$sparepart->unit})",
                'price' => $sparepart->price,
                'compare_at_price' => null,
                'cached_stock' => (int) $sparepart->stock,
                'is_listed' => false,
                'is_car' => false,
                'weight_gram' => 500,
                'images' => json_encode(['https://images.unsplash.com/photo-1486006920555-c77dce18193b?auto=format&fit=crop&w=800&q=80']),
                'created_at' => $sparepart->created_at ?? now(),
                'updated_at' => $sparepart->updated_at ?? now(),
            ]);

            if ((int) $sparepart->stock > 0) {
                DB::table('inv_stock_movements')->insert([
                    'product_id' => $productId,
                    'qty' => (int) $sparepart->stock,
                    'reason' => 'initial',
                    'source_type' => 'serve_sparepart',
                    'source_id' => $sparepart->id,
                    'note' => 'Migrasi stok awal dari data sparepart lama',
                    'created_by' => null,
                    'created_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $productIds = DB::table('store_products')
            ->where('productable_type', 'serve_sparepart')
            ->pluck('id');

        DB::table('inv_stock_movements')->whereIn('product_id', $productIds)->delete();
        DB::table('store_products')->whereIn('id', $productIds)->delete();
    }
};
