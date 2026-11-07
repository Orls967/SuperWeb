<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class HtlTimeshareSchedule extends Model
{
    use HasUuids;

    protected $table = 'htl_timeshare_schedules';

    protected $fillable = [
        'id',
        'timeshare_unit_id',
        'owner_reference',
        'week_number',
        'season',
        'status',
        'rental_income_gross_idr',
        'dividend_net_idr',
    ];

    protected $casts = [
        'week_number' => 'integer',
        'rental_income_gross_idr' => 'integer',
        'dividend_net_idr' => 'integer',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(TimeshareUnit::class, 'timeshare_unit_id');
    }

    public function assignToRentalPool(): void
    {
        if ($this->status === 'OCCUPIED_BY_OWNER') {
            throw new InvalidArgumentException('Schedule already occupied by owner cannot be assigned to rental pool.');
        }

        $this->status = 'RENTAL_POOL';
        $this->save();
    }

    public function distributeYield(int $grossIncomeIdr, int $maintenanceFeeBps = 1500): int
    {
        if ($this->status !== 'RENTAL_POOL') {
            throw new InvalidArgumentException('Yield distribution only applies to units in RENTAL_POOL.');
        }

        $fee = (int) round(($grossIncomeIdr * $maintenanceFeeBps) / 10000);
        $netDividend = max(0, $grossIncomeIdr - $fee);

        $this->rental_income_gross_idr += $grossIncomeIdr;
        $this->dividend_net_idr += $netDividend;
        $this->save();

        return $netDividend;
    }
}
