# Blockers Log

Dokumen ini mencatat kendala atau blocker teknis yang dihadapi selama implementasi, beserta pendekatan yang dicoba dan solusi/alternatif yang diputuskan.

Aturan pemakaian (PROGRESS.md §P6):
- Pelaksana **berhenti** dan menulis entri di sini bila gate gagal karena sebab di luar fase, butuh dependensi baru, butuh keputusan desain yang belum ada di `KONSEP.md`/`DECISIONS.md`, prasyarat belum ✅, atau satu-satunya jalan adalah anti-pola X1–X25.
- Setiap entri juga dicatat di Register Minus fase terkait.
- Entri yang selesai dipindah ke **Riwayat** beserta keputusannya (dan anchor `DECISIONS.md` bila ada). Entri tidak dihapus.

---

## Blocker aktif

| ID | Sejak | Fase / item | Blocker | Yang terblokir | Pemutus |
|---|---|---|---|---|---|
| B-01 | 2026-10-10 | R0.3 | Branch protection `master` belum aktif | Kriteria terima R0.3; status ✅ Fase R0 | Pemilik (pengaturan GitHub) |
| B-02 | 2026-10-11 | R1 (DoR) | Keputusan D1–D8 di DoR Fase R1 | Mulai kode R1 | Pemilik |
| B-03 | 2026-10-11 | R0.3.b | Dua perbaikan `approval_id` yang berbeda arah | Merge branch verifikator ke `feature/fase-r0-mac` (semantik kolom) | Pemilik, atas rekomendasi verifikator |
| B-04 | 2026-10-11 | R0.3.b | Konstanta genesis hash-chain diubah | Verifikasi rantai hash pada data lama | Pemilik (bergantung B-05) |
| B-05 | 2026-10-11 | R0.3.b, R1.8 | Belum diketahui apakah ada database berisi data nyata | Cara mengubah skema: edit migrasi lama vs migrasi baru | Pemilik |
| B-06 | 2026-10-11 | R0.2/R0.3 | CI membuat laporan gate dengan `--fase=R0` tertulis tetap | Laporan gate resmi untuk Fase R1 dan seterusnya | Pelaksana R0 |

### B-01 — Branch protection `master` belum aktif

- **Konteks:** kriteria terima R0.3 berbunyi "PR tidak bisa di-merge bila CI merah". Itu hanya bisa dipenuhi lewat pengaturan GitHub, yang tidak bisa dilakukan agent (token agent sengaja tanpa izin Administration, lihat `KNOWLEDGE.md` K-39).
- **Yang perlu dilakukan pemilik** (*Settings → Branches → Add rule* untuk `master`):
  1. *Require status checks to pass before merging*: centang ketiga job dengan nama persis seperti di run CI terakhir, yaitu `PHP 8.4 × SQLite (Full Gate Suite)`, `PHP 8.4 × MySQL 8 (DB Portability)`, dan `PHP 8.4 × Pest Mutation Testing (min 60%)`.
  2. *Require a pull request before merging*. Matikan *Allow squash merging* dan *Allow rebase merging* di pengaturan repo agar hanya merge commit yang dipakai (hash di blok Bukti harus tetap ada).
  3. *Require review from Code Owners*: lihat catatan di bawah sebelum mencentang.
- **Catatan CODEOWNERS:** GitHub tidak mengizinkan penulis PR menyetujui PR-nya sendiri. Semua commit agent di-push memakai akun `Orls967`, sehingga review code owner tidak akan pernah terpenuhi. Pilihannya:
  - (a) agent memakai akun mesin/bot terpisah, sehingga pemilik bisa menjadi reviewer; atau
  - (b) jangan wajibkan review code owner; pemilik merge dengan hak admin setelah bagian Verifikasi di laporan gate terisi.

  Rekomendasi: (b) sekarang, (a) bila agent dipakai rutin.
- **Bukti selesai:** tangkapan layar pengaturan atau `gh api repos/Orls967/superweb/branches/master/protection` (perlu token pemilik), dicatat di Bukti R0.3.

### B-02 — Keputusan DoR Fase R1 (D1–D8)

- **Konteks:** DoR Fase R1 sudah ditulis di `PROGRESS.md` (di bawah judul FASE R1) dengan "Disetujui pemilik: [ ]". Delapan keputusan menentukan desain tabel, migrasi, dan urutan PR.
- **Ringkas:**
  - D1: penyimpanan aset non-IDR (integer unit terkecil vs `DECIMAL(36,18)` dengan MySQL/PostgreSQL wajib).
  - D2: registry `type` transaksi (enum pusat vs per modul).
  - D3: konversi data `decimal`→`bigInteger` (bergantung B-05).
  - D4: izin mengubah test lama yang mengunci tanda salah.
  - D5: pemecahan sub-item dan 3 PR.
  - D6: tempat chart of accounts untuk service Integration.
  - D7: refund lewat event atau panggilan langsung.
  - D8: jadwal rekonsiliasi.
- **Yang terblokir:** seluruh kode R1. Prasyarat lain: R0 ✅.

### B-03 — Konvensi `approval_id`: id approval atau UUID approval?

