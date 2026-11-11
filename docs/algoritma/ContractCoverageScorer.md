# Algoritma: ContractCoverageScorer (`Modules\Core\Domain\Algorithms\Boundaries\ContractCoverageScorer`)

Dokumen referensi kelas kanonik algoritma **Contract Coverage Permille Scorer** untuk Fase R4 ([ContractCoverageScorer.php](file:///Users/orl123456/Documents/typeshit/sw-w8-p4/modules/Core/Domain/Algorithms/Boundaries/ContractCoverageScorer.php)).

## 1. Intuisi & Rumus Permil Bulat
Untuk mengukur proporsi pemanggilan lintas modul yang sudah memakai kontrak resmi versus impor langsung (`use Modules\{Lain}\Domain\*` / akses tabel terlarang), digunakan skor integer permil $[0, 1000]$ agar bebas galat floating-point IEEE 754:
$$\text{skor}(m) = \begin{cases} 1000 & \text{bila } c_m + d_m = 0 \\ \left\lfloor \dfrac{1000 \times c_m}{c_m + d_m} \right\rfloor & \text{bila } c_m + d_m > 0 \end{cases}$$
dengan $c_m$ jumlah referensi `via_kontrak` dan $d_m$ jumlah referensi `langsung`.
**Aturan Peringkat**: Urut menaik menurut `score` (prioritas remediasi tertinggi berada di atas), dengan tie-break nama modul menaik (`strcmp`).

## 2. Jejak Hitung Vektor Prompt
Masukan per modul:
- `X (18, 6)`: total $24$, $\lfloor 18000 / 24 \rfloor = 750$.
- `Y (1, 2)`: total $3$, $\lfloor 1000 / 3 \rfloor = 333$.
- `Z (0, 0)`: total $0$, konvensi $1000$.
- `W (5, 5)`: total $10$, $\lfloor 5000 / 10 \rfloor = 500$.
- `V (5, 0)`: total $5$, $\lfloor 5000 / 5 \rfloor = 1000$.

Pengurutan menaik (`score ASC, module ASC`):
1. `Y` (`333`)
2. `W` (`500`)
3. `X` (`750`)
4. `V` (`1000`)
5. `Z` (`1000`, seri 1000: `'V' < 'Z'`)

## 3. Kompleksitas, Batas & Verifikasi Sertifikat
- **Kompleksitas**: $\mathcal{O}(M \log M + V)$ pada $M$ modul dan $V$ pelanggaran ($M \le 1.000$).
- **Aritmetika Bilangan Bulat**: Murni `intdiv`, tanpa `float`.
- **Sertifikat SHA-256**: [`CoverageCertificateVerifier`](file:///Users/orl123456/Documents/typeshit/sw-w8-p4/modules/Core/Domain/Algorithms/Boundaries/CoverageCertificateVerifier.php) memverifikasi rentang $[0, 1000]$, konsistensi $\text{globalScore} = \lfloor 1000 \times \sum c / (\sum c + \sum d) \rfloor$, serta digest SHA-256 kanonik.

## 4. Uji Penutup
- [`BoundaryAlgorithmsTest::test_algorithm_a3_contract_coverage_scorer_exact_vectors`](file:///Users/orl123456/Documents/typeshit/sw-w8-p4/tests/Acceptance/Boundaries/BoundaryAlgorithmsTest.php)

