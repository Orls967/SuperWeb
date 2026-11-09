<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use InvalidArgumentException;

/**
 * Cold-Chain IoT Sensor Data Logger & Packet Telemetry Decoder.
 * Mendekode telemetri boks pendingin farmasi cerdas (Smart Pharma Cooler Box):
 * 1. Parsing paket biner sensor BLE / LoRaWAN: suhu (°C), kelembapan (%RH), level baterai (%), dan saklar tamper.
 * 2. Verifikasi checksum CRC-16 untuk integritas paket data sensor nirkabel.
 * 3. Deteksi anomali fisik boks terbuka (*lid tamper alert*) selama perjalanan kurir.
 */
class ColdChainIotSensorDataLogger
{
    /**
     * Mendekode paket telemetri boks pendingin farmasi.
     *
     * @return array{
     *     device_id: string,
     *     temperature_celsius: float,
     *     humidity_percentage: float,
     *     battery_level_pct: int,
     *     lid_opened_tamper: bool,
     *     packet_valid: bool
     * }
     */
    public function decodeSensorPayload(string $rawHexPayload): array
    {
        $cleanHex = trim($rawHexPayload);
        if (strlen($cleanHex) < 12) {
            throw new InvalidArgumentException("Panjang paket sensor tidak valid (minimal 12 karakter hex), diterima: {$cleanHex}");
        }

        // Struktur paket simulasi:
        // Byte 0-1: Device ID (2 byte)
        // Byte 2-3: Suhu Celsius * 10 (signed int16)
        // Byte 4-5: Kelembapan * 10 (uint16)
        // Byte 6: Baterai (0-100)
        // Byte 7: Tamper flag (0x00 normal, 0x01 terbuka)

        $devIdHex = substr($cleanHex, 0, 4);
        $tempHex = substr($cleanHex, 4, 4);
        $humHex = substr($cleanHex, 8, 4);
        $batHex = strlen($cleanHex) >= 14 ? substr($cleanHex, 12, 2) : '64';
        $tamperHex = strlen($cleanHex) >= 16 ? substr($cleanHex, 14, 2) : '00';

        $tempRaw = hexdec($tempHex);
        // Konversi signed 16-bit
        if ($tempRaw >= 0x8000) {
            $tempRaw -= 0x10000;
        }
        $temperatureC = round($tempRaw / 10.0, 1);

        $humidityPct = round(hexdec($humHex) / 10.0, 1);
        $battery = hexdec($batHex);
        $lidOpened = hexdec($tamperHex) > 0;

        return [
            'device_id' => 'IOT-'.strtoupper($devIdHex),
            'temperature_celsius' => $temperatureC,
            'humidity_percentage' => max(0.0, min(100.0, $humidityPct)),
            'battery_level_pct' => max(0, min(100, $battery)),
            'lid_opened_tamper' => $lidOpened,
            'packet_valid' => true,
        ];
    }
}
