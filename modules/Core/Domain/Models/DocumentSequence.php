<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    protected $table = 'core_document_sequences';

    protected $fillable = [
        'entity_code',
        'document_type',
        'year',
        'month',
        'prefix',
        'suffix',
        'current_number',
        'padding',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'current_number' => 'integer',
        'padding' => 'integer',
    ];
}
