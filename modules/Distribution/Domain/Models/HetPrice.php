<?php

declare(strict_types=1);

namespace Modules\Distribution\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/** Harga Eceran Tertinggi per SKU (SIMULASI) — price compliance 43.7. */
class HetPrice extends Model
{
    protected $table = 'dist_het_prices';

    protected $fillable = ['sku', 'het_idr', 'valid_from', 'valid_until'];

    protected $casts = ['het_idr' => 'integer', 'valid_from' => 'date', 'valid_until' => 'date'];

    public function isValidAt(?string $at = null): bool
    {
        $date = $at ?? now()->toDateString();

        return $this->valid_from->toDateString() <= $date
            && ($this->valid_until === null || $this->valid_until->toDateString() >= $date);
    }
}
