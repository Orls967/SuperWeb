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
        // 29.1 Keuangan
        'advance_amount_idr', 'advance_paid_idr', 'retention_percent',
        // 29.3 Eskalasi
        'escalation_enabled', 'escalation_formula', 'escalation_index_code',
        'escalation_index_base', 'escalation_cap_percent',
        // 29.7 Kepatuhan & risiko
        'arbitration_rules', 'risk_score', 'risk_flags',
        // 29.5 Rekonsiliasi (cache agregat)
        'used_value_idr',
        // 29.6 Integrasi non-breaking
        'linked_rate_card_id', 'linked_lease_id', 'linked_royalty_ref',
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
        'advance_amount_idr' => 'integer',
        'advance_paid_idr' => 'integer',
        'retention_percent' => 'integer',
        'escalation_enabled' => 'boolean',
        'escalation_index_base' => 'float',
        'escalation_cap_percent' => 'float',
        'risk_score' => 'integer',
        'risk_flags' => 'array',
        'used_value_idr' => 'integer',
        'linked_rate_card_id' => 'integer',
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

    public function paymentSchedules(): HasMany
    {
        return $this->hasMany(PaymentSchedule::class, 'contract_id')->orderBy('due_date');
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(Amendment::class, 'contract_id')->orderByDesc('effective_date');
    }

    public function penaltyRules(): HasMany
    {
        return $this->hasMany(PenaltyRule::class, 'contract_id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(UsageLedger::class, 'contract_id')->orderByDesc('occurred_at');
    }

    /**
     * Nilai plafon yang tersisa (total nilai kontrak - nilai terpakai).
     */
    public function remainingValue(): int
    {
        return max(0, (int) $this->total_value_idr - (int) $this->used_value_idr);
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
