# Definition of Ready (DoR) — Lini 16: Pendidikan & Talent (Edu) MVP

Dokumen ini mendefinisikan kesiapan implementasi untuk Lini 16 (Fase 135: Academy & Sertifikasi, Fase 136: Talent Pipeline & Workforce Marketplace) berdasarkan KONSEP.md dan PROGRESS.md.

---

## 1. Rujukan dan Sitasi Dokumen Acuan

### 1.1 docs/KONSEP.md
- **§A1 (baris 70–108)**: Registry modul & prefiks tabel resmi `edu_`. Modul `Edu` adalah pemilik tunggal dari tabel berprefiks `edu_`.
- **§A2 (baris 109–150)**: Ledger & Finansial:
  - **A2.1 (baris 111–122)**: Konvensi tanda tunggal kredit (+), debit (-). Sisi normal akun pendapatan/kewajiban/escrow (kredit), beban/aset/piutang (debit).
  - **A2.2 (baris 124)**: Format kode akun `{prefiks}:{nama_akun}[:{subjek}:{id}]:{ASET}`, contoh: `ar:edu:corporate:{client_id}:IDR`, `edu:tuition_revenue:IDR`, `escrow:headhunter_guarantee:IDR`, `edu:headhunter_revenue:IDR`.
  - **A2.3 (baris 126)**: Chart of Accounts sebagai kode di `Modules/Edu/Ledger/EduAccounts.php` dan seeder idempoten.
  - **A2.4 (baris 128)**: Tipe transaksi terdaftar di `EduTransactionTypes.php` (≤ 32 karakter).
  - **A2.5 (baris 130)**: Format idempotency key deterministik `{prefiks}:{aksi}:{id_bisnis}[:{sub}]` tanpa `Str::uuid()`, `now()`, atau `microtime()`.
  - **A2.6 (baris 132–138)**: Uang IDR dalam integer rupiah (minor unit), fee dalam integer basis poin (`bps`), pembulatan HalfUp.
- **§A3 (baris 151–158)**: State machine enrollment (`ENROLLED`, `IN_PROGRESS`, `GRADUATED`, `CANCELLED`), sertifikat (`ACTIVE`, `EXPIRED`, `REVOKED`), penempatan headhunter (`PLACED`, `CONFIRMED`, `RELEASED`). Nomor sertifikat gapless deterministik.
- **§A4 (baris 159–188)**: Event domain (`edu.certificate.issued.v1`, `edu.talent.matched.v1`), konsumsi event spine, isolasi modul via kontrak antarmuka (`CredentialVerifier`).
- **§A5 (baris 189–196)**: Audit dua sumber `edu:audit` membandingkan fakta operasional dengan pembukuan ledger:
  - Sertifikat terbit = attempt asesmen lulus;
  - Nomor sertifikat gapless tanpa lubang urutan;
  - Rantai hash sertifikat per program valid (PR2/S4);
  - Tagihan korporat = enrollment kuota korporat;
  - Penempatan talent berasal dari MatchRun valid tanpa blocking pair (Gale-Shapley B0″.8);
  - Fee headhunter diproses tepat sekali (hold -> capture/release).
- **§A6 (baris 197–204)**: RBAC modul: peran `academy_admin`, `trainer`, `student`, `recruiter`. Anti-IDOR pada profil siswa, sertifikat, dan attempt ujian.
- **§A7 (baris 205–208)**: UI blade mobile-responsive 375px untuk halaman verifikasi sertifikat publik dan ujian proctored.
- **§A8 (baris 209–220)**: Matriks acceptance test (a) happy path, (b) auth/validasi 403/422, (c) idempotensi key deterministik, (d) invarian ledger/sertifikat, (e) edge case/konkurensi.
- **§A10 (baris 232–241)**: Determinisme waktu via `ClockInterface`, simulasi deterministik 6 bulan dengan hash run 1 == run 2.
- **§A13 (baris 266–323)**: Kepatuhan larangan X1–X25 (tanpa float uang, tanpa nomor sertifikat acak/berlubang, tanpa bypass verifier).
- **§A15 (baris 324–474)**: Template implementasi normatif, posting DTO, audit chunkById.
- **GELOMBANG 2 Lini 16 (baris 1325–1330)**: Spesifikasi Minimum MVP:
  - Larangan jalan pintas: sertifikat tanpa nomor gapless/hash; sertifikat tidak dipakai sebagai prasyarat lewat Contract; kuota korporat yang tidak ditagih.

