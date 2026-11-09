<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReleaseTrain extends Model
{
    use HasFactory;

    protected $table = 'plt_release_trains';

    protected $fillable = [
        'train_code',
        'version',
        'release_manager_id',
        'status',
        'freeze_at',
        'scheduled_deploy_at',
        'deployed_at',
        'release_notes_markdown',
    ];

    protected $casts = [
        'freeze_at' => 'datetime',
        'scheduled_deploy_at' => 'datetime',
        'deployed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ReleaseTrain $train): void {
            if (! isset($train->attributes['departure_date'])) {
                $train->attributes['departure_date'] = now()->toDateString();
            }
        });
    }

    public function releaseManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'release_manager_id');
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(ChangeRequest::class, 'release_train_id');
    }

    public function hotfixes(): HasMany
    {
        return $this->hasMany(ReleaseHotfix::class, 'release_train_id');
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(ChangeDeployment::class, 'release_train_id');
    }
}
