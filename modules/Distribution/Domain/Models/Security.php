<?php

declare(strict_types=1);

namespace Modules\Distribution\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Jaminan onboarding: bank garansi / deposit. */
class Security extends Model
{
    protected $table = 'dist_securities';

    protected $fillable = [
        'distributor_id', 'kind', 'amount_idr', 'reference', 'issued_at', 'expires_at', 'status', 'notes',
    ];

    protected $casts = ['amount_idr' => 'integer', 'issued_at' => 'date', 'expires_at' => 'date'];

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'distributor_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
