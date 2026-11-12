<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ControlPoint extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'ef_control_points';

    protected $fillable = [
        'id',
        'name',
        'category',
        'automated',
        'description',
    ];

    protected $casts = [
        'automated' => 'boolean',
    ];

    public function testRuns(): HasMany
    {
        return $this->hasMany(ControlTestRun::class, 'control_id');
    }
}