### 1.2 docs/PROGRESS.md
- **P2 (baris 21–64)**: Protokol vertical slice V1–V12.
- **P3 (baris 65–124)**: Larangan X1–X25 (X3 integer minor, X4 idempotency key deterministik, X16 larangan domain import lintas modul).
- **P8 (baris 240–280)**: Format DoR & persetujuan desain arsitektur.
- **P9 (baris 281–310)**: Register Minus arsitektur & kompensasi kontrol.
- **Fase 135 (baris 9980–10020)**: Academy, Sertifikasi & Kurikulum Berlapis (Prerequisite DAG, Gapless Numbering, Hash Chain, Public QR).
- **Fase 136 (baris 10025–10070)**: Talent Pipeline, Headhunter Warranty & Workforce Marketplace (Gale-Shapley Matching, Headhunter Fee Hold/Capture).

---

## 2. Entitas & Model Domain

1. **EduProgram**: Program pelatihan (`id`, `program_code`, `title`, `industry_sector`, `tuition_fee_minor`, `passing_score`, `validity_months`, `status`).
2. **EduPrerequisite**: Graf prasyarat program (`id`, `program_id`, `prerequisite_program_id`, `created_at`). Divalidasi anti-siklus via `DagValidator` (B0″.10).
3. **EduCohort**: Rombongan belajar (`id`, `cohort_code`, `program_id`, `max_capacity`, `enrolled_count`, `start_date`, `end_date`, `status`).
4. **EduCorporateQuota**: Alokasi kuota B2B prabayar (`id`, `corporate_client_id`, `program_id`, `quota_total`, `quota_used`, `unit_price_minor`, `status`).
5. **EduEnrollment**: Pendaftaran siswa (`id`, `enrollment_code`, `student_user_id`, `cohort_id`, `corporate_quota_id`, `amount_paid_minor`, `status`, `idempotency_key`).
6. **EduExamAttempt**: Fakta asesmen append-only (`id`, `attempt_code`, `student_user_id`, `enrollment_id`, `score`, `passing_score`, `is_passed`, `proctor_verification_hash`).
7. **EduCertificate**: Sertifikat kompetensi gapless ber-hash-chain (`id`, `certificate_number`, `program_id`, `student_user_id`, `exam_attempt_id`, `issued_at`, `expires_at`, `prev_hash`, `certificate_hash`, `qr_token`, `status`).
8. **EduJobOpening**: Lowongan kerja berbobot kompetensi (`id`, `opening_code`, `hiring_entity`, `title`, `capacity`, `annual_salary_minor`, `headhunter_fee_bps`, `status`).
9. **EduTalentPreference**: Preferensi kandidat terhadap lowongan (`id`, `student_user_id`, `opening_id`, `rank_order`).
10. **EduPlacement**: Penempatan kerja hasil matching (`id`, `placement_code`, `student_user_id`, `opening_id`, `placed_at`, `guarantee_days`, `status`, `idempotency_key`).
11. **EduHeadhunterFee**: Penahanan & pencairan fee headhunter (`id`, `placement_id`, `fee_minor`, `held_at`, `captured_at`, `released_at`, `status`, `idempotency_key`).

---

## 3. Bagan Akun & Transaksi Finansial

### 3.1 Bagan Akun (Modules/Edu/Ledger/EduAccounts.php)
- `edu:tuition_receivable:IDR` (Aset/Piutang)
- `edu:tuition_revenue:IDR` (Pendapatan Pelatihan Publik)
- `edu:corporate_revenue:IDR` (Pendapatan Pelatihan Korporat B2B)
- `edu:headhunter_receivable:IDR` (Piutang Fee Headhunter)
- `edu:headhunter_revenue:IDR` (Pendapatan Fee Headhunter)
- `escrow:headhunter_guarantee:IDR` (Escrow Jaminan Garansi Kerja)

### 3.2 Tipe Transaksi (Modules/Edu/Ledger/EduTransactionTypes.php)
- `EDU_TUITION_PAYMENT`: Pembayaran tuition siswa individu.
- `EDU_CORPORATE_SETTLEMENT`: Tagihan kuota korporat B2B.
- `EDU_HEADHUNTER_HOLD`: Penahanan fee headhunter ke escrow saat penempatan.
- `EDU_HEADHUNTER_CAPTURE`: Pengakuan pendapatan fee headhunter setelah masa garansi 90 hari.
- `EDU_HEADHUNTER_RELEASE`: Pengembalian fee ke klien bila kandidat resign sebelum masa garansi.

