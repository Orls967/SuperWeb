<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EduCampusInstitution extends Model
{
    protected $table = 'edu_campus_institutions';

    protected $fillable = [
        'institution_code',
        'name',
        'type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function terms(): HasMany
    {
        return $this->hasMany(EduCampusTerm::class, 'institution_id');
    }

    public function courses(): HasMany
    {
        return $this->hasMany(EduCampusCourse::class, 'institution_id');
    }
}
