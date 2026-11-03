<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Geo;

use InvalidArgumentException;

/**
 * Algoritma Geohash standar (Morton order / Z-order curve base32) dengan sertifikasi POD.
 */
final class Geohash
{
    private const string BASE32 = '0123456789bcdefghjkmnpqrstuvwxyz';

    /**
     * Encode koordinat lintang & bujur menjadi geohash base32 dengan presisi yang ditentukan.
     */
    public function encode(float $latitude, float $longitude, int $precision = 9): string
    {
        if ($precision < 1 || $precision > 12) {
            throw new InvalidArgumentException("Presisi geohash harus antara 1 dan 12, diberikan: {$precision}");
        }

        if ($latitude < -90.0 || $latitude > 90.0) {
            throw new InvalidArgumentException("Latitude di luar batas [-90, 90]: {$latitude}");
        }

        if ($longitude < -180.0 || $longitude > 180.0) {
            throw new InvalidArgumentException("Longitude di luar batas [-180, 180]: {$longitude}");
        }

        $latInterval = [-90.0, 90.0];
        $lonInterval = [-180.0, 180.0];
        $geohash = '';
        $bits = [16, 8, 4, 2, 1];
        $bit = 0;
        $ch = 0;
        $isEven = true;

        while (strlen($geohash) < $precision) {
            if ($isEven) {
                $mid = ($lonInterval[0] + $lonInterval[1]) / 2;
                if ($longitude > $mid) {
                    $ch |= $bits[$bit];
                    $lonInterval[0] = $mid;
                } else {
                    $lonInterval[1] = $mid;
                }
            } else {
                $mid = ($latInterval[0] + $latInterval[1]) / 2;
                if ($latitude > $mid) {
                    $ch |= $bits[$bit];
                    $latInterval[0] = $mid;
                } else {
                    $latInterval[1] = $mid;
                }
            }

            $isEven = ! $isEven;
            if ($bit < 4) {
                $bit++;
            } else {
                $geohash .= self::BASE32[$ch];
                $bit = 0;
                $ch = 0;
            }
        }

        return $geohash;
    }

    /**
     * Decode geohash ke interval koordinat kotak pembatas (bounding box).
     *
     * @return array{lat_min: float, lat_max: float, lon_min: float, lon_max: float, latitude: float, longitude: float}
     */
    public function decode(string $geohash): array
    {
        $geohash = strtolower(trim($geohash));
        if ($geohash === '') {
            throw new InvalidArgumentException('Geohash tidak boleh kosong');
        }

        $latInterval = [-90.0, 90.0];
        $lonInterval = [-180.0, 180.0];
        $isEven = true;
        $bits = [16, 8, 4, 2, 1];

        $len = strlen($geohash);
        for ($i = 0; $i < $len; $i++) {
            $char = $geohash[$i];
            $idx = strpos(self::BASE32, $char);
            if ($idx === false) {
                throw new InvalidArgumentException("Karakter geohash tidak valid: '{$char}'");
            }

            for ($bit = 0; $bit < 5; $bit++) {
                $mask = $bits[$bit];
                if ($isEven) {
                    $mid = ($lonInterval[0] + $lonInterval[1]) / 2;
                    if (($idx & $mask) !== 0) {
                        $lonInterval[0] = $mid;
                    } else {
                        $lonInterval[1] = $mid;
                    }
                } else {
                    $mid = ($latInterval[0] + $latInterval[1]) / 2;
                    if (($idx & $mask) !== 0) {
                        $latInterval[0] = $mid;
                    } else {
                        $latInterval[1] = $mid;
                    }
                }
                $isEven = ! $isEven;
            }
        }

        return [
            'lat_min' => $latInterval[0],
            'lat_max' => $latInterval[1],
            'lon_min' => $lonInterval[0],
            'lon_max' => $lonInterval[1],
            'latitude' => ($latInterval[0] + $latInterval[1]) / 2,
            'longitude' => ($lonInterval[0] + $lonInterval[1]) / 2,
        ];
    }

    /**
     * Verifikasi sertifikat Proof of Delivery (POD): memastikan geohash mencakup titik serah fisik.
     * Melempar InvalidArgumentException jika geohash tidak memuat titik serah.
     */
    public function verifyPod(string $geohash, float $latitude, float $longitude, int $expectedPrecision = 9): bool
    {
        if (strlen($geohash) !== $expectedPrecision) {
            throw new InvalidArgumentException("Panjang geohash tidak sesuai presisi: diharapkan {$expectedPrecision}, diberikan ".strlen($geohash));
        }

        $box = $this->decode($geohash);

        // Toleransi floating point tipis pada batas kotak
        $epsilon = 1e-7;
        if (
            $latitude < ($box['lat_min'] - $epsilon) || $latitude > ($box['lat_max'] + $epsilon) ||
            $longitude < ($box['lon_min'] - $epsilon) || $longitude > ($box['lon_max'] + $epsilon)
        ) {
            throw new InvalidArgumentException(
                "Sertifikat POD geohash '{$geohash}' rusak: titik serah ({$latitude}, {$longitude}) berada di luar bounding box [lat: {$box['lat_min']}..{$box['lat_max']}, lon: {$box['lon_min']}..{$box['lon_max']}]"
            );
        }

        return true;
    }
}
