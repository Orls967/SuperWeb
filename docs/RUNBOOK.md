# Runbook Operasional & Panduan Troubleshooting — Superwebsite

Dokumen ini adalah panduan standar operasional prosedur (SOP) bagi tim Engineering, DevOps, dan Operasional Holding dalam memantau, memelihara, dan menangani insiden pada platform **Superwebsite**.

---

## 1. Pemantauan Rutin & Pemeriksaan Kesehatan Harian

### 1.1 Diagnosa Menyeluruh 8 Pilar Platform
Jalankan diagnosa otomatis satu pintu setiap pagi atau pasca deployment:
```bash
php artisan super:health-check
```
**Parameter Keberhasilan**:
- Seluruh 8 sub-sistem berstatus `<fg=green>✓ HEALTHY</>`:
  1. Koneksi Database Primary (latensi $< 50$ ms)
  2. Cache & In-Memory Store
  3. Izin Akses Direktori Storage (writable)
  4. Konsistensi Double-Entry Ledger (`bank:reconcile` 0 selisih)
  5. Integritas Hash-Chain Paspor Kendaraan (`core:verify-passports` rantai utuh)
  6. Audit Tagihan & Revenue Mall (`mall:audit-billing` 0 selisih)
  7. Operasional & Shift Kasir Resto (`resto:close-day --check` lolos)
  8. Logistik: Billing & Rantai Kustodi (`lgx:audit-billing` & `lgx:verify-custody` bersih)
- Terbit entri log audit di tabel `core_audit_logs` dengan aksi `system.health_check`.

### 1.2 Dashboard Observabilitas GUI
Akses melalui peramban: `http://127.0.0.1:8000/admin/health` (Login sebagai `admin@autoserve.test`).
Halaman ini menyajikan metrik latensi, status komponen visual, tombol pemindaian instan, dan 15 riwayat jejak rekam audit sistem terakhir.

---

## 2. Playbook Penanganan Insiden (Incident Response)

### Playbook A: Diskrepansi Saldo Buku Besar (Ledger Imbalance)
- **Gejala**: `bank:reconcile` menghasilkan kode keluar `1` atau melaporkan `DITEMUKAN DISKREPANSI`.
- **Dampak Kritis**: Ketidaksesuaian nilai uang di dompet pengguna atau selisih debet/kredit global.
- **Langkah Mitigasi**:
  1. Jalankan `php artisan bank:reconcile -v` untuk melihat detail akun yang bermasalah.
  2. Periksa apakah inkonsistensi berupa:
     - **Tipe 1: Cached Balance Mismatch** (`cached_balance != sum(entries)`): Terjadi jika ada update langsung pada kolom `cached_balance` tanpa melalui `LedgerService`.
       - *Solusi*: Rekalkulasi saldo akun:
         ```sql
         UPDATE bank_ledger_accounts 
         SET cached_balance = (SELECT COALESCE(SUM(amount), 0) FROM bank_ledger_entries WHERE account_id = bank_ledger_accounts.id)
         WHERE id = [ID_AKUN];
         ```
     - **Tipe 2: Global Asset Imbalance** ($\sum \text{Entries per Aset} \neq 0$): Terjadi transaksi satu sisi tanpa pasangan balancing.
       - *Solusi*: Telusuri `transaction_id` pada `bank_ledger_entries` yang tidak memiliki pasangan debet/kredit seimbang. Lakukan entri penyesuaian manual berimbang melalui `ManualAdjustmentAction`.
  3. Jalankan kembali `php artisan bank:reconcile` untuk memastikan selisih kembali `0`.

---

### Playbook B: Kerusakan Hash-Chain Paspor Kendaraan
- **Gejala**: `core:verify-passports` melaporkan `[✗] RUSAK pada sequence N`.
- **Dampak**: Paspor digital kendaraan gagal verifikasi kriptografis, riwayat servis tidak dapat dipercaya.
- **Langkah Mitigasi**:
  1. Cari UUID kendaraan yang terdampak dari output command.
  2. Periksa baris event pada tabel `core_vehicle_events`:
     ```sql
     SELECT id, sequence, type, prev_hash, hash, occurred_at 
     FROM core_vehicle_events 
     WHERE vehicle_id = [ID_VEHICLE] 
     ORDER BY sequence ASC;
     ```
  3. Cek log perubahan database atau audit log untuk menemukan modifikasi baris data ilegal. Karena tabel `core_vehicle_events` bersifat *append-only* (dilindungi model boot events), modifikasi biasanya terjadi akibat eksekusi query raw langsung.
  4. Pulihkan *payload* asli dari backup transaksi / audit log, lalu verifikasi ulang rantai hash hingga seluruh sequence kembali valid.

