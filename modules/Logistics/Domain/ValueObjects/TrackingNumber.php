<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\ValueObjects;

use InvalidArgumentException;

class TrackingNumber
{
    public const PREFIX = 'SRX';

    /**
     * Compute Luhn check digit for a string of numerical digits.
     */
    public static function computeLuhnCheckDigit(string $digits): int
    {
        if (! ctype_digit($digits)) {
            throw new InvalidArgumentException("Digits must contain only numbers, got: {$digits}");
        }

        $sum = 0;
        $len = strlen($digits);

        // Process from right to left (1-indexed from right: odd positions get doubled)
        for ($i = 0; $i < $len; $i++) {
            $digit = (int) $digits[$len - 1 - $i];
            if ($i % 2 === 0) {
                // First digit from right of payload is odd position from right in full number (since check digit will be pos 0)
                $doubled = $digit * 2;
                $sum += ($doubled > 9) ? ($doubled - 9) : $doubled;
            } else {
                $sum += $digit;
            }
        }

        return (10 - ($sum % 10)) % 10;
    }

    /**
     * Validate whether a tracking number is in valid format and has a correct Luhn check digit.
     */
    public static function validate(string $trackingNumber): bool
    {
        $trackingNumber = strtoupper(trim($trackingNumber));

        if (! str_starts_with($trackingNumber, self::PREFIX)) {
            return false;
        }

        $digits = substr($trackingNumber, strlen(self::PREFIX));

        // Must have exactly 11 digits: 10 payload digits + 1 check digit
        if (strlen($digits) !== 11 || ! ctype_digit($digits)) {
            return false;
        }

        $payload = substr($digits, 0, 10);
        $checkDigit = (int) substr($digits, 10, 1);

        return self::computeLuhnCheckDigit($payload) === $checkDigit;
    }

    /**
     * Generate a new tracking number with 10 digits + Luhn check digit.
     */
    public static function generate(?string $payload = null): string
    {
        if ($payload === null) {
            // Generate 10 numeric digits (e.g. timestamp/microsecond/random)
            $payload = str_pad((string) random_int(1000000000, 9999999999), 10, '0', STR_PAD_LEFT);
        } else {
            $payload = str_pad(substr($payload, 0, 10), 10, '0', STR_PAD_LEFT);
        }

        $checkDigit = self::computeLuhnCheckDigit($payload);

        return self::PREFIX.$payload.$checkDigit;
    }

    /**
     * Normalize tracking number string by trimming and uppercasing.
     */
    public static function normalize(string $trackingNumber): string
    {
        return strtoupper(trim($trackingNumber));
    }
}
