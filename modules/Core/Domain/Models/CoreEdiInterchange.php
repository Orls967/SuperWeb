<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoreEdiInterchange extends Model
{
    protected $table = 'core_edi_interchanges';

    protected $fillable = [
        'tenant_id',
        'control_ref',
        'standard',
        'message_type',
        'sender_ref',
        'receiver_ref',
        'document_number',
        'status',
        'raw_payload',
        'canonical_json',
        'contrl_payload',
        'handler_module',
        'handler_idempotency_key',
        'target_reference',
        'error_codes',
        'rejection_reason',
        'cert_hash',
        'idempotency_key',
    ];

    protected $casts = [
        'error_codes' => 'array',
        'canonical_json' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(CoreTenant::class, 'tenant_id', 'id');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(CoreEdiTransition::class, 'edi_interchange_id')->orderBy('id');
    }
}
