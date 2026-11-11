# Spesifikasi Algoritma — Contract Coverage Scorer (Integer Permille)

**ID Algoritma**: `R4-ALG-03`  
**Kelas Implementasi**: `Modules\Core\Domain\Algorithms\Boundaries\ContractCoverageScorer`  
**Kelas Verifier**: `Modules\Core\Domain\Algorithms\Boundaries\CoverageCertificateVerifier`  

---

## 1. Masalah & Tujuan
Untuk mengukur kematangan modularitas sistem secara kuantitatif, tim arsitektur membutuhkan metrik cakupan kontrak (*contract coverage*) yang mengukur proporsi dependensi antar modul yang telah melalui antarmuka kontrak resmi dibandingkan dengan panggilan liar (*direct domain coupling* atau *boundary violations*).

Tujuan algoritma:
1. Menghitung skor cakupan kontrak per modul dan secara global untuk seluruh sistem.
2. Menggunakan representasi **bilangan bulat permil (integer permille, $0$–$1.000$)**, di mana $1.000$ mewakili cakupan kontrak $100{,}0\%$. Penggunaan integer permille menghindari galat aproksimasi biner floating-point (*IEEE 754 precision issues*) dan mematuhi aturan arsitektur `FloatMoneyRule`.
3. Menghasilkan sertifikat matematis yang dapat diverifikasi oleh CI dan gerbang kualitas.

---

## 2. Formulasi Matematis

Diberikan:
- $C_{u, v} \in \mathbb{N}_0$: Jumlah pemanggilan yang menggunakan kontrak resmi dari modul $u$ ke modul $v$.
- $V_{u, v} \in \mathbb{N}_0$: Jumlah pelanggaran batas arsitektur (A1, A2, A3) dari modul $u$ ke modul $v$.

Maka skor cakupan permil dari modul $u$ ke modul $v$ didefinisikan sebagai:
$$\text{Score}(u, v) = \begin{cases} 
1.000, & \text{jika } C_{u, v} + V_{u, v} = 0 \\
\left\lfloor \frac{C_{u, v} \times 1.000}{C_{u, v} + V_{u, v}} \right\rfloor, & \text{jika } C_{u, v} + V_{u, v} > 0
\end{cases}$$

Skor agregat modul $u$ (Outgoing Coverage):
$$\text{Score}_{\text{out}}(u) = \begin{cases} 
1.000, & \text{jika } \sum_v (C_{u, v} + V_{u, v}) = 0 \\
\left\lfloor \frac{\sum_v C_{u, v} \times 1.000}{\sum_v (C_{u, v} + V_{u, v})} \right\rfloor, & \text{lainnya}
\end{cases}$$

Skor Sistem Global:
$$\text{Score}_{\text{global}} = \begin{cases} 
1.000, & \text{jika } \sum_{u, v} (C_{u, v} + V_{u, v}) = 0 \\
\left\lfloor \frac{\sum_{u, v} C_{u, v} \times 1.000}{\sum_{u, v} (C_{u, v} + V_{u, v})} \right\rfloor, & \text{lainnya}
\end{cases}$$

---

## 3. Pseudokode Algoritma

```text
Algorithm CalculateContractCoverage(contractCalls, violations):
    Input:
        contractCalls: Map of (callerModule, targetModule) -> integer count
        violations: List of BoundaryViolation objects (callerModule, targetModule, ruleId)
    Output:
        perModuleScores: Map of module -> integer permille
        pairScores: Map of (caller, target) -> integer permille
        globalScore: integer permille
        certificate: CoverageCertificate object

    1. Aggregate violations by (caller, target):
       violationCounts = map()
       for each v in violations:
           key = (v.callerModule, v.targetModule)
           violationCounts[key] = (violationCounts[key] or 0) + 1

    2. Collect all active modules:
       modules = union of callers and targets from contractCalls and violationCounts

    3. Calculate pair scores:
       pairScores = map()
       totalContracts = 0
       totalViolations = 0

       for each (caller, target) in all_pairs:
           c = contractCalls[(caller, target)] or 0
           v = violationCounts[(caller, target)] or 0
           total = c + v
           if total == 0:
               score = 1000
           else:
               score = (c * 1000) // total
           pairScores[(caller, target)] = score
           totalContracts += c
           totalViolations += v

    4. Calculate per-module scores:
       perModuleScores = map()
       for each m in modules:
           c_m = sum(contractCalls[(m, *)])
           v_m = sum(violationCounts[(m, *)])
           total_m = c_m + v_m
           if total_m == 0:
               score_m = 1000
           else:
               score_m = (c_m * 1000) // total_m
           perModuleScores[m] = score_m

    5. Calculate global score:
       globalTotal = totalContracts + totalViolations
       if globalTotal == 0:
           globalScore = 1000
       else:
           globalScore = (totalContracts * 1000) // globalTotal

    6. Return Result(perModuleScores, pairScores, globalScore)
```

---

## 4. Sertifikat & Verifikasi (`CoverageCertificateVerifier`)
`CoverageCertificateVerifier` memvalidasi hasil kalkulasi:
1. **Batas Nilai Valid**: Setiap skor permil berada dalam rentang terikat $[0, 1.000]$.
2. **Kesesuaian Agregasi**: $\sum C$ dan $\sum V$ pada tingkat pasangan modul identik dengan total akumulasi global.
3. **Monotonisitas**: Penambahan kontrak baru tanpa menambah pelanggaran terbukti tidak menurunkan skor permil.

