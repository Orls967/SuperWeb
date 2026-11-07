<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Nilai Objek Metrik Risiko Cincin Sybil & Kartel Calo Pasar Sekunder.
 * Menggabungkan analisis grafik terarah, sentralitas simpul, kolisi sidik jari perangkat,
 * serta korelasi temporal jendela transaksi berkecepatan mikro-detik.
 */
final readonly class SybilRingRiskMetrics
{
    public const string RISK_LOW = 'LOW';

    public const string RISK_MEDIUM = 'MEDIUM';

    public const string RISK_HIGH = 'HIGH';

    public const string RISK_CRITICAL = 'CRITICAL';

    /**
     * @param  int  $clusterId  Identitas unik klaster cincin terdeteksi
     * @param  int  $nodeCount  Jumlah entitas (rekening/pengguna) dalam cincin calo
     * @param  int  $edgeDensityBps  Kerapatan keterhubungan antar-simpul dalam basis poin (0 - 10000)
     * @param  int  $sharedDeviceCollisionCount  Jumlah benturan UUID/Fingerprint perangkat bersama
     * @param  int  $sharedIpSubnetCollisionCount  Jumlah entitas yang beroperasi di /24 subnet IP yang sama
     * @param  int  $sharedBankingDestinationCount  Jumlah tujuan rekening penarikan/rekening bank yang identik
     * @param  int  $timeCorrelationScoreBps  Skor sinkronisasi waktu transaksi berkecepatan tinggi (0 - 10000)
     * @param  int  $compositeRiskScoreBps  Skor risiko komposit tertimbang (0 - 10000 bps)
     * @param  string  $riskTier  Klasifikasi tingkat ancaman (LOW, MEDIUM, HIGH, CRITICAL)
     * @param  array<int, string>  $flaggedSignals  Daftar sinyal anomali spesifik yang terpicu
     */
    public function __construct(
        public int $clusterId,
        public int $nodeCount,
        public int $edgeDensityBps,
        public int $sharedDeviceCollisionCount,
        public int $sharedIpSubnetCollisionCount,
        public int $sharedBankingDestinationCount,
        public int $timeCorrelationScoreBps,
        public int $compositeRiskScoreBps,
        public string $riskTier,
        public array $flaggedSignals = []
    ) {
        if ($this->nodeCount < 0) {
            throw new InvalidArgumentException('Node count tidak boleh bernilai negatif.');
        }
        if ($this->compositeRiskScoreBps < 0 || $this->compositeRiskScoreBps > 10000) {
            throw new InvalidArgumentException('Composite risk score bps harus berada di rentang 0 hingga 10000.');
        }
    }

    /**
     * Kalkulasi metrik risiko komposit secara deterministik dari komponen observasi primer.
     * Bobot Tertimbang:
     * - Shared Banking Destination: 35% (indikator kuat pencucian atau kartel terorganisir)
     * - Shared Device Fingerprint: 25% (indikator multi-akun pada perangkat bot yang sama)
     * - Time Synchronization Velocity: 20% (eksekusi bot terkoordinasi)
     * - Edge Density: 10% (struktur jaringan internal tertutup)
     * - Shared IP Subnet: 10% (proksi/VPN bot farm)
     *
     * @param  array<int, string>  $signals
     */
    public static function compute(
        int $clusterId,
        int $nodeCount,
        int $edgeDensityBps,
        int $sharedDeviceCount,
        int $sharedIpSubnetCount,
        int $sharedBankCount,
        int $timeCorrelationBps,
        array $signals = []
    ): self {
        $bankScore = min(10000, $sharedBankCount * 2500);
        $deviceScore = min(10000, $sharedDeviceCount * 2000);
        $subnetScore = min(10000, $sharedIpSubnetCount * 1250);

        // Bobot: Bank (35%), Device (25%), Time Sync (20%), Subnet (10%), Density (10%)
        $compositeBps = (int) intdiv(
            ($bankScore * 35) +
            ($deviceScore * 25) +
            ($timeCorrelationBps * 20) +
            ($subnetScore * 10) +
            ($edgeDensityBps * 10),
            100
        );

        $compositeBps = max(0, min(10000, $compositeBps));

        $tier = match (true) {
            $compositeBps >= 7500 || $sharedBankCount >= 3 => self::RISK_CRITICAL,
            $compositeBps >= 5000 || $sharedDeviceCount >= 2 => self::RISK_HIGH,
            $compositeBps >= 2500 => self::RISK_MEDIUM,
            default => self::RISK_LOW,
        };

        $derivedSignals = $signals;
        if ($sharedBankCount >= 2) {
            $derivedSignals[] = 'SHARED_BANKING_DESTINATION_DETECTED';
        }
        if ($sharedDeviceCount >= 2) {
            $derivedSignals[] = 'DEVICE_FINGERPRINT_SPOOFING_CLUSTER';
        }
        if ($timeCorrelationBps >= 7000) {
            $derivedSignals[] = 'HIGH_VELOCITY_COORDINATED_SNIPING';
        }
        if ($nodeCount >= 5 && $edgeDensityBps >= 5000) {
            $derivedSignals[] = 'DENSE_SYBIL_WHEEL_TOPOLOGY';
        }

        return new self(
            clusterId: $clusterId,
            nodeCount: $nodeCount,
            edgeDensityBps: $edgeDensityBps,
            sharedDeviceCollisionCount: $sharedDeviceCount,
            sharedIpSubnetCollisionCount: $sharedIpSubnetCount,
            sharedBankingDestinationCount: $sharedBankCount,
            timeCorrelationScoreBps: $timeCorrelationBps,
            compositeRiskScoreBps: $compositeBps,
            riskTier: $tier,
            flaggedSignals: array_values(array_unique($derivedSignals))
        );
    }

    /**
     * Periksa apakah klaster ini wajib di-quarantine seketika oleh engine anti-scalping.
     */
    public function requiresImmediateQuarantine(): bool
    {
        return $this->riskTier === self::RISK_CRITICAL ||
            $this->compositeRiskScoreBps >= 7500 ||
            $this->sharedBankingDestinationCount >= 3;
    }

    /**
     * Hitung koefisien penalti harga sekunder bila klaster dicurigai namun belum diblokir permanen.
     * Mengembalikan rasio basis poin potongan harga (misal 500 bps = 5%).
     */
    public function calculateRiskPenaltyBps(): int
    {
        return match ($this->riskTier) {
            self::RISK_CRITICAL => 5000,
            self::RISK_HIGH => 2500,
            self::RISK_MEDIUM => 1000,
            default => 0,
        };
    }

    /**
     * Konversi representasi metrik menjadi format array terstruktur untuk audit trail.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cluster_id' => $this->clusterId,
            'node_count' => $this->nodeCount,
            'edge_density_bps' => $this->edgeDensityBps,
            'shared_device_collision_count' => $this->sharedDeviceCollisionCount,
            'shared_ip_subnet_collision_count' => $this->sharedIpSubnetCollisionCount,
            'shared_banking_destination_count' => $this->sharedBankingDestinationCount,
            'time_correlation_score_bps' => $this->timeCorrelationScoreBps,
            'composite_risk_score_bps' => $this->compositeRiskScoreBps,
            'risk_tier' => $this->riskTier,
            'flagged_signals' => $this->flaggedSignals,
            'quarantine_mandatory' => $this->requiresImmediateQuarantine(),
        ];
    }
}
