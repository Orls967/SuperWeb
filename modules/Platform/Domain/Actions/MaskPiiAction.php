<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

/**
 * PII Masking Deterministik (W8-D10).
 * Mengamankan data sensitif pada sandbox dan pelaporan developer.
 */
class MaskPiiAction
{
    public function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return '***@masked.local';
        }

        $local = $parts[0];
        $domain = $parts[1];

        if (strlen($local) <= 2) {
            return '***@'.$domain;
        }

        $first = $local[0];
        $last = $local[strlen($local) - 1];

        return "{$first}***{$last}@{$domain}";
    }

    public function maskPhone(string $phone): string
    {
        return $this->maskPreserveBoundary(
            value: $phone,
            prefixLength: 5,
            suffixLength: 4,
            threshold: 6
        );
    }

    public function maskNationalId(string $nik): string
    {
        return $this->maskPreserveBoundary(
            value: $nik,
            prefixLength: 4,
            suffixLength: 4,
            threshold: 8
        );
    }

    public function maskAddress(string $address): string
    {
        if (str_starts_with($address, 'Jl.')) {
            return 'Jl. [MASKED]';
        }

        return '[MASKED ADDRESS]';
    }

    public function maskAccountNumber(string $acc): string
    {
        return $this->maskPreserveBoundary(
            value: $acc,
            prefixLength: 3,
            suffixLength: 3,
            threshold: 6
        );
    }

    private function maskPreserveBoundary(string $value, int $prefixLength, int $suffixLength, int $threshold): string
    {
        $len = strlen($value);
        if ($len <= $threshold) {
            return str_repeat('*', $len);
        }

        $prefix = substr($value, 0, $prefixLength);
        $suffix = substr($value, -$suffixLength);
        $maskedLength = max(1, $len - ($prefixLength + $suffixLength));

        return $prefix.str_repeat('*', $maskedLength).$suffix;
    }
}
