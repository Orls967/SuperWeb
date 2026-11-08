<?php

declare(strict_types=1);

namespace Modules\Marine\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaboratoryAssay extends Model
{
    protected $table = 'mar_laboratory_assays';

    protected $fillable = [
        'assay_certificate_number',
        'export_lot_id',
        'accredited_laboratory_name',
        'tested_at',
        'chloramphenicol_ppb',
        'nitrofuran_aoz_ppb',
        'nitrofuran_amoz_ppb',
        'fluoroquinolones_ppb',
        'malachite_green_ppb',
        'histamine_ppm',
        'heavy_metal_cadmium_ppm',
        'heavy_metal_lead_ppm',
        'microbiology_salmonella_cfu_25g',
        'overall_compliance_verdict',
        'signed_analyst_name',
    ];

    public function exportLot(): BelongsTo
    {
        return $this->belongsTo(AquacultureExportLot::class, 'export_lot_id');
    }
}
