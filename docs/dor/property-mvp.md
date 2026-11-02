# Definition of Ready (DoR) — LINI 6: Properti (Proptech & EPC/Asset MVP)

## 1. Rujukan Dokumen & Kutipan Baris Konsep

- **docs/KONSEP.md §A1 (baris 3-45):** Kepemilikan modul dan tabel otonom. Modul Proptech mengelola prefiks canonical `prp_*`, modul Epc mengelola prefiks canonical `epc_*`, Mall mengelola `mall_*`, dan Asset mengelola `ast_*`. Dilarang keras melakukan direct write lintas tabel modul (misal: Epc menulis langsung tabel `ast_*` atau Proptech menulis langsung tabel `mall_*`).
- **docs/KONSEP.md §A2 (seluruhnya, baris 48-124):** Bagan Akun Standar (CoA) double-entry ledger terintegrasi. Single-sign convention: Debit negatif (−), Kredit positif (+). Akun kontrol dan subledger terisolasi. Seluruh akun pendapatan, aset non-kliring, dan piutang/utang mengikuti aturan invariant integritas.
- **docs/KONSEP.md §A3 (baris 126-170):** Prinsip kebenaran numerik dan zero floating-point arithmetic. Seluruh nilai uang dalam integer rupiah (IDR), tarif per satuan utilitas dalam integer/bps, persentase kemajuan fisik dalam basis points (bps) atau rasio eksak. Pembulatan pembagian utilitas wajib menggunakan algoritma Pembulatan Kumulatif (B0′.2) bersertifikat.
- **docs/KONSEP.md §A4 (baris 172-210):** Batasan modularitas kontraktual dan integrasi berbasis domain event. Komunikasi antar modul melalui DTO terdefinisi ketat dan event asinkron (`prop.meter.read.v1`, `epc.project.handed_over.v1`).
- **docs/KONSEP.md §A5 (baris 212-255):** Append-only facts table: bacaan sensor/meteran utilitas (`prp_building_sensors`), sertifikat prestasi bulanan (`epc_progress_certificates`), berita acara serah terima (`epc_bast_records`), dan riwayat booking flex-space (`prp_flex_bookings`). Model fakta melarang mutasi UPDATE/DELETE langsung; koreksi wajib melalui entri pembalik.
- **docs/KONSEP.md §A6 (baris 257-295):** Idempotency key unik pada seluruh operasi kritis: penagihan utilitas, pemesanan flex-space, penerbitan MC kontraktor, dan kapitalisasi aset BAST.
- **docs/KONSEP.md §A7 (baris 297-330):** RBAC granular: rute `/proptech` (`facility_manager`, `property_manager`, tenant portal), rute `/epc` (`epc_manager`, `site_supervisor`, `asset_manager`), dan rute `/flex` (`customer`, `facility_manager`).
- **docs/KONSEP.md §A8 (baris 332-370):** Two-source audit commands (`prp:audit` / `pro:audit` dan `epc:audit`) yang memverifikasi keselarasan operasional terhadap ledger finansial dan mencakup fixture korupsi data buatan.
- **docs/KONSEP.md §A10 (baris 372-405):** Penggunaan `ClockInterface` / `SimClockInterface` untuk seluruh determinisme penanggalan, audit billing, dan simulasi 90 hari.
- **docs/KONSEP.md §A13 (baris 407-440):** Jalur galat bersih HTTP 422/redirect dengan `withErrors`, anti HTTP 500.
- **docs/KONSEP.md §A15 (baris 442-500):** Higienitas pengujian, isolasi test, dan penegakan arsitektur ratchet zero-regression.
- **docs/KONSEP.md §4F (baris 711-736):** Spesifikasi implementasi minimum (MVP vertical slice) untuk Properti dan EPC:
  - Baris 713: Modul & prefiks: Mall (`mall_`), Proptech (`prp_`), Epc (`epc_`), Asset (`ast_`).
  - Baris 715: Slice 1 — Tagihan utilitas dari bacaan aktual: zona (`prp_building_zones`) → sensor/meter (`prp_building_sensors`) → bacaan per interval → event `prop.meter.read.v1` → penagihan utilitas tenant (tarif kontrak/config) dengan algoritma pembulatan kumulatif B0′.2.
  - Baris 716: Slice 2 — Flex-space: ruang (`prp_flex_spaces`) → booking per jam anti-overlap (`lockForUpdate` pada slot) → `hold` saat booking, `capture` saat check-in, `release`/no-show fee sesuai aturan.
  - Baris 717: Slice 3 — EPC → kapitalisasi: MC tersertifikasi memposting CIP & utang kontraktor (dengan retensi) → BAST final memancarkan `epc.project.handed_over.v1` → Asset membuat aset tetap & mereklasifikasi CIP lewat Contract milik Asset (bukan tulis tabel `ast_` dari Epc).
  - Baris 718: Slice 4 — BIM terikat WBS: komponen BIM (`prp_twin_components`) ditautkan ke node WBS; progres fisik = komponen selesai / total bobot, diverifikasi bottom-up dan top-down (PR2).
  - Baris 721-726: Matriks posting double-entry ledger lengkap.
  - Baris 728: Rute & role akses.
  - Baris 729-730: Command audit & two-source audit verification.
  - Baris 731: Test kunci penjaminan kualitas.
  - Baris 734: Jalan pintas terlarang (estimasi tagihan, cross-module DB write, kapitalisasi sebelum BAST, booking tanpa locking).
- **docs/PROGRESS.md P2, P3 (X1–X25), P8, P9:** Baseline ratchet zero-regression, integritas proof blocks, dan tata kelola commit atomic.

