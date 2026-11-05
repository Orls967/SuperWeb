# Design Specification — P5.4 Lini 20: Edu Campus (Fase 166)

## 1. Domain Models & Skema Database
Prefiks tabel: `edu_campus_`
- `edu_campus_institutions`: Data kampus/fakultas/sekolah (`institution_code`, `name`, `type`, `is_active`).
- `edu_campus_terms`: Semester/periode akademik (`term_code`, `name`, `academic_year`, `starts_at`, `ends_at`, `is_active`).
- `edu_campus_courses`: Mata kuliah (`course_code`, `name`, `credits`, `prerequisite_course_code`, `passing_grade`).
- `edu_campus_classes`: Kelas kuliah & jadwal (`class_code`, `course_id`, `term_id`, `day_of_week`, `start_minute`, `end_minute`, `room_id`, `lecturer_name`, `max_capacity`, `enrolled_count`).
- `edu_campus_krs_enrollments`: Rencana Studi mahasiswa (`student_user_id`, `class_id`, `status`).
- `edu_campus_transcripts`: Transkrip nilai berantai hash (`student_user_id`, `course_id`, `term_id`, `grade_point`, `letter_grade`, `prev_hash`, `certificate_hash`, `is_locked`).
- `edu_campus_tuition_invoices`: Tagihan UKT semesteran (`invoice_code`, `student_user_id`, `term_id`, `gross_amount_idr`, `scholarship_deduction_idr`, `net_payable_idr`, `status`, `idempotency_key`).

## 2. Shared Algorithms Integration
- **`Modules\Shared\Domain\Numerics\IntervalIndex`**: Validasi bentrok slot jadwal (hari dan menit ke-[start, end)).
- **`Modules\Shared\Domain\Numerics\CanonicalHashChain`**: Pembuatan hash chain sertifikat/transkrip nilai mahasiswa.
- **`Modules\Shared\Domain\Math\HamiltonApportionment`**: Pembagian cicilan UKT bebas pembulatan pincang.

## 3. Akuntansi & Double-Entry Ledger
- Piutang UKT: `edu:tuition_receivable:IDR` (Debit `+$netPayable`)
- Pendapatan UKT: `edu:tuition_revenue:IDR` (Kredit `-$netPayable`)

## 4. Acceptance Tests & Audit
- `tests/Acceptance/Edu/EduCampusAcceptanceTest.php`:
  1. Kontrol akses rute (admin, academic_admin, lecturer, student).
  2. Alur KRS end-to-end: validasi prasyarat, IPK SKS limit, deteksi bentrok `IntervalIndex`.
  3. Pembukuan invoice UKT dengan bounded beasiswa & amortisasi Hamilton.
  4. Penguncian transkrip nilai append-only & validasi hash-chain `CanonicalHashChain`.
  5. Audit dua sumber `edu:audit` (clean exit 0 vs fixture exit nonzero).

