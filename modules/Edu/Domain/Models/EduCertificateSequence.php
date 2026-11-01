<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduCertificateSequence extends Model
{
    protected $table = 'edu_certificate_sequences';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'program_id',
        'year_period',
        'last_sequence',
    ];

    protected $casts = [
        'year_period' => 'integer',
        'last_sequence' => 'integer',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(EduProgram::class, 'program_id', 'id');
    }
}
