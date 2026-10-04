<?php

declare(strict_types=1);

namespace Modules\Supplier\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierCertification extends Model
{
    protected $table = 'sup_certifications';

    protected $fillable = ['supplier_id', 'type', 'number', 'issuer', 'issued_at', 'expires_at', 'document_path', 'is_active'];

    protected $casts = ['issued_at' => 'date', 'expires_at' => 'date', 'is_active' => 'boolean'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