---

### Playbook C: Kegagalan Auto-Debit Tagihan Sewa Tenant Mall
- **Gejala**: Command harian `mall:auto-debit` gagal melunasi invoice tenant karena saldo dompet tidak mencukupi.
- **Dampak**: Invoice berstatus `OVERDUE` dan penerimaan kas holding tertunda.
- **Langkah Mitigasi**:
  1. Buka Portal Mandiri Tenant atau hubungi PIC tenant yang tercatat di `mall_tenants`.
  2. Minta tenant melakukan top-up dompet IDR melalui menu Dompet (`wallet.index`).
  3. Setelah saldo terisi, eksekusi pelunasan via portal tenant atau jalankan ulang:
     ```bash
     php artisan mall:auto-debit
     ```
  4. Jika invoice melewati tanggal jatuh tempo (`due_date`), jalankan:
     ```bash
     php artisan mall:apply-penalties
     ```
     Sistem akan menambahkan denda keterlambatan 2% flat secara otomatis.

---

### Playbook D: Selisih Kas Laci Resto (Cash Drawer Discrepancy)
- **Gejala**: Kasir menginput uang tunai riil saat tutup shift yang berbeda dari kalkulasi sistem POS.
- **Dampak**: Muncul entri varians kas di buku besar.
- **Langkah Penanganan**:
  1. Manajer outlet memeriksa fisik uang kas di laci kasir outlet terkait.
  2. Sistem secara otomatis mencatat selisih tersebut ke akun `expense:resto:cash_variance:IDR` dan menyesuaikan saldo akun kas `cash:drawer:{outlet}:IDR`.
  3. Jalankan verifikasi penutupan harian:
     ```bash
     php artisan resto:close-day --check --outlet=[KODE_OUTLET]
     ```
  4. Verifikasi bahwa angka ringkasan harian di `resto_daily_summaries` tetap seimbang dengan ledger.

---

### Playbook E: Volatilitas Kripto & Likuidasi Pinjaman HODL-to-Drive
- **Gejala**: Terjadi penurunan drastis harga jaminan kripto (BTC/ETH/SOL) menyebabkan rasio pinjaman terhadap nilai jaminan (LTV) melonjak.
- **Dampak**: Risiko kerugian pembiayaan pada neraca holding.
- **Langkah Operasional**:
  1. Scheduler `finance:monitor-risk` memantau LTV setiap 5 menit:
     - **LTV $\ge 75\%$ (Margin Call)**: Notifikasi peringatan otomatis dikirim ke customer agar menambah jaminan atau mencicil pokok utang.
     - **LTV $\ge 85\%$ (Likuidasi Otomatis)**: Kolateral kripto otomatis dieksekusi jual di harga pasar, dana hasil penjualan didebet untuk menutup sisa pokok utang dan denda, dan sisa dana dikembalikan ke dompet customer.
  2. Verifikasi status pinjaman di menu Pembiayaan:
     ```sql
     SELECT id, loan_number, status, current_ltv FROM fin_loans WHERE status IN ('ACTIVE', 'DEFAULTED');
     ```

---

### Playbook F: Diskrepansi Billing Logistik atau Kerusakan Chain of Custody
- **Gejala**: `lgx:audit-billing` atau `lgx:verify-custody` keluar dengan kode 1 / merah.
- **Dampak**: Ketidaksesuaian piutang shipper, unearned revenue, akrual carrier/D&D, atau manipulasi histori event resi.
- **Langkah Penanganan**:
  1. Jalankan `php artisan lgx:audit-billing` untuk mengisolasi 15 titik rekonsiliasi logistik.
  2. Bila ditemukan selisih pengakuan unearned/freight revenue:
     - Telusuri `lgx_shipments` dengan status `Delivered` yang belum tercatat `revenue_recognized_at`.
     - Jalankan `php artisan lgx:verify-custody` untuk memverifikasi SHA-256 hash chain per shipment.
  3. Periksa akun ledger `lgx:*` terkait untuk memastikan balancing debit/credit.

