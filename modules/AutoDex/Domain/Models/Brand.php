<?php

declare(strict_types=1);

namespace Modules\AutoDex\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\Shared\Domain\Traits\HasUuid;

class Brand extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'dex_brands';

    protected $fillable = [
        'name', 'slug', 'country', 'category', 'logo_url', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // Auto-generate slug saat create
    protected static function booted(): void
    {
        static::creating(function (Brand $brand) {
            if (empty($brand->slug)) {
                $brand->slug = Str::slug($brand->name);
            }
        });
    }

    public function cars(): HasMany
    {
        return $this->hasMany(Car::class, 'brand_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Label warna per kategori untuk UI badge */
    public function getCategoryBadgeAttribute(): string
    {
        return match ($this->category) {
            'jdm' => 'bg-red-500/10 text-red-400 border-red-500/20',
            'usdm' => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
            'euro' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
            'korean' => 'bg-sky-500/10 text-sky-400 border-sky-500/20',
            'chinese' => 'bg-orange-500/10 text-orange-400 border-orange-500/20',
            'ev' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
            default => 'bg-slate-500/10 text-slate-400 border-slate-500/20',
        };
    }
}
