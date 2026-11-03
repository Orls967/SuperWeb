<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Pricing\Contracts\MaterialPriceQuote;

class MaterialPriceQuoteService implements MaterialPriceQuote
{
    public function pricePerKgIdr(string $materialCode, string $gradeCode, CarbonImmutable $at): ?int
    {
        $atString = $at->toDateTimeString();

        $row = DB::table('pric_material_prices')
            ->where('material_code', $materialCode)
            ->where('grade_code', $gradeCode)
            ->where('is_active', true)
            ->where('effective_from', '<=', $atString)
            ->where(function ($q) use ($atString) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>', $atString);
            })
            ->orderByDesc('effective_from')
            ->first();

        if (! $row) {
            return null;
        }

        return (int) $row->price_per_kg_idr;
    }
}