---

### Playbook G: Kegagalan Pengiriman Webhook (Dead-Letter Outbox)
- **Gejala**: Partner/shipper eksternal melaporkan tidak menerima notifikasi webhook status shipment.
- **Dampak**: Keterlambatan integrasi sistem mitra B2B.
- **Langkah Penanganan**:
  1. Periksa tabel `lgx_webhook_deliveries` untuk status `dead_letter` atau `pending`:
     ```sql
     SELECT id, endpoint_id, event, attempts, status, response_status, last_error 
     FROM lgx_webhook_deliveries 
     WHERE status IN ('failed', 'dead_letter')
     ORDER BY updated_at DESC LIMIT 20;
     ```
  2. Jalankan retry manual via artisan command:
     ```bash
     php artisan lgx:retry-webhooks
     ```
  3. Jika endpoint klien mengembalikan error 4xx/5xx persisten, hubungi pihak IT mitra untuk memeriksa konfigurasi URL atau shared secret HMAC-SHA256.

---

## 3. Jadwal Scheduler Otomatis (Production Crontab)

Pastikan satu baris crontab berikut aktif di server produksi:

```bash
* * * * * cd /var/www/autoserve && php artisan schedule:run >> /dev/null 2>&1
```

### Matriks Jadwal Tugas Terjadwal (Scheduled Tasks)

| Waktu Eksekusi | Artisan Command | Deskripsi Pekerjaan |
|---|---|---|
| Setiap Menit | `crypto:tick` | Update harga pasar simulasi aset kripto |
| Setiap 5 Menit | `lgx:retry-webhooks` | Retry pengiriman webhook gagal (exponential backoff) |
| Setiap 10 Menit | `store:cancel-stale-orders` | Batalkan reservasi belanja yang kedaluwarsa |
| Setiap 15 Menit | `resto:expire-display` | Buang makanan etalase lewat batas 6 jam |
| Setiap 15 Menit | `lgx:detect-late` | Deteksi shipment melewati estimasi SLA waktu |
| Setiap Jam | `store:auto-capture-c2c` | Auto-capture dana escrow C2C setelah 3 hari |
| Setiap Jam | `payment:release-expired-holds` | Rilis dana hold escrow yang melewati batas waktu |
| Setiap Jam | `lgx:capacity-check` | Verifikasi kapasitas jadwal armada tidak overload |
| Harian 00:10 | `lgx:accrue-dd` | Akrual harian denda Demurrage & Detention kontainer |
| Harian 00:30 | `mall:expire-points` | Kadaluwarsa Duta Points loyalitas |
| Harian 00:45 | `mall:expire-vouchers` | Bukukan voucher kedaluwarsa ke breakage |
| Harian 01:00 | `finance:charge-installments` | Auto-debit cicilan pinjaman HODL-to-Drive |
| Harian 02:00 | `resto:post-royalty` | Hitung & posting royalti waralaba holding |
| Harian 02:30 | `lgx:verify-custody` | Audit integritas kriptografis rantai kustodi resi |
| Harian 04:00 | `mall:auto-debit` | Eksekusi auto-debit tagihan tenant dari dompet |
| Harian 04:45 | `mfg:run-mrp` | Eksekusi kalkulasi kebutuhan material MRP harian |
| Harian 05:00 | `mall:apply-penalties` | Terapkan denda 2% bagi tagihan terlambat |
| Harian 06:00 | `mall:renew-parking-members` | Perpanjangan otomatis langganan parkir |
| Harian 06:30 | `mall:generate-pm-orders` | Terbitkan work order pemeliharaan gedung |
| Harian 06:45 | `sup:scan-risks` | Pemindaian risiko pemasok, sertifikasi & sanksi |
| Harian 07:00 | `mall:audit-billing` | Audit integritas penagihan mall vs ledger |
| Harian 07:10 | `sup:remind-certifications` | Kirim pengingat kedaluwarsa sertifikasi pemasok |
| Harian 07:15 | `lgx:audit-billing` | Audit integritas penagihan logistik vs ledger |
| Harian 07:30 | `resto:check-stock` | Pantau stok kritis bahan baku & draf PO |
| Harian 08:30 | `lgx:settle-cod` | Cairkan setoran COD ke dompet shipper (D+N) |
| Harian 23:50 | `chain:audit-all` | Orkestrasi 18 audit rantai nilai global ekosistem |
| Harian 23:59 | `resto:close-day --check` | Tutup harian resto, buang waste & ringkasan |
| Harian 23:59 | `bank:reconcile` | Audit keselarasan saldo buku besar double-entry |
| Mingguan Senin, 08:00 | `mall:settle-vouchers` | Cairkan klaim voucher belanja tenant |
| Mingguan Senin, 09:00 | `lgx:pay-carriers` | Bayar tagihan leg carrier subkontrak jatuh tempo |
| Bulanan Tgl 1, 01:00 | `ast:depreciate` | Perhitungan & posting beban depresiasi aset tetap |
| Bulanan Tgl 1, 01:30 | `ast:audit` | Audit rekonsiliasi subledger aset tetap vs ledger |
| Bulanan Tgl 1, 02:00 | `lgx:invoice-shippers` | Terbitkan tagihan bulanan shipper pascabayar B2B |
| Bulanan Tgl 1, 03:00 | `mall:generate-invoices` | Terbitkan tagihan sewa & utilitas bulanan |

