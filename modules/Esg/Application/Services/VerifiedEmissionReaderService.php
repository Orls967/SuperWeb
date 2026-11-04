<?php

declare(strict_types=1);

namespace Modules\Esg\Application\Services;

use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Esg\Contracts\VerifiedEmissionReader;

/**
 * K7: membaca emisi spesifik terverifikasi berversi (S2: parameter regulasi/verifikator, bukan input bebas).
 */
final class VerifiedEmissionReaderService implements VerifiedEmissionReader
{
    public function specificEmissionPerTonne(string $productCode, string $originCountry, CarbonImmutable $at): ?array
    {
        $atString = $at->utc()->toDateTimeString();

        $row = DB::table('esg_verified_product_emissions')
            ->where('product_code', strtoupper(trim($productCode)))
            ->where('origin_country', strtoupper(trim($originCountry)))
            ->where('effective_from', '<=', $atString)
            ->where(function ($q) use ($atString) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>', $atString);
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            't_co2e_per_t' => (string) BigDecimal::of((string) $row->t_co2e_per_t)->toScale(6),
            'verification_ref' => (string) $row->verification_ref,
            'verified_at' => CarbonImmutable::parse($row->verified_at, 'UTC')->toIso8601String(),
        ];
    }
}
