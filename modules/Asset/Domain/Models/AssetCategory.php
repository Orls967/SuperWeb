<?php

declare(strict_types=1);

namespace Modules\Asset\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Kategori aset dengan umur ekonomis & metode penyusutan default (30.1, PSAK 16 simulasi).
 */
class AssetCategory extends Model
{
    protected $table = 'ast_categories';

    protected $fillable = ['code', 'name', 'useful_life_years', 'depreciation_method', 'is_active'];

    protected $casts = [
        'useful_life_years' => 'integer',
        'is_active' => 'boolean',
    ];

    public function assets()
    {
        return $this->hasMany(Asset::class, 'category_id');
    }
}
