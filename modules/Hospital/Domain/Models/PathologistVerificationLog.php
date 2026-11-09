<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Model Jejak Audit Tanda Tangan Digital Dokter Spesialis Patologi Klinik (Fase 105).
 *
 * @property int $id
 * @property int $specimen_id
 * @property string $test_code
 * @property int $pathologist_id
 * @property string $digital_signature_hash
 * @property string|null $clinical_interpretation
 * @property Carbon $signed_at
 */
class PathologistVerificationLog extends Model
{
    use HasFactory;

    protected $table = 'hsp_pathologist_verification_logs';

    protected $fillable = [
        'specimen_id',
        'test_code',
        'pathologist_id',
        'digital_signature_hash',
        'clinical_interpretation',
        'signed_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public function specimen(): BelongsTo
    {
        return $this->belongsTo(LabSpecimen::class, 'specimen_id');
    }

    public function pathologist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pathologist_id');
    }
}
