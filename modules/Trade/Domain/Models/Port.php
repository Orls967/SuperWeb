<?php

declare(strict_types=1);

namespace Modules\Trade\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Port extends Model
{
    use HasUuids;

    protected $table = 'trd_ports';

    protected $fillable = [
        'code',
        'name',
        'country_code',
        'type',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }
}
