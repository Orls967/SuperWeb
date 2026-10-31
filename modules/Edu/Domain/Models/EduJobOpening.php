<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EduJobOpening extends Model
{
    protected $table = 'edu_job_openings';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'job_code',
        'opening_code',
        'title',
        'hiring_entity_id',
        'hiring_entity',
        'location_city',
        'required_skills',
        'salary_budget_minor',
        'capacity',
        'annual_salary_minor',
        'headhunter_fee_bps',
        'required_credential_code',
        'job_type',
        'status',
    ];

    protected $casts = [
        'required_skills' => 'array',
        'salary_budget_minor' => 'integer',
        'annual_salary_minor' => 'integer',
        'capacity' => 'integer',
        'headhunter_fee_bps' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (EduJobOpening $opening) {
            if (empty($opening->job_code)) {
                $opening->job_code = $opening->opening_code ?? ('JOB-'.strtoupper(substr(md5(uniqid()), 0, 8)));
            }
            if (empty($opening->opening_code)) {
                $opening->opening_code = $opening->job_code;
            }
            if (empty($opening->hiring_entity_id)) {
                $opening->hiring_entity_id = $opening->hiring_entity ?? 'ENTITY-DEFAULT';
            }
            if (empty($opening->hiring_entity)) {
                $opening->hiring_entity = $opening->hiring_entity_id;
            }
            if (empty($opening->location_city)) {
                $opening->location_city = 'Jakarta';
            }
            if (empty($opening->required_skills)) {
                $opening->required_skills = [$opening->required_credential_code ?? 'GENERAL'];
            }
            if (empty($opening->salary_budget_minor)) {
                $opening->salary_budget_minor = $opening->annual_salary_minor ?? 100_000_000;
            }
            if (empty($opening->annual_salary_minor)) {
                $opening->annual_salary_minor = $opening->salary_budget_minor;
            }
            if (empty($opening->job_type)) {
                $opening->job_type = 'FULL_TIME';
            }
        });
    }

    public function preferences(): HasMany
    {
        return $this->hasMany(EduTalentPreference::class, 'opening_id', 'id');
    }

    public function placements(): HasMany
    {
        return $this->hasMany(EduPlacement::class, 'opening_id', 'id');
    }
}