---

## 4. Prosedur Pencadangan & Pemulihan (Backup & Disaster Recovery)

### 4.1 Pencadangan Data (Backup)
```bash
# Backup SQLite Database
sqlite3 database/database.sqlite ".backup 'backups/db_$(date +%Y%m%d_%H%M%S).sqlite'"

# Backup Storage Dokumen & Bukti Bayar
tar -czvf backups/storage_$(date +%Y%m%d_%H%M%S).tar.gz storage/app/public
```

### 4.2 Pemulihan Data (Restore)
1. Hentikan web server sementara (maintenance mode):
   ```bash
   php artisan down --secret="holding-emergency"
   ```
2. Pulihkan file database dari salinan cadangan terakhir:
   ```bash
   cp backups/db_terpilih.sqlite database/database.sqlite
   ```
3. Jalankan audit kesehatan platform lengkap:
   ```bash
   php artisan super:health-check
   ```
4. Jika seluruh pilar lolos verifikasi, aktifkan kembali server:
   ```bash
   php artisan up
   ```

---

## 5. SOP Operasional 17 Lini Bisnis & Gelombang 2

### 5.1 Energi & Smart Grid (`egy_`)
- **Automated Grid Dispatch**: Pantau kestabilan frekuensi ($50 \pm 0.2$ Hz) dan beban merit-order generation.
- **Microgrid Emergency Islanding**: Saat grid blackout terjadi, isolasi microgrid RS dan fasilitas darurat secara otomatis dalam waktu $< 5$ detik.
- **Audit Rutin**: `php artisan egy:audit` (0 deviasi metering / settling).

### 5.2 Telekomunikasi & Network Operations Center (`tlx_`)
- **NOC Incident Protocol**: Pantau ketersediaan SLA tower dan latency link fiber backhaul ($< 15$ ms).
- **Data Center PUE Management**: Awasi rasio PUE pendinginan rack server agar tetap $\le 1.35$.
- **Audit Rutin**: `php artisan tlx:audit` (verifikasi SLA rebate & IP transit balance).

### 5.3 Media & Hak Cipta (`med_`)
- **DRM & Watermark Traceability**: Setiap aset video/audio streaming wajib disisipkan watermark digital hash-chain sebelum dipublikasikan ke kanal SVOD atau broadcast.
- **Royalty Attribution**: Penagihan royalti streaming per play diakumulasi dan dicairkan sesuai kontrak pembagian revenue creator.
- **Audit Rutin**: `php artisan med:audit`.

### 5.4 Pendidikan & Cohorts (`edu_`)
- **Student Certification Hash-Chain**: Terbitkan sertifikat kelulusan kompetensi dengan verifikasi hash SHA-256 yang dapat divalidasi publik.
- **Tuition Escrow**: Biaya kelas ditahan di escrow hingga milestone pembelajaran selesai, kemudian dibagi pro-rata ke instruktur dan platform.
- **Audit Rutin**: `php artisan edu:audit`.

