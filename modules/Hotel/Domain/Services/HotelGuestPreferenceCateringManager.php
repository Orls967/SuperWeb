<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\HotelGuestIdentity;

class HotelGuestPreferenceCateringManager
{
    /**
     * @var list<string>
     */
    private const CRITICAL_ALLERGENS = [
        'PEANUTS',
        'SHELLFISH',
        'GLUTEN',
        'DAIRY_LACTOSE',
        'TREE_NUTS',
    ];

    /**
     * Memindai catatan profil preferensi tamu untuk menghasilkan peringatan keamanan pangan dapur.
     *
     * @return array{guest_id: string, full_name: string, detected_allergens: list<string>, is_high_risk_allergy: bool, kitchen_alert_memo: string}
     */
    public function scanGuestDietaryRisk(string $guestIdentityId): array
    {
        $guest = HotelGuestIdentity::findOrFail($guestIdentityId);
        $metadata = $guest->metadata ?? [];
        $allergies = (array) ($metadata['dietary_allergies'] ?? []);

        $detected = [];
        $isHighRisk = false;

        foreach ($allergies as $alg) {
            $normalized = strtoupper(str_replace(' ', '_', (string) $alg));
            if (in_array($normalized, self::CRITICAL_ALLERGENS, true)) {
                $detected[] = $normalized;
                $isHighRisk = true;
            }
        }

        $memo = $isHighRisk
            ? 'PERINGATAN KHUSUS DAPUR: Tamu memiliki alergi parah terhadap '.implode(', ', $detected).'. Sterilisasi talenan dan wajan sebelum memasak.'
            : 'Standar penyajian aman: Tidak ada riwayat alergi kritis terdaftar.';

        return [
            'guest_id' => $guest->id,
            'full_name' => $guest->full_name,
            'detected_allergens' => $detected,
            'is_high_risk_allergy' => $isHighRisk,
            'kitchen_alert_memo' => $memo,
        ];
    }
}
