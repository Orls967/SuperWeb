<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Algorithms;

use RuntimeException;

/**
 * B5: CodeBlueEscalationPlanner.
 * Deteksi anomali ganda dalam jendela 3 menit dan perencanaan eskalasi Code Blue.
 * Kompleksitas: O(K) di mana K adalah titik telemetri.
 */
final class CodeBlueEscalationPlanner
{
    /**
     * @param list<array{
     *     minute: int,
     *     spo2: int,
     *     heart_rate: int
     * }> $telemetryReadings
     * @param ?int $ackMinute Menit staf klinis meng-acknowledge (null jika tidak di-ack)
     * @return CodeBluePlanResult
     */
    public function plan(array $telemetryReadings, ?int $ackMinute = null): CodeBluePlanResult
    {
        // 1. Identifikasi menit terjadinya pelanggaran
        $breachMinutes = [];
        foreach ($telemetryReadings as $r) {
            $isBreach = ($r['spo2'] < 85 || $r['heart_rate'] < 40 || $r['heart_rate'] > 150);
            if ($isBreach) {
                $breachMinutes[] = $r['minute'];
            }
        }

        // 2. Deteksi apakah ada 2 pelanggaran dalam jendela 3 menit (jarak <= 3)
        $triggerMinute = null;
        for ($i = 1; $i < count($breachMinutes); $i++) {
            $diff = $breachMinutes[$i] - $breachMinutes[$i - 1];
            if ($diff <= 3) {
                $triggerMinute = $breachMinutes[$i];
                break;
            }
        }

        if ($triggerMinute === null) {
            $certificate = new CodeBlueEscalationCertificate(
                isTriggered: false,
                triggerMinute: null,
                escalations: [],
                generatedAt: time()
            );
            return new CodeBluePlanResult(false, null, [], $certificate);
        }

        // 3. Rencanakan eskalasi:
        // - PERAWAT saat naik (@triggerMinute)
        // - DOKTER +2 menit (@triggerMinute + 2) bila ack belum dilakukan (< triggerMinute + 2)
        // - TIM +5 menit (@triggerMinute + 5) bila ack belum dilakukan (< triggerMinute + 5)
        $escalations = [];

        // Notifikasi perawat saat naik
        $escalations[] = "PERAWAT@{$triggerMinute}";

        $doctorMinute = $triggerMinute + 2;
        $teamMinute = $triggerMinute + 5;

        if ($ackMinute === null || $ackMinute > $doctorMinute) {
            $escalations[] = "DOKTER@{$doctorMinute}";
        }

        if ($ackMinute === null || $ackMinute > $teamMinute) {
            $escalations[] = "TIM@{$teamMinute}";
        }

        $certificate = new CodeBlueEscalationCertificate(
            isTriggered: true,
            triggerMinute: $triggerMinute,
            escalations: $escalations,
            generatedAt: time()
        );

        CodeBlueCertificateVerifier::verify($certificate);

        return new CodeBluePlanResult(true, $triggerMinute, $escalations, $certificate);
    }
}

final class CodeBluePlanResult
{
    /**
     * @param list<string> $escalations
     */
    public function __construct(
        public readonly bool $isTriggered,
        public readonly ?int $triggerMinute,
        public readonly array $escalations,
        public readonly CodeBlueEscalationCertificate $certificate
    ) {}
}

final class CodeBlueEscalationCertificate
{
    public readonly string $sha256;

    /**
     * @param list<string> $escalations
     */
    public function __construct(
        public readonly bool $isTriggered,
        public readonly ?int $triggerMinute,
        public readonly array $escalations,
        public readonly int $generatedAt,
        ?string $customSha256 = null
    ) {
        $payload = json_encode([
            'triggered' => $this->isTriggered,
            'minute' => $this->triggerMinute,
            'escalations' => $this->escalations,
        ], JSON_THROW_ON_ERROR);

        $this->sha256 = $customSha256 ?? hash('sha256', $payload);
    }
}

final class CodeBlueCertificateVerifier
{
    public static function verify(CodeBlueEscalationCertificate $certificate): void
    {
        $payload = json_encode([
            'triggered' => $certificate->isTriggered,
            'minute' => $certificate->triggerMinute,
            'escalations' => $certificate->escalations,
        ], JSON_THROW_ON_ERROR);

        $expectedHash = hash('sha256', $payload);
        if (! hash_equals($expectedHash, $certificate->sha256)) {
            throw new RuntimeException("Sertifikat Code Blue tidak valid: hash tidak cocok.");
        }
    }
}
