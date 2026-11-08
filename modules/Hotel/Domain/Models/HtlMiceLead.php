<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class HtlMiceLead extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'htl_mice_leads';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['signature_hash'];

    /**
     * @return HasMany<HtlEventProposal, $this>
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(HtlEventProposal::class, 'lead_id');
    }
}