- **Fakta:** enam service menulis `$approval->uuid` ke kolom `approval_id` bertipe `bigint` dengan cast `'integer'`, yaitu:
  - `SupplierService` (kualifikasi supplier);
  - `ProcurementService` (requisisi, award tender);
  - `ReceivingService` (batch pembayaran);
  - `RevaluationService` (revaluasi, disposal).

  Nilainya tidak merujuk approval mana pun, dan MySQL strict menolak seeder (`prc_requisitions`). Modul lain (Pricing, Agency, Wms, Distribution) dan `ApprovalEngineService` menyimpan `$approval->id` lalu membacanya dengan `approve((int) $model->approval_id)`.
- **Dua perbaikan yang sudah ada:**
  - Branch `tools/r0-mysql-ddl-replay` (`ad6245d`): service menulis `$approval->id`. Lima test diperkuat dengan assertion "approval_id merujuk baris `core_approvals`" (merah sebelum perbaikan). Kolom tetap `bigint`.
  - Branch `feature/fase-r0-mac` (`5b2828a`): tujuh kolom `approval_id` di Procurement, Supplier, dan Asset diubah menjadi `string(64)` dengan cast `'string'`, sehingga UUID muat. Test hanya memeriksa tipe kolom.
- **Bila keduanya di-merge apa adanya:** kolom `string` berisi id angka sebagai teks. Kode berjalan, tetapi konvensinya campur dan tidak tercatat di mana pun.
- **Rekomendasi verifikator:** satu konvensi untuk seluruh repo, `approval_id` = `core_approvals.id` (bigint). Alasannya:
  - sama dengan modul lain dan `ApprovalEngineService`;
  - bisa diberi FK ke `core_approvals.id`;
  - test perilaku sudah ada.

  Bila dipilih, perubahan tipe kolom di `5b2828a` dibatalkan dengan commit baru (bukan rewrite). Bila pemilik memilih UUID, semua modul (termasuk Pricing/Agency/Wms/Distribution) wajib ikut, dan pembacaan `(int)` diganti pencarian per `uuid`.
- **Keputusan dicatat:** `DECISIONS.md` (entri baru "Konvensi approval_id") + Bukti R0.3.b.

### B-04 — Konstanta genesis hash-chain diubah

- **Fakta:** penanda genesis `GENESIS_AST_…` (73 karakter) dan `GENESIS_CTR_…` (72 karakter) tidak muat di kolom `prev_hash varchar(64)` di MySQL. Dua perbaikan berbeda arah:
  - `feature/fase-r0-mac` (`0a21d42`, `f6fbefd`): **mengubah konstanta** genesis Asset, Contract, dan Manufacturing menjadi 64 karakter.
  - `tools/r0-mysql-ddl-replay` (`1d74ed1`): **memperlebar kolom** `prev_hash` menjadi 80 karakter, konstanta tetap.
- **Risiko mengubah konstanta:** verifikasi rantai membandingkan `prev_hash` event pertama dengan konstanta genesis. Rantai yang sudah tersimpan dengan genesis lama akan dilaporkan rusak oleh audit setelah upgrade.
- **Pilihan:**
  - (a) pertahankan konstanta baru dan pastikan tidak ada data lama (lihat B-05);
  - (b) kembalikan konstanta lama dan pakai kolom 80 karakter;
  - (c) konstanta baru, tetapi verifikasi menerima genesis lama maupun baru (dengan test).
- **Rekomendasi:** (a) bila B-05 menjawab "tidak ada data nyata". Selain itu (c).

### B-05 — Apakah ada database berisi data nyata?

- **Konteks:** `.env.example` dan `RUNBOOK.md` §4 (backup `database/database.sqlite`) mengarah ke SQLite sebagai database berjalan. Jawaban pertanyaan ini menentukan:
  - apakah perbaikan skema boleh mengedit migrasi lama (seperti yang dilakukan R0.3.b), atau wajib migrasi baru dengan konversi data;
  - pilihan B-04;
  - D1 dan D3 di DoR R1 (konversi `decimal`→`bigInteger`).
- **Pertanyaan untuk pemilik:** adakah instance staging/produksi (SQLite, MySQL, atau lainnya) yang datanya harus dipertahankan? Bila ada: di mana, dan sejak versi berapa.

### B-06 — Laporan gate di CI selalu untuk Fase R0

- **Fakta:** job `PHP 8.4 × SQLite (Full Gate Suite)` menjalankan `php artisan gate:report --fase=R0` dan mengunggah artefak bernama `gate-report-fase-r0`. Fase berikutnya tidak bisa mendapat laporan resmi dari CI tanpa mengubah workflow.
- **Usulan:** fase diambil dari input workflow (`workflow_dispatch`) atau dari nama branch (`feature/fase-<id>-…`). Laporan dibuat untuk fase itu, dan nama artefak mengikuti fase. `CiWorkflowContractTest` diperbarui agar `--fase` tidak boleh tertulis tetap.
- **Pemutus:** pelaksana R0 (tooling); dicatat di Register Minus R0 bila belum selesai saat R0 ditutup.

---

## Riwayat

| ID | Periode | Blocker | Penyelesaian |
|---|---|---|---|
| B-00 | 2026-10-10 s.d. 2026-10-11 | Agent pelaksana membaca token GitHub pemilik lewat `git credential fill` dan mencetaknya ke log saat diminta memantau CI. | Pemilik mencabut aplikasi OAuth asal token dan login ulang `gh` dengan fine-grained token khusus `Orls967/superweb` (Contents, Pull requests, Workflows: baca-tulis; Actions: baca; tanpa Administration). Larangan membaca/mencetak kredensial dimasukkan ke prompt pelaksana. Lihat `KNOWLEDGE.md` K-39 dan `RUNBOOK.md` §7. |
