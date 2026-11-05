<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Insurance\Domain\Concerns\AppendOnly;
use Modules\Party\Contracts\Party;

class BlacklistedEntity extends Model
{
    use AppendOnly;

    protected $table = 'ins_blacklisted_entities';

    /** @var list<string> */
    protected $fillable = [
        'party_id',
        'identity_number',
        'phone_number_hash',
        'reason',
        'source_case_number',
        'blacklisted_at',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'blacklisted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
