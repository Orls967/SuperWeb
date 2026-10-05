<?php

declare(strict_types=1);

namespace Modules\Partner\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntellectualProperty extends Model
{
    use HasUuids;

    protected $table = 'ptn_intellectual_properties';

    protected $fillable = [
        'partner_id', 'type', 'registration_number', 'name', 'registered_at',
        'expires_at', 'status',
    ];

    protected $casts = [
        'registered_at' => 'date',
        'expires_at' => 'date',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }
}
