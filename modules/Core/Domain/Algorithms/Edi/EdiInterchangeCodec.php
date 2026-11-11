<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Edi;

use DomainException;
use Modules\Core\Contracts\CanonicalEdiDocument;
use Modules\Core\Domain\Algorithms\CanonicalCertificateSupport;

/**
 * Deterministic EDI subset codec & validator for UN/EDIFACT (ORDERS/INVOIC/DESADV) and ASC X12 (850/997) (B3 / W8-D49).
 *
 * Implements ISO 9735 release-character unescaping/escaping (`?+`, `?:`, `?'`, `??`)
 * and byte-identical regeneration for valid interchanges. Produces CONTRL and 997 acknowledgments.
 * NOTE: Subset simulation only; does not claim full X12/EDIFACT production compliance.
 *
 * Complexity: O(n) single-pass tokenization on payload character length n. Integer counters only.
 */
final class EdiInterchangeCodec
{
    /**
     * Decode, validate, and build CONTRL / 997 acknowledgment with SHA-256 certificate.
     */
    public function decode(string $rawPayload): CanonicalEdiDocument
    {
        $trimmed = trim($rawPayload);
        if ($trimmed === '') {
            return $this->buildInvalidDocument('EDIFACT', $rawPayload, ['EMPTY_PAYLOAD']);
        }

        if (str_starts_with($trimmed, 'UNA') || str_starts_with($trimmed, 'UNB') || str_starts_with($trimmed, 'UNH')) {
            return $this->decodeEdifact($trimmed);
        }

        if (str_starts_with($trimmed, 'ISA') || str_starts_with($trimmed, 'GS*') || str_starts_with($trimmed, 'ST*')) {
            return $this->decodeX12($trimmed);
        }

        return $this->buildInvalidDocument('UNKNOWN', $rawPayload, ['UNRECOGNIZED_EDI_SYNTAX']);
    }

    /**
     * Regenerate raw EDI interchange byte-identically from parsed canonical segments.
     */
    public function encode(CanonicalEdiDocument $document): string
    {
        if ($document->segments === []) {
            throw new DomainException('EDI_CANNOT_ENCODE_EMPTY: Dokumen kanonik tidak memiliki segmen.');
        }

        $segTerm = $document->delimiters['segment'] ?? "'";
        $elemSep = $document->delimiters['element'] ?? '+';
        $compSep = $document->delimiters['component'] ?? ':';
        $relChar = $document->delimiters['release'] ?? '';

        $encodedSegments = [];
        foreach ($document->segments as $seg) {
            if ($seg['tag'] === 'UNA' && $seg['raw'] !== '') {
                $encodedSegments[] = $seg['raw'];

                continue;
            }

            $encodedElements = [$seg['tag']];
            foreach ($seg['elements'] as $components) {
                $escapedComponents = array_map(
                    fn (string $value): string => $this->escapeValue($value, $segTerm, $elemSep, $compSep, $relChar),
                    $components
                );
                $encodedElements[] = implode($compSep, $escapedComponents);
            }

            $encodedSegments[] = implode($elemSep, $encodedElements).$segTerm;
        }

        return implode('', $encodedSegments);
    }

    /**
     * Generate functional acknowledgment (CONTRL for EDIFACT, 997 for X12).
     */
    public function generateAcknowledgment(CanonicalEdiDocument $document): string
    {
        return $document->acknowledgmentPayload;
    }

