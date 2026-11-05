<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SybTradeCustodyDocument extends Model
{
    protected $table = 'syb_trade_custody_documents';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'document_type',
        'document_number',
        'issuer_name',
        'carrier_name',
        'cargo_description',
        'document_hash_sha256',
        'verified_qabdh_at',
        'verifier_officer_ref',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'verified_qabdh_at' => 'datetime',
    ];

    /**
     * @return HasMany<SybTradeCustodyInspection, $this>
     */
    public function inspections(): HasMany
    {
        return $this->hasMany(SybTradeCustodyInspection::class, 'custody_document_id');
    }
}
