<?php

declare(strict_types=1);

namespace Modules\Port\Application\Services;

use DomainException;
use Modules\Port\Domain\Models\FleetPortCall;

/**
 * Service Keamanan Fasilitas Pelabuhan & Kapal Niaga (ISPS Code & Maritime Security).
 * Standar: International Ship and Port Facility Security (ISPS) Code Parts A & B.
 */
final class PortFacilitySecurityAuditService
{
    /**
     * Terbitkan Deklarasi Keamanan Maritim (Declaration of Security / DoS).
     * Wajib jika level keamanan kapal berbeda dengan level fasilitas pelabuhan,
     * atau saat memuat kargo berbahaya Kelas IMO 1 / 7, atau jika kapal beroperasi di ISPS Level 2/3.
     *
     * @param  int  $shipSecurityLevel  Level keamanan kapal (1, 2, atau 3)
     * @param  int  $portSecurityLevel  Level keamanan pelabuhan (1, 2, atau 3)
     * @param  string  $shipSecurityOfficerName  Nama SSO kapal
     * @param  string  $portFacilityOfficerName  Nama PFSO pelabuhan
     * @return array{
     *     port_call_id: int,
     *     dos_reference: string,
     *     ship_level: int,
     *     port_level: int,
     *     effective_operating_level: int,
     *     dos_mandatory: bool,
     *     security_measures: array<int, string>,
     *     status: string
     * }
     */
    public function issueDeclarationOfSecurity(
        int $portCallId,
        int $shipSecurityLevel,
        int $portSecurityLevel,
        string $shipSecurityOfficerName,
        string $portFacilityOfficerName
    ): array {
        $portCall = FleetPortCall::with('voyage.vessel')->findOrFail($portCallId);

        if ($shipSecurityLevel < 1 || $shipSecurityLevel > 3 || $portSecurityLevel < 1 || $portSecurityLevel > 3) {
            throw new DomainException('Level keamanan ISPS Code harus bernilai 1 (Normal), 2 (Tinggi), atau 3 (Luar Biasa/Bahaya Nyata).');
        }

        // Prinsip ISPS: Kapal dan pelabuhan harus beroperasi pada level keamanan TERTINGGI di antara keduanya
        $effectiveLevel = max($shipSecurityLevel, $portSecurityLevel);
        $isDosMandatory = ($shipSecurityLevel !== $portSecurityLevel) || ($effectiveLevel >= 2);

        $measures = [];
        if ($effectiveLevel === 1) {
            $measures[] = 'Pemeriksaan identitas seluruh pengunjung dan awak kapal di tangga pandu (gangway).';
            $measures[] = 'Penerangan dek dan tangga tambat selama malam hari.';
        } elseif ($effectiveLevel === 2) {
            $measures[] = 'Pengetatan akses gangway: pemeriksaan 100% barang bawaan dan penggeledahan acak.';
            $measures[] = 'Patroli dek keliling setiap 30 menit dan penguncian pintu akomodasi bagian dalam.';
            $measures[] = 'Pembatasan pergerakan kapal boat di sekitar kolam dermaga.';
        } else {
            $measures[] = 'Level Keamanan 3: Siaga darurat bahaya sabotase/terorisme.';
            $measures[] = 'Penghentian total operasi muat/bongkar kargo.';
            $measures[] = 'Pemberlakuan pengosongan dermaga dan penempatan regu keamanan bersenjata.';
        }

        $dosRef = 'DOS-'.strtoupper(substr($portCall->port_code, -3)).'-'.bin2hex(random_bytes(3));

        return [
            'port_call_id' => $portCall->id,
            'dos_reference' => $dosRef,
            'ship_level' => $shipSecurityLevel,
            'port_level' => $portSecurityLevel,
            'effective_operating_level' => $effectiveLevel,
            'dos_mandatory' => $isDosMandatory,
            'security_measures' => $measures,
            'status' => 'SIGNED_AND_ENFORCED',
        ];
    }
}
