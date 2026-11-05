<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EduCampusTuitionInvoice extends Model
{
    protected $table = 'edu_campus_tuition_invoices';

    protected $fillable = [
        'invoice_code',
        'student_user_id',
        'term_id',
        'gross_amount_idr',
        'scholarship_deduction_idr',
        'net_payable_idr',
        'status',
        'idempotency_key',
    ];

    protected $casts = [
        'gross_amount_idr' => 'integer',
        'scholarship_deduction_idr' => 'integer',
        'net_payable_idr' => 'integer',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(EduCampusTerm::class, 'term_id');
    }
}
