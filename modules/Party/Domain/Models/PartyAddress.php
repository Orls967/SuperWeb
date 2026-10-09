<?php

declare(strict_types=1);

namespace Modules\Party\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartyAddress extends Model
{
    protected $table = 'pty_addresses';

    protected $fillable = [
        'party_id', 'label', 'line1', 'line2', 'city',
        'province', 'postal_code', 'country', 'latitude', 'longitude', 'is_primary',
    ];

    protected $casts = ['is_primary' => 'boolean'];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }
}
