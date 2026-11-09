<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Model Notifikasi Nilai Kritis & Karantina Hold Patolog (Fase 105).
 *
 * @property int $id
 * @property string $notification_uid
 * @property int $specimen_id
 * @property int $patient_id
 * @property string $test_code
 * @property string $analyte_name
 * @property float $measured_value
 * @property float $panic_threshold
 * @property string $panic_type
 * @property string $notification_protocol
 * @property int|null $doctor_id
 * @property string|null $doctor_phone
 * @property Carbon|null $notified_at
 * @property Carbon|null $acknowledged_at
 * @property bool $read_back_confirmed
 * @property string|null $read_back_recipient_name
 * @property Carbon|null $pathologist_verified_at
 * @property string|null $pathologist_signature_hash
 * @property string $status
 */
class CriticalRangeNotification extends Model
{
    use HasFactory;

    protected $table = 'hsp_critical_notifications';

    protected $fillable = [
        'notification_uid',
        'specimen_id',
        'patient_id',
        'test_code',
        'analyte_name',
        'measured_value',
        'panic_threshold',
        'panic_type',
        'notification_protocol',
        'doctor_id',
        'doctor_phone',
        'notified_at',
        'acknowledged_at',
        'read_back_confirmed',
        'read_back_recipient_name',
        'pathologist_verified_at',
        'pathologist_signature_hash',
        'status',
    ];

    protected $casts = [
        'doctor_phone' => 'encrypted',
        'measured_value' => 'float',
        'panic_threshold' => 'float',
        'read_back_confirmed' => 'boolean',
        'notified_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'pathologist_verified_at' => 'datetime',
    ];

    public function specimen(): BelongsTo
    {
        return $this->belongsTo(LabSpecimen::class, 'specimen_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }
}
