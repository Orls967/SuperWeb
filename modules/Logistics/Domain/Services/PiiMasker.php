<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Services;

class PiiMasker
{
    /**
     * Mask full name keeping the initial of each word followed by asterisks.
     * E.g.: "Budi Santoso" -> "B*** S***"
     */
    public function maskName(?string $name): string
    {
        if ($name === null || trim($name) === '') {
            return '***';
        }

        $parts = preg_split('/\s+/', trim($name));
        $masked = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $firstChar = mb_substr($part, 0, 1);
            $masked[] = $firstChar.'***';
        }

        return implode(' ', $masked);
    }

    /**
     * Mask telephone number keeping the first 4 and last 4 characters.
     * E.g.: "081234567890" -> "0812****7890"
     */
    public function maskPhone(?string $phone): string
    {
        if ($phone === null || trim($phone) === '') {
            return '****';
        }

        $clean = trim($phone);
        $len = strlen($clean);

        if ($len <= 6) {
            return substr($clean, 0, 2).'****';
        }

        $prefix = substr($clean, 0, 4);
        $suffix = substr($clean, -4);

        return $prefix.'****'.$suffix;
    }

    /**
     * Mask address details to protect consignee privacy.
     * Shows public routing city, but obscures street name and partial postal code.
     *
     * @param  array<string, mixed>|string|null  $address
     * @return array{street: string, city: string, postal_code: string}
     */
    public function maskAddress(array|string|null $address): array
    {
        if (is_string($address)) {
            $len = mb_strlen($address);
            $prefix = mb_substr($address, 0, min(6, $len));

            return [
                'street' => $prefix.'*** (Disamarkan untuk privasi)',
                'city' => '',
                'postal_code' => '*****',
            ];
        }

        if (! is_array($address)) {
            return [
                'street' => '*** (Disamarkan untuk privasi)',
                'city' => '***',
                'postal_code' => '*****',
            ];
        }

        $street = (string) ($address['street'] ?? '');
        $city = (string) ($address['city'] ?? '');
        $postalCode = (string) ($address['postal_code'] ?? '');

        $maskedStreet = mb_strlen($street) > 5
            ? mb_substr($street, 0, 6).'*** (Disamarkan untuk privasi)'
            : 'Alamat disamarkan untuk privasi';

        $maskedPostalCode = mb_strlen($postalCode) >= 3
            ? substr($postalCode, 0, 2).'***'
            : '*****';

        return [
            'street' => $maskedStreet,
            'city' => $city,
            'postal_code' => $maskedPostalCode,
        ];
    }
}
