<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class CoreAiInference extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'core_ai_inferences';

    protected $fillable = [
        'model_code',
        'model_version',
        'input_snapshot',
        'input_hash',
        'seed',
        'output',
        'output_hash',
    ];

    protected $casts = [
        'input_snapshot' => 'array',
        'output' => 'array',
        'seed' => 'integer',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('CoreAiInference bersifat append-only: update dilarang (PR5)');
        });

        static::deleting(function (): void {
            throw new RuntimeException('CoreAiInference bersifat append-only: delete dilarang (PR5)');
        });
    }
}

