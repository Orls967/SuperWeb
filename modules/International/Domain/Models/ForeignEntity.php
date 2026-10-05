<?php

declare(strict_types=1);

namespace Modules\International\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForeignEntity extends Model
{
    protected $table = 'intl_foreign_entities';

    protected $guarded = [];

    protected $casts = [
        'has_apostille' => 'boolean',
        'aml_screened' => 'boolean',
    ];

    public function jointVentures(): HasMany
    {
        return $this->hasMany(JointVenture::class, 'foreign_entity_id');
    }

    public function technologyLicenses(): HasMany
    {
        return $this->hasMany(TechnologyLicense::class, 'foreign_entity_id');
    }

    public function oemContracts(): HasMany
    {
        return $this->hasMany(OemContract::class, 'foreign_entity_id');
    }

    public function techTransfers(): HasMany
    {
        return $this->hasMany(TechTransfer::class, 'foreign_entity_id');
    }
}
