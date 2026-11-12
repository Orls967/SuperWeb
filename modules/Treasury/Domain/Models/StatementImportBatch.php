<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatementImportBatch extends Model
{
    use HasUuids;

    protected $table = 'trs_statement_import_batches';

    protected $fillable = [
        'bank_account_id',
        'batch_reference',
        'format',
        'payload_hash',
        'total_lines',
        'imported_lines',
        'skipped_duplicate_lines',
        'opening_balance_minor',
        'closing_balance_minor',
        'imported_by',
    ];

    protected $casts = [
        'total_lines' => 'integer',
        'imported_lines' => 'integer',
        'skipped_duplicate_lines' => 'integer',
        'opening_balance_minor' => 'integer',
        'closing_balance_minor' => 'integer',
        'imported_by' => 'integer',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
