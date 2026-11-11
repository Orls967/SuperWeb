<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Weighted multi-party identity link with double-consent gating (W8-D56).
 */
class CoreIdentityLink extends Model
{
    protected $table = 'core_identity_links';

    protected $fillable = [
        'link_code',
        'tenant_id',
        'subject_type',
        'source_entity_id',
        'target_entity_id',
        'linkage_hash',
        'match_score',
        'source_consent',
        'target_consent',
        'status',
        'is_active',
        'linked_at',
    ];

    protected function casts(): array
    {
        return [
            'source_entity_id' => 'integer',
            'target_entity_id' => 'integer',
            'match_score' => 'integer',
            'source_consent' => 'boolean',
            'target_consent' => 'boolean',
            'is_active' => 'boolean',
            'linked_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(CoreTenant::class, 'tenant_id');
    }
}
