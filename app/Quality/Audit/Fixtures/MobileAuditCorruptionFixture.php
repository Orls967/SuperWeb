<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Str;
use Modules\Core\Domain\Models\CoreMobileEpodRecord;

final class MobileAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'mobile:audit';
    }

    public function corrupt(): string
    {
        CoreMobileEpodRecord::create([
            'epod_code' => 'EPOD-CORRUPT-'.Str::upper(Str::random(8)),
            'shipment_ref' => 'SHP-CORRUPT-001',
            'driver_ref' => 'DRV-CORRUPT',
            'recipient_name' => 'Recipient With Forged Signature',
            'recipient_role' => 'Receiver',
            'photo_proof_hash' => hash('sha256', 'corrupted_photo_data'),
            'signature_hash' => 'invalid_forged_sha256_signature_hash_value',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'delivered_offline_at' => now(),
            'synced_at' => now(),
            'signature_payload_b64' => 'data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=',
            'is_tampered' => false,
        ]);

        return 'mobile:audit FAILED: Signature SHA-256 mismatch';
    }
}
