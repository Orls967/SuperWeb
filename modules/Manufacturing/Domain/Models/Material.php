<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    use HasUuids;

    protected $table = 'mfg_materials';

    protected $fillable = ['code', 'name', 'kind', 'base_uom', 'lot_tracked', 'expiry_tracked', 'serial_tracked', 'is_active', 'description'];

    protected $casts = ['lot_tracked' => 'boolean', 'expiry_tracked' => 'boolean', 'serial_tracked' => 'boolean', 'is_active' => 'boolean'];

    public function uomConversions(): HasMany
    {
        return $this->hasMany(UomConversion::class, 'material_id');
    }

    public function bomsAsOutput(): HasMany
    {
        return $this->hasMany(Bom::class, 'output_material_id');
    }

    public function routings(): HasMany
    {
        return $this->hasMany(Routing::class, 'output_material_id');
    }

    public function formulas(): HasMany
    {
        return $this->hasMany(Formula::class, 'output_material_id');
    }
}
