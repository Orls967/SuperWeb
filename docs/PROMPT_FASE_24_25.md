# PROMPT: Selesaikan Fase 24–25 + Pembersihan/Optimasi (SuperWeb)

Salin seluruh isi di bawah garis ini sebagai prompt ke sesi baru.

---

## Peran & konteks

Kamu engineer senior yang melanjutkan **SuperWeb** (Laravel 13, modular monolith `modules/*`, namespace `Modules\`, Pest 4, SQLite, Blade+Tailwind+Alpine, Vite). Sistem: ledger double-entry, hash-chain (Vehicle Passport & tracking event), pola Action/Service, arch test batas modul (controller tidak boleh memakai DB facade).

Status: Fase 1–23 selesai dan sudah di `master` (PR #1 Fase 22, PR #2 Fase 23). Sisa: **Fase 24 (24.1–24.7)** dan **Fase 25 (25.1–25.9)** di `docs/PROGRESS.md`, ditambah **Tahap 0 pembersihan** (di bawah). Baca dulu: `CLAUDE.md`, `docs/PROGRESS.md`, `docs/AUDIT.md`, `docs/DECISIONS.md`, `docs/ARCHITECTURE.md`, `docs/RUNBOOK.md`.

Catatan lingkungan: `composer.lock` butuh PHP ≥ 8.4, sandbox PHP 8.3. Gunakan `platform.php 8.3.6` hanya sementara, **jangan commit perubahan composer.lock**. Brick Math v1: pakai `RoundingMode::HalfUp`. Gunakan `event(new ...)`, bukan `::dispatch` pada event tanpa trait Dispatchable.

## Aturan kerja (wajib)

1. Branch kerja: `feature/logistics-fase-24-25` dari `master` terbaru. **Jangan memakai kata "claude" di nama branch.** Jangan push ke branch lain.
2. Kerjakan **berurutan**: Tahap 0 → Fase 24 → Fase 25. Satu commit bermakna per sub-tugas (pesan commit deskriptif berbahasa Indonesia/Inggris konsisten dengan riwayat).
3. Setiap sub-tugas wajib diuji dengan test (a) happy path, (b) validasi/otorisasi, (c) idempotensi/retry, (d) invarian ledger (saldo seimbang, tidak ada selisih), (e) edge case. Tidak boleh ada test yang di-skip, dinonaktifkan, atau dilonggarkan demi hijau.
4. Setelah tiap fase: jalankan quality gate penuh (lihat bawah), perbarui `docs/PROGRESS.md` (centang), `docs/AUDIT.md` (gate fase), `docs/DECISIONS.md` (keputusan + alasan).
5. Jangan menambah dependensi tanpa alasan tertulis di DECISIONS. Ikuti idiom, penamaan, dan kepadatan komentar kode sekitar.
6. Jangan menyentuh/merusak data uang: semua uang `Brick\Money`/integer IDR, tanpa float. Setiap posting ledger idempoten via idempotency key.
7. Buat PR ke `master` hanya setelah semua gate hijau, lalu merge **hanya jika diminta**. Berhenti setelah Fase 25.9; laporkan ringkas.
8. Bila ada keputusan desain yang ambigu: ambil default yang konservatif, catat di DECISIONS.md, lanjut (jangan berhenti bertanya).

## Quality gate (dijalankan di akhir Tahap 0, Fase 24, Fase 25)

- `vendor/bin/pest` penuh: 0 gagal, 0 skipped
- `vendor/bin/pint --test` bersih
- `npm run build` (Vite) sukses
- Arch test (batas modul) lulus
- `php artisan bank:reconcile` → 0 selisih
- `php artisan lgx:audit-billing` → exit 0, 0 selisih
- `php artisan verify-custody` dan `capacity-check` hijau (dengan data nyata, bukan vacuous)
- `php artisan super:health-check` hijau (termasuk pilar Logistik setelah 24.6)

---

## TAHAP 0 — Pembersihan & optimasi dari fase sebelumnya

Lakukan audit dulu (baca kode, jalankan test, `grep`), catat temuan di `docs/AUDIT.md` bagian "Tahap 0", baru perbaiki. Setiap perbaikan perlu test regresi. Checklist minimum:

**A. Kebenaran & robustnes**
- Telusuri semua Action di `modules/Logistics/Application/Actions` dan pastikan: semua mutasi multi-tabel berada dalam `DB::transaction`, posting ledger memakai idempotency key deterministik, tidak ada side-effect (event/notifikasi) yang terkirim bila transaksi rollback (gunakan `afterCommit`).
- Event/listener (`ShipmentDelivered` → `RecognizeFreightRevenueOnDelivery`): pastikan idempoten bila dipicu dua kali, dan kegagalan listener tidak diam-diam menelan error (log + retry).
- State machine `ShipmentStatus`/`FleetStatus`: cari transisi yang bisa dilompati lewat jalur selain Action (mass-assignment, query langsung). Tutup dengan guard di model/Action.
- Guard konkurensi: `lockForUpdate` pada alokasi truk/driver/kapasitas jadwal, klaim aktif (`active_key`), COD collection, dan pembayaran carrier. Tambah test race (dua eksekusi berurutan dengan state sama).
- Re-verifikasi perhitungan uang: pembulatan HalfUp konsisten (D&D, bea cukai BM/PPN/PPh22, fee COD, klaim), tidak ada float, tidak ada pembagian yang membuat selisih 1 rupiah antar sisi debit/kredit.
- Validasi Form Request untuk semua endpoint logistik; pastikan policy/gate dipasang di setiap rute (bukan hanya di menu).

**B. Performa**
- Cari N+1 di semua controller/view logistik (dispatch board, driver tasks, exceptions, cod, carriers, claims, demurrage, customs, fuel). Pasang `with()`/eager loading, `withCount`, dan pagination. Tambahkan test jumlah query (`DB::enableQueryLog` / helper query budget).
- Periksa indeks migrasi: kolom yang dipakai `where/orderBy/join` (status, tanggal, FK, `tracking_number`, `active_key`) harus berindeks; tambah migrasi baru (jangan edit migrasi lama yang sudah merged) bila kurang.
- Command terjadwal (`lgx:detect-late`, `lgx:settle-cod`, `lgx:pay-carriers`, `lgx:accrue-dd`, `lgx:audit-billing`): gunakan `chunkById`/`cursor`, hindari memuat seluruh tabel ke memori.
- `BillingAuditor` (15 pemeriksaan): ubah ke query agregat berbasis SQL di mana mungkin, bukan loop PHP per baris.

**C. Kode & struktur**
- Hilangkan duplikasi (mis. pola guard di `AbstractDriverTaskAction`, kalkulasi fee/pajak, builder posting di `LogisticsLedger`); ekstrak hanya bila ≥ 3 pemakaian.
- Hapus kode mati, import tak terpakai, konstanta tak terpakai, config tak terpakai. Pastikan tidak ada magic number: pindahkan ke `config` atau enum.
- Konsistensi penamaan exception/enum/event, pesan error berbahasa seragam, `declare(strict_types=1)` konsisten dengan modul lain.
- Pastikan batas modul: Logistics tidak mengimpor internal modul lain selain via contract (`Ledger`, `ShipmentBooking`, dsb). Perketat arch test bila ada celah.
- Seeder: pastikan `LogisticsSeeder`/`LogisticsFinanceSeeder` idempoten (aman dijalankan dua kali) dan deterministik.

**D. Keamanan**
- IDOR: customer hanya boleh melihat shipment/invoice/klaim miliknya; driver hanya tugas miliknya; verifikasi dengan test per role.
- OTP pengiriman: hash, rate limit, kedaluwarsa, tidak bocor di log/response. Upload bukti (tanda tangan/foto): validasi mime/ukuran, tidak path traversal.
- Four-eyes approval (klaim, pembayaran carrier): pembuat ≠ penyetuju, ditegakkan di server.
- Pastikan tidak ada secret/PII di log, dan mass assignment (`$fillable`/`$guarded`) aman.

**E. Dokumentasi & test**
- Tutup celah yang sudah diketahui: README belum memuat Logistics dan command `lgx:*` (kerjakan penuh di 25.8); `RouteSmokeTest`/`SecurityTest` belum mencakup rute logistik (25.6).
- Catat semua temuan + keputusan di AUDIT.md dan DECISIONS.md. Output Tahap 0: satu commit per kategori (A–E) dan gate hijau sebelum lanjut.

---

## FASE 24 — Integrasi lintas modul

Prinsip: integrasi **hanya lewat contract/event**, bukan import langsung antar-modul. Semua event ditangani `afterCommit`, idempoten (kunci per sumber, mis. `order_id`), dan gagal secara aman (retry + log, tidak menggagalkan transaksi sumber).

- **24.1 Store → Logistik**: event order dibayar → buat shipment otomatis via contract `ShipmentBooking`; ongkir Store dicatat ke `unearned_freight`; nomor resi tampil di detail order. Test: order dibayar dua kali → satu shipment; refund/batal order → shipment dibatalkan + ongkir dibalik; ledger seimbang.
- **24.2 Pengiriman mobil** (Store/AutoDex/HODL-to-Drive): shipment FTL car carrier; event `delivered_by_carrier` masuk Vehicle Passport (hash-chain harus tetap valid, `verify-custody` hijau). Test: urutan hash, tidak bisa menyisipkan event di tengah.
- **24.3 Perawatan armada → AutoServe**: odometer dari trip melewati `service_interval_m` → event `FleetServiceDue` → booking bengkel via contract; status armada `Maintenance` (tidak bisa dialokasikan dispatch); selesai servis → kembali `Available`. Test: tidak double-booking, truk Maintenance ditolak `AssignScheduleResourcesAction`.
- **24.4 Resto → Logistik (cold-chain)**: replenishment dapur pusat CK-01 → shipment reefer; tabel `lgx_temperature_readings`; excursion alert (suhu di luar rentang → `ShipmentException` severity tinggi); penerimaan stok via Inventory (idempoten, qty sesuai). Test: excursion memicu exception sekali, stok bertambah sekali.
- **24.5 Mall → Logistik (loading dock)**: `lgx_dock_appointments` di Duta Mall; slot time-lock tanpa overlap (unique/constraint + lock, bukan hanya cek aplikasi); portal tenant booking dock; satpam check-in/out. Test: dua booking bersamaan slot sama → satu berhasil; check-out tanpa check-in ditolak.
- **24.6 Finance & observabilitas**: pendapatan Logistik masuk Group Dashboard P&L **tanpa menaikkan query budget** dashboard (agregasi, cache, atau tabel ringkas; buktikan dengan test jumlah query sebelum/sesudah); tambah pilar Logistik di `super:health-check` (rekonsiliasi billing, custody chain, antrean webhook/dead-letter, shipment stuck).
- **24.7 Quality gate Fase 24**: gate penuh + test integrasi end-to-end per alur (Store→Logistik→Delivered→Revenue recognized→Reconcile 0 selisih). Update PROGRESS/AUDIT/DECISIONS. Commit & push.

---

## FASE 25 — Skala, API, operasional, dokumentasi

- **25.1 `LogisticsLargeSeeder`**: ≥ 200.000 shipment, ≥ 2.000.000 tracking event, ≥ 5.000 kontainer, ≥ 20 kapal, ≥ 300 truk, 12 bulan riwayat, B2B postpaid ledger. Syarat: bulk insert chunk (≥ 1000 baris/insert), hash-chain tracking tetap valid, ledger seimbang, deterministik (seed RNG), bisa di-resume/idempoten, **mencetak benchmark waktu** per tahap. Opsi `--scale` untuk smoke-run kecil di CI; run penuh dilakukan manual dan hasilnya dicatat di docs.
- **25.2 Anggaran kinerja (`QueryBudgetTest`)**: lookup resi ≤ 3 query dengan p95 < 50 ms; dispatcher ≤ 10 query; control tower ≤ 12 query; `lgx:accrue-dd` < 30 s pada dataset besar. Tambahkan dokumentasi `EXPLAIN` (SQLite `EXPLAIN QUERY PLAN`) untuk query kritis di `docs/` dan tambah indeks yang kurang lewat migrasi baru.
- **25.3 API v1 (Sanctum)**: endpoint `quotes`, `shipments` (header `Idempotency-Key` wajib untuk POST; request ulang → respons yang sama, bukan duplikat), `tracking`; token abilities per scope (`quotes:read`, `shipments:write`, `tracking:read`); rate limit per token; API Resource + versioning `/api/v1`; error format konsisten (JSON problem); `docs/API.md` lengkap dengan contoh curl. Test: tanpa ability → 403, tanpa token → 401, replay idempoten, rate limit 429, tidak bisa akses data pelanggan lain.
- **25.4 Webhook outbox**: `lgx_webhook_endpoints` + `lgx_webhook_deliveries` (outbox ditulis dalam transaksi yang sama dengan event bisnis); tanda tangan HMAC-SHA256 (header timestamp + signature, tolak replay lama); exponential backoff, maksimum 8 retry, lalu dead-letter; replay manual oleh admin; secret endpoint disimpan terenkripsi; SSRF guard (tolak host privat/loopback). Test: backoff schedule, dead-letter setelah retry ke-8, replay, verifikasi signature.
- **25.5 Queue & scheduler**: job idempoten dengan `ShouldBeUnique` (kunci jelas) + `ShouldQueue`; seluruh command lgx terdaftar di `routes/console.php` dengan `withoutOverlapping`/`onOneServer`; `docs/RUNBOOK.md` diperbarui (jadwal, cara re-run, penanganan dead-letter, rollback, pemulihan hash-chain, rekonsiliasi). Test: job dijalankan dua kali tidak menggandakan efek.
- **25.6 Matriks otorisasi**: `SecurityTest` & `RouteSmokeTest` mencakup **seluruh rute logistik** × seluruh role (guest, customer, driver, dispatcher, logistics_admin, role modul lain). Buat matriks data-driven (dataset Pest) yang otomatis gagal bila ada rute logistik baru tanpa entri matriks. Verifikasi 200/302/403/404 yang diharapkan.
- **25.7 Control Tower** (`logistics_admin`): KPI OTIF, grafik status, utilisasi armada, dwell time, COD outstanding, margin per lane; semua dari query agregat/cached, ≤ 12 query, aman pada dataset besar; tampilan konsisten dengan UI yang ada (Blade+Tailwind+Alpine), responsif.
- **25.8 Dokumentasi final**: `ARCHITECTURE.md` (diagram modul, alur uang, alur hash-chain, event/contract), `RUNBOOK.md`, `README.md` (setup, command `lgx:*`, API, testing, seeding besar), `DECISIONS.md` lengkap, `API.md`. Semua contoh perintah harus benar-benar dijalankan/diverifikasi.
- **25.9 Quality gate final**: seluruh test + large seeder (skala penuh bila sumber daya cukup, jika tidak skala dikurangi dan dicatat jujur di AUDIT) + semua gate hijau. Sertakan tabel hasil gate dan benchmark di AUDIT.md.

---

## Definition of Done

- Semua sub-tugas Tahap 0, 24.1–24.7, 25.1–25.9 selesai & tercentang di PROGRESS.md.
- Quality gate hijau; angka (jumlah test, assertion, waktu seeder, query budget) dilaporkan **apa adanya**; jika ada yang tidak bisa dijalankan di sandbox, katakan terang-terangan dan apa yang diverifikasi sebagai gantinya.
- Tidak ada perubahan `composer.lock`, tidak ada test dinonaktifkan, tidak ada float untuk uang.
- Push ke `feature/logistics-fase-24-25`. PR/merge hanya bila diminta. Setelah selesai: berhenti dan beri ringkasan singkat (apa yang dikerjakan, temuan Tahap 0, angka gate, hal yang perlu tindakan manual).
