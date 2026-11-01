<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;

class MiningStockpile extends Model
{
    protected $table = 'min_stockpiles';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'site_id',
        'stockpile_code',
        'name',
        'total_kg',
        'sum_kg_calories',
        'sum_kg_ash_bps',
        'sum_kg_sulfur_bps',
        'sum_kg_moisture_bps',
        'sum_kg_metal_bps',
    ];

    protected $casts = [
        'total_kg' => 'integer',
        'sum_kg_calories' => 'integer',
        'sum_kg_ash_bps' => 'integer',
        'sum_kg_sulfur_bps' => 'integer',
        'sum_kg_moisture_bps' => 'integer',
        'sum_kg_metal_bps' => 'integer',
    ];

    /**
     * Add material into stockpile, accumulating sum of kg and sum of (kg x specs) (PR4).
     *
     * @param  array<string, int>  $specs
     */
    public function addMaterial(int $kg, array $specs = []): void
    {
        $this->total_kg += $kg;
        if (isset($specs['metal_bps'])) {
            $this->sum_kg_metal_bps += ($kg * $specs['metal_bps']);
        }
        if (isset($specs['calories'])) {
            $this->sum_kg_calories += ($kg * $specs['calories']);
        }
        if (isset($specs['ash_bps'])) {
            $this->sum_kg_ash_bps += ($kg * $specs['ash_bps']);
        }
        if (isset($specs['sulfur_bps'])) {
            $this->sum_kg_sulfur_bps += ($kg * $specs['sulfur_bps']);
        }
        if (isset($specs['moisture_bps'])) {
            $this->sum_kg_moisture_bps += ($kg * $specs['moisture_bps']);
        }
        $this->save();
    }

    /**
     * Deduct material from stockpile.
     */
    public function deductMaterial(int $kg, array $specs = []): void
    {
        if ($kg > $this->total_kg) {
            throw new \DomainException("Deduction of {$kg}kg exceeds stockpile balance of {$this->total_kg}kg.");
        }

        $metalBps = $specs['metal_bps'] ?? (int) $this->getAverageMetalBpsAttribute();
        $this->total_kg -= $kg;
        $this->sum_kg_metal_bps = max(0, $this->sum_kg_metal_bps - ($kg * $metalBps));
        $this->save();
    }

    /**
     * Dynamic calculation of average grade on read via BigRational (PR4).
     */
    public function getAverageMetalBpsAttribute(): string
    {
        if ($this->total_kg <= 0 || $this->sum_kg_metal_bps <= 0) {
            return '0';
        }

        $rational = BigRational::of("{$this->sum_kg_metal_bps}/{$this->total_kg}");

        return (string) $rational->toScale(0, RoundingMode::Down)->toInt();
    }
}
