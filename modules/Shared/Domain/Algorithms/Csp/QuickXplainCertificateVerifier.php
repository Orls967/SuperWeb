<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Csp;

use InvalidArgumentException;

final class QuickXplainCertificateVerifier
{
    /**
     * Memverifikasi minimalitas himpunan konflik:
     * 1. Konflik harus tidak konsisten terhadap constraint latar belakang.
     * 2. Setiap subset sejati (penghilangan 1 elemen) harus konsisten.
     *
     * @param  list<Constraint>  $backgroundConstraints
     * @param  array<string, mixed>  $userAssignments
     */
    public static function verify(
        array $backgroundConstraints,
        array $userAssignments,
        QuickXplainResult $result
    ): bool {
        $conflict = $result->minimalConflict;

        // 1. Seluruh elemen konflik harus merupakan bagian dari input user
        foreach ($conflict as $k => $v) {
            if (! array_key_exists($k, $userAssignments) || $userAssignments[$k] !== $v) {
                throw new InvalidArgumentException("Elemen konflik [{$k} => {$v}] bukan bagian dari pilihan pengguna.");
            }
        }

        // 2. Konflik harus melanggar setidaknya satu constraint latar belakang
        $violates = false;
        foreach ($backgroundConstraints as $c) {
            if (array_key_exists($c->var1, $conflict) && array_key_exists($c->var2, $conflict)) {
                if (! $c->check($conflict[$c->var1], $conflict[$c->var2])) {
                    $violates = true;
                    break;
                }
            }
        }

        if (! $violates) {
            throw new InvalidArgumentException('Sertifikat QuickXplain tidak valid: himpunan konflik tidak melanggar constraint.');
        }

        // 3. Minimalitas: untuk setiap elemen pada konflik, jika dihapus, tidak boleh ada pelanggaran
        foreach ($conflict as $removeKey => $_) {
            $subConflict = $conflict;
            unset($subConflict[$removeKey]);

            $subViolates = false;
            foreach ($backgroundConstraints as $c) {
                if (array_key_exists($c->var1, $subConflict) && array_key_exists($c->var2, $subConflict)) {
                    if (! $c->check($subConflict[$c->var1], $subConflict[$c->var2])) {
                        $subViolates = true;
                        break;
                    }
                }
            }

            if ($subViolates) {
                throw new InvalidArgumentException("Sertifikat QuickXplain tidak minimal: penghapusan '{$removeKey}' masih menyisakan konflik.");
            }
        }

        $expectedHash = hash('sha256', json_encode($conflict, JSON_THROW_ON_ERROR));
        if ($result->certificate !== $expectedHash) {
            throw new InvalidArgumentException('Sertifikat QuickXplain rusak: hash mismatch.');
        }

        return true;
    }
}
