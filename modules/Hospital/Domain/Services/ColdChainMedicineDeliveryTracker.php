<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Carbon\Carbon;
use InvalidArgumentException;
use Modules\Hospital\Domain\Models\EPharmacyOrder;
use Modules\Hospital\Domain\Models\MedicineCourierDelivery;
use RuntimeException;

/**
 * Cold-Chain Medicine Delivery Tracker & e-POD Auditor.
 * Memantau:
 * 1. Suhu penyimpanan rantai dingin ketat (2.0°C – 8.0°C) selama perjalanan kurir logistik.
 * 2. Karantina instan paket jika terjadi pelanggaran batas suhu (thermal breach).
 * 3. Audit penyerahan obat keras berbasis electronic Proof of Delivery (e-POD).
 * 4. Verifikasi identitas penerima melalui NIK KTP dan hash tanda tangan digital.
 */
class ColdChainMedicineDeliveryTracker
{
    public const DEFAULT_MIN_TEMP_C = 2.0;

    public const DEFAULT_MAX_TEMP_C = 8.0;

    /**
     * Memulai ekspedisi kurir obat dengan inisialisasi telemetri suhu.
     */
    public function initiateDelivery(
        EPharmacyOrder $order,
        array $deliveryParams
    ): MedicineCourierDelivery {
        $requiresColdChain = (bool) ($deliveryParams['requires_cold_chain'] ?? false);
        $minTemp = (float) ($deliveryParams['min_temp_c'] ?? self::DEFAULT_MIN_TEMP_C);
        $maxTemp = (float) ($deliveryParams['max_temp_c'] ?? self::DEFAULT_MAX_TEMP_C);
        $initialTemp = isset($deliveryParams['initial_temp_c']) ? (float) $deliveryParams['initial_temp_c'] : 4.0;

        if ($requiresColdChain && ($initialTemp < $minTemp || $initialTemp > $maxTemp)) {
            throw new InvalidArgumentException(
                "Suhu awal paket ({$initialTemp}°C) berada di luar batas toleransi rantai dingin [{$minTemp}°C - {$maxTemp}°C]."
            );
        }

        $code = 'DEL-'.strtoupper(bin2hex(random_bytes(4)));

        return MedicineCourierDelivery::create([
            'delivery_code' => $code,
            'epharmacy_order_id' => $order->id,
            'courier_service' => $deliveryParams['courier_service'] ?? 'HOSPITAL_EXPRESS',
            'courier_name' => $deliveryParams['courier_name'] ?? 'Kurir Farmasi Internal',
            'courier_phone' => $deliveryParams['courier_phone'] ?? '081234567890',
            'recipient_name' => $deliveryParams['recipient_name'],
            'recipient_phone' => $deliveryParams['recipient_phone'],
            'destination_address' => $deliveryParams['destination_address'],
            'destination_latitude' => $deliveryParams['destination_latitude'] ?? null,
            'destination_longitude' => $deliveryParams['destination_longitude'] ?? null,
            'requires_cold_chain' => $requiresColdChain,
            'min_allowed_temp_c' => $minTemp,
            'max_allowed_temp_c' => $maxTemp,
            'last_recorded_temp_c' => $initialTemp,
            'temperature_breached' => false,
            'delivery_status' => 'IN_TRANSIT',
            'dispatched_at' => Carbon::now(),
        ]);
    }

    /**
     * Merekam telemetri suhu dari sensor boks pendingin kurir IoT.
     */
    public function recordTemperatureTelemetry(
        MedicineCourierDelivery $delivery,
        float $currentTempCelsius
    ): MedicineCourierDelivery {
        if ($delivery->delivery_status !== 'IN_TRANSIT') {
            return $delivery;
        }

        $delivery->last_recorded_temp_c = $currentTempCelsius;

        if ($delivery->requires_cold_chain) {
            $isBreached = ($currentTempCelsius < $delivery->min_allowed_temp_c) ||
                          ($currentTempCelsius > $delivery->max_allowed_temp_c);

            if ($isBreached) {
                $delivery->temperature_breached = true;
                $delivery->delivery_status = 'QUARANTINED_BREACH';

                // Update juga status order terkait
                $order = $delivery->order;
                if ($order) {
                    $order->delivery_status = 'QUARANTINED_BREACH';
                    $order->save();
                }
            }
        }

        $delivery->save();

        return $delivery;
    }

    /**
     * Menyelesaikan serah terima paket obat dengan tanda tangan elektronik penerima (e-POD).
     */
    public function completeProofOfDelivery(
        MedicineCourierDelivery $delivery,
        string $recipientNik,
        string $rawSignatureOrToken
    ): MedicineCourierDelivery {
        if ($delivery->delivery_status === 'QUARANTINED_BREACH') {
            throw new RuntimeException(
                'Paket obat telah dikarantina karena kegagalan rantai dingin (thermal breach) dan dilarang diserahkan ke pasien.'
            );
        }

        if (strlen(trim($recipientNik)) < 16) {
            throw new InvalidArgumentException('Nomor Induk Kependudukan (NIK) penerima wajib 16 digit.');
        }

        $podHash = hash('sha256', implode('|', [
            $delivery->delivery_code,
            $recipientNik,
            $rawSignatureOrToken,
            now()->toIso8601String(),
        ]));

        $delivery->pod_recipient_nik = $recipientNik;
        $delivery->pod_recipient_signature_hash = $podHash;
        $delivery->delivery_status = 'DELIVERED';
        $delivery->delivered_at = Carbon::now();
        $delivery->save();

        $order = $delivery->order;
        if ($order) {
            $order->pod_signature_hash = $podHash;
            $order->delivery_status = 'DELIVERED';
            $order->status = 'DELIVERED';
            $order->save();
        }

        return $delivery;
    }
}
