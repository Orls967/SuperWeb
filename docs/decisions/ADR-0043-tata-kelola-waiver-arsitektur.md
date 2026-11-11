# ADR-0043: Tata Kelola State Machine Boundary Violation & Siklus Hidup Waiver (W8-D43)

## Status
Diterima (Accepted)

## Konteks
Pembersihan utang teknis dan pemisahan batas arsitektur pada sistem besar tidak dapat diselesaikan sekaligus dalam satu langkah drastis tanpa risiko regresi. Di sisi lain, membiarkan pengecualian tanpa batas waktu (*indefinite waivers*) membuat batas arsitektur runtuh perlahan. Diperlukan tata kelola siklus hidup pelanggaran batas modular yang formal, transparan, terikat waktu, dan diawasi oleh mesin keadaan (*state machine*) serta mesin persetujuan (*ApprovalEngine*).

## Keputusan
1. **State Machine 7 Status (`BoundaryViolationStateMachine`)**:
   Pelanggaran arsitektur (A1, A2, A3) dimodelkan sebagai entitas state machine dengan 7 status terdefinisi:
   - `detected`: Pelanggaran baru terdeteksi oleh scanner statis, belum ditugaskan.
   - `triaged`: Kategori pelanggaran dan modul pemilik telah ditetapkan.
   - `contract_designed`: Spesifikasi kontrak pengganti telah dirancang dan memiliki rujukan ADR.
   - `migrating`: Proses refaktor kode sedang berlangsung di branch kerja.
   - `verified`: Tanda tangan pelanggaran pada baseline telah dihapus dan unit test lulus hijau.
   - `closed`: Status terminal; pemindaian statis `arch:scan` bersih pada commit tercatat.
   - `waived`: Pengecualian sementara dengan dispensasi waktu terbatas.

2. **Tata Kelola Waiver Bertenggat Waktu ($\le 90$ Hari)**:
   - Transisi ke status `waived` wajib memenuhi guard: rujukan anchor dokumen arsitektur `docs/DECISIONS.md#...`, justifikasi teknis tertulis, identitas pejabat arsitektur yang menyetujui, dan tanggal kedaluwarsa maksimal 90 hari sejak tanggal persetujuan.
   - Persetujuan waiver memanfaatkan `ApprovalEngine` dengan pemisahan peran (*separation of duties*).
   - Menjelang kedaluwarsa (7 hari sebelum batas), sistem otomatis menerbitkan tugas di Kotak Masuk "Tugas Saya" via `TaskInboxService::createTask()`.

3. **Penyusutan Otomatis & Pembalikan Status**:
   Command terjadwal harian `php artisan boundary:expire-waivers` memeriksa seluruh waiver aktif. Setiap waiver yang melewati `expires_at` otomatis dibalikkan ke status `triaged`, mencabut dispensasi, dan menerbitkan event Outbox `core.boundary.waiver_expired.v1`.

4. **Batas Kontribusi Penurunan Baseline**:
   Maksimal 50% dari total penurunan baseline $A1+A2$ boleh disumbang oleh waiver ber-anchor. Sisanya wajib berasal dari eliminasi pelanggaran nyata melalui implementasi kontrak atau refaktor migrasi data.

## Konsekuensi
- Menjamin setiap pengecualian arsitektur memiliki batas kedaluwarsa yang tegas dan akuntabilitas kepemilikan.
- Menghilangkan *silent architecture drift* karena pelanggaran yang kedaluwarsa langsung tampak pada dasbor dan audit.
- Membantu tim memprioritaskan kontrak baru secara terencana sebelum tenggat waiver habis.

