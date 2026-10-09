<?php

declare(strict_types=1);

namespace Modules\Party\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartyContact extends Model
{
    protected $table = 'pty_contacts';

    protected $fillable = ['party_id', 'label', 'contact_type', 'value', 'name', 'is_primary'];

    protected $casts = ['is_primary' => 'boolean'];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }
}
