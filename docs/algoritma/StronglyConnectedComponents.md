# Algoritma: StronglyConnectedComponents (`Modules\Core\Domain\Algorithms\Boundaries\StronglyConnectedComponents`)

Dokumen referensi kelas kanonik algoritma **Tarjan Strongly Connected Components** untuk Fase R4 ([StronglyConnectedComponents.php](file:///Users/orl123456/Documents/typeshit/sw-w8-p4/modules/Core/Domain/Algorithms/Boundaries/StronglyConnectedComponents.php)).

## 1. Intuisi & Rumus
Graf dependensi modul didefinisikan sebagai $G = (V, E)$, dengan $V$ himpunan direktori modul `modules/*` terurut alfabetis dan $E$ sisi lintas modul $(u, v)$ terurut alfabetis.
Setiap simpul diberi bilangan bulat:
- $\text{index}[v]$: urutan penemuan DFS (mulai $0$).
- $\text{lowlink}[v]$: indeks terkecil yang dapat dicapai dari $v$ melalui subpohon DFS dan maksimal satu back-edge ke simpul aktif di stack:
  $$\text{lowlink}[v] = \min\big(\text{index}[v], \min_{(v,w)\in E_{\text{tree}}} \text{lowlink}[w], \min_{(v,w)\in E_{\text{back-in-stack}}} \text{index}[w]\big)$$
- Bila $\text{lowlink}[v] = \text{index}[v]$, elemen dikeluarkan dari stack menjadi satu komponen kuat $C$.
- **Aturan Siklus W8-D41**: Hanya $C$ dengan $|C| > 1$ yang dilaporkan dalam daftar `cycles`; self-loop $(A \to A)$ diabaikan (`|C| = 1`).
- **Graf Kondensasi**: Setiap SCC dikondensasi menjadi satu simpul pada Directed Acyclic Graph (DAG).

## 2. Jejak Hitung Vektor Prompt
Masukan: `V = [A, B, C, D, E, F, G]`, sisi `A->B, B->C, C->A, C->D, D->E, E->F, F->D, F->G`.
1. Kunjungan mulai `A` ($\text{index}=0, \text{low}=0$) $\to$ `B` ($1,1$) $\to$ `C` ($2,2$).
2. Dari `C` ke `A` (pada stack): $\text{lowlink}[C] = \min(2, 0) = 0$.
3. Dari `C` lanjut ke `D` ($3,3$) $\to$ `E` ($4,4$) $\to$ `F` ($5,5$).
4. Dari `F` ke `D` (pada stack): $\text{lowlink}[F] = 3$; ke `G` ($6,6$, out-degree 0, pop `[G]`).
5. Backtrack ke `D`: $\text{lowlink}[D]=3=\text{index}[D]$, pop `[D, E, F]` $\to$ urut alfabet `[D, E, F]`.
6. Kembali ke `A`: $\text{lowlink}[A]=0=\text{index}[A]$, pop `[A, B, C]`.
7. Hasil akhir terurut menurut anggota pertama: `cycles = [["A", "B", "C"], ["D", "E", "F"]]`; `G` tunggal tidak dilaporkan.
8. Uji tepi: graf asiklik `A->B->C` menghasilkan `[]`; self-loop `A->A` menghasilkan `[]`.

## 3. Kompleksitas, Batas & Verifikasi Sertifikat
- **Kompleksitas Waktu & Memori**: $\mathcal{O}(|V| + |E|)$.
- **Batas Deterministik**: Maksimum $10.000$ simpul, murni aritmetika bilangan bulat.
- **Sertifikat SHA-256**: Kelas verifier [`SccCertificateVerifier`](file:///Users/orl123456/Documents/typeshit/sw-w8-p4/modules/Core/Domain/Algorithms/Boundaries/SccCertificateVerifier.php) menghasilkan dan memverifikasi `{input, steps, result, sha256}` melalui [`CanonicalCertificateSupport`](file:///Users/orl123456/Documents/typeshit/sw-w8-p4/modules/Core/Domain/Algorithms/CanonicalCertificateSupport.php) serta membuktikan partisi tepat dan kondensasi bebas siklus (Kahn topological check). Sertifikat yang dimanipulasi melempar `InvalidArgumentException`.

## 4. Uji Penutup
- [`BoundaryAlgorithmsTest::test_algorithm_a1_strongly_connected_components_exact_vectors`](file:///Users/orl123456/Documents/typeshit/sw-w8-p4/tests/Acceptance/Boundaries/BoundaryAlgorithmsTest.php)

