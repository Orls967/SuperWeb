<?php

declare(strict_types=1);

namespace Modules\Pricing\tests\Unit;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Pricing\Application\Services\MaterialPriceQuoteService;
use Tests\TestCase;

class MaterialPriceQuoteServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_per_kg_idr_returns_exact_integer_and_filters_correctly(): void
    {
        $service = new MaterialPriceQuoteService;
        $now = CarbonImmutable::parse('2026-11-03 10:00:00');

        // 1. Data belum ada -> return null
        $price = $service->pricePerKgIdr('SCRAP_ALUM', 'GRADE_A', $now);
        $this->assertNull($price);

        // 2. Insert harga aktif
        DB::table('pric_material_prices')->insert([
            'material_code' => 'SCRAP_ALUM',
            'grade_code' => 'GRADE_A',
            'price_per_kg_idr' => 24500,
            'effective_from' => '2026-11-01 00:00:00',
            'effective_to' => '2026-11-30 23:59:59',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Cek harga aktif ditemukan dan bertipe integer murni
        $quoted = $service->pricePerKgIdr('SCRAP_ALUM', 'GRADE_A', $now);
        $this->assertSame(24500, $quoted);
        $this->assertIsInt($quoted);

        // 3. Filter kode material salah -> null
        $this->assertNull($service->pricePerKgIdr('OTHER_MAT', 'GRADE_A', $now));

        // 4. Filter grade salah -> null
        $this->assertNull($service->pricePerKgIdr('SCRAP_ALUM', 'GRADE_B', $now));

        // 5. Filter waktu sebelum effective_from -> null
        $before = CarbonImmutable::parse('2026-10-31 23:59:59');
        $this->assertNull($service->pricePerKgIdr('SCRAP_ALUM', 'GRADE_A', $before));

        // 6. Filter waktu sesudah effective_to -> null
        $after = CarbonImmutable::parse('2026-12-01 00:00:00');
        $this->assertNull($service->pricePerKgIdr('SCRAP_ALUM', 'GRADE_A', $after));

        // 7. Nonaktifkan harga -> null
        DB::table('pric_material_prices')->where('material_code', 'SCRAP_ALUM')->update(['is_active' => false]);
        $this->assertNull($service->pricePerKgIdr('SCRAP_ALUM', 'GRADE_A', $now));

        // 8. Harga tanpa batas effective_to (null)
        DB::table('pric_material_prices')->insert([
            'material_code' => 'SCRAP_IRON',
            'grade_code' => 'GRADE_A',
            'price_per_kg_idr' => 6500,
            'effective_from' => '2026-11-01 00:00:00',
            'effective_to' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ironQuoted = $service->pricePerKgIdr('SCRAP_IRON', 'GRADE_A', $now->addMonths(6));
        $this->assertSame(6500, $ironQuoted);
        $this->assertIsInt($ironQuoted);
    }
}
