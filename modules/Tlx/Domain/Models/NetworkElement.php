<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NetworkElement extends Model
{
    use HasUuids;

    protected $table = 'tlx_network_elements';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'element_code',
        'element_type',
        'parent_element_id',
        'status',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(NetworkElement::class, 'parent_element_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(NetworkElement::class, 'parent_element_id');
    }

    public function alarms(): HasMany
    {
        return $this->hasMany(NocAlarm::class, 'element_id');
    }
}
