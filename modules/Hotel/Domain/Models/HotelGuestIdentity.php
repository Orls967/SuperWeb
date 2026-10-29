<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use App\Models\User;
use App\Security\BlindIndex;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelGuestIdentity extends Model
{
    use HasFactory;

    protected $table = 'htl_guest_identities';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'user_id',
        'reservation_id',
        'identity_type',
        'identity_number_encrypted',
        'identity_number_bidx',
        'full_name',
        'phone',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'identity_number_encrypted' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(HotelReservation::class, 'reservation_id');
    }

    /**
     * Menampilkan nomor identitas utuh atau termasking berdasarkan role pengguna (H-11).
     */
    public function getFormattedIdentityNumber(?User $viewer = null): string
    {
        $rawNumber = (string) $this->identity_number_encrypted;

        if ($viewer && in_array($viewer->role, ['admin', 'hotel_manager', 'front_office', 'front_desk', 'hotel_admin'])) {
            return $rawNumber;
        }

        $length = strlen($rawNumber);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        $prefix = substr($rawNumber, 0, 4);
        $suffix = substr($rawNumber, -4);
        $maskedMiddle = str_repeat('*', max(4, $length - 8));

        return $prefix.$maskedMiddle.$suffix;
    }

    public static function findByIdentityNumber(string $rawNumber): ?self
    {
        $bidx = BlindIndex::forNik($rawNumber);

        return self::where('identity_number_bidx', $bidx)->first();
    }
}