---

## 4. Port & Integrasi Lintas Modul (Z9)

1. **Kontrak `CredentialVerifier` (`Modules/Edu/Contracts/CredentialVerifier.php`)**:
   ```php
   interface CredentialVerifier
   {
       public function hasValid(int|string $userId, string $credentialCode, \Carbon\CarbonImmutable $at): bool;
   }
   ```
2. **Adopsi Nyata di Manufacturing (Z9)**:
   - Modul `Manufacturing` memanggil `CredentialVerifier::hasValid($userId, 'k3_loto', now())` saat `IssueMaintenanceLotoSafetyPermitAction`.
   - Tanpa sertifikat aktif -> ditolak dengan `SafetyPermitUnauthorizedException`.
   - Sertifikat kedaluwarsa -> ditolak.
3. **Adopsi Patch (Z13)**:
   - Disediakan file patch `docs/patches/multilini-3/edu-credential-verifier.patch` untuk modul Mining (K3) dan AutoServe (Mekanik).
4. **Intercompany Billing Port**:
   - Tagihan kuota korporat untuk entitas dalam konglomerasi grup ditagih secara deterministik melalui `IntercompanyBillingPort`.

---

## 5. Bukti untuk Dipindahkan ke PROGRESS

- **Fase 135**:
  - `135.1`: Kurikulum berlapis & validasi graf prasyarat DAG anti-siklus via `DagValidator` (B0″.10).
  - `135.2`: Cohort berkapasitas dan kuota korporat prabayar B2B anti-oversubscription.
  - `135.3`: Asesmen append-only & nomor sertifikat gapless deterministik (`CERT-{YEAR}-{PROG}-{SEQ}`).
  - `135.4`: Rantai hash sertifikat kanonik per program (`CanonicalHashChain` B0″.8).
  - `135.5`: QR verification publik dengan tanda tangan HMAC-SHA256 tanpa membocorkan PII.
- **Fase 136**:
  - `136.1`: Matching talenta Gale-Shapley many-to-one kandidat-mengusul via `StableMatching` (B0″.8).
  - `136.2`: Verifikasi sertifikat kelayakan tanpa blocking pair (`BlockingPairVerifier`).
  - `136.3`: Kontrak `CredentialVerifier` dan adopsi terintegrasi pada `Manufacturing`.
  - `136.4`: Headhunter warranty hold/capture/release 90 hari dengan rekonsiliasi escrow ledger.

---

## 6. Usulan DECISIONS

1. **D-EDU-1: Penomoran Sertifikat Gapless**: Nomor urut sertifikat dikelola dengan kunci atomik per program per tahun kalender menggunakan `DB::transaction` dengan `lockForUpdate` pada tabel sekuensial `edu_certificate_sequences`.
2. **D-EDU-2: Format Token QR Publik**: Format QR adalah `{uuid}.{key_id}.{hmac}` di mana `hmac = hash_hmac('sha256', "{$uuid}:{$key_id}", hash_hmac('sha256', 'edu-cert-v1', config('app.key')))`.
3. **D-EDU-3: Prasyarat Transitif**: Penutupan transitif (transitive closure) dihitung saat enrollment. Siswa wajib lulus seluruh prasyarat langsung dan prasyarat leluhur sebelum dapat mendaftar.

---

## 7. Register Minus Usulan

| No | Komponen | Deskripsi Keterbatasan | Risiko | Kompensasi Kontrol | Target Penyelesaian |
|---|---|---|---|---|---|
| M16-01 | Mining & AutoServe K3 | Adopsi `CredentialVerifier` pada modul Mining & AutoServe disiapkan dalam patch (Z13) dan belum dimerge ke master | Operator tidak tersaring otomatis di modul luar sampai patch diaplikasikan | Manufacturing mengadopsi secara langsung pada runtime aktif; test patch membuktikan kompabilitas | Multi-lini Fase Gabungan |
| M16-02 | Proctor AI Streaming | Streaming proctoring video hanya diverifikasi melalui ringkasan hash signature | Manipulasi stream video sebelum hashing | Verifikasi hash tanda tangan pengawas pada `EduExamAttempt` | Fase Video AI |
| M16-03 | Contingent Timesheet | Manajemen timesheet harian pekerja kontrak di luar MVP modul ini | Payroll buruh harian manual | Integrasi modul HCM & Project pada gelombang berikutnya | Fase 85/97 HCM |
