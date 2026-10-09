<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tenaga kerja produksi (tanpa payroll — Fase 35.8). */
class Worker extends Model
{
    protected $table = 'mfg_workers';

    protected $fillable = [
        'user_id', 'employee_code', 'name', 'status', 'skills', 'certifications',
    ];

    protected $casts = [
        'skills' => 'array',
        'certifications' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(WorkerShift::class, 'worker_id');
    }

    /** Sertifikasi yang masih berlaku pada tanggal tertentu. */
    public function validCertifications(?string $at = null): array
    {
        $date = $at ?? now()->toDateString();

        return array_values(array_filter(
            $this->certifications ?? [],
            static fn (array $cert): bool => ($cert['expires_at'] ?? null) === null
                || $cert['expires_at'] >= $date
        ));
    }
}
