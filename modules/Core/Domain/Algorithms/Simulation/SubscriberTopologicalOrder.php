<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Simulation;

use Modules\Core\Domain\Algorithms\Simulation\Exceptions\CyclicDependencyException;
use Modules\Core\Domain\Algorithms\Simulation\Exceptions\InvalidAlgorithmCertificateException;

/**
 * Algoritma A2: Subscriber Topological Order berbasis Algoritma Kahn dengan Antrean Siap Terurut Alfabetis.
 *
 * Kompleksitas Waktu: O(V + E log V) di mana V adalah jumlah simpul/subscriber dan E adalah jumlah dependensi "X sebelum Y".
 * Kompleksitas Ruang: O(V + E) untuk graf ketetanggaan dan derajat masuk.
 *
 * Antrean derajat masuk 0 selalu diurutkan secara leksikografis demi menjamin determinisme mutlak.
 */
class SubscriberTopologicalOrder
{
    /**
     * @var list<string>
     */
    protected array $lastResult = [];

    /**
     * @var array<string, mixed>
     */
    protected array $lastInput = [];

    /**
     * Mengurutkan simpul secara topologis berdasarkan relasi dependensi "from sebelum to".
     *
     * @param  list<string>  $nodes  Daftar seluruh identifier simpul
     * @param  list<array{from: string, to: string}>  $edges  Daftar relasi dependensi "from mendahului to"
     * @return list<string> Urutan eksekusi yang valid
     *
     * @throws CyclicDependencyException jika terdapat siklus dependensi sirkular
     */
    public function sort(array $nodes, array $edges): array
    {
        $this->lastInput = [
            'nodes' => $nodes,
            'edges' => $edges,
        ];

        $inDegree = [];
        $adj = [];

        foreach ($nodes as $node) {
            $inDegree[$node] = 0;
            $adj[$node] = [];
        }

        foreach ($edges as $edge) {
            $from = $edge['from'];
            $to = $edge['to'];

            if (! isset($inDegree[$to])) {
                $inDegree[$to] = 0;
                $adj[$to] = [];
            }
            if (! isset($inDegree[$from])) {
                $inDegree[$from] = 0;
                $adj[$from] = [];
            }

            $adj[$from][] = $to;
            $inDegree[$to]++;
        }

        // Antrean simpul siap (in-degree == 0)
        $readyQueue = [];
        foreach ($inDegree as $node => $deg) {
            if ($deg === 0) {
                $readyQueue[] = (string) $node;
            }
        }
        sort($readyQueue, SORT_STRING);

        $result = [];

        while (! empty($readyQueue)) {
            // Ambil elemen pertama dari antrean siap terurut
            $current = array_shift($readyQueue);
            $result[] = $current;

            foreach ($adj[$current] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $readyQueue[] = (string) $neighbor;
                    sort($readyQueue, SORT_STRING);
                }
            }
        }

        // Deteksi siklus jika hasil urutan tidak mencakup seluruh simpul yang memiliki dependensi
        $totalNodes = count($inDegree);
        if (count($result) < $totalNodes) {
            $cycleNodes = [];
            foreach ($inDegree as $node => $deg) {
                if ($deg > 0) {
                    $cycleNodes[] = (string) $node;
                }
            }
            sort($cycleNodes, SORT_STRING);

            throw new CyclicDependencyException($cycleNodes);
        }

        $this->lastResult = $result;

        return $result;
    }

    /**
     * Menghasilkan sertifikat kriptografis untuk audit integritas eksekusi algoritma A2.
     *
     * @return array{
     *     algorithm: string,
     *     input_nodes_count: int,
     *     edges_count: int,
     *     result: list<string>,
     *     sha256: string
     * }
     */
    public function generateCertificate(): array
    {
        $canonicalData = [
            'algorithm' => 'A2_SubscriberTopologicalOrder',
            'edges_count' => count($this->lastInput['edges'] ?? []),
            'input_nodes_count' => count($this->lastInput['nodes'] ?? []),
            'result' => $this->lastResult,
        ];

        $json = (string) json_encode($canonicalData, JSON_UNESCAPED_SLASHES);
        $hash = hash('sha256', $json);

        return array_merge($canonicalData, ['sha256' => $hash]);
    }

    /**
     * Memverifikasi validitas sertifikat eksekusi A2.
     *
     * @param  array<string, mixed>  $certificate
     *
     * @throws InvalidAlgorithmCertificateException
     */
    public function verifyCertificate(array $certificate): bool
    {
        $expectedHash = $certificate['sha256'] ?? '';
        $dataToHash = $certificate;
        unset($dataToHash['sha256']);

        $json = (string) json_encode($dataToHash, JSON_UNESCAPED_SLASHES);
        $computedHash = hash('sha256', $json);

        if (! hash_equals($expectedHash, $computedHash)) {
            throw new InvalidAlgorithmCertificateException('Sertifikat A2 gagal diverifikasi: hash integritas tidak cocok.');
        }

        return true;
    }
}
