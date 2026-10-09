<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Slotting ABC per product (41.6). */
class Slotting extends Model
{
    protected $table = 'wms_slottings';

    protected $fillable = ['product_id', 'abc_class', 'annual_value_idr', 'suggested_zone_id'];

    protected $casts = ['product_id' => 'integer', 'annual_value_idr' => 'decimal:2', 'suggested_zone_id' => 'integer'];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'suggested_zone_id');
    }

    /** Kelas ABC dari nilai tahunan: A ≥ 80%, B ≥ 95%, sisanya C. */
    public static function classify(float $annualValue, float $grandTotal): string
    {
        if ($grandTotal <= 0) {
            return 'C';
        }

        $share = $annualValue / $grandTotal;
        if ($share >= 0.80) {
            return 'A';
        }

        return $share >= 0.95 ? 'B' : 'C';
    }
}
