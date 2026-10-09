<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Sertifikat per lot (COA/COC/SNI/Halal/BPOM/GMP — data simulasi). */
class Certificate extends Model
{
    use HasUuids;

    protected $table = 'mfg_certificates';

    protected $fillable = ['lot_id', 'type', 'number', 'issued_at', 'expires_at', 'issuer', 'verified'];

    protected $casts = ['issued_at' => 'date', 'expires_at' => 'date', 'verified' => 'boolean'];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class, 'lot_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
