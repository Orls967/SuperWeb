<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EduCorporateQuota extends Model
{
    protected $table = 'edu_corporate_quotas';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'corporate_client_id',
        'program_id',
        'quota_total',
        'quota_used',
        'unit_price_minor',
        'status',
        'idempotency_key',
    ];

    protected $casts = [
        'quota_total' => 'integer',
        'quota_used' => 'integer',
        'unit_price_minor' => 'integer',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(EduProgram::class, 'program_id', 'id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(EduEnrollment::class, 'corporate_quota_id', 'id');
    }
}
