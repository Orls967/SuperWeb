<?php

namespace Modules\Proptech\Domain\Models\Bim;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BimModel extends Model
{
    protected $table = 'prp_bim_models';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'version' => 'integer',
    ];

    public function components(): HasMany
    {
        return $this->hasMany(TwinComponent::class, 'bim_model_id');
    }
}
