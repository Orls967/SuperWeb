<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CoreAiModel extends Model
{
    protected $table = 'core_ai_models';

    protected $fillable = [
        'code',
        'version',
        'canonical_parameters',
        'parameter_hash',
    ];

    protected $casts = [
        'canonical_parameters' => 'array',
    ];
}
