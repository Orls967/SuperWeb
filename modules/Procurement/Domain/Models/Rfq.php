<?php

declare(strict_types=1);

namespace Modules\Procurement\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rfq extends Model
{
    use HasUuids;

    protected $table = 'prc_rfqs';

    protected $fillable = [
        'number', 'requisition_id', 'title', 'status', 'opens_at', 'closes_at',
        'terms', 'created_by_user_id',
    ];

    protected $casts = ['opens_at' => 'datetime', 'closes_at' => 'datetime', 'created_by_user_id' => 'integer'];

    public function invitations(): HasMany
    {
        return $this->hasMany(RfqInvitation::class, 'rfq_id');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class, 'rfq_id');
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class, 'requisition_id');
    }
}
