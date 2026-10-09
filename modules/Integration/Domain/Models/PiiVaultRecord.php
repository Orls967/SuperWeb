<?php

declare(strict_types=1);

namespace Modules\Integration\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class PiiVaultRecord extends Model
{
    protected $table = 'sec_pii_vault_records';

    protected $fillable = [
        'subject_id',
        'pii_category',
        'line_code',
        'encrypted_value',
        'vault_token',
        'encryption_key_version',
        'is_active',
        'anonymized_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'anonymized_at' => 'datetime',
    ];

    public function isAnonymized(): bool
    {
        return ! $this->is_active && $this->anonymized_at !== null;
    }
}
