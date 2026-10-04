<?php

declare(strict_types=1);

namespace Modules\Contract\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Contract\Domain\Enums\ContractStatus;
use Modules\Contract\Domain\Enums\ContractType;
use Modules\Party\Domain\Models\LegalEntity;

class Contract extends Model
{
    use HasUuids;

    protected $table = 'ctr_contracts';

    protected $fillable = [
        'contract_number', 'title', 'contract_type', 'status',
        'legal_entity_id', 'template_id', 'total_value_idr', 'currency',
        'start_date', 'end_date', 'notice_period_days', 'auto_renew',
        'renewal_period_months', 'governing_law', 'dispute_forum',
        'current_body', 'current_hash', 'approval_id', 'signed_at',
        'activated_at', 'terminated_at', 'termination_reason',
        'suspension_reason', 'renewed_to_id', 'created_by',
    ];

    protected $casts = [
        'contract_type' => ContractType::class,
        'status' => ContractStatus::class,
        'total_value_idr' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'notice_period_days' => 'integer',
        'auto_renew' => 'boolean',
        'renewal_period_months' => 'integer',
        'signed_at' => 'datetime',
        'activated_at' => 'datetime',
        'terminated_at' => 'datetime',
    ];

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class, 'legal_entity_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class, 'template_id');
    }

    public function parties(): HasMany
    {
        return $this->hasMany(ContractParty::class, 'contract_id')->orderBy('signing_order');
    }

    public function clauses(): HasMany
    {
        return $this->hasMany(ContractClause::class, 'contract_id')->orderBy('display_order');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ContractVersion::class, 'contract_id')->orderBy('sequence');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ContractMilestone::class, 'contract_id')->orderBy('due_date');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ContractAttachment::class, 'contract_id');
    }

    public function latestVersion(): ?ContractVersion
    {
        return $this->versions()->latest('sequence')->first();
    }

    public function isFullySigned(): bool
    {
        $parties = $this->parties;
        if ($parties->isEmpty()) {
            return false;
        }

        return $parties->every(fn (ContractParty $p) => $p->is_signed);
    }
}
