<?php

declare(strict_types=1);

namespace Modules\ProServices\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProServicesInvoice extends Model
{
    protected $table = 'psv_invoices';

    protected $fillable = [
        'invoice_code',
        'project_id',
        'client_party_ref',
        'subtotal_idr',
        'idempotency_key',
        'status',
    ];

    protected $casts = [
        'project_id' => 'integer',
        'client_party_ref' => 'integer',
        'subtotal_idr' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ProServicesProject::class, 'project_id');
    }
}
