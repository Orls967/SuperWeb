# Algoritma: AirworthinessDispatchGate (A2)

## 1. Lokasi Kode & Arsitektur
- **Kelas Algoritma:** `Modules\Shared\Domain\Algorithms\Aviation\AirworthinessDispatchGate`
- **Sertifikat:** `Modules\Shared\Domain\Algorithms\Aviation\AirworthinessCertificate`
- **Verifier:** `Modules\Shared\Domain\Algorithms\Aviation\AirworthinessCertificateVerifier`
- **Hasil:** `Modules\Shared\Domain\Algorithms\Aviation\AirworthinessResult`
- **Pillar/Lini:** Lini 26 (Aviasi, Fase 177)
- **Kompleksitas Waktu:** $O(1)$ evaluasi batas aritmetika dan kalender.
- **Kompleksitas Ruang:** $O(1)$ penampung kode pelanggaran.

## 2. Intuisi Bisnis & Dasar Regulasi
Mengacu pada Civil Aviation Safety Regulations (CASR Part 121 / DGCA) dan ICAO Annex 6:
1. **Sertifikat Kelaikan Udara Standar (Certificate of Airworthiness):** Dokumen legal kalender yang menyatakan pesawat aman beroperasi. Pesawat yang masa berlaku sertifikatnya telah terlewati ($T_{\text{cert}} < T_{\text{eval}}$) dilarang terbang secara mutlak.
2. **Interval Perawatan A-Check (Flight Hours Margin):** Inspeksi mendalam berkala dilakukan setiap interval jam terbang tertentu (mis. 500 jam atau 36.000 menit). Pesawat dilarang diberangkatkan apabila estimasi durasi sektor penerbangan melebihi margin sisa jam terbang sebelum batas A-Check tercapai.
3. **Batas Siklus Struktur (Flight Cycles Margin):** Rangka pesawat dan mesin memiliki batas lelah siklus lepas landas/mendarat (*pressurization cycles*). Sisa siklus harus mencukupi untuk penerbangan yang direncanakan.
4. **Minimum Equipment List (MEL) Deferral Expiry:** Komponen minor pesawat yang rusak namun diizinkan beroperasi sementara di bawah panduan MEL memiliki batas tanggal perbaikan definitif. Jika tanggal batas kedaluwarsa ($T_{\text{MEL}} < T_{\text{eval}}$), pesawat wajib di-grounded hingga perbaikan diselesaikan.

## 3. Rumus Matematis & Spesifikasi Logika
Sebuah penerbangan diizinkan lepas landas (`isPermitted = true`) jika dan hanya jika seluruh kondisi berikut terpenuhi:
1. $T_{\text{cert}} \ge T_{\text{eval}}$, jika gagal tambahkan kode penolakan `CERT_EXPIRED`.
2. $(T_{\text{A-due}} - T_{\text{A-current}}) \ge D_{\text{flight}}$, jika gagal tambahkan `A_CHECK_HOURS`.
3. $(C_{\text{limit}} - C_{\text{current}}) \ge C_{\text{flight}}$, jika gagal tambahkan `CYCLES_EXCEEDED`.
4. $T_{\text{MEL}} \ge T_{\text{eval}}$ (bila ada deferral MEL aktif), jika gagal tambahkan `MEL_EXPIRED`.

Seluruh kode alasan penolakan diurutkan secara alfabetis untuk menjamin determinisme sertifikat.

## 4. Contoh Hitung Langkah Demi Langkah (Vektor Prompt)
**Kondisi Awal Armada:**
- Tanggal evaluasi: `2026-11-08`.
- Batas A-Check: 36.000 menit; Jam berjalan: 35.910 menit $\implies$ Margin jam $= 36.000 - 35.910 = 90$ menit.
- Siklus batas: 20.000; Siklus berjalan: 19.990 $\implies$ Margin siklus $= 10$ siklus.

**Kasus Uji:**
1. **Skenario 1:** Durasi terbang 60 menit, sertifikat `2026-12-31`, MEL `2026-11-10`:
   - Margin jam 90 menit $\ge 60$ menit $\implies$ Lolos.
   - Sertifikat kalender `2026-12-31` $\ge$ `2026-11-08` $\implies$ Lolos.
   - MEL `2026-11-10` $\ge$ `2026-11-08` $\implies$ Lolos.
   - Hasil: Izin diberikan (`isPermitted = true`), margin tersisa 90 menit, daftar alasan kosong `[]`.
2. **Skenario 2:** Durasi terbang 120 menit:
   - Margin jam 90 menit $< 120$ menit $\implies$ Gagal (`A_CHECK_HOURS`).
   - Hasil: Izin ditolak (`isPermitted = false`), alasan `['A_CHECK_HOURS']`.
3. **Skenario 3:** MEL kedaluwarsa `2026-11-07`:
   - MEL `2026-11-07` $<$ `2026-11-08` $\implies$ Gagal (`MEL_EXPIRED`).
   - Hasil: Izin ditolak, alasan `['MEL_EXPIRED']`.
4. **Skenario 4:** Multi-pelanggaran (Sertifikat `2026-11-07`, MEL `2026-11-07`, terbang 120 menit):
   - Jam terbang gagal $\implies$ `A_CHECK_HOURS`.
   - Sertifikat kalender gagal $\implies$ `CERT_EXPIRED`.
   - Deferral MEL gagal $\implies$ `MEL_EXPIRED`.
   - Hasil: Izin ditolak, alasan terurut alfabet: `['A_CHECK_HOURS', 'CERT_EXPIRED', 'MEL_EXPIRED']`.

## 5. Sertifikat & Integritas Pengujian
Sertifikat diverifikasi oleh `AirworthinessCertificateVerifier` untuk memastikan tidak ada dispatch yang lolos secara ilegal. Modifikasi sertifikat yang mencoba memanipulasi flag izin atau menyembunyikan alasan penolakan secara otomatis melempar `InvalidCertificateException`.

