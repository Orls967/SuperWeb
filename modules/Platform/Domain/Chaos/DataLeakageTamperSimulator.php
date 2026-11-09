<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Chaos;

/**
 * Simulator sabotase injeksi bocoran plaintext PII untuk menguji verifikasi ketat mask-verify.
 */
class DataLeakageTamperSimulator
{
    /**
     * @return array<string, mixed>
     */
    public function generateLeakedPayload(): array
    {
        return [
            'id' => 999,
            'name' => 'Alice Leaked User',
            'email' => 'alice.unmasked@enterprise-target.com', // Bocor: email murni tanpa masking
            'phone' => '+6281987654321', // Bocor: nomor telepon murni
            'nik' => '3171012345678901', // Bocor: NIK 16 digit murni
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }
}

