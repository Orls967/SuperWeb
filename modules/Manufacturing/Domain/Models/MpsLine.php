<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Baris MPS: kuantitas barang jadi per periode (frozen bila dalam time fence). */
class MpsLine extends Model
{
    protected $table = 'mfg_mps_lines';

    protected $fillable = ['header_id', 'material_id', 'period_start', 'qty', 'frozen'];

    protected $casts = ['period_start' => 'date', 'qty' => 'decimal:6', 'frozen' => 'boolean'];

    public function header(): BelongsTo
    {
        return $this->belongsTo(MpsHeader::class, 'header_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
