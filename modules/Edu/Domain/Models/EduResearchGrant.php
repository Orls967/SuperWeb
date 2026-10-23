<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EduResearchGrant extends Model
{
    protected $table = 'edu_research_grants';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'grant_code',
        'title',
        'principal_investigator_name',
        'institution_party_uuid',
        'total_grant_minor',
        'disbursed_grant_minor',
        'status',
    ];

    protected $casts = [
        'total_grant_minor' => 'integer',
        'disbursed_grant_minor' => 'integer',
    ];

    public function milestones(): HasMany
    {
        return $this->hasMany(EduGrantMilestone::class, 'grant_uuid', 'id');
    }
}
