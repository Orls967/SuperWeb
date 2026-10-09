<?php

declare(strict_types=1);

namespace Modules\Contract\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Contract\Domain\Enums\AmendmentKind;

/**
 * Jejak amandemen/addendum kontrak — 29.4.
 *
 * Setiap amandemen menyimpan diff field (`changes`) dan menautkan versi
 * hash-chain yang dihasilkan sehingga jejak audit lengkap.
 */
class Amendment extends Model
{
    use HasUuids;

    protected $table = 'ctr_amendments';

    protected $fillable = [
        'contract_id', 'contract_version_id', 'kind', 'changes',
        'old_value_idr', 'new_value_idr', 'old_end_date', 'new_end_date',
        'effective_date', 'schedule_recalculated', 'reason', 'created_by_name',
    ];

    protected $casts = [
        'kind' => AmendmentKind::class,
        'changes' => 'array',
        'old_value_idr' => 'integer',
        'new_value_idr' => 'integer',
        'old_end_date' => 'date',
        'new_end_date' => 'date',
        'effective_date' => 'date',
        'schedule_recalculated' => 'boolean',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ContractVersion::class, 'contract_version_id');
    }
}