    private function decodeEdifact(string $raw): CanonicalEdiDocument
    {
        $delimiters = [
            'component' => ':',
            'element' => '+',
            'decimal' => '.',
            'release' => '?',
            'segment' => "'",
        ];

        $hasUna = false;
        $cursor = 0;
        if (str_starts_with($raw, 'UNA') && strlen($raw) >= 9) {
            $delimiters['component'] = $raw[3];
            $delimiters['element'] = $raw[4];
            $delimiters['decimal'] = $raw[5];
            $delimiters['release'] = $raw[6];
            $delimiters['segment'] = $raw[8];
            $hasUna = true;
            $cursor = 9;
        }

        $segStrings = $this->splitWithEscape(substr($raw, $cursor), $delimiters['segment'], $delimiters['release']);
        $segments = [];
        if ($hasUna) {
            $segments[] = ['tag' => 'UNA', 'elements' => [], 'raw' => substr($raw, 0, 9)];
        }

        foreach ($segStrings as $segStr) {
            if ($segStr === '') {
                continue;
            }
            $elemStrings = $this->splitWithEscape($segStr, $delimiters['element'], $delimiters['release']);
            $tag = $this->unescapeValue($elemStrings[0] ?? '', $delimiters['release']);
            $elements = [];
            $elemCount = count($elemStrings);
            for ($i = 1; $i < $elemCount; $i++) {
                $compStrings = $this->splitWithEscape($elemStrings[$i], $delimiters['component'], $delimiters['release']);
                $elements[] = array_map(fn (string $c): string => $this->unescapeValue($c, $delimiters['release']), $compStrings);
            }
            $segments[] = ['tag' => $tag, 'elements' => $elements, 'raw' => $segStr.$delimiters['segment']];
        }

        $unbRef = '';
        $senderId = '';
        $receiverId = '';
        $unhRef = '';
        $messageType = 'ORDERS';
        $docTypeCode = '';
        $docNumber = '';
        $buyerId = '';
        $untCount = null;
        $untRef = null;
        $unzCount = null;
        $unzRef = null;
        $unhIndex = null;
        $untIndex = null;
        $hasBgm = false;
        $linItems = [];
        $currentLin = null;

        foreach ($segments as $idx => $seg) {
            $tag = $seg['tag'];
            $el = $seg['elements'];
            if ($tag === 'UNB') {
                $senderId = $el[1][0] ?? '';
                $receiverId = $el[2][0] ?? '';
                $unbRef = $el[4][0] ?? '';
            } elseif ($tag === 'UNH') {
                $unhIndex = $idx;
                $unhRef = $el[0][0] ?? '';
                $messageType = $el[1][0] ?? 'ORDERS';
            } elseif ($tag === 'BGM') {
                $hasBgm = true;
                $docTypeCode = $el[0][0] ?? '';
                $docNumber = $el[1][0] ?? '';
            } elseif ($tag === 'NAD' && ($el[0][0] ?? '') === 'BY') {
                $buyerId = $el[1][0] ?? '';
            } elseif ($tag === 'LIN') {
                if ($currentLin !== null) {
                    $linItems[] = $currentLin;
                }
                $currentLin = [
                    'line_number' => $el[0][0] ?? (string) (count($linItems) + 1),
                    'sku' => $el[2][0] ?? 'UNKNOWN',
                    'quantity' => 0,
                    'unit_price' => 0,
                    'has_qty' => false,
                ];
            } elseif ($tag === 'QTY' && $currentLin !== null) {
                $currentLin['quantity'] = (int) ($el[0][1] ?? 0);
                $currentLin['has_qty'] = true;
            } elseif ($tag === 'UNT') {
                $untIndex = $idx;
                $untCount = isset($el[0][0]) ? (int) $el[0][0] : null;
                $untRef = $el[1][0] ?? '';
            } elseif ($tag === 'UNZ') {
                $unzCount = isset($el[0][0]) ? (int) $el[0][0] : null;
                $unzRef = $el[1][0] ?? '';
            }
        }
        if ($currentLin !== null) {
            $linItems[] = $currentLin;
        }

        $errors = [];
        $actualMsgSegs = ($unhIndex !== null && $untIndex !== null && $untIndex >= $unhIndex)
            ? ($untIndex - $unhIndex + 1)
            : 0;

        if ($unhIndex === null || $untIndex === null || $unbRef === '' || $unzRef === null) {
            $errors[] = 'MALFORMED_EDIFACT_ENVELOPE';
        } else {
            if ($unhRef !== $untRef) {
                $errors[] = 'UNH_UNT_REFERENCE_MISMATCH';
            }
            if ($untCount !== $actualMsgSegs) {
                $errors[] = 'UNT_COUNT_MISMATCH';
            }
            if ($unbRef !== $unzRef) {
                $errors[] = 'UNB_UNZ_REFERENCE_MISMATCH';
            }
            if ($unzCount !== 1) {
                $errors[] = 'UNZ_COUNT_MISMATCH';
            }
        }

        if (! $hasBgm) {
            $errors[] = 'MISSING_BGM_SEGMENT';
        }
        if ($linItems === []) {
            $errors[] = 'MISSING_LIN_SEGMENT';
        }
        foreach ($linItems as $item) {
            if (! $item['has_qty']) {
                $errors[] = 'LIN_WITHOUT_QTY';
                break;
            }
        }

        sort($errors);
        $errors = array_values(array_unique($errors));
        $isValid = $errors === [];
        $action = $isValid ? '7' : '4';

        $contrlSegments = [
            "UNB+UNOC:3+{$receiverId}:ZZ+{$senderId}:ZZ+261108:1030+ACK{$unbRef}'",
            "UNH+1+CONTRL:D:96A:UN'",
            "UCI+{$unbRef}+{$senderId}:ZZ+{$receiverId}:ZZ+{$action}".($errors !== [] ? '+'.implode(':', $errors) : '')."'",
            "UNT+3+1'",
            "UNZ+1+ACK{$unbRef}'",
        ];
        $contrlPayload = implode('', $contrlSegments);

        $publicLines = array_map(static fn (array $l): array => [
            'line_number' => $l['line_number'],
            'sku' => $l['sku'],
            'quantity' => $l['quantity'],
            'unit_price' => $l['unit_price'],
        ], $linItems);

        $cert = CanonicalCertificateSupport::issue(
            ['standard' => 'EDIFACT', 'raw_sha256' => hash('sha256', $raw)],
            [
                'segment_count='.count($segments),
                "message_segment_count={$actualMsgSegs}",
                'errors='.($errors === [] ? 'NONE' : implode(',', $errors)),
            ],
            [
                'is_valid' => $isValid,
                'action' => $action,
                'segment_count' => count($segments),
                'message_segment_count' => $actualMsgSegs,
            ],
        );

        return new CanonicalEdiDocument(
            standard: 'EDIFACT',
            messageType: $messageType,
            interchangeControlRef: $unbRef,
            messageControlRef: $unhRef,
            senderId: $senderId,
            receiverId: $receiverId,
            documentTypeCode: $docTypeCode,
            documentNumber: $docNumber,
            buyerIdentifier: $buyerId,
            segmentCount: count($segments),
            messageSegmentCount: $actualMsgSegs,
            isValid: $isValid,
            acknowledgmentAction: $action,
            acknowledgmentPayload: $contrlPayload,
            segments: $segments,
            lineItems: $publicLines,
            errorCodes: $errors,
            delimiters: $delimiters,
            certificate: $cert,
            rawPayload: $raw,
        );
    }

