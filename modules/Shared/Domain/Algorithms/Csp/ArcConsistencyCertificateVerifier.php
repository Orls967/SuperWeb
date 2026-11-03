<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Csp;

use InvalidArgumentException;

final class ArcConsistencyCertificateVerifier
{
    /**
     * Memverifikasi sertifikat AC-3: setiap nilai dalam domain tereduksi memiliki support
     * di setiap variabel tetangga yang memiliki constraint biner.
     *
     * @param  array<string, list<mixed>>  $initialDomains
     * @param  list<Constraint>  $constraints
     */
    public static function verify(
        array $initialDomains,
        array $constraints,
        ArcConsistencyResult $result
    ): bool {
        if (! $result->isConsistent) {
            // Jika tidak konsisten, salah satu domain harus kosong
            $hasEmpty = false;
            foreach ($result->domains as $dom) {
                if ($dom === []) {
                    $hasEmpty = true;
                    break;
                }
            }
            if (! $hasEmpty) {
                throw new InvalidArgumentException('Sertifikat AC-3 tidak konsisten tetapi tidak ada domain kosong.');
            }

            return true;
        }

        // Domain tereduksi harus subset dari domain awal
        foreach ($result->domains as $var => $values) {
            $init = $initialDomains[$var] ?? [];
            foreach ($values as $val) {
                if (! in_array($val, $init, true)) {
                    throw new InvalidArgumentException("Nilai '{$val}' pada variabel {$var} tidak berasal dari domain awal.");
                }
            }
        }

        // Setiap nilai pada $var1 harus memiliki setidaknya satu support pada $var2
        foreach ($constraints as $c) {
            $dom1 = $result->domains[$c->var1] ?? [];
            $dom2 = $result->domains[$c->var2] ?? [];

            foreach ($dom1 as $v1) {
                $hasSupport = false;
                foreach ($dom2 as $v2) {
                    if ($c->check($v1, $v2)) {
                        $hasSupport = true;
                        break;
                    }
                }
                if (! $hasSupport) {
                    throw new InvalidArgumentException("Pelanggaran AC-3: nilai '{$v1}' pada {$c->var1} tidak memiliki support pada {$c->var2}.");
                }
            }
        }

        $expectedHash = hash('sha256', json_encode($result->domains, JSON_THROW_ON_ERROR).($result->isConsistent ? '1' : '0'));
        if ($result->certificate !== $expectedHash) {
            throw new InvalidArgumentException('Sertifikat AC-3 rusak: hash mismatch.');
        }

        return true;
    }
}
