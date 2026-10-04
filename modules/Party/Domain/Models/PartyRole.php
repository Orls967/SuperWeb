<?php

declare(strict_types=1);

namespace Modules\Party\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Party\Domain\Enums\PartyRoleType;

class PartyRole extends Model
{
    protected $table = 'pty_party_roles';

    protected $fillable = ['party_id', 'role', 'scope_type', 'scope_id', 'is_active'];

    protected $casts = [
        'role' => PartyRoleType::class,
        'is_active' => 'boolean',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }
}
