<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Algorithms;

use RuntimeException;

/**
 * B1: BedAssignmentOptimizer.
 * Alokasi ranjang rawat inap berjenjang dan deterministik.
 * Kompleksitas: O(N log N + M log M).
 */
final class BedAssignmentOptimizer
{
    /**
     * @param list<array{
     *     id: string|int,
     *     required_class: string,
     *     priority: int,
     *     waiting_minutes: int
     * }> $patients
     * @param list<array{
     *     bed_code: string|int,
     *     bed_class: string
     * }> $beds
     */
    public function optimize(array $patients, array $beds): BedAssignmentResult
    {
        // 1. Urutkan pasien: prioritas menaik (0=EMERGENCY, 1=URGENT, 2=ELECTIVE), lalu waktu tunggu menurun, lalu id menaik
        $sortedPatients = $patients;
        usort($sortedPatients, function (array $a, array $b): int {
            if ($a['priority'] !== $b['priority']) {
                return $a['priority'] <=> $b['priority'];
            }
            if ($a['waiting_minutes'] !== $b['waiting_minutes']) {
                return $b['waiting_minutes'] <=> $a['waiting_minutes']; // Menurun
            }

            return strcmp((string) $a['id'], (string) $b['id']);
        });

        // 2. Siapkan ranjang bebas terurut berdasarkan nomor bed terkecil
        $availableBeds = $beds;
        usort($availableBeds, function (array $a, array $b): int {
            return strcmp((string) $a['bed_code'], (string) $b['bed_code']);
        });

        $assignments = [];
        $unassigned = [];
        $bedUsage = []; // bed_code => bool

        foreach ($sortedPatients as $p) {
            $pId = (string) $p['id'];
            $req = strtoupper((string) $p['required_class']);
            $assignedBed = null;
            $isUpgrade = false;

            // Aturan non-substitusi untuk ICU dan ISOLATION
            if ($req === 'ICU' || $req === 'ISOLATION') {
                foreach ($availableBeds as $idx => $b) {
                    $bCode = (string) $b['bed_code'];
                    if (! isset($bedUsage[$bCode]) && strtoupper((string) $b['bed_class']) === $req) {
                        $assignedBed = $b;
                        $bedUsage[$bCode] = true;
                        break;
                    }
                }
            } else {
                // Kelas reguler: K3, K2, K1
                // Cari kelas persis terlebih dahulu
                foreach ($availableBeds as $idx => $b) {
                    $bCode = (string) $b['bed_code'];
                    if (! isset($bedUsage[$bCode]) && strtoupper((string) $b['bed_class']) === $req) {
                        $assignedBed = $b;
                        $bedUsage[$bCode] = true;
                        break;
                    }
                }

                // Jika tidak ada kelas persis, cari upgrade 1 tingkat ke atas (K3 -> K2, K2 -> K1, K3 -> K1)
                if ($assignedBed === null) {
                    $allowedUpgrades = match ($req) {
                        'K3', 'CLASS_3' => ['K2', 'CLASS_2', 'K1', 'CLASS_1'],
                        'K2', 'CLASS_2' => ['K1', 'CLASS_1'],
                        default => [],
                    };

                    foreach ($allowedUpgrades as $targetClass) {
                        foreach ($availableBeds as $idx => $b) {
                            $bCode = (string) $b['bed_code'];
                            if (! isset($bedUsage[$bCode]) && strtoupper((string) $b['bed_class']) === $targetClass) {
                                $assignedBed = $b;
                                $bedUsage[$bCode] = true;
                                $isUpgrade = true;
                                break 2;
                            }
                        }
                    }
                }
            }

            if ($assignedBed !== null) {
                $assignments[] = [
                    'patient_id' => $pId,
                    'bed_code' => (string) $assignedBed['bed_code'],
                    'bed_class' => (string) $assignedBed['bed_class'],
                    'is_upgrade' => $isUpgrade,
                ];
            } else {
                $unassigned[] = [
                    'patient_id' => $pId,
                    'required_class' => $req,
                    'status' => 'antre',
                ];
            }
        }

        $certificate = new BedAssignmentCertificate(
            sortedPatientIds: array_map(fn ($p) => (string) $p['id'], $sortedPatients),
            assignments: $assignments,
            unassigned: $unassigned,
            generatedAt: time()
        );

        BedAssignmentCertificateVerifier::verify($certificate);

        return new BedAssignmentResult($sortedPatients, $assignments, $unassigned, $certificate);
    }
}

final class BedAssignmentResult
{
    /**
     * @param  list<array<string, mixed>>  $sortedPatients
     * @param  list<array{patient_id: string, bed_code: string, bed_class: string, is_upgrade: bool}>  $assignments
     * @param  list<array{patient_id: string, required_class: string, status: string}>  $unassigned
     */
    public function __construct(
        public readonly array $sortedPatients,
        public readonly array $assignments,
        public readonly array $unassigned,
        public readonly BedAssignmentCertificate $certificate
    ) {}

    /**
     * @return list<string>
     */
    public function sortedPatientIds(): array
    {
        return array_map(fn ($p) => (string) $p['id'], $this->sortedPatients);
    }

    public function findAssignment(string $patientId): ?array
    {
        foreach ($this->assignments as $a) {
            if ($a['patient_id'] === $patientId) {
                return $a;
            }
        }

        return null;
    }

    public function isUnassigned(string $patientId): bool
    {
        foreach ($this->unassigned as $u) {
            if ($u['patient_id'] === $patientId) {
                return true;
            }
        }

        return false;
    }
}

final class BedAssignmentCertificate
{
    public readonly string $sha256;

    /**
     * @param  list<string>  $sortedPatientIds
     * @param  list<array{patient_id: string, bed_code: string, bed_class: string, is_upgrade: bool}>  $assignments
     * @param  list<array{patient_id: string, required_class: string, status: string}>  $unassigned
     */
    public function __construct(
        public readonly array $sortedPatientIds,
        public readonly array $assignments,
        public readonly array $unassigned,
        public readonly int $generatedAt,
        ?string $customSha256 = null
    ) {
        $payload = json_encode([
            'sorted' => $this->sortedPatientIds,
            'assigned' => $this->assignments,
            'unassigned' => $this->unassigned,
        ], JSON_THROW_ON_ERROR);

        $this->sha256 = $customSha256 ?? hash('sha256', $payload);
    }
}

final class BedAssignmentCertificateVerifier
{
    public static function verify(BedAssignmentCertificate $certificate): void
    {
        $payload = json_encode([
            'sorted' => $certificate->sortedPatientIds,
            'assigned' => $certificate->assignments,
            'unassigned' => $certificate->unassigned,
        ], JSON_THROW_ON_ERROR);

        $expectedHash = hash('sha256', $payload);
        if (! hash_equals($expectedHash, $certificate->sha256)) {
            throw new RuntimeException('Sertifikat alokasi bed tidak valid: hash payload tidak cocok.');
        }

        // Validasi tidak ada ranjang yang dialokasikan ganda
        $usedBeds = [];
        foreach ($certificate->assignments as $a) {
            $code = $a['bed_code'];
            if (isset($usedBeds[$code])) {
                throw new RuntimeException("Pelanggaran alokasi bed: ranjang {$code} dialokasikan ganda!");
            }
            $usedBeds[$code] = true;
        }
    }
}
