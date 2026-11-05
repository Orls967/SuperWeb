<?php

declare(strict_types=1);

namespace Modules\ProServices\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProServicesProject extends Model
{
    protected $table = 'psv_projects';

    protected $fillable = [
        'project_code',
        'client_party_ref',
        'contract_reference',
        'billing_type',
        'total_contract_idr',
        'estimated_cost_idr',
        'status',
    ];

    protected $casts = [
        'client_party_ref' => 'integer',
        'total_contract_idr' => 'integer',
        'estimated_cost_idr' => 'integer',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(ProServicesProjectMember::class, 'project_id');
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(ProServicesTimesheet::class, 'project_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(ProServicesInvoice::class, 'project_id');
    }

    public function revenueRecognitions(): HasMany
    {
        return $this->hasMany(ProServicesRevenueRecognition::class, 'project_id');
    }
}
