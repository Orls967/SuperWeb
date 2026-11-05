<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EduCampusCourse extends Model
{
    protected $table = 'edu_campus_courses';

    protected $fillable = [
        'institution_id',
        'course_code',
        'name',
        'credits',
        'prerequisite_course_code',
        'minimum_gpa_required',
        'is_active',
    ];

    protected $casts = [
        'credits' => 'integer',
        'minimum_gpa_required' => 'float',
        'is_active' => 'boolean',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(EduCampusInstitution::class, 'institution_id');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(EduCampusClass::class, 'course_id');
    }
}
