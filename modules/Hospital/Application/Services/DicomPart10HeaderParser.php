<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use InvalidArgumentException;

/**
 * Parser Struktur Biner Header File DICOM (NEMA PS 3.10 - Media Storage and File Format) (Fase 105).
 *
 * Menganalisis file stream DICOM Part 10:
 * - 128-byte Preamble
 * - 4-byte Magic Marker 'DICM'
 * - File Meta Information Group (0002,xxxx)
 * - Transfer Syntax UID (Implicit VR Little Endian, Explicit VR Little/Big Endian, JPEG encapsulation)
 * - Ekstraksi Standard Data Elements:
 *   * Patient ID (0010,0020) & Patient Name (0010,0010)
 *   * Study Instance UID (0020,000D) & Series Instance UID (0020,000E)
 *   * SOP Instance UID (0008,0018) & SOP Class UID (0008,0016)
 *   * Modality (0008,0060)
 *   * Image Dimensions: Rows (0028,0010), Columns (0028,0011), Bits Allocated (0028,0100)
 *   * Pixel Spacing (0028,0030) & Slice Thickness (0018,0050)
 *   * Window Center (0028,1050) & Window Width (0028,1051)
 *   * Rescale Intercept (0028,1052) & Rescale Slope (0028,1053)
 */
class DicomPart10HeaderParser
{
    public const DICOM_MAGIC = 'DICM';

    public const PREAMBLE_LENGTH = 128;

    /**
     * UID Standar DICOM Transfer Syntax yang umum didukung.
     */
    public const TRANSFER_SYNTAX_IMPLICIT_VR_LITTLE_ENDIAN = '1.2.840.10008.1.2';

    public const TRANSFER_SYNTAX_EXPLICIT_VR_LITTLE_ENDIAN = '1.2.840.10008.1.2.1';

    public const TRANSFER_SYNTAX_DEFLATED_EXPLICIT_VR_LE = '1.2.840.10008.1.2.1.99';

    public const TRANSFER_SYNTAX_EXPLICIT_VR_BIG_ENDIAN = '1.2.840.10008.1.2.2';

    public const TRANSFER_SYNTAX_JPEG_BASELINE_PROCESS_1 = '1.2.840.10008.1.2.4.50';

    public const TRANSFER_SYNTAX_JPEG_LOSSLESS_NH_14 = '1.2.840.10008.1.2.4.70';

    /**
     * Memvalidasi apakah buffer biner memiliki header valid DICOM Part 10.
     *
     * @param  string  $binaryData  Konten biner file
     */
    public function isValidDicomStream(string $binaryData): bool
    {
        if (strlen($binaryData) < (self::PREAMBLE_LENGTH + 4)) {
            return false;
        }

        $magic = substr($binaryData, self::PREAMBLE_LENGTH, 4);

        return $magic === self::DICOM_MAGIC;
    }

