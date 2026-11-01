<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduPrerequisite extends Model
{
    protected $table = 'edu_prerequisites';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'program_id',
        'prerequisite_program_id',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(EduProgram::class, 'program_id', 'id');
    }

    public function prerequisiteProgram(): BelongsTo
    {
        return $this->belongsTo(EduProgram::class, 'prerequisite_program_id', 'id');
    }
}
