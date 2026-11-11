# Algoritma: TablePrefixOwnershipResolver (`Modules\Core\Domain\Algorithms\Boundaries\TablePrefixOwnershipResolver`)

Dokumen referensi kelas kanonik algoritma **Longest-Prefix Table Ownership Resolution** untuk Fase R4 ([TablePrefixOwnershipResolver.php](file:///Users/orl123456/Documents/typeshit/sw-w8-p4/modules/Core/Domain/Algorithms/Boundaries/TablePrefixOwnershipResolver.php)).

## 1. Intuisi & Aturan Resolusi
Setiap tabel dalam `Schema::create`, `Schema::table`, `DB::table`, atau `$table` model wajib dimiliki oleh satu modul sesuai peta `config/modules.php`.
- **Prefiks Bertumpuk (Hierarchical Prefix)**: Urutkan daftar prefiks $P$ menurun berdasarkan panjang byte (`strlen($p)`) lalu alfabetis sebagai tie-break.
- **Aturan Garis Bawah Penuh**: Prefiks bertumpuk seperti `hcm_pay_` berakhir dengan garis bawah (`_`), sehingga `hcm_pay_runs` cocok dengan `hcm_pay_`, sementara `hcm_payroll` hanya cocok dengan `hcm_`.
- **Konflik Prefiks (`PREFIX_CONFLICT`)**: Dua modul berbeda yang mendaftarkan prefiks identik dideteksi oleh `TablePrefixOwnershipResolver::detectConflicts()`.
- **Tak Bertuan (`UNOWNED`)**: Tabel yang tidak diawali prefiks mana pun dikembalikan sebagai `'UNOWNED'` oleh `resolveOwnershipLabel()`.

## 2. Jejak Hitung Vektor Prompt
Peta uji: `hcm_ -> Hcm`, `hcm_pay_ -> Payroll`, `ins_ -> Insurance`, `pric_ -> Pricing`.
Urutan evaluasi panjang menurun: `hcm_pay_` (8) $\to$ `pric_` (5) $\to$ `hcm_` (4) $\to$ `ins_` (4).
1. `hcm_employees`: tidak cocok `hcm_pay_`, cocok `hcm_` $\to$ `Hcm`.
2. `hcm_pay_runs`: cocok pertama `hcm_pay_` $\to$ `Payroll`.
3. `hcm_payroll`: `hcm_pay_` tidak menjadi awalan `hcm_payroll`; turun ke `hcm_` $\to$ `Hcm`.
4. `ins_claims`: cocok `ins_` $\to$ `Insurance`.
5. `foo_bar`: tidak ada prefiks yang cocok $\to$ `'UNOWNED'`.
6. Peta ganda (`ins_ -> Insurance` dan `ins_ -> Health`) $\to$ `detectConflicts()` mengembalikan `["ins_"]`.

## 3. Kompleksitas, Batas & Verifikasi Sertifikat
- **Kompleksitas**: Inisialisasi $\mathcal{O}(P \log P)$, pencarian per tabel $\mathcal{O}(P)$ dengan batas $P \le 500$ prefiks.
- **Aritmetika & Determinisme**: Murni pembandingan string/panjang bilangan bulat tanpa heuristik probabilistik.
- **Sertifikat SHA-256**: [`PrefixCertificateVerifier`](file:///Users/orl123456/Documents/typeshit/sw-w8-p4/modules/Core/Domain/Algorithms/Boundaries/PrefixCertificateVerifier.php) memvalidasi format prefiks (`/^[a-z0-9_]+_$/`), keunikan prefiks, invarian longest-match, dan keutuhan hash SHA-256 via [`CanonicalCertificateSupport`](file:///Users/orl123456/Documents/typeshit/sw-w8-p4/modules/Core/Domain/Algorithms/CanonicalCertificateSupport.php).

## 4. Uji Penutup
- [`BoundaryAlgorithmsTest::test_algorithm_a2_table_prefix_ownership_exact_vectors`](file:///Users/orl123456/Documents/typeshit/sw-w8-p4/tests/Acceptance/Boundaries/BoundaryAlgorithmsTest.php)

