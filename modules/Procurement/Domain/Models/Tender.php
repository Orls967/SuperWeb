<?php

declare(strict_types=1);

namespace Modules\Procurement\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tender extends Model
{
    use HasUuids;

    protected $table = 'prc_tenders';

    protected $fillable = [
        'number', 'title', 'type', 'status', 'bids_open_at', 'bids_close_at',
        'addendums', 'criteria', 'created_by_user_id',
    ];

    protected $casts = [
        'bids_open_at' => 'datetime', 'bids_close_at' => 'datetime',
        'addendums' => 'array', 'criteria' => 'array', 'created_by_user_id' => 'integer',
    ];

    public function bids(): HasMany
    {
        return $this->hasMany(TenderBid::class, 'tender_id');
    }

    /** Tambah addendum (revisi dokumen tender sebelum tenggat). */
    public function addAddendum(string $description): void
    {
        $addendums = $this->addendums ?? [];
        $addendums[] = ['description' => $description, 'at' => now()->toIso8601String()];
        $this->update(['addendums' => $addendums]);
    }
}
