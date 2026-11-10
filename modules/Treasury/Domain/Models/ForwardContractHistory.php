<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForwardContractHistory extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'trs_forward_contract_histories';

    protected $fillable = [
        'forward_contract_id',
        'from_status',
        'to_status',
        'actor_id',
        'action',
        'reason',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'actor_id' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(ForwardContract::class, 'forward_contract_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
