<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EduCampusTerm extends Model
{
    protected $table = 'edu_campus_terms';

    protected $fillable = [
        'institution_id',
        'term_code',
        'name',
        'academic_year',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'date',
        'ends_at' => 'date',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(EduCampusInstitution::class, 'institution_id');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(EduCampusClass::class, 'term_id');
    }
}