---

## 2. Kriteria Kesiapan (Ready Criteria) per Komponen

### PRO-1: Actual Meter Reading Utility Billing & Cumulative Rounding (B0′.2)
- [x] Pencatatan pembacaan register meter utilitas (listrik kWh, air m³) dari sensor fisik/IoT zona gedung (`prp_building_sensors`).
- [x] Konsumsi utilitas dihitung dari selisih pembacaan register aktual per periode tagihan (fakta murni, bukan estimasi atau flat-rate).
- [x] Pembagian tagihan berjenjang atau multi-tenant menggunakan algoritma Pembulatan Kumulatif (B0′.2):
  - Total tagihan terdistribusi $\sum = \text{total}$ (zero discrepancy).
  - Deviasi tiap langkah $\le 1$ IDR dari nilai fraksional proporsional eksak.
  - Diverifikasi oleh `CumulativeRoundingCertificateVerifier` independen.
- [x] Posting ledger penagihan:
  - Debit: `ar:mall:tenant:{id}:IDR` (atau `prp:utility_ar:IDR`)
  - Kredit: `mall:utility_revenue:IDR` (atau `prp:utility_rev:IDR`)
- [x] Pembacaan duplikat (idempotency key berulang) tidak menggandakan tagihan.

### PRO-2: Flex-Space Anti-Overlap Booking & Escrow Hold/Capture
- [x] Setiap slot pemesanan ruang flex (`prp_flex_spaces`) diperiksa terhadap reservasi aktif menggunakan transaksi database berkunci eksklusif (`lockForUpdate`).
- [x] Pemesanan yang bertabrakan waktu (overlapping interior time windows) ditolak seketika (throw RuntimeException / 422).
- [x] Alur finansial terintegrasi ledger:
  - Saat booking: `hold` dana deposit pemesanan:
    - Debit: `escrow:payment:IDR` (atau `prp:flex_escrow:IDR`)
    - Kredit: `prp:flex_revenue_unearned:IDR`
  - Saat check-in (validasi paspor kriptografis): `capture` sisa tagihan dan akru pendapatan:
    - Debit: `prp:flex_revenue_unearned:IDR`
    - Debit: `escrow:payment:IDR` (sisa)
    - Kredit: `prp:flex_revenue:IDR`
  - Saat no-show: penahanan deposit hangus sebagai pendapatan penalti (`prp:flex_penalty_rev:IDR`).

### EPC-1: Certified Monthly Progress Certificate (MC) & Retention AP
- [x] Sertifikat prestasi pekerjaan fisik bulanan (Monthly Certificate / MC) diajukan per periode dan diverifikasi konsultan pengawas.
- [x] Kemajuan fisik dihitung dari akumulasi bobot node WBS dan komponen fisik BIM (`prp_twin_components`).
- [x] Verifikasi sertifikat WBS (PR2):
  - Rekalkulasi independen bottom-up (dari daun WBS) dan top-down (dari level proyek).
  - Deviasi antara klaim konsultan dan kalkulasi bottom-up wajib 0 bps; sertifikat tidak valid memicu exception penolakan.
- [x] Posting ledger pengakuan nilai pekerjaan:
  - Debit: `ast:cip:{project}:IDR` (Konstruksi Dalam Pengerjaan)
  - Kredit: `ap:epc:contractor:{party}:IDR` (Utang Kontraktor Netto)
  - Kredit: `epc:retention_payable:{project}:IDR` (Utang Retensi Pemeliharaan 5%)

### EPC-2: BAST Handover, CIP Reclassification & Fixed Asset Capitalization
- [x] Penerbitan Berita Acara Serah Terima (BAST):
  - BAST-1 (Partial Handover): Mensyaratkan progres fisik $\ge 95\%$, mencatat jaminan masa pemeliharaan retensi.
  - BAST-Final (Final Acceptance): Mensyaratkan progres fisik $100\%$ dan seluruh masa retensi/checklist tuntas.
- [x] Kapitalisasi aset sebelum BAST Final disetujui DITOLAK secara tegas oleh sistem.
- [x] Penerbitan BAST Final memancarkan event `epc.project.handed_over.v1`.
- [x] Kapitalisasi Konstruksi Dalam Pengerjaan (CIP) ke Aset Tetap:
  - Debit: `ast:fixed_assets:IDR`
  - Kredit: `ast:cip:{project}:IDR`
- [x] Saldo CIP proyek setelah kapitalisasi BAST Final wajib kembali tepat Rp 0.

### SEC & AUDIT: Two-Source Verification & Corruption Fixtures (Z7, Z8)
- [x] `prp:audit` (`pro:audit`) merekonsiliasi 2 sumber:
  1. $\sum (\text{konsumsi meter} \times \text{tarif}) = \text{total baris tagihan utilitas tenant}$.
  2. Booking flex-space terverifikasi bebas overlap dan konsisten terhadap ledger pendapatan sewa/escrow.
- [x] `epc:audit` merekonsiliasi 2 sumber:
  1. $\sum \text{MC Bruto} = \text{Saldo CIP berjalan} + \text{Total Nilai Kapitalisasi Aset Tetap}$.
  2. $\sum \text{MC Netto} + \sum \text{Retensi} = \sum \text{Posting Utang Kontraktor} + \sum \text{Posting Retensi}$.
  3. Progres fisik aktual proyek tidak pernah melebihi 100.00%.
- [x] Disediakan fixture korupsi buatan yang membuktikan audit gagal (exit code 1) saat terjadi ketidaksesuaian data.