    private function decodeX12(string $raw): CanonicalEdiDocument
    {
        $delimiters = ['segment' => '~', 'element' => '*', 'component' => ':', 'release' => ''];
        $rawSegments = array_values(array_filter(explode('~', $raw), static fn (string $s): bool => $s !== ''));

        $segments = [];
        $txSet = '850';
        $stRef = '';
        $seCount = null;
        $seRef = null;
        $docNumber = '';
        $stIdx = null;
        $seIdx = null;
        $lineItems = [];

        foreach ($rawSegments as $idx => $segStr) {
            $parts = explode('*', $segStr);
            $tag = $parts[0];
            $elements = [];
            for ($i = 1; $i < count($parts); $i++) {
                $elements[] = [$parts[$i]];
            }
            $segments[] = ['tag' => $tag, 'elements' => $elements, 'raw' => $segStr.'~'];

            if ($tag === 'ST') {
                $stIdx = $idx;
                $txSet = $parts[1] ?? '850';
                $stRef = $parts[2] ?? '';
            } elseif ($tag === 'BEG') {
                $docNumber = $parts[3] ?? '';
            } elseif ($tag === 'PO1') {
                $lineItems[] = [
                    'line_number' => $parts[1] ?? (string) (count($lineItems) + 1),
                    'sku' => $parts[7] ?? 'UNKNOWN',
                    'quantity' => (int) ($parts[2] ?? 0),
                    'unit_price' => (int) ($parts[4] ?? 0),
                ];
            } elseif ($tag === 'SE') {
                $seIdx = $idx;
                $seCount = isset($parts[1]) ? (int) $parts[1] : null;
                $seRef = $parts[2] ?? '';
            }
        }

        $errors = [];
        $ak5Code = null;
        $actualMsgSegs = ($stIdx !== null && $seIdx !== null && $seIdx >= $stIdx) ? ($seIdx - $stIdx + 1) : 0;

        if ($stIdx === null || $seIdx === null) {
            $errors[] = 'MALFORMED_X12_ENVELOPE';
            $ak5Code = '1';
        } else {
            if ($stRef !== $seRef) {
                $errors[] = 'ST_SE_CONTROL_MISMATCH';
                $ak5Code = '4';
            }
            if ($seCount !== $actualMsgSegs) {
                $errors[] = 'SE_COUNT_MISMATCH';
                $ak5Code = '5';
            }
        }

        sort($errors);
        $errors = array_values(array_unique($errors));
        $isValid = $errors === [];
        $funcCode = $txSet === '850' ? 'PO' : ($txSet === '856' ? 'SH' : ($txSet === '810' ? 'IN' : 'FA'));

        if ($isValid) {
            $ack997 = "AK1*{$funcCode}*1~AK2*{$txSet}*{$stRef}~AK5*A~AK9*A*1*1*1~";
        } else {
            $ack997 = "AK1*{$funcCode}*1~AK2*{$txSet}*{$stRef}~AK5*R*{$ak5Code}~AK9*R*1*1*0~";
        }

        $cert = CanonicalCertificateSupport::issue(
            ['standard' => 'X12', 'raw_sha256' => hash('sha256', $raw)],
            ["message_segment_count={$actualMsgSegs}", 'errors='.($errors === [] ? 'NONE' : implode(',', $errors))],
            ['is_valid' => $isValid, 'message_segment_count' => $actualMsgSegs, 'ak5' => $isValid ? 'A' : "R*{$ak5Code}"],
        );

        return new CanonicalEdiDocument(
            standard: 'X12',
            messageType: $txSet,
            interchangeControlRef: $stRef,
            messageControlRef: $stRef,
            senderId: 'X12-SENDER',
            receiverId: 'X12-RECEIVER',
            documentTypeCode: $txSet,
            documentNumber: $docNumber,
            buyerIdentifier: 'X12-BUYER',
            segmentCount: count($segments),
            messageSegmentCount: $actualMsgSegs,
            isValid: $isValid,
            acknowledgmentAction: $isValid ? 'A' : 'R',
            acknowledgmentPayload: $ack997,
            segments: $segments,
            lineItems: $lineItems,
            errorCodes: $errors,
            delimiters: $delimiters,
            certificate: $cert,
            rawPayload: $raw,
        );
    }

