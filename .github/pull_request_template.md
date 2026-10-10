## Ringkasan Perubahan

- **Fase:** Fase N — <Judul Fase>
- **Status Penyerahan:** 🔵 MENUNGGU VERIFIKASI (Pelaksana berhenti di 🔵, dilarang memberi ✅ sendiri — P7, X20)
- **Branch:** `feature/<nama-branch>`
- **Commit Terakhir:** `<hash>`
- **Laporan Gate:** `docs/gates/fase-<N>.md` (dihasilkan oleh `php artisan gate:report --fase=<N>`)

---

## Checklist Pelaksana: Vertical Slice (V1–V12)

Item fitur wajib memenuhi seluruh kriteria V1–V12 berikut sebelum PR diajukan (item dokumen/riset cukup V10–V12):

- [ ] **V1** Modul pemilik & prefiks tabel sesuai registry `docs/KONSEP.md` §A1 (bukan `Integration`, bukan prefiks modul lain).
- [ ] **V2** Migrasi dengan FK + index; model `$fillable` eksplisit; `declare(strict_types=1)` pada semua file PHP baru/diubah.
- [ ] **V3** Action/service: `DB::transaction` + `lockForUpdate` + guard state machine + idempotency key deterministik.
- [ ] **V4** Uang: akun terdaftar di `{M}Accounts` + seeder; posting via helper `Posting`; tanda debit/kredit sesuai §A2.1; tanpa float/decimal IDR.
- [ ] **V5** Jalan masuk: rute + controller tipis + view Blade + menu + role/policy (atau command/listener terdaftar bila headless + alasan di DECISIONS).
- [ ] **V6** Lintas modul: event di katalog + produsen + **konsumen nyata** + test end-to-end; atau Contract milik modul pemilik.
- [ ] **V7** Audit dua-sumber + fixture korupsi (bila memegang uang/stok/dokumen bernilai).
- [ ] **V8** Test (a)–(e) sesuai `docs/KONSEP.md` §A8, minimal satu request HTTP / command nyata.
- [ ] **V9** Seeder T1: fitur terlihat dan siap pakai setelah `php artisan migrate:fresh --seed`.
- [ ] **V10** Gate penuh hijau (`composer gate`); `arch:scan` & baseline tidak naik; mutation score ≥ ambang (R0.12).
- [ ] **V11** Blok `Bukti:` per item di `docs/PROGRESS.md` terisi lengkap dan valid (`ProgressIntegrityTest` hijau) + Register Minus terisi.
- [ ] **V12** Tidak menggunakan jalan pintas terlarang X1–X25.

---

## Checklist Verifikator Independen: Verifikasi Silang (C1–C14)

*(Bagian ini diverifikasi dan diisi oleh verifikator independen / sesi agent terpisah sebelum status fase diubah ke ✅ atau 🔁)*:

- [ ] **C1** `ProgressIntegrityTest` hijau untuk fase ini (semua blok `Bukti:` valid).
- [ ] **C2** Jalankan sendiri `composer gate`, `php artisan arch:scan`, `php artisan bank:reconcile`, audit modul terkait — jangan percaya laporan salin-tempel.
- [ ] **C3** Untuk setiap item: baca teks asli item lalu file bukti; implementasi sesuai **teks asli** item (bukan versi yang dipersempit); tidak ada status sukses tanpa aksi (X9).
- [ ] **C4** Jalan masuk: rute bukti diakses sebagai role berhak (bukan 403) dan tak berhak (403); menu muncul.
- [ ] **C5** Uang: akun di `{M}Accounts` & seeder; arah posting sesuai `KONSEP.md` §A2.1; panggil aksi 2× → satu efek ledger **dan** satu efek state.
- [ ] **C6** Audit: jalankan pada seed bersih (exit 0), terapkan fixture korupsi merusak satu baris data sumber (exit ≠ 0).
- [ ] **C7** Test: tidak ada X15/X17/X23; komentari satu baris inti implementasi → test bukti harus gagal (atau skor mutation ≥ ambang).
- [ ] **C8** `arch:scan` & semua baseline tidak naik; tidak ada file baru di `modules/Integration` di luar allowlist.
- [ ] **C9** Lingkup: teks item tidak berubah kecuali dengan `⬇️ diturunkan` + DECISIONS + persetujuan pemilik (P10).
- [ ] **C10** `php artisan migrate:fresh --seed` lalu fitur terlihat dan bisa dipakai sebagai role terkait (seeder T1).
- [ ] **C11** Register Minus lengkap & jujur; verifikator menambahkan minus bila ditemukan. Ada minus P0/P1 terbuka → wajib 🔁.
- [ ] **C12** Dokumen: bagian otomatis `CODEBASE.md` sinkron; keputusan baru tercatat di `docs/DECISIONS.md`.
- [ ] **C13** Riwayat commit: satu commit per item (`feat(fase-N): N.x ...`); tidak ada item fase lain yang dicentang.
- [ ] **C14** Red flags P12 diperiksa; setiap red flag punya penjelasan yang dapat diterima.

---

## Hasil Verifikasi Silang

- **Tanggal:** 
- **Verifikator:** 
- **Keputusan:** `[ ] ✅ DISETUJUI` / `[ ] 🔁 PERBAIKI (daftar alasan di Register Minus)`
