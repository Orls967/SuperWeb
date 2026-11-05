<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LifeBeneficiary extends Model
{
    protected $table = 'ins_lh_beneficiaries';

    /** @var list<string> */
    protected $fillable = [
        'life_policy_id',
        'beneficiary_party_id',
        'relationship',
        'share_percentage_bps',
        'is_primary',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'life_policy_id' => 'integer',
            'share_percentage_bps' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    /** @return BelongsTo<LifePolicy, $this> */
    public function lifePolicy(): BelongsTo
    {
        return $this->belongsTo(LifePolicy::class, 'life_policy_id');
    }
}
