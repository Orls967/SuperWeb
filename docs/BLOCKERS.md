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
| B-05 | 2026-10-11 | R0.3.b, R1.8 | Belum diketahui apakah ada database berisi data nyata | Cara mengubah skema: edit migrasi lama vs migrasi baru | Pemilik |
| B-07 | 2026-10-13 | R0.3, R0.12 | CI PR #8 Job Pest Mutation Testing gagal (exit 2, run 38090351376) | Status hijau CI PR #8 & penyelesaian R0 | Pelaksana / konfig CI |

### B-07 — CI PR #8 Pest Mutation Testing gagal (run 38090351376)

- **Konteks:** Eksekusi workflow CI pada Draft PR #8 (`PHP 8.4 × Pest Mutation Testing (min 60%)`, commit `3493ed1`) mengalami kegagalan setelah 1 jam 50 menit dengan `Fatal error: Premature end of PHP process ... exit code 2` saat menjalankan mutasi pada `ModuleBoundariesTest`.
- **Dampak:** CI pull request tidak berstatus hijau, menghalangi kriteria terima R0.3 dan verifikasi penuh R0.
- **Rekomendasi tindak lanjut:** Pelaksana perlu mengisolasi cakupan runner mutasi per modul/subset kelas yang relevan atau meninjau alokasi memori/timeout di GitHub Actions.

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

### B-05 — Apakah ada database berisi data nyata?

- **Konteks:** `.env.example` dan `RUNBOOK.md` §4 (backup `database/database.sqlite`) mengarah ke SQLite sebagai database berjalan. Jawaban pertanyaan ini menentukan:
  - apakah perbaikan skema boleh mengedit migrasi lama (seperti yang dilakukan R0.3.b), atau wajib migrasi baru dengan konversi data;
  - pilihan B-04;
  - D1 dan D3 di DoR R1 (konversi `decimal`→`bigInteger`).
- **Pertanyaan untuk pemilik:** adakah instance staging/produksi (SQLite, MySQL, atau lainnya) yang datanya harus dipertahankan? Bila ada: di mana, dan sejak versi berapa.

---

## Riwayat

| ID | Periode | Blocker | Penyelesaian |
|---|---|---|---|
| B-00 | 2026-10-10 s.d. 2026-10-11 | Agent pelaksana membaca token GitHub pemilik lewat `git credential fill` dan mencetaknya ke log saat diminta memantau CI. | Pemilik mencabut aplikasi OAuth asal token dan login ulang `gh` dengan fine-grained token khusus `Orls967/superweb` (Contents, Pull requests, Workflows: baca-tulis; Actions: baca; tanpa Administration). Larangan membaca/mencetak kredensial dimasukkan ke prompt pelaksana. Lihat `KNOWLEDGE.md` K-39 dan `RUNBOOK.md` §7. |
| B-03 | 2026-10-11 | Dua perbaikan `approval_id` yang berbeda arah (`core_approvals.id` vs UUID) | Sesuai keputusan pemilik K-B03, konvensi `approval_id` ditetapkan menyimpan `core_approvals.id` (`bigint` FK). Tujuh kolom di Procurement, Supplier, Asset serta Contract disatukan ke `foreignId`, model cast kembali `integer`, `ContractService` menulis `$approval->id`, dan dipasang pagar permanen `ApprovalIdConventionTest`. Lihat `DECISIONS.md`. |
| B-04 | 2026-10-11 | Konstanta genesis hash-chain diubah (Asset, Contract, Manufacturing) | Sesuai keputusan pemilik K-B04 (opsi c), verifikasi rantai menerima genesis baru dan lama (`LEGACY_GENESIS_*`). Kolom `prev_hash` dipastikan 80 karakter. Teruji via `DualGenesisCompatibilityTest`. Lihat `DECISIONS.md`. |
| B-06 | 2026-10-11 | Laporan gate di CI selalu untuk Fase R0 | Ditambahkan langkah resolusi otomatis fase dari nama branch (regex `feature/fase-([a-zA-Z0-9]+)`) serta input `workflow_dispatch` di `.github/workflows/ci.yml`. Nama artefak dinamis `gate-report-fase-${{ steps.resolve-phase.outputs.fase }}`. Dipasang pagar kontrak `CiWorkflowContractTest`. Terbukti pada commit `92385b0` dan eksekusi CI push #38089526607. |
