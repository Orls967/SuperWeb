<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EduCampusClass extends Model
{
    protected $table = 'edu_campus_classes';

    protected $fillable = [
        'course_id',
        'term_id',
        'class_code',
        'day_of_week',
        'start_minute',
        'end_minute',
        'room_code',
        'lecturer_name',
        'max_capacity',
        'enrolled_count',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'start_minute' => 'integer',
        'end_minute' => 'integer',
        'max_capacity' => 'integer',
        'enrolled_count' => 'integer',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(EduCampusCourse::class, 'course_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(EduCampusTerm::class, 'term_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(EduCampusKrsEnrollment::class, 'class_id');
    }
}
