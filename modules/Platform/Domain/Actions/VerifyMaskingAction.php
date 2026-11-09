<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

/**
 * Memverifikasi apakah suatu record atau dataset mengandung PII terbuka (unmasked).
 */
class VerifyMaskingAction
{
    /**
     * @param  array<string, mixed>  $record
     * @return list<string> Daftar field yang bocor (unmasked)
     */
    public function detectLeaks(array $record): array
    {
        $leaks = [];

        foreach ($record as $key => $val) {
            if (! is_string($val)) {
                continue;
            }

            // Cek bocoran email asli (mengandung @ tanpa tanda masking *)
            if (str_contains($key, 'email') && filter_var($val, FILTER_VALIDATE_EMAIL) && ! str_contains($val, '*')) {
                $leaks[] = $key;
            }

            // Cek bocoran NIK asli 16 digit murni angka
            if ((str_contains($key, 'nik') || str_contains($key, 'ktp')) && preg_match('/^\d{16}$/', $val)) {
                $leaks[] = $key;
            }

            // Cek nomor telepon terbuka > 10 digit angka tanpa *
            if ((str_contains($key, 'phone') || str_contains($key, 'telp')) && preg_match('/^\+?\d{10,15}$/', $val) && ! str_contains($val, '*')) {
                $leaks[] = $key;
            }
        }

        return $leaks;
    }
}
