<?php

declare(strict_types=1);

namespace Modules\Procurement\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Supplier\Domain\Models\Supplier;

class SupplierAdvance extends Model
{
    use HasUuids;

    protected $table = 'prc_supplier_advances';

    protected $fillable = ['supplier_id', 'amount_idr', 'used_amount_idr', 'status', 'reference'];

    protected $casts = ['amount_idr' => 'integer', 'used_amount_idr' => 'integer'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function remaining(): int
    {
        return max(0, (int) $this->amount_idr - (int) $this->used_amount_idr);
    }
}
