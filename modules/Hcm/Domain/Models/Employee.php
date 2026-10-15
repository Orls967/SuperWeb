<?php

declare(strict_types=1);

namespace Modules\Hcm\Domain\Models;

use App\Models\User;
use App\Security\BlindIndex;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasUuids;

    protected $table = 'hcm_employees';

    protected $fillable = [
        'user_id',
        'employee_number',
        'name',
        'nik',
        'nik_bidx',
        'nik_hash',
        'email',
        'department_id',
        'position',
        'employment_type',
        'basic_salary_idr',
        'allowances_idr',
        'bank_name',
        'bank_account_number',
        'is_active',
    ];

    protected $casts = [
        'nik' => 'encrypted',
        'basic_salary_idr' => 'integer',
        'allowances_idr' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Find employee by NIK via deterministic blind index.
     */
    public static function findByNik(string $nik): ?self
    {
        $bidx = BlindIndex::forNik($nik);

        return self::where('nik_bidx', $bidx)->first();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class, 'employee_id');
    }
}