    /**
     * @param  list<string>  $errors
     */
    private function buildInvalidDocument(string $standard, string $raw, array $errors): CanonicalEdiDocument
    {
        $cert = CanonicalCertificateSupport::issue(
            ['standard' => $standard, 'raw_sha256' => hash('sha256', $raw)],
            ['invalid_syntax'],
            ['is_valid' => false, 'errors' => $errors],
        );

        return new CanonicalEdiDocument(
            standard: $standard,
            messageType: 'UNKNOWN',
            interchangeControlRef: 'UNKNOWN',
            messageControlRef: 'UNKNOWN',
            senderId: '',
            receiverId: '',
            documentTypeCode: '',
            documentNumber: '',
            buyerIdentifier: '',
            segmentCount: 0,
            messageSegmentCount: 0,
            isValid: false,
            acknowledgmentAction: '4',
            acknowledgmentPayload: "UNH+1+CONTRL:D:96A:UN'UCI+UNKNOWN+++4:".implode(':', $errors)."'UNT+2+1'",
            segments: [],
            lineItems: [],
            errorCodes: $errors,
            delimiters: ['segment' => "'", 'element' => '+', 'component' => ':', 'release' => '?'],
            certificate: $cert,
            rawPayload: $raw,
        );
    }

    /**
     * Split string by single delimiter skipping escaped sequences prefixed by release char.
     *
     * @return list<string>
     */
    private function splitWithEscape(string $input, string $delimiter, string $release): array
    {
        $parts = [];
        $buffer = '';
        $escaped = false;
        $len = strlen($input);

        for ($i = 0; $i < $len; $i++) {
            $ch = $input[$i];
            if ($escaped) {
                $buffer .= $ch;
                $escaped = false;

                continue;
            }
            if ($release !== '' && $ch === $release) {
                $buffer .= $ch;
                $escaped = true;

                continue;
            }
            if ($ch === $delimiter) {
                $parts[] = $buffer;
                $buffer = '';

                continue;
            }
            $buffer .= $ch;
        }

        if ($buffer !== '') {
            $parts[] = $buffer;
        }

        return $parts;
    }

    private function unescapeValue(string $value, string $release): string
    {
        if ($release === '') {
            return $value;
        }

        $out = '';
        $escaped = false;
        $len = strlen($value);
        for ($i = 0; $i < $len; $i++) {
            $ch = $value[$i];
            if ($escaped) {
                $out .= $ch;
                $escaped = false;

                continue;
            }
            if ($ch === $release) {
                $escaped = true;

                continue;
            }
            $out .= $ch;
        }

        return $out;
    }

    private function escapeValue(string $value, string $segTerm, string $elemSep, string $compSep, string $relChar): string
    {
        if ($relChar === '') {
            return $value;
        }

        $out = '';
        $len = strlen($value);
        for ($i = 0; $i < $len; $i++) {
            $ch = $value[$i];
            if ($ch === $relChar || $ch === $segTerm || $ch === $elemSep || $ch === $compSep) {
                $out .= $relChar.$ch;
            } else {
                $out .= $ch;
            }
        }

        return $out;
    }
}
