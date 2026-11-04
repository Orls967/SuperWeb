<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Domain\Models\Clearing;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/** Sertifikat CBAM per kontainer — immutable (PR5). */
class CbamDeclaration extends Model
{
    protected $table = 'tf_cbam_declarations';

    protected $fillable = [
        'certificate_number', 'container_number', 'product_code', 'origin_country', 'net_mass_kg',
        'specific_t_co2e_per_t', 'verification_ref', 'embedded_milli_tco2e', 'parameter_version', 'levy_idr',
        'simulation_label', 'document_checksum', 'certificate_hash', 'idempotency_key', 'declared_by_user_id', 'declared_at',
    ];

    protected $casts = [
        'net_mass_kg' => 'integer',
        'embedded_milli_tco2e' => 'integer',
        'levy_idr' => 'integer',
        'declared_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('Sertifikat CBAM immutable; koreksi lewat sertifikat pembalik baru.');
        });
        static::deleting(function () {
            throw new RuntimeException('Sertifikat CBAM tidak boleh dihapus.');
        });
    }

    /** tCO2e dengan 3 desimal (string). */
    public function embeddedTco2e(): string
    {
        return number_format($this->embedded_milli_tco2e / 1000, 3, '.', '');
    }
}
