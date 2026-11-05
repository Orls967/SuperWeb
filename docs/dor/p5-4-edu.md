# Definition of Ready (DoR) — P5.4 Lini 20: Edu Campus (Fase 166)

## Deskripsi
Mengimplementasikan Lini 20 Pendidikan Formal & Kampus (Fase 166) secara vertical slice mandiri di modul `Edu` (`edu_`). Menggantikan logika lama di `Integration/Application/Services/CampusEducationService.php` dengan implementasi domain-driven berstandar enterprise yang mematuhi:
- **Prasyarat & SKS Rule Engine**: Batas SKS berdasarkan IPK semester lalu (IPK >= 3.00 max 24 SKS, IPK >= 2.50 max 21 SKS, IPK < 2.50 max 18 SKS) serta validasi prasyarat mata kuliah (DagValidator/graph check).
- **Deteksi Bentrok Jadwal KRS (Timetable Clash Detector)**: Menggunakan algoritma numerik `IntervalIndex` half-open `[start, end)` untuk mendeteksi bentrok jam kuliah mahasiswa dan ruangan/dosen.
- **Transkrip Nilai Append-Only & Hash-Chain**: Transkrip berantai kriptografis menggunakan `CanonicalHashChain` (genesis `GENESIS_PREV_HASH`, payload deterministic JSON). Nilai terkunci immutable.
- **UKT Billing, Bounded Beasiswa & Amortisasi Hamilton**: Penagihan UKT per semester, batas beasiswa $\le$ tagihan kotor, alokasi cicilan UKT dengan sisa pembagian terkontrol menggunakan `HamiltonApportionment`.
- **Audit Dua Sumber `edu:audit`**: Memverifikasi integritas KRS (tidak ada bentrok jadwal), konsistensi hash-chain transkrip mahasiswa, dan keabsahan tagihan UKT (discrepancy 0 vs fixture non-zero).

## Keputusan Arsitektur
- D20-1: Modul pemilik adalah `Modules\Edu` (bukan modul baru `camp_`). Tabel menggunakan prefiks `edu_campus_*`.
- D20-2: Validasi bentrok jadwal menggunakan `IntervalIndex`.
- D20-3: Transkrip menggunakan `CanonicalHashChain` append-only.
- D20-4: Billing UKT & cicilan terintegrasi dengan `LedgerService` (`edu:tuition_receivable:IDR` dan `edu:tuition_revenue:IDR`).

