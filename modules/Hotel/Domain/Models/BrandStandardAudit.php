<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandStandardAudit extends Model
{
    protected $table = 'htl_brand_standard_audits';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'audit_code',
        'property_id',
        'audit_date',
        'total_checklist_items',
        'passed_items_count',
        'compliance_score_percent',
        'simulated_star_grade',
        'listing_status',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(HotelProperty::class, 'property_id');
    }
}
