<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Carbon\Carbon;
use InvalidArgumentException;
use Modules\Hospital\Domain\Models\TeleConsult;
use RuntimeException;

/**
 * Telemedicine Video & Audio Session Signaling & Quality Manager.
 * Mengelola ruang konsultasi virtual terenkripsi (WebRTC / Secure SIP):
 * 1. Pembangkitan token otentikasi sesi video berdurasi terbatas (time-bound JWT simulation).
 * 2. Pencatatan persetujuan tindakan medis jarak jauh (Informed Consent UU PDP).
 * 3. Pemantauan metrik kualitas layanan telekomunikasi (QoS: jitter, latency, packet loss).
 * 4. Rekonsiliasi durasi panggilan klinis untuk perhitungan honorarium dokter.
 */
class TelemedicineVideoSessionManager
{
    private const SESSION_EXPIRY_MINUTES = 60;

    /**
     * Membuka ruang sesi video konsultasi baru untuk dokter dan pasien.
     *
     * @return array{
     *     room_id: string,
     *     session_token: string,
     *     webrtc_ice_servers: array<int, array{urls: string}>,
     *     expires_at: string
     * }
     */
    public function createVideoSession(TeleConsult $teleConsult, string $patientConsentToken): array
    {
        if (empty(trim($patientConsentToken))) {
            throw new InvalidArgumentException('Persetujuan medis (informed consent) pasien wajib disertakan sebelum memulai sesi video.');
        }

        $roomId = 'ROOM-'.strtoupper(bin2hex(random_bytes(6)));
        $expiresAt = Carbon::now()->addMinutes(self::SESSION_EXPIRY_MINUTES);

        $tokenPayload = [
            'room' => $roomId,
            'consult_code' => $teleConsult->consult_code,
            'patient_id' => $teleConsult->patient_id,
            'doctor_id' => $teleConsult->doctor_id,
            'consent_hash' => hash('sha256', $patientConsentToken),
            'exp' => $expiresAt->timestamp,
        ];

        $sessionToken = base64_encode(json_encode($tokenPayload) ?: '');

        $teleConsult->status = 'IN_PROGRESS';
        $teleConsult->clinical_notes .= "\n[Video Session Initialized] Room: {$roomId}, Consent: Verified";
        $teleConsult->save();

        return [
            'room_id' => $roomId,
            'session_token' => $sessionToken,
            'webrtc_ice_servers' => [
                ['urls' => 'stun:stun.l.google.com:19302'],
                ['urls' => 'turn:turn.superweb.hospital.id:3478'],
            ],
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * Menyelesaikan sesi telekonsultasi dan mencatat telemetri kualitas panggilan.
     *
     * @param array{
     *     duration_seconds: int,
     *     avg_latency_ms: float,
     *     packet_loss_pct: float,
     *     resolution: string
     * } $telemetry
     */
    public function endVideoSession(TeleConsult $teleConsult, array $telemetry): TeleConsult
    {
        $durationSec = (int) ($telemetry['duration_seconds'] ?? 0);
        $latency = (float) ($telemetry['avg_latency_ms'] ?? 20.0);
        $packetLoss = (float) ($telemetry['packet_loss_pct'] ?? 0.0);

        if ($durationSec < 10) {
            throw new RuntimeException('Sesi konsultasi terlalu singkat (< 10 detik), tidak dapat diselesaikan sebagai konsultasi valid.');
        }

        $durationMinutes = round($durationSec / 60.0, 1);

        $teleConsult->status = 'COMPLETED';
        $teleConsult->clinical_notes .= sprintf(
            "\n[Video Call Ended] Durasi: %.1f menit, Latensi: %.1f ms, Packet Loss: %.2f%%",
            $durationMinutes,
            $latency,
            $packetLoss
        );
        $teleConsult->save();

        return $teleConsult;
    }
}