    /**
     * Parse stream biner file DICOM dan ekstrak metadata kunci.
     *
     * @param  string  $binaryData  Konten biner lengkap atau potongan awal (minimal 2048 bytes)
     * @return array{
     *     is_valid_dicom: bool,
     *     transfer_syntax_uid: string,
     *     transfer_syntax_name: string,
     *     patient_id: string|null,
     *     patient_name: string|null,
     *     study_instance_uid: string|null,
     *     series_instance_uid: string|null,
     *     sop_instance_uid: string|null,
     *     modality: string|null,
     *     rows: int|null,
     *     columns: int|null,
     *     bits_allocated: int|null,
     *     pixel_spacing: list<float>|null,
     *     slice_thickness_mm: float|null,
     *     window_center: float|null,
     *     window_width: float|null,
     *     rescale_slope: float,
     *     rescale_intercept: float,
     *     elements_parsed_count: int
     * }
     */
    public function parseDicomStream(string $binaryData): array
    {
        if (! $this->isValidDicomStream($binaryData)) {
            throw new InvalidArgumentException("Header file bukan format DICOM Part 10 valid (prefix 'DICM' tidak ditemukan pada offset 128).");
        }

        $length = strlen($binaryData);
        $offset = self::PREAMBLE_LENGTH + 4; // 132

        $transferSyntaxUid = self::TRANSFER_SYNTAX_EXPLICIT_VR_LITTLE_ENDIAN;
        $elements = [];

        // Parsing loop elemen data DICOM
        while ($offset + 8 <= $length) {
            $group = unpack('v', substr($binaryData, $offset, 2))[1] ?? 0;
            $element = unpack('v', substr($binaryData, $offset + 2, 2))[1] ?? 0;
            $offset += 4;

            $tagHex = sprintf('(%04X,%04X)', $group, $element);

            // Group 0002 selalu Explicit VR Little Endian
            $isExplicit = ($group === 0x0002) || ($transferSyntaxUid !== self::TRANSFER_SYNTAX_IMPLICIT_VR_LITTLE_ENDIAN);

            $vr = '';
            $valueLength = 0;

            if ($isExplicit && $offset + 2 <= $length) {
                $vr = substr($binaryData, $offset, 2);
                $offset += 2;

                // VR 32-bit length: OB, OW, OF, OD, SQ, UC, UR, UT, UN
                if (in_array($vr, ['OB', 'OW', 'OF', 'OD', 'SQ', 'UC', 'UR', 'UT', 'UN'], true)) {
                    $offset += 2; // Reserved bytes
                    if ($offset + 4 <= $length) {
                        $valueLength = unpack('V', substr($binaryData, $offset, 4))[1] ?? 0;
                        $offset += 4;
                    }
                } else {
                    if ($offset + 2 <= $length) {
                        $valueLength = unpack('v', substr($binaryData, $offset, 2))[1] ?? 0;
                        $offset += 2;
                    }
                }
            } else {
                // Implicit VR: 32-bit integer length
                if ($offset + 4 <= $length) {
                    $valueLength = unpack('V', substr($binaryData, $offset, 4))[1] ?? 0;
                    $offset += 4;
                }
            }

            // Jika nilai elemen pixel data atau sequence tak berhingga, hentikan pembacaan header
            if ($valueLength === 0xFFFFFFFF || $tagHex === '(7FE0,0010)') {
                break;
            }

            if ($offset + $valueLength > $length) {
                break;
            }

            $valueRaw = substr($binaryData, $offset, $valueLength);
            $offset += $valueLength;

            $elements[$tagHex] = [
                'group' => $group,
                'element' => $element,
                'vr' => $vr,
                'length' => $valueLength,
                'value' => rtrim($valueRaw, "\0 "),
            ];

            // Update transfer syntax jika ditemukan pada Group 0002, 0010
            if ($tagHex === '(0002,0010)') {
                $transferSyntaxUid = rtrim($valueRaw, "\0 ");
            }
        }

        // Ekstraksi nilai semantik
        $patientId = $elements['(0010,0020)']['value'] ?? null;
        $patientName = $elements['(0010,0010)']['value'] ?? null;
        $studyUid = $elements['(0020,000D)']['value'] ?? null;
        $seriesUid = $elements['(0020,000E)']['value'] ?? null;
        $sopUid = $elements['(0008,0018)']['value'] ?? null;
        $modality = $elements['(0008,0060)']['value'] ?? null;

        $rows = isset($elements['(0028,0010)']) ? (int) $elements['(0028,0010)']['value'] : null;
        $columns = isset($elements['(0028,0011)']) ? (int) $elements['(0028,0011)']['value'] : null;
        $bitsAllocated = isset($elements['(0028,0100)']) ? (int) $elements['(0028,0100)']['value'] : null;

        $pixelSpacing = null;
        if (isset($elements['(0028,0030)'])) {
            $parts = explode('\\', $elements['(0028,0030)']['value']);
            if (count($parts) >= 2) {
                $pixelSpacing = [(float) $parts[0], (float) $parts[1]];
            }
        }

        $sliceThickness = isset($elements['(0018,0050)']) ? (float) $elements['(0018,0050)']['value'] : null;
        $windowCenter = isset($elements['(0028,1050)']) ? (float) explode('\\', $elements['(0028,1050)']['value'])[0] : null;
        $windowWidth = isset($elements['(0028,1051)']) ? (float) explode('\\', $elements['(0028,1051)']['value'])[0] : null;
        $rescaleSlope = isset($elements['(0028,1053)']) ? (float) $elements['(0028,1053)']['value'] : 1.0;
        $rescaleIntercept = isset($elements['(0028,1052)']) ? (float) $elements['(0028,1052)']['value'] : 0.0;

        return [
            'is_valid_dicom' => true,
            'transfer_syntax_uid' => $transferSyntaxUid,
            'transfer_syntax_name' => $this->getTransferSyntaxName($transferSyntaxUid),
            'patient_id' => $patientId,
            'patient_name' => $patientName,
            'study_instance_uid' => $studyUid,
            'series_instance_uid' => $seriesUid,
            'sop_instance_uid' => $sopUid,
            'modality' => $modality,
            'rows' => $rows,
            'columns' => $columns,
            'bits_allocated' => $bitsAllocated,
            'pixel_spacing' => $pixelSpacing,
            'slice_thickness_mm' => $sliceThickness,
            'window_center' => $windowCenter,
            'window_width' => $windowWidth,
            'rescale_slope' => $rescaleSlope,
            'rescale_intercept' => $rescaleIntercept,
            'elements_parsed_count' => count($elements),
        ];
    }

    /**
     * Konversi representasi teks nama dari Transfer Syntax UID.
     */
    public function getTransferSyntaxName(string $uid): string
    {
        return match ($uid) {
            self::TRANSFER_SYNTAX_IMPLICIT_VR_LITTLE_ENDIAN => 'Implicit VR Little Endian',
            self::TRANSFER_SYNTAX_EXPLICIT_VR_LITTLE_ENDIAN => 'Explicit VR Little Endian',
            self::TRANSFER_SYNTAX_DEFLATED_EXPLICIT_VR_LE => 'Deflated Explicit VR Little Endian',
            self::TRANSFER_SYNTAX_EXPLICIT_VR_BIG_ENDIAN => 'Explicit VR Big Endian',
            self::TRANSFER_SYNTAX_JPEG_BASELINE_PROCESS_1 => 'JPEG Baseline (Process 1)',
            self::TRANSFER_SYNTAX_JPEG_LOSSLESS_NH_14 => 'JPEG Lossless, Non-Hierarchical (Process 14)',
            default => "Custom/Unknown Transfer Syntax ({$uid})",
        };
    }
}
