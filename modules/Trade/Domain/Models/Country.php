<?php

declare(strict_types=1);

namespace Modules\Trade\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $table = 'trd_countries';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'currency_code',
        'has_fta',
    ];

    protected $casts = [
        'has_fta' => 'boolean',
    ];

    public function ports(): HasMany
    {
        return $this->hasMany(Port::class, 'country_code', 'code');
    }
}
