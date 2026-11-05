<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SybGroupMember extends Model
{
    protected $table = 'syb_group_members';

    protected $fillable = [
        'group_code',
        'member_party_ref',
        'member_name',
        'share_amount_idr',
        'is_active',
    ];

    protected $casts = [
        'share_amount_idr' => 'integer',
        'is_active' => 'boolean',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(SybGroupFinancing::class, 'group_code', 'group_code');
    }
}
