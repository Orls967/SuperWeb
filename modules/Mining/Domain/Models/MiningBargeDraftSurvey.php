<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiningBargeDraftSurvey extends Model
{
    use HasUuids;

    protected $table = 'min_barge_draft_surveys';

    protected $fillable = [
        'id',
        'barge_shipment_id',
        'barge_code',
        'survey_code',
        'survey_type',
        'draft_fp_m',
        'draft_fs_m',
        'draft_mp_m',
        'draft_ms_m',
        'draft_ap_m',
        'draft_as_m',
        'draft_forward_port',
        'draft_forward_starboard',
        'draft_midship_port',
        'draft_midship_starboard',
        'draft_aft_port',
        'draft_aft_starboard',
        'quarter_mean_draft_m',
        'trim_meters',
        'water_density_t_m3',
        'water_density_kg_l',
        'displacement_initial_ton',
        'displacement_final_ton',
        'displacement_tonnage_mt',
        'calculated_cargo_ton',
        'cargo_tonnage_wmt',
        'weighbridge_cargo_ton',
        'variance_percent',
        'reconciliation_variance_pct',
        'surveyor_company',
        'surveyor_name',
        'status',
        'hmac_signature',
        'surveyed_at',
        'metadata',
    ];

    protected $casts = [
        'draft_fp_m' => 'float',
        'draft_fs_m' => 'float',
        'draft_mp_m' => 'float',
        'draft_ms_m' => 'float',
        'draft_ap_m' => 'float',
        'draft_as_m' => 'float',
        'draft_forward_port' => 'float',
        'draft_forward_starboard' => 'float',
        'draft_midship_port' => 'float',
        'draft_midship_starboard' => 'float',
        'draft_aft_port' => 'float',
        'draft_aft_starboard' => 'float',
        'quarter_mean_draft_m' => 'float',
        'trim_meters' => 'float',
        'water_density_t_m3' => 'float',
        'water_density_kg_l' => 'float',
        'displacement_initial_ton' => 'float',
        'displacement_final_ton' => 'float',
        'displacement_tonnage_mt' => 'float',
        'calculated_cargo_ton' => 'float',
        'cargo_tonnage_wmt' => 'float',
        'weighbridge_cargo_ton' => 'float',
        'variance_percent' => 'float',
        'reconciliation_variance_pct' => 'float',
        'surveyed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(MiningOfftakeShipment::class, 'barge_shipment_id');
    }
}