### 5.5 Ritel Omnichannel & Q-Commerce (`ret_`)
- **Order Management System (OMS)**: Alokasi inventori dari multi-channel (store, app, marketplace) dengan perlindungan anti-double-sell.
- **Dark Store Picking Waves**: Dispatch wave picking berurutan untuk pesanan instan target kirim $< 30$ menit.
- **Audit Rutin**: `php artisan ret:audit`.

### 5.6 Multi-Region Active-Active DR Drill (`dr:audit`)
- **Langkah Simulasi DR**:
  1. Jalankan `php artisan dr:audit` untuk memvalidasi replikasi Jakarta $\to$ Singapore (SG-2).
  2. Pastikan RPO = 0 (zero ledger discrepancy) dan RTO $< 15$ menit untuk lini critical (RS, Energi, Pembayaran).
  3. Periksa kepatuhan residensi data kedaulatan data lokal.

---

## 6. Quality Gate, CI & Portabilitas Database

Bagian ini untuk pengembang dan agent yang menutup fase. Aturan lengkapnya ada di `docs/PROGRESS.md` §P4/§P7, `docs/gates/README.md`, dan `docs/gates/VERIFIKATOR.md`.

### 6.1 Sebelum push

```bash
vendor/bin/pint --dirty --format agent            # rapikan file yang diubah
vendor/bin/pest tests/Architecture                # semua pagar otomatis (baseline ratchet)
vendor/bin/pest --filter=<NamaTestYangDisentuh>   # test fitur yang diubah
```

Bila sebuah pagar baseline merah:
- **Pelanggaran baru** (`BARU (tidak ada di baseline)`): perbaiki kodenya. Menambah entri baseline hanya boleh dengan keputusan pemilik yang tercatat:
  ```bash
  UPDATE_BASELINES=1 BASELINE_DECISION="DECISIONS.md#<anchor>" vendor/bin/pest --filter=<NamaTest>
  ```
  Khusus `arch:scan`: `php artisan arch:scan --update-baseline --allow-new="DECISIONS.md#<anchor>"`.
- **Pelanggaran hilang** (`stale`): turunkan baseline di commit yang sama:
  ```bash
  UPDATE_BASELINES=1 vendor/bin/pest --filter=<NamaTest>
  ```
  Khusus `arch:scan`: `php artisan arch:scan --update-baseline`.

### 6.2 Gate penuh

```bash
composer gate     # validate, pest penuh (--parallel), pint --test, arch:scan, migrate:fresh --seed (DB khusus gate),
                  # bank:reconcile, chain:audit-all, super:health-check, npm run build
```

Manifest dan JUnit ditulis ke `storage/logs/gate-manifest.json` dan `storage/logs/pest-junit.xml`. Gate memakai database SQLite khusus, sehingga database dev dari `.env` tidak tersentuh.

Laporan resmi fase hanya diambil dari artefak CI (`docs/gates/README.md` §3). Gate lokal hanya pemeriksaan awal.

### 6.3 Job CI dan artinya

| Job | Isi | Bila merah |
|---|---|---|
| `PHP 8.4 × SQLite (Full Gate Suite)` | `composer gate` + `gate:report` + unggah artefak | Buka step yang gagal; jalankan test yang sama secara lokal |
| `PHP 8.4 × MySQL 8 (DB Portability)` | `migrate:fresh --seed` di MySQL 8.4 + `pest --group=db-portability` | Lihat §6.4 |
| `PHP 8.4 × Pest Mutation Testing (min 60%)` | mutation testing kelas Action/Service yang diubah PR (hanya pada pull request) | Perkuat test kelas yang disebut; jangan turunkan ambang |

Membaca hasil CI tanpa membuka browser:
```bash
gh run list --repo Orls967/superweb --branch <branch> --limit 3
gh api repos/Orls967/superweb/actions/runs/<run-id>/jobs \
  --jq '.jobs[] | "\(.name): \(.conclusion) | gagal di: \([.steps[] | select(.conclusion=="failure") | .name] | join(","))"'
gh run view <run-id> --repo Orls967/superweb --log-failed
```

### 6.4 Job MySQL merah

`migrate:fresh` di MySQL berhenti di error pertama. Sebelum memperbaiki satu per satu lewat CI, kumpulkan semua masalah sekaligus:

```bash
vendor/bin/pest tests/Architecture/MysqlSchemaCompatibilityTest.php   # semua DDL yang ditolak MySQL 8.4
```

