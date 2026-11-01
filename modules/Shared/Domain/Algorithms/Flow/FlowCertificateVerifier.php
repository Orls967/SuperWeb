<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Flow;

use RuntimeException;

/**
 * Verifier independen untuk sertifikat aliran maksimum dan potongan minimum (PR2).
 * Memverifikasi:
 * 1. Batasan kapasitas: 0 <= f(e) <= c(e) untuk setiap sisi.
 * 2. Konservasi aliran di setiap simpul selain sumber (source) dan muara (sink).
 * 3. Nilai aliran total dari sumber == maxFlow.
 * 4. Teorema Max-Flow Min-Cut: Nilai aliran == Kapasitas potongan S-T (kesetaraan adalah bukti matematis optimal).
 */
class FlowCertificateVerifier
{
    /**
     * @param  list<array{u: int, v: int, cap: int}>  $edges  Daftar sisi asli
     * @param  int  $vertexCount  Jumlah simpul dalam graf
     * @param  int  $source  Simpul sumber
     * @param  int  $sink  Simpul muara
     * @param  FlowResult  $result  Hasil yang diverifikasi
     *
     * @throws RuntimeException Bila sertifikat tidak valid
     */
    public function verify(
        array $edges,
        int $vertexCount,
        int $source,
        int $sink,
        FlowResult $result
    ): bool {
        $m = count($edges);

        // 1. Periksa batas kapasitas 0 <= flow <= capacity
        foreach ($edges as $idx => $edge) {
            $f = $result->flowPerEdge[$idx] ?? 0;
            if ($f < 0 || $f > $edge['cap']) {
                throw new RuntimeException(
                    "Aliran pada sisi {$idx} ({$edge['u']} -> {$edge['v']}) di luar batas: "
                    ."aliran {$f}, kapasitas {$edge['cap']}"
                );
            }
        }

        // 2. Konservasi aliran di setiap simpul
        $netFlow = array_fill(0, $vertexCount, 0);
        foreach ($edges as $idx => $edge) {
            $f = $result->flowPerEdge[$idx] ?? 0;
            $netFlow[$edge['u']] -= $f;
            $netFlow[$edge['v']] += $f;
        }

        for ($u = 0; $u < $vertexCount; $u++) {
            if ($u === $source || $u === $sink) {
                continue;
            }
            if ($netFlow[$u] !== 0) {
                throw new RuntimeException("Pelanggaran konservasi aliran pada simpul {$u}: aliran neto {$netFlow[$u]} != 0");
            }
        }

        // 3. Aliran neto sumber dan muara
        if (-$netFlow[$source] !== $result->maxFlow) {
            throw new RuntimeException(
                'Aliran keluar sumber ('.(-$netFlow[$source]).") tidak sama dengan maxFlow ({$result->maxFlow})"
            );
        }
        if ($netFlow[$sink] !== $result->maxFlow) {
            throw new RuntimeException(
                "Aliran masuk muara ({$netFlow[$sink]}) tidak sama dengan maxFlow ({$result->maxFlow})"
            );
        }

        // 4. Sertifikat Min-Cut S-T
        $sSet = array_fill(0, $vertexCount, false);
        foreach ($result->sourceCutSet as $node) {
            if ($node >= 0 && $node < $vertexCount) {
                $sSet[$node] = true;
            }
        }

        if (! $sSet[$source]) {
            throw new RuntimeException("Sumber {$source} tidak berada di dalam himpunan potongan S");
        }
        if ($sSet[$sink]) {
            throw new RuntimeException("Muara {$sink} tidak boleh berada di dalam himpunan potongan S");
        }

        $cutCapacity = 0;
        foreach ($edges as $idx => $edge) {
            $uInS = $sSet[$edge['u']];
            $vInS = $sSet[$edge['v']];

            if ($uInS && ! $vInS) {
                $cutCapacity += $edge['cap'];
                $f = $result->flowPerEdge[$idx] ?? 0;
                if ($f !== $edge['cap']) {
                    throw new RuntimeException(
                        "Sisi potongan ({$edge['u']} -> {$edge['v']}) tidak jenuh: aliran {$f} != kapasitas {$edge['cap']}"
                    );
                }
            } elseif (! $uInS && $vInS) {
                $f = $result->flowPerEdge[$idx] ?? 0;
                if ($f !== 0) {
                    throw new RuntimeException(
                        "Sisi balik ({$edge['u']} -> {$edge['v']}) membawa aliran positif: {$f}"
                    );
                }
            }
        }

        if ($cutCapacity !== $result->maxFlow) {
            throw new RuntimeException(
                "Pelanggaran teorema Max-Flow Min-Cut: kapasitas potongan ({$cutCapacity}) != maxFlow ({$result->maxFlow})"
            );
        }

        return true;
    }
}
