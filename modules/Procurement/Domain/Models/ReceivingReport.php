<?php

declare(strict_types=1);

namespace Modules\Procurement\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReceivingReport extends Model
{
    use HasUuids;

    protected $table = 'prc_receiving_reports';

    protected $fillable = ['number', 'po_id', 'status', 'received_at', 'notes', 'received_by_user_id'];

    protected $casts = ['received_at' => 'date', 'received_by_user_id' => 'integer'];

    public function po(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ReceivingLine::class, 'grn_id');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class, 'grn_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SupplierReturn::class, 'grn_id');
    }
}