Penyebab yang sudah pernah terjadi (`KNOWLEDGE.md` K-33, K-40, K-41):

| Error MySQL | Penyebab | Perbaikan yang benar |
|---|---|---|
| `1059 Identifier name … is too long` | nama index/FK bawaan Laravel > 64 karakter | beri nama eksplisit ≤ 64 karakter |
| `1071 Specified key was too long` | index komposit > 3.072 byte (`VARCHAR(255)` × 4 byte) | perpendek kolom **hanya** bila semua penulis nilainya divalidasi `max`; atau ubah urutan/isi index |
| `TypeError addslashes()` saat migrasi | `->comment([...])` berisi array | komentar berupa string |
| `1366 Incorrect integer value` saat seeder | UUID ditulis ke kolom angka (`*_id`, morph) | pakai id yang benar, atau kolom rujukan generik `string(64)` (tunggu konvensi K-41) |
| `1406 Data too long` saat seeder | konstanta/nilai lebih panjang dari `varchar(n)` | perlebar kolom; jangan ubah konstanta yang sudah tersimpan di data |
| `3780` FK beda tipe | kolom FK `integer`/`string` merujuk `bigint unsigned` | samakan tipe (`foreignId`, `foreignUuid`) |

Menjalankan MySQL sendiri (bila Docker tersedia):
```bash
docker run -d --name superweb-mysql -e MYSQL_ROOT_PASSWORD=password -e MYSQL_DATABASE=superweb -p 3306:3306 mysql:8.4
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=superweb DB_USERNAME=root DB_PASSWORD=password php artisan migrate:fresh --seed --force
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=superweb DB_USERNAME=root DB_PASSWORD=password vendor/bin/pest --group=db-portability
```

---

## 7. Akses GitHub untuk Agent & Respons Insiden Kredensial

### 7.1 Token untuk agent

Agent (pelaksana/verifikator) bekerja dengan **fine-grained personal access token** khusus repo ini, bukan login penuh pemilik:

| Pengaturan | Nilai |
|---|---|
| Repository access | *Only select repositories* → `Orls967/superweb` |
| Contents | Read and write (push) |
| Pull requests | Read and write (draft PR) |
| Workflows | Read and write (push perubahan `.github/workflows`) |
| Actions | Read-only (log & artefak CI) |
| Metadata | Read-only (otomatis) |
| Administration dan lainnya | **tidak diberikan** (branch protection & setting repo tetap di tangan pemilik) |
| Expiration | 7–30 hari |

Pasang di mesin agent:
```bash
gh auth logout
pbpaste | gh auth login -h github.com --with-token   # macOS; salin token dulu, jangan diketik di kolom tersembunyi
gh auth setup-git                                    # git push memakai token yang sama
gh auth status                                       # Token: github_pat_…
```

Prompt agent wajib memuat larangan: hanya repo `Orls967/superweb`; dilarang `git credential fill`, `gh auth token`, atau cara lain membaca/mencetak token; bila `gh` belum login, berhenti dan minta pemilik.

### 7.2 Bila token/kredensial bocor (mis. tercetak di log agent)

1. **Kenali jenis token** tanpa mencetak isinya (hanya 4 huruf awal):
   ```bash
   printf "protocol=https\nhost=github.com\n\n" | git credential fill | grep '^password=' | cut -c1-13
   ```
   - `gho_`: token aplikasi OAuth. Cabut di *github.com/settings/applications → Authorized OAuth Apps* (mis. "Visual Studio Code", yang dipakai editor turunan VS Code untuk login git).
   - `ghu_`: token GitHub App. Cabut di tab *Authorized GitHub Apps*.
   - `ghp_` / `github_pat_`: personal access token. Hapus di *Developer settings → Personal access tokens*.
2. **Hapus salinan lokal** dan login ulang:
   ```bash
   printf "protocol=https\nhost=github.com\n\n" | git credential reject
   gh auth logout
   ```
   Lalu pasang token baru sesuai §7.1.
3. **Hapus log** yang memuat token (folder kerja/log alat agent). Setelah token dicabut, isinya sudah tidak berlaku, tetapi tetap dibersihkan.
4. **Catat** di `docs/BLOCKERS.md` (Riwayat) tanpa menuliskan nilai token.
