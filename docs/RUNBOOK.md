# Runbook Operasional & Panduan Troubleshooting — Superwebsite

Dokumen ini adalah panduan standar operasional prosedur (SOP) bagi tim Engineering, DevOps, dan Operasional Holding dalam memantau, memelihara, dan menangani insiden pada platform **Superwebsite**.

---

## 1. Pemantauan Rutin & Pemeriksaan Kesehatan Harian

### 1.1 Diagnosa Menyeluruh 12 Pilar Platform
Jalankan diagnosa otomatis satu pintu setiap pagi atau pasca deployment:
```bash
php artisan super:health-check
```
**Parameter Keberhasilan**:
- Seluruh 12 sub-sistem berstatus `<fg=green>✓ HEALTHY</>`:
  1. Koneksi Database Primary (latensi $< 50$ ms)
  2. Cache & In-Memory Store
  3. Izin Akses Direktori Storage (writable)
  4. Konsistensi Double-Entry Ledger (`bank:reconcile` 0 selisih)
  5. Integritas Hash-Chain Paspor Kendaraan (`core:verify-passports` rantai utuh)
  6. Audit Tagihan & Revenue Mall (`mall:audit-billing` 0 selisih)
  7. Operasional & Shift Kasir Resto (`resto:close-day --check` lolos)
  8. Logistik: Billing & Rantai Kustodi (`lgx:audit-billing` & `lgx:verify-custody` bersih)
  9. Aset: Subledger & Rantai Hash (`ast:audit` 0 selisih)
  10. Pembelian: Subledger AP & GR/IR (`proc:audit` 0 selisih)
  11. Kontrak: Subledger Plafon & Rantai Versi (`ctr:audit` & `contracts:verify-chain` valid)
  12. Manufaktur: Biaya Produksi WIP & Mutu QMS (`mfg:audit-costing` & `mfg:qms-audit` seimbang)
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

### Playbook H: Rekonsiliasi Dividen Token RWA & Penanganan Residu Dust (Fase 96)
- **Gejala**: Perhitungan dividen investor token RWA menyisakan fraksi desimal minor unit.
- **Dampak Kritis**: Selisih pembulatan moneter yang berpotensi melanggar invarian konservasi nilai buku besar.
- **Langkah Mitigasi**:
  1. Pastikan eksekusi distribusi pro-rata menggunakan `RwaCrossLineDividendDistributionService`:
     $$\sum \text{payouts} + \text{dust} = \text{total\_pool}$$
  2. Periksa akun penampung `rwa:dust_reserve:IDR` di buku besar untuk memastikan residu pembulatan tertampung rapi:
     ```bash
     php artisan rwa:audit
     ```
  3. Verifikasi status keseimbangan multi-aset:
     ```bash
     php artisan bank:reconcile
     ```

---

### Playbook I: Penanganan Transaksi Karantina Anomali AI Anti-Fraud (Fase 99)
- **Gejala**: Transaksi pembayaran atau klaim diblokir dan berstatus `PENDING_REVIEW` pada `core_ai_anomaly_quarantines`.
- **Dampak**: Settlement dana tertahan sementara menunggu peninjauan kepatuhan (*four-eyes review*).
- **Langkah Operasional**:
  1. Buka tabel karantina anomali AI:
     ```sql
     SELECT id, transaction_reference, domain_code, anomaly_type, risk_score, resolution_status, created_at
     FROM core_ai_anomaly_quarantines
     WHERE resolution_status = 'PENDING_REVIEW'
     ORDER BY risk_score DESC;
     ```
  2. Evaluasi 5 indikator risiko yang memicu karantina (kecepatan, deviasi magnitudo, lonjakan geo, pelanggaran kontrak, ketidakcocokan perangkat).
  3. Jalankan audit determinisme AI:
     ```bash
     php artisan ai:audit
     ```
  4. Bila transaksi terbukti sah (*false-positive*), petugas risk officer memperbarui status menjadi `CLEARED` untuk membuka blokir settlement. Bila terbukti penipuan, tandai `FRAUD_CONFIRMED`.

---

### Playbook J: Mitigasi Insiden Bruteforce Terminal IGD & Rotasi Token Telematika (Fase 100)
- **Gejala**: Terminal IGD RS melaporkan `TERMINAL_IGD_LOCKED_OUT` atau perangkat armada gagal ingest data telematika (`INVALID_OLD_TOKEN_REJECTED`).
- **Dampak**: Staf medis tidak dapat input triage darurat dari terminal tertentu atau pelacakan armada terputus.
- **Langkah Penanganan**:
  1. **Lockout IGD**: Kunci lockout terminal berlaku otomatis 15 menit pasca 3 kegagalan kredensial berturut-turut.
     - Bila situasi darurat klinis (*break-glass*), admin RS dapat mereset kunci terminal:
       ```php
       Cache::forget("igd:lockout:{$terminalIp}");
       Cache::forget("igd:fail_count:{$terminalIp}:{$staffId}");
       ```
  2. **Rotasi Token IoT Telematika**:
     - Sistem menyediakan *grace period* 60 detik selama pergantian token aktif.
     - Periksa apakah perangkat lama mencoba ingest menggunakan token yang telah melewati masa tenggang grace period:
       ```bash
       php artisan super:health-check
       ```

---

### Playbook K: Eskalasi Izin Operasional Kedaluwarsa Kalender Kepatuhan (Fase 100)
- **Gejala**: Kalender kepatuhan mencatat izin berstatus `EXPIRING_SOON` atau `EXPIRED`.
- **Dampak**: Risiko sanksi regulasi atau penghentian paksa operasional unit bisnis.
- **Langkah Operasional**:
  1. Jalankan audit izin terjadwal harian melalui service `TwelveLinesComplianceCalendarService`.
  2. Periksa daftar kewajiban perizinan bermasalah di tabel `core_compliance_obligations`:
     ```sql
     SELECT permit_code, domain_code, permit_name, issuing_authority, expiration_date, status 
     FROM core_compliance_obligations 
     WHERE status IN ('EXPIRING_SOON', 'EXPIRED')
     ORDER BY expiration_date ASC;
     ```
---

### Playbook L: Penanganan Failover Bencana Multi-Wilayah & Kuorum Raft Split-Brain (Fase 101)
- **Gejala**: Cluster database region primer (ID-CGK-1) tidak responsif, alarm latensi replikasi $> 1.000$ ms.
- **Dampak Kritis**: Potensi split-brain dan dual-master write jika failover dipicu tanpa konsensus kuorum.
- **Langkah Operasional**:
  1. Periksa status kuorum node cluster menggunakan artisan:
     ```bash
     php artisan dr:audit
     ```
  2. Verifikasi voting kuorum Raft:
     - Jika kuorum $\ge 2/3$ node sepakat, promosi master sekunder (ID-SUB-2) berlangsung otomatis dengan RPO=0.
     - Jika kuorum terpecah ($< 2/3$), pastikan kluster terdegradasi secara aman ke mode `READ_ONLY` guna mencegah korupsi data.
  3. Lakukan failover manual terkontrol jika diperlukan:
     ```bash
     php artisan dr:failover --target=ID-SUB-2 --force
     ```

---

### Playbook M: Audit Kriptografi Pasca-Kuantum (PQC) & Rotasi Envelop Kunci Induk (Fase 101)
- **Gejala**: Peringatan kadaluwarsa envelop kunci enkripsi (KMS) atau kegagalan verifikasi tanda tangan kuantum.
- **Langkah Operasional**:
  1. Jalankan audit kriptografis 8 rantai Merkle pasca-kuantum:
     ```bash
     php artisan pq:audit-chains
     ```
  2. Pastikan hasil pemeriksaan melaporkan `0 discrepancies`.
  3. Lakukan rotasi envelop kunci enkripsi master secara idempotens tanpa merusak ciphertext lama:
     ```bash
     php artisan kms:rotate --domain=all
     ```

---

### Playbook N: Penanganan Antrean Dead-Letter Webhook (DLQ) & Resolusi Konflik Vektor Jam Mobile (Fase 102)
- **Gejala**: Webhook integrasi mitra gagal setelah 5 kali percobaan (exponential backoff) dan terparkir di `core_webhook_deliveries` status `DEAD_LETTER_QUEUE`, atau konflik versi pada sinkronisasi offline perangkat mobile.
- **Langkah Operasional**:
  1. Periksa daftar antrean DLQ melalui API v3:
     ```bash
     curl -s https://api.autoserve.internal/v3/partner/webhooks \
       -H "X-Tenant-ID: holding-corp"
     ```
  2. Setelah server tujuan mitra normal kembali, picu pengiriman ulang (*replay*) pesan DLQ:
     ```bash
     curl -X POST https://api.autoserve.internal/v3/partner/webhooks/{id}/replay \
       -H "X-Tenant-ID: holding-corp"
     ```
  3. Periksa audit ledger konflik versi mobile di tabel `core_sync_conflict_ledgers`:
     ```sql
     SELECT id, device_uuid, entity_type, entity_id, resolution_strategy, created_at
     FROM core_sync_conflict_ledgers
     ORDER BY id DESC LIMIT 20;
     ```
  4. Pastikan konflik klinis diselesaikan melalui `SERVER_HEALTHCARE_AUTHORITY_PRIORITY` dan konflik stok diselesaikan melalui penyesuaian buku besar kompensasi.

---

### Playbook O: Penanganan Pelanggaran Rantai Dingin Spesimen Laboratorium (Cold-Chain Breach) (Fase 105)
- **Gejala**: Log serah terima spesimen laboratorium melaporkan suhu penanganan $> 8.0^\circ\text{C}$ (`COLD_CHAIN_EXCEEDED`), status spesimen berubah menjadi `REJECTED_COLD_CHAIN_BREACH`.
- **Dampak Kritis**: Degradasi sampel darah/biologis (e.g., hemolisis sampel elektrolit darah atau denaturasi enzim), pembatalan hasil tes demi kepatuhan ISO 15189 dan standar keselamatan pasien.
- **Langkah Operasional**:
  1. Periksa riwayat transfer kustodi spesimen:
     ```sql
     SELECT id, specimen_barcode, handler_id, temperature_c, status, notes, created_at
     FROM hsp_lab_custody_logs
     WHERE temperature_c > 8.0 OR status = 'REJECTED_COLD_CHAIN_BREACH'
     ORDER BY created_at DESC;
     ```
  2. Segera kirimkan notifikasi penolakan ke unit pengambil sampel (rawat inap/rawat jalan) untuk pengambilan ulang spesimen (*re-draw*).
  3. Periksa kondisi unit pendingin transport (*cool box* / data logger suhu) petugas kurir pembawa sampel.
  4. Pastikan sistem tidak menagihkan biaya pengujian lab (`hsp:lab_revenue:IDR`) untuk spesimen yang ditolak akibat pelanggaran rantai dingin internal.

---

### Playbook P: Penanganan Peringatan Nilai Panik Kritis Laboratorium (Panic Value Alert) (Fase 105)
- **Gejala**: Pengujian lab mendeteksi parameter melewati batas kritis ekstrem (e.g., Kalium $< 2.5\text{ mEq/L}$ atau $> 6.5\text{ mEq/L}$, Troponin I $> 0.04\text{ ng/mL}$ STAT, Gula Darah Sewaktu $< 45\text{ mg/dL}$ atau $> 500\text{ mg/dL}$).
- **Dampak Kritis**: Kondisi klinis mengancam jiwa pasien (*life-threatening*) yang membutuhkan tindakan medis darurat oleh dokter penanggung jawab pelayanan (DPJP) dalam hitungan menit.
- **Langkah Operasional**:
  1. Sistem menandai hasil lab dengan flag `is_panic_critical = true` dan status `HELD_FOR_PATHOLOGIST_REVIEW`.
  2. Sistem secara otomatis memicu broadcast notifikasi darurat prioritas tinggi (STAT alert) ke terminal perawat dan perangkat DPJP:
     ```sql
     SELECT id, test_code, measured_value, unit, is_panic_critical, verification_status
     FROM hsp_lab_results
     WHERE is_panic_critical = 1 AND verification_status = 'HELD_FOR_PATHOLOGIST_REVIEW';
     ```
  3. Dokter Patologi Klinik memverifikasi hasil pemeriksaan instrumen (auto-rerun) dan mengonfirmasi keabsahan nilai kritis.
  4. Petugas laboratorium melakukan pembacaan kembali secara lisan (*read-back confirmation*) via telepon ke perawat ruangan sesuai standar Patient Safety Goal 2.

---

### Playbook Q: Kontraindikasi Alergi Silang E-Resep & Proteksi Pasien Klirens Ginjal Rendah (Fase 104)
- **Gejala**: Dokter meresepkan obat yang memiliki kontraindikasi alergi silang dengan riwayat alergi pasien atau dosis obat melampaui kapasitas klirens ginjal ($eGFR < 30\text{ mL/min}$).
- **Dampak**: Risiko reaksi anafilaksis fatal atau toksisitas ginjal akut pada pasien.
- **Langkah Operasional**:
  1. Engine e-prescribing memblokir penerbitan resep secara otomatis dengan kode galat `ERR_CLINICAL_CONTRAINDICATION`.
  2. Periksa kalkulasi klirens ginjal dan riwayat alergi pada profil klinis pasien:
     ```sql
     SELECT mrn, serum_creatinine, egfr, allergy_flags
     FROM hsp_patient_clinical_profiles
     WHERE mrn = '[PATIENT_MRN]';
     ```
  3. Bila dokter memerlukan override klinis atas pertimbangan darurat (*break-glass clinician override*), wajib menyertakan justifikasi medis formal, PIN otorisasi spesialis, dan persetujuan farmasis klinis.
  4. Seluruh aktivitas override dicatat permanen dalam audit trail `core_audit_logs` untuk audit keselamatan pasien oleh Komite Farmasi & Terapi (KFT).

---

### Playbook R: Kliring Finansial Antar-Cabang Apotek & Rekonsiliasi Saldo Resiprokal (Fase 105)
- **Gejala**: Terjadi diskrepansi saldo akun perantara kliring antar-cabang (`hsp:inter_branch_clearing:IDR`) pasca mutasi stok obat antar-apotek cabang holding.
- **Dampak**: Ketidakseimbangan laporan keuangan konsolidasi unit bisnis rumah sakit dan apotek jejaring.
- **Langkah Operasional**:
  1. Jalankan audit rekonsiliasi transfer antar cabang:
     ```sql
     SELECT id, source_branch_id, destination_branch_id, item_code, quantity, total_amount, clearing_status
     FROM hsp_inter_branch_clearings
     WHERE clearing_status != 'BALANCED_RECIPROCAL'
     ORDER BY created_at DESC;
     ```
  2. Pastikan setiap pengiriman stok dari Cabang A ke Cabang B menghasilkan dua pasang jurnal seimbang:
     - Cabang Pengirim: Debit `hsp:inter_branch_clearing:IDR`, Kredit `hsp:pharmacy_inventory:IDR`
     - Cabang Penerima: Debit `hsp:pharmacy_inventory:IDR`, Kredit `hsp:inter_branch_clearing:IDR`
  3. Verifikasi saldo netto akun perantara bernilai nol:
     ```bash
     php artisan bank:reconcile -v
     ```

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
384: 4. **Catat** di `docs/BLOCKERS.md` (Riwayat) tanpa menuliskan nilai token.
385: 
---

## 8. Prosedur Rotasi Pepper Blind Index & Kunci Enkripsi (Fase R3 / D2)

### 8.1 Arsitektur Blind Index & Pepper
Kolom data pribadi terenkripsi (seperti NIK pada `hcm_employees` dan NPWP pada `mall_tenants`/`pty_legal_entities`) menggunakan mekanisme **Blind Index HMAC-SHA256** melalui kelas `App\Security\BlindIndex`.
Pepper rahasia dimuat dari variabel lingkungan `BLIND_INDEX_PEPPER` (`config('app.blind_index_pepper')`).

**Aturan Keamanan Mutlak:**
- Nilai pepper dilarang di-commit ke repositori Git.
- Pada lingkungan produksi (`APP_ENV=production`), aplikasi secara otomatis menolak booting (`RuntimeException`) bila `BLIND_INDEX_PEPPER` kosong.

### 8.2 Prosedur Operasional Rotasi Pepper
Rotasi pepper dilakukan ketika terjadi pergantian kunci berkala atau dugaan kompromi kredensial:
1. **Persiapan Pepper Baru**: Buat string acak kriptografis 32+ karakter (misal via `openssl rand -hex 32`).
2. **Mode Transisi**:
   - Simpan pepper lama sebagai variabel sementara (misal `BLIND_INDEX_PEPPER_OLD`).
   - Perbarui nilai `BLIND_INDEX_PEPPER` di environment server (misal `.env` / AWS Secrets Manager).
3. **Eksekusi Re-Indexing Idempoten**:
   Jalankan command re-index untuk memperbarui kolom `*_bidx` pada basis data:
   ```bash
   php artisan app:rotate-blind-index --old-pepper="<PEPPER_LAMA>" --dry-run
   php artisan app:rotate-blind-index --old-pepper="<PEPPER_LAMA>" --force
   ```
4. **Verifikasi Pencarian**:
   Uji pencarian exact-match NIK/NPWP untuk memastikan data dapat ditemukan menggunakan pepper baru.
5. **Pembersihan**: Hapus variabel `BLIND_INDEX_PEPPER_OLD` setelah re-indexing selesai 100%.


---

## 9. Katalog Lengkap 35+ Perintah Audit & Remediasi Artisan

Platform Superwebsite dilengkapi dengan perintah audit mandiri berbasis Artisan untuk memvalidasi invarian data, rantai kriptografi, dan kepatuhan akuntansi di seluruh modul bisnis:

### 9.1 `php artisan agri:audit` — Audit Subledger & Kontrak Agribisnis
- **Tujuan & Invarian**: Memverifikasi integrasi panen, bagi hasil inti-plasma, dan akrual pupuk.
- **Opsi & Parameter Utama**: `--fix (re-anchor saldo), --strict`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika saldo subledger agribisnis = saldo GL akun agri; Exit 1 jika terjadi ketidakseimbangan.
- **SOP Remediasi Kegagalan**: Jalankan dengan `--fix` untuk menyelaraskan akrual panen yang belum terposting; periksa audit log transaksi petani.

### 9.2 `php artisan agy:audit` — Audit Komisi & Klaim Agensi Penjualan
- **Tujuan & Invarian**: Memverifikasi komisi multi-tier agen, withholding pajak PPh 21/23, dan clawback retur.
- **Opsi & Parameter Utama**: `--dry-run, --settle-pending`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika komisi dibayarkan sesuai tiering; Exit 1 jika over-komisi atau salah kalkulasi.
- **SOP Remediasi Kegagalan**: Periksa formula tiering pada `agy_commission_tiers`; rekalkulasi saldo komisi pending via `--settle-pending`.

### 9.3 `php artisan ai:audit` — Audit Tata Kelola & Model Drift AI
- **Tujuan & Invarian**: Memeriksa model drift analitik prediktif, kuota inferensi, dan status karantina AI.
- **Opsi & Parameter Utama**: `--quarantine-check, --threshold=0.05`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika drift < ambang batas 5%; Exit 1 jika terjadi anomali model melebihi deviasi.
- **SOP Remediasi Kegagalan**: Isolasi model prediktif yang melenceng; kembalikan bobot inferensi ke model checkpoint sebelumnya.

### 9.4 `php artisan api:audit` — Audit Kepatuhan & Kuota API Gateway
- **Tujuan & Invarian**: Memvalidasi kuota rate-limit per tier, rekonsiliasi HMAC key, dan sanitasi log PII.
- **Opsi & Parameter Utama**: `--check-keys, --clean-logs`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika seluruh signature key valid dan log bersih dari PII; Exit 1 jika ditemukan kebocoran.
- **SOP Remediasi Kegagalan**: Lakukan rotasi key HMAC terdampak; bersihkan payload sensitif di cache gateway.

### 9.5 `php artisan ast:audit` — Audit Subledger Aset Tetap (PSAK 16/73)
- **Tujuan & Invarian**: Memverifikasi nilai buku aset, depresiasi komersial/fiskal, dan kapitalisasi CIP.
- **Opsi & Parameter Utama**: `--recalculate, --period=YYYY-MM`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika saldo aset = akun kontrol GL 1500; Exit 1 jika terjadi selisih depresiasi.
- **SOP Remediasi Kegagalan**: Jalankan `--recalculate` untuk memposting ulang jurnal depresiasi bulan berjalan yang tertinggal.

### 9.6 `php artisan ast:verify-chain` — Verifikasi Integritas Hash-Chain Aset
- **Tujuan & Invarian**: Memeriksa keutuhan rantai hash SHA-256 riwayat mutasi dan pemeliharaan aset.
- **Opsi & Parameter Utama**: `--asset-id=[UUID], --deep`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika seluruh rantai hash utuh; Exit 1 jika terjadi tamper data historis.
- **SOP Remediasi Kegagalan**: Telusuri event sequence yang rusak; pulihkan rekaman dari replikasi standby ber-timestamp valid.

### 9.7 `php artisan b2b:audit` — Audit Rekonsiliasi Escrow B2B Marketplace
- **Tujuan & Invarian**: Memverifikasi saldo rekening penampung escrow lelang surplus dan transaksi grosir.
- **Opsi & Parameter Utama**: `--release-stale, --dispute-check`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika saldo escrow sama persis dengan total pesanan aktif; Exit 1 jika ada selisih kas.
- **SOP Remediasi Kegagalan**: Telusuri pesanan lelang kadaluarsa; lepaskan dana escrow yang tertahan setelah masa sanggah.

### 9.8 `php artisan bank:reconcile` — Rekonsiliasi Double-Entry Ledger Sentral
- **Tujuan & Invarian**: Memvalidasi invarian fundamental: SUM(amount) == 0 per aset dan cached_balance == SUM(entries).
- **Opsi & Parameter Utama**: `--fix-cache, -v (verbose)`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika zero mismatch; Exit 1 jika ditemukan diskrepansi saldo atau debet!=kredit.
- **SOP Remediasi Kegagalan**: Gunakan `--fix-cache` jika selisih hanya pada cached_balance; selidiki transaksi tak seimbang manual via `ManualAdjustmentAction`.

### 9.9 `php artisan bidding:audit` — Audit Integritas Tender & Bidding Otomatis
- **Tujuan & Invarian**: Memverifikasi lelang tertutup, enkripsi penawaran vendor, dan hash anti-sniping.
- **Opsi & Parameter Utama**: `--verify-hashes`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika seluruh penawaran tersegel aman; Exit 1 jika terdeteksi manipulasi waktu tender.
- **SOP Remediasi Kegagalan**: Batalkan putaran lelang yang tidak sah; jadwalkan ulang tender pengadaan.

### 9.10 `php artisan chain:audit-all` — Pemeriksaan Menyeluruh Seluruh Rantai Kriptografis
- **Tujuan & Invarian**: Memvalidasi paspor kendaraan, kontrak, dokumen hukum, dan sertifikat mutu secara paralel.
- **Opsi & Parameter Utama**: `--parallel=4, --stop-on-failure`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika semua rantai kripto 100% valid; Exit 1 jika ada satu rantai rusak.
- **SOP Remediasi Kegagalan**: Identifikasi modul yang mengalami kerusakan hash; rujuk ke playbook pemulihan modul terkait.

### 9.11 `php artisan ck:audit` — Audit Dapur Satelit Cloud Kitchen & HPP
- **Tujuan & Invarian**: Memverifikasi sinkronisasi pesanan multi-brand dan konsumsi bahan resep dapur.
- **Opsi & Parameter Utama**: `--sync-kds, --outlet=[CODE]`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika HPP bahan baku sesuai porsi standar; Exit 1 jika terjadi deviasi limbah berlebih.
- **SOP Remediasi Kegagalan**: Lakukan penyesuaian stok bahan baku basi; audit porsi hidangan di KDS.

### 9.12 `php artisan clearing:audit` — Audit Kliring Antar-Bank & BI-FAST
- **Tujuan & Invarian**: Memvalidasi settlement transaksi transfer dana eksternal dan status VA.
- **Opsi & Parameter Utama**: `--reconcile-va, --date=YYYY-MM-DD`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika seluruh batch transfer Kliring selesai; Exit 1 jika ada transaksi pending > 2 jam.
- **SOP Remediasi Kegagalan**: Kirim permintaan status inquiry ke switching bank mitra; lakukan manual settlement jika ACK sukses.

### 9.13 `php artisan contracts:verify-chain` — Verifikasi Rantai Hash Kontrak Korporat
- **Tujuan & Invarian**: Memeriksa integritas digital seal klausul kontrak dan riwayat adendum.
- **Opsi & Parameter Utama**: `--contract-id=[ID]`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika segel kriptografi valid; Exit 1 jika draf kontrak dimodifikasi di luar alur resmi.
- **SOP Remediasi Kegagalan**: Tolak adendum yang tidak sah; lakukan rollback ke versi hash terakhir yang terverifikasi tim legal.

### 9.14 `php artisan core:verify-passports` — Verifikasi Paspor Digital Kendaraan
- **Tujuan & Invarian**: Memeriksa rantai riwayat kepemilikan, servis, dan kecelakaan pada kendaraan.
- **Opsi & Parameter Utama**: `--repair-cache`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika paspor digital utuh; Exit 1 jika rantai sequence terputus.
- **SOP Remediasi Kegagalan**: Cari UUID mobil terdampak; periksa `core_vehicle_events`; pulihkan sequence yang terputus dari audit log.

### 9.15 `php artisan ctr:audit` — Audit Subledger & Termin Kontrak
- **Tujuan & Invarian**: Memverifikasi jadwal penagihan termin dan pagu pembiayaan kontrak.
- **Opsi & Parameter Utama**: `--reconcile-milestones`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika termin teragendakan tepat waktu; Exit 1 jika terjadi tagihan ganda termin.
- **SOP Remediasi Kegagalan**: Batalkan invoice termin duplikat; selaraskan jadwal pencairan dengan Berita Acara Serah Terima.

### 9.16 `php artisan dist:audit` — Audit Jaringan Distribusi & Limit Kredit AR
- **Tujuan & Invarian**: Memverifikasi plafon kredit distributor, faktur pajak konsinyasi, dan kalkulasi rebate.
- **Opsi & Parameter Utama**: `--recalc-limits`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika saldo AR berada dalam batas plafon; Exit 1 jika distributor melampaui limit kredit.
- **SOP Remediasi Kegagalan**: Bekukan pemesanan baru bagi distributor yang macet; jalankan penagihan piutang intensif.

### 9.17 `php artisan dr:audit` — Audit Kesiapan Failover Multi-Region DR
- **Tujuan & Invarian**: Memeriksa latensi replikasi data DC sekunder, kuorum node Raft, dan checksum ledger.
- **Opsi & Parameter Utama**: `--check-quorum, --heartbeat-all`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika RPO=0 terpenuhi dan kuorum 2/3 aktif; Exit 1 jika node standby tertinggal WAL > 10MB.
- **SOP Remediasi Kegagalan**: Sinkronisasi ulang WAL log yang tertinggal; periksa konektivitas jaringan leased line antar-DC.

### 9.18 `php artisan ecosystem:audit-12-lines` — Audit Integrasi Menyeluruh 12 Lini Bisnis
- **Tujuan & Invarian**: Pemeriksaan transaksi end-to-end melintasi batas 12 lini bisnis konglomerasi.
- **Opsi & Parameter Utama**: `--full-matrix`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika tidak ada transaksi yang menggantung antar-modul; Exit 1 jika ada kebuntuan alur.
- **SOP Remediasi Kegagalan**: Telusuri event outbox yang tidak terkirim di modul sumber; re-dispatch event via bus.

### 9.19 `php artisan enterprise:audit` — Audit Tata Kelola Keuangan Holding
- **Tujuan & Invarian**: Memverifikasi kepatuhan pagu anggaran anggaran unit bisnis dan alokasi biaya manajemen.
- **Opsi & Parameter Utama**: `--fiscal-year=YYYY`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika realisasi beban tidak melebihi pagu; Exit 1 jika terjadi pelanggaran hard-stop.
- **SOP Remediasi Kegagalan**: Penerbitan surat peringatan over-budget; eskalasi ke CFO untuk persetujuan anggaran suplemen.

### 9.20 `php artisan epc:audit` — Audit Proyek Konstruksi & Monthly Certificate
- **Tujuan & Invarian**: Memvalidasi kurva-S, progres fisik lapangan, pemotongan retensi 5%, dan CIP.
- **Opsi & Parameter Utama**: `--project-id=[ID]`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika sertifikat MC sesuai progres fisik; Exit 1 jika over-claim pembayaran kontraktor.
- **SOP Remediasi Kegagalan**: Tahan persetujuan MC bermasalah; lakukan inspeksi fisik lapangan bersama konsultan pengawas.

### 9.21 `php artisan esg:audit` — Audit Pengukuran Emisi & Sertifikat Karbon
- **Tujuan & Invarian**: Memverifikasi kalkulasi gas rumah kaca Scope 1, 2, 3 dan penarikan kredit karbon.
- **Opsi & Parameter Utama**: `--verify-registry`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika emisi terukur sesuai standar GHG Protocol; Exit 1 jika data energi anomali.
- **SOP Remediasi Kegagalan**: Koreksi data konsumsi bahan bakar solar dan listrik pabrik; audit kalibrasi meteran emisi.

### 9.22 `php artisan ev:audit` — Audit Stasiun Pengisian EV & Baterai
- **Tujuan & Invarian**: Memverifikasi sesi pengisian daya listrik OCPP, kWh terpakai, dan State of Health.
- **Opsi & Parameter Utama**: `--check-sessions`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika tarif kWh tertagih tepat; Exit 1 jika terjadi selisih pengukuran daya meter.
- **SOP Remediasi Kegagalan**: Reset sesi charger yang macet; kalibrasi smart meter SPKLU bersama teknisi.

### 9.23 `php artisan flt:audit` — Audit Utilisasi & Pemeliharaan Armada Fleet
- **Tujuan & Invarian**: Memeriksa jadwal servis preventif berbasis odometer dan biaya sewa operasional.
- **Opsi & Parameter Utama**: `--check-maintenance`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika armada terawat sesuai jadwal; Exit 1 jika ada kendaraan terlambat servis > 1.000km.
- **SOP Remediasi Kegagalan**: Tandai kendaraan untuk masuk bengkel servis segera; alokasikan unit cadangan.

### 9.24 `php artisan governance:audit` — Audit Pemisahan Tugas (SoD) & Akses Pengguna
- **Tujuan & Invarian**: Memverifikasi larangan perangkapan peran terlarang dan masa aktif token.
- **Opsi & Parameter Utama**: `--scan-conflicts`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika zero conflict SoD; Exit 1 jika ditemukan pengguna dengan peran bertentangan.
- **SOP Remediasi Kegagalan**: Cabut salah satu peran yang berkonflik segera; laporkan temuan ke Head of Compliance.

### 9.25 `php artisan group:audit` — Audit Konsolidasi & Eliminasi Saldo Resiprokal
- **Tujuan & Invarian**: Memvalidasi eliminasi piutang/utang antar-perusahaan anak dan transfer pricing.
- **Opsi & Parameter Utama**: `--period=YYYY-MM`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika seluruh saldo resiprokal tereliminasi sempurna; Exit 1 jika ada selisih intercompany.
- **SOP Remediasi Kegagalan**: Lakukan rekonsiliasi SO-PO cermin; posting jurnal penyesuaian selisih kurs antar-entitas.

### 9.26 `php artisan hcm:audit` — Audit Penggajian Karyawan & Pajak PPh 21
- **Tujuan & Invarian**: Memvalidasi potongan BPJS Ketenagakerjaan/Kesehatan, tarif TER PPh 21, dan lembur.
- **Opsi & Parameter Utama**: `--month=YYYY-MM`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika slip gaji seimbang dengan kas keluar payroll; Exit 1 jika ada salah hitung pajak.
- **SOP Remediasi Kegagalan**: Koreksi perhitungan PPh 21 pada masa pajak berikutnya; selaraskan data NIK pegawai terdaftar.

### 9.27 `php artisan hsp:audit` — Audit Pasien, Billing & E-Apotek Rumah Sakit
- **Tujuan & Invarian**: Memeriksa integritas EMR, klaim BPJS INA-CBG, dan pengeluaran obat sistem FEFO.
- **Opsi & Parameter Utama**: `--verify-bpjs`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika rekam medis dan billing selaras; Exit 1 jika ada obat keluar tanpa resep sah.
- **SOP Remediasi Kegagalan**: Investigasi selisih stok farmasi; ajukan banding koreksi klaim BPJS yang ditolak.

### 9.28 `php artisan htl:audit` — Audit Penutupan Hari (Night Audit) Hotel
- **Tujuan & Invarian**: Memverifikasi pemisahan tagihan folio kamar, city ledger, dan sinkronisasi OTA.
- **Opsi & Parameter Utama**: `--hotel-id=[ID]`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika trial balance seimbang dan tanggal sistem ditutup; Exit 1 jika saldo folio gantung.
- **SOP Remediasi Kegagalan**: Selesaikan transaksi tamu yang belum settled; pindahkan saldo piutang perusahaan ke City Ledger.

### 9.29 `php artisan ins:audit` — Audit Polis Parametrik & Cadangan Klaim
- **Tujuan & Invarian**: Memeriksa rasio solvabilitas premi, dana cadangan klaim, dan pembayaran otomatis.
- **Opsi & Parameter Utama**: `--solvency-check`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika dana cadangan memenuhi ambang batas 120% RBC; Exit 1 jika solvabilitas menurun.
- **SOP Remediasi Kegagalan**: Suntik dana cadangan likuiditas dari rekening penjaminan induk; batasi penerbitan polis baru.

### 9.30 `php artisan intl:audit` — Audit Kepatuhan Pajak Internasional & P3B
- **Tujuan & Invarian**: Memverifikasi pemotongan Withholding Tax cross-border dan tarif tax treaty.
- **Opsi & Parameter Utama**: `--country=[CODE]`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika tarif P3B sesuai SKD resmi; Exit 1 jika terjadi salah potongan pajak luar negeri.
- **SOP Remediasi Kegagalan**: Ajukan permohonan restitusi pajak lebih bayar; perbarui sertifikat domisili fiskal vendor asing.

### 9.31 `php artisan lgx:audit-billing` — Audit Tagihan & Ongkos Kirim Logistik
- **Tujuan & Invarian**: Memverifikasi tarif berat volumetrik, diskon shipper, dan pengembalian dana COD.
- **Opsi & Parameter Utama**: `--reconcile-cod`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika kas COD tertagih cocok dengan setoran kurir; Exit 1 jika ada selisih kas COD.
- **SOP Remediasi Kegagalan**: Panggil kurir bersangkutan untuk menyetorkan kekurangan uang tunai hasil COD.

### 9.32 `php artisan lgx:audit-circular` — Audit Reverse Logistics & Daur Ulang
- **Tujuan & Invarian**: Memvalidasi pengembalian kemasan ramah lingkungan dan insentif pengembalian barang.
- **Opsi & Parameter Utama**: `--check-returns`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika inventaris daur ulang tercatat rapi; Exit 1 jika terjadi kebocoran kupon daur ulang.
- **SOP Remediasi Kegagalan**: Bekukan kupon insentif yang anomali; audit stok fisik wadah daur ulang di gudang.

### 9.33 `php artisan lgx:audit-coldchain` — Audit Rantai Dingin & Kepatuhan Farmasi
- **Tujuan & Invarian**: Memeriksa log suhu reefer container 2-8°C dan riwayat peringatan ekskursi.
- **Opsi & Parameter Utama**: `--verify-excursions`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika seluruh pengiriman biofarma memenuhi standar GDP; Exit 1 jika suhu jebol.
- **SOP Remediasi Kegagalan**: Karantina produk obat terdampak; terbitkan formulir investigasi penyimpangan mutu.

### 9.34 `php artisan lgx:verify-custody` — Verifikasi Rantai Kustodi Paket Kiriman
- **Tujuan & Invarian**: Memeriksa bukti serah terima digital ePoD, GPS tracking, dan tanda tangan penerima.
- **Opsi & Parameter Utama**: `--shipment-id=[ID]`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika rantai serah terima tidak terputus; Exit 1 jika paket hilang tanpa jejak scan.
- **SOP Remediasi Kegagalan**: Tuntut ganti rugi ke pihak hub transit terakhir; cairkan asuransi kehilangan barang.

### 9.35 `php artisan mall:audit-billing` — Audit Tagihan Sewa & Utilitas Tenant Mall
- **Tujuan & Invarian**: Memverifikasi tagihan sewa minimum, bagi hasil omzet (Turnover Rent), dan meteran PLN/Air.
- **Opsi & Parameter Utama**: `--mall-id=[ID]`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika seluruh invoice tenant terbit sesuai rumus sewa; Exit 1 jika ada tenant belum tertagih.
- **SOP Remediasi Kegagalan**: Jalankan penerbitan invoice perbaikan; hubungi tenant untuk auto-debit ulang.

### 9.36 `php artisan mfg:audit-costing` — Audit Biaya Produksi (WIP vs Standar)
- **Tujuan & Invarian**: Memeriksa varians bahan baku (PPV), varians efisiensi tenaga kerja, dan HPP produk.
- **Opsi & Parameter Utama**: `--plant-id=[ID]`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika varians biaya berada dalam batas toleransi 3%; Exit 1 jika terjadi pemborosan bahan.
- **SOP Remediasi Kegagalan**: Lakukan kalibrasi standar BOM; audit pemakaian material di lantai produksi (shop floor).

### 9.37 `php artisan mfg:qms-audit` — Audit Mutu & Karantina Produk Pabrik
- **Tujuan & Invarian**: Memverifikasi sertifikat analisa (CoA), status lot karantina, dan tindakan korektif CAPA.
- **Opsi & Parameter Utama**: `--status=quarantine`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika tidak ada produk cacat yang lolos ke gudang jadi; Exit 1 jika lot bermasalah bocor.
- **SOP Remediasi Kegagalan**: Tarik produk dari gudang distribusi segera; buka formulir penyelidikan CAPA.

### 9.38 `php artisan min:audit` — Audit Pengangkutan Tambang & Timbangan
- **Tujuan & Invarian**: Memeriksa kesesuaian tiket timbangan jembatan (weighbridge) dengan hasil assay kadar bijih.
- **Opsi & Parameter Utama**: `--mine-id=[ID]`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika tonase angkut konsisten antara pit dan pelabuhan; Exit 1 jika ada selisih > 0.5%.
- **SOP Remediasi Kegagalan**: Kalibrasi ulang sensor jembatan timbang; periksa potensi tumpahan batubara di jalan hauling.

### 9.39 `php artisan mobile:audit` — Audit Sinkronisasi Aplikasi Mobile Lapangan
- **Tujuan & Invarian**: Memeriksa antrean sinkronisasi offline-first dan integritas database SQLite lokal perangkat.
- **Opsi & Parameter Utama**: `--device-id=[ID]`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika seluruh transaksi offline telah terunggah sempurna; Exit 1 jika terjadi konflik data.
- **SOP Remediasi Kegagalan**: Jalankan protokol resolusi konflik Last-Write-Wins; perbarui cache master data pada aplikasi mobile.

### 9.40 `php artisan plm:audit` — Audit Siklus Hidup Produk & Riwayat ECO
- **Tujuan & Invarian**: Memverifikasi alur Engineering Change Order (ECO) dan konsistensi EBOM ke MBOM pabrik.
- **Opsi & Parameter Utama**: `--eco-id=[ID]`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika seluruh perubahan formula resep disetujui tim R&D; Exit 1 jika ada perubahan sepihak.
- **SOP Remediasi Kegagalan**: Batalkan ECO yang tidak sah; kembalikan formula produksi ke revisi resmi yang disetujui.

### 9.41 `php artisan pq:audit-chains` — Audit Kriptografi Pasca-Kuantum Rantai Hash
- **Tujuan & Invarian**: Memeriksa algoritma enkripsi dan signature berantai untuk ketahanan komputasi kuantum.
- **Opsi & Parameter Utama**: `--algorithm=all`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika seluruh signature memenuhi standar post-quantum; Exit 1 jika ditemukan cipher lemah.
- **SOP Remediasi Kegagalan**: Lakukan re-hashing data lama menggunakan kunci kriptografi quantum-safe.

### 9.42 `php artisan pricing:audit` — Audit Engine Harga & Waterfall Diskon
- **Tujuan & Invarian**: Memeriksa kepatuhan harga minimum (margin floor) dan konsistensi price lock.
- **Opsi & Parameter Utama**: `--item-id=[ID]`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika tidak ada penjualan di bawah harga pokok tanpa izin; Exit 1 jika margin bocor.
- **SOP Remediasi Kegagalan**: Koreksi aturan diskon bertingkat; batalkan promo yang mengakibatkan kerugian margin.

### 9.43 `php artisan proc:audit` — Audit Pengadaan Barang & 3-Way Match
- **Tujuan & Invarian**: Memverifikasi kecocokan antara PO, Surat Jalan Penerimaan (GRN), dan Faktur Tagihan (Invoice).
- **Opsi & Parameter Utama**: `--match-all`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika 3-way match seimbang 100%; Exit 1 jika terjadi diskrepansi jumlah/harga.
- **SOP Remediasi Kegagalan**: Tahan pembayaran tagihan faktur hingga vendor memberikan klarifikasi atau nota kredit.

### 9.44 `php artisan prp:audit` — Audit Operasional Properti & Kontrak Sewa
- **Tujuan & Invarian**: Memverifikasi okupansi ruang gedung dan kepatuhan pembayaran uang muka sewa.
- **Opsi & Parameter Utama**: `--property-id=[ID]`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika unit tersewa selaras dengan jadwal kas masuk; Exit 1 jika ada sewa tanpa kontrak sah.
- **SOP Remediasi Kegagalan**: Terbitkan surat pengosongan unit atau lakukan perpanjangan kontrak sewa resmi.

### 9.45 `php artisan prp:audit-bim` — Audit Model Digital Twin & BIM Bangunan
- **Tujuan & Invarian**: Memverifikasi keselarasan sensor IoT gedung dengan model representasi digital twin.
- **Opsi & Parameter Utama**: `--building-id=[ID]`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika telemetri suhu dan daya sinkron dengan model BIM; Exit 1 jika data sensor terputus.
- **SOP Remediasi Kegagalan**: Periksa konektivitas gateway LoRaWAN di lantai gedung; restart modul sensor yang offline.

### 9.46 `php artisan prp:audit-flex` — Audit Reservasi Ruang Flex-Space & Coworking
- **Tujuan & Invarian**: Memeriksa pencegahan pemesanan ganda (double-booking) dan tagihan per jam.
- **Opsi & Parameter Utama**: `--check-conflicts`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika pemesanan anti-overlap 100% terjaga; Exit 1 jika terjadi tumpang tindih jadwal.
- **SOP Remediasi Kegagalan**: Relokasi pengguna terdampak ke ruang pertemuan yang setara; perbaiki lock reservation atomik.

### 9.47 `php artisan prp:audit-smartbuilding` — Audit Sistem Otomasi Gedung Pintar (BMS)
- **Tujuan & Invarian**: Memverifikasi efisiensi pendingin HVAC, pencahayaan otomatis, dan akses pintu turnstile.
- **Opsi & Parameter Utama**: `--energy-mode`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika konsumsi energi gedung optimal; Exit 1 jika sistem HVAC menyala di ruang kosong.
- **SOP Remediasi Kegagalan**: Terapkan profil suhu hemat energi setback otomatis; periksa sensor kehadiran PIR.

### 9.48 `php artisan ptn:audit` — Audit Kemitraan Strategis & Revenue Share
- **Tujuan & Invarian**: Memverifikasi perhitungan bagi hasil mitra co-selling dan pencapaian Joint Business Plan.
- **Opsi & Parameter Utama**: `--partner-id=[ID]`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika bagi hasil terhitung akurat sesuai kontrak; Exit 1 jika ada perselisihan saldo mitra.
- **SOP Remediasi Kegagalan**: Lakukan rekonsiliasi data penjualan bersama perwakilan mitra; terbitkan settlement statement.

### 9.49 `php artisan rwa:audit` — Audit Tokenisasi Aset Riil & Cadangan Dividen
- **Tujuan & Invarian**: Memeriksa keseimbangan antara jumlah token beredar dengan nilai valuasi aset fisik.
- **Opsi & Parameter Utama**: `--token-id=[ID]`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika total token = valuasi aset terbitan dan dividen seimbang; Exit 1 jika ada deviasi minor unit.
- **SOP Remediasi Kegagalan**: Koreksi alokasi dividen integer minor unit; salurkan sisa pembulatan ke akun cadangan reserve.

### 9.50 `php artisan security:audit` — Audit Keamanan Siber & Deteksi Intrusi
- **Tujuan & Invarian**: Memeriksa log upaya login gagal, brute force, kebocoran token, dan integritas firewall.
- **Opsi & Parameter Utama**: `--scan-ips`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika sistem aman dari upaya penetrasi aktif; Exit 1 jika terdeteksi ancaman serangan.
- **SOP Remediasi Kegagalan**: Blokir alamat IP penyerang di firewall Cloudflare; paksa reset kata sandi akun terdampak.

### 9.51 `php artisan sim:audit` — Audit Simulasi Kernel & Skenario Stress-Test
- **Tujuan & Invarian**: Memverifikasi determinisme simulasi beban transaksi ultra tinggi dan pemutaran event.
- **Opsi & Parameter Utama**: `--scenario=[NAME]`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika hasil simulasi konsisten dan terisolasi dari data produksi; Exit 1 jika ada data bocor.
- **SOP Remediasi Kegagalan**: Bersihkan database sandbox pengujian simulasi; periksa isolasi tenancy pengujian.

### 9.52 `php artisan tf:audit` — Audit Trade Finance & Letter of Credit (L/C)
- **Tujuan & Invarian**: Memeriksa diskrepansi dokumen ekspor-impor terhadap aturan UCP 600 dan jatuh tempo SCF.
- **Opsi & Parameter Utama**: `--lc-id=[ID]`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika dokumen ekspor-impor clean tanpa diskrepansi; Exit 1 jika ada dokumen ditolak bank.
- **SOP Remediasi Kegagalan**: Perbaiki dokumen faktur komersial dan bill of lading sebelum batas waktu presentasi berakhir.

### 9.53 `php artisan tlm:audit` — Audit Telemetri IoT & Skor Pengemudi
- **Tujuan & Invarian**: Memverifikasi ingestion data sensor CAN-bus kendaraan, pengereman mendadak, dan BBM.
- **Opsi & Parameter Utama**: `--device-id=[ID]`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika data telemetri mengalir lancar; Exit 1 jika ada paket data telemetri yang korup.
- **SOP Remediasi Kegagalan**: Perbarui firmware modul IoT OBD-II kendaraan; periksa kabel antena GPS.

### 9.54 `php artisan tower:audit` — Audit Menara Kendali Rantai Pasok (Control Tower)
- **Tujuan & Invarian**: Memverifikasi radar peringatan dini hambatan pasokan, buffer stock, dan alokasi CTP.
- **Opsi & Parameter Utama**: `--region=[CODE]`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika peringatan rantai pasok terpetakan dengan mitigasi; Exit 1 jika buffer stock jebol.
- **SOP Remediasi Kegagalan**: Kirim pesanan darurat antar-hub pergudangan; aktifkan rute ekspedisi alternatif.

### 9.55 `php artisan trade:audit` — Audit Operasi Kepabeanan & Ekspor-Impor
- **Tujuan & Invarian**: Memvalidasi dokumen PIB/PEB, pembayaran bea masuk, dan kepatuhan Incoterms 2020.
- **Opsi & Parameter Utama**: `--customs-id=[ID]`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika dokumen bea cukai telah mendapat status Surat Pemberitahuan Jalur Hijau; Exit 1 jika tertahan.
- **SOP Remediasi Kegagalan**: Lengkapi dokumen sertifikat asal (COO) dan hasil uji mutu lab bea cukai.

### 9.56 `php artisan treasury:audit` — Audit Treasury Multi-Valas & Posisi Hedging
- **Tujuan & Invarian**: Memverifikasi posisi kas valuta asing, kurs buku besar scaled 1e6, dan kontrak lindung nilai.
- **Opsi & Parameter Utama**: `--revalue-fx`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika nilai buku valas selaras dengan kurs tengah BI; Exit 1 jika eksposur valas terbuka.
- **SOP Remediasi Kegagalan**: Jalankan perintah `--revalue-fx` untuk membukukan laba/rugi selisih kurs yang belum terealisasi.

### 9.57 `php artisan ven:audit` — Audit Akses Gate Venue & Penjualan Konser
- **Tujuan & Invarian**: Memverifikasi tiket turnstile RFID, rekonsiliasi minimum spend VIP, dan kapasitas area.
- **Opsi & Parameter Utama**: `--event-id=[ID]`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika jumlah tiket terjual sama dengan pembacaan sensor turnstile; Exit 1 jika over-capacity.
- **SOP Remediasi Kegagalan**: Kunci pintu masuk gate; arahkan penonton ke zona cadangan dengan pemantauan petugas K3.

### 9.58 `php artisan vmi:audit` — Audit Vendor-Managed Inventory & C2M
- **Tujuan & Invarian**: Memeriksa stok konsinyasi milik vendor di gudang hub dan pesanan replenishment otomatis.
- **Opsi & Parameter Utama**: `--vendor-id=[ID]`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika stok konsinyasi terhitung akurat; Exit 1 jika terjadi kehilangan barang titipan.
- **SOP Remediasi Kegagalan**: Terbitkan laporan pertanggungjawaban fisik konsinyasi; lakukan pembayaran atas barang terpakai.

### 9.59 `php artisan vnd:audit` — Audit Jaringan Mesin Vending Pintar
- **Tujuan & Invarian**: Memverifikasi pembayaran QRIS, ketersediaan stok produk di dispenser, dan temperatur pendingin.
- **Opsi & Parameter Utama**: `--machine-id=[ID]`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika mesin berfungsi prima dan uang terkumpul cocok; Exit 1 jika dispenser macet.
- **SOP Remediasi Kegagalan**: Kirim teknisi lapangan untuk mengeluarkan produk yang tersangkut dan isi ulang stok produk.

### 9.60 `php artisan wm:audit` — Audit Portofolio Robo-Advisor & Profil Risiko
- **Tujuan & Invarian**: Memeriksa kepatuhan portofolio nasabah terhadap profil risiko dan toleransi drawdown.
- **Opsi & Parameter Utama**: `--rebalance-all`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika seluruh portofolio nasabah berada dalam koridor risiko yang disetujui; Exit 1 jika drift > 5%.
- **SOP Remediasi Kegagalan**: Jalankan `--rebalance-all` untuk mereposisi alokasi reksa dana dan obligasi nasabah.

### 9.61 `php artisan wms:audit` — Audit Tata Kelola Pergudangan & Stock Opname
- **Tujuan & Invarian**: Memverifikasi alokasi bin rak gudang, status putaway, dan akurasi cycle count empat mata.
- **Opsi & Parameter Utama**: `--warehouse-id=[ID]`.
- **Konvensi Kode Keluar (Exit Code)**: Exit 0 jika akurasi inventaris gudang mencapai minimal 99.8%; Exit 1 jika ada selisih bin stok.
- **SOP Remediasi Kegagalan**: Lakukan stock opname fisik ulang pada lorong rak terdampak; sesuaikan catatan inventory.

---

## 10. SOP Pemeliharaan Partisi Database PostgreSQL & Query Budget Enforcer

Tabel dengan volume data tinggi (`bank_ledger_entries`, `core_audit_logs`, `core_webhook_outbox`, `lgx_telematics_pings`) menggunakan partisi berbasis waktu bulanan:

1. **Pembuatan Partisi Otomatis**: Command terjadwal `php artisan db:partition-maintenance` berjalan setiap tanggal 25 untuk menyiapkan tabel partisi bulan berikutnya.
2. **Penegakan Query Budget**: Setiap controller dibatasi maksimal mengeksekusi 15 query database per HTTP request. Jika melebihi batas, `QueryBudgetEnforcer` memicu peringatan ke log dan Sentry.
3. **Pembersihan Data Historis**: Data log lebih dari 12 bulan diarsipkan secara otomatis ke cold storage S3 Glacier sebelum partisi lama dihapus (`DROP TABLE`).

## 11. SOP Pengujian Bencana & Latihan DR Multi-Region (Disaster Recovery Drill)

Simulasi failover multi-region dijalankan setiap semester untuk menjamin kesiapan tim dan keandalan sistem:

1. **Pemberitahuan Stakeholder**: Kirim pengumuman jendela pemeliharaan minimal T-7 hari ke seluruh unit bisnis.
2. **Verifikasi Sinkronisasi WAL**: Pastikan selisih replikasi antara DC Jakarta dan DC Bali adalah 0 byte.
3. **Simulasi Pemutusan Jaringan**: Matikan interface jaringan data center primer secara terkontrol.
4. **Validasi Promosi Otomatis**: Periksa apakah node Raft saksi mempromosikan standby replica menjadi master dalam waktu $< 30$ detik.
5. **Verifikasi Transaksi Buku Besar**: Jalankan `php artisan bank:reconcile` di DC sekunder untuk memastikan zero balance loss.
6. **Failback**: Setelah latihan selesai, pulihkan peran master kembali ke DC primer menggunakan streaming reverse replication.

---

## 12. Panduan Eksekusi Production Containerization Stack (Docker & Compose)

Platform SuperWeb Enterprise menyediakan image kontainer multi-stage siap produksi berbasis PHP 8.4 CLI/FPM/FrankenPHP dengan ekstensi enterprise lengkap (`pdo_mysql`, `bcmath`, `intl`, `pcntl`, `redis`, dan `opcache` preloading).

### 12.1 Membangun Image Produksi Multi-Stage
```bash
# Build image aplikasi produksi
docker build -t superweb-enterprise:latest -f Dockerfile .
```

### 12.2 Menjalankan Klaster Layanan Lengkap (docker-compose.prod.yml)
Klaster produksi mengorkestrasi 7 layanan terintegrasi:
```bash
# Jalankan seluruh stack kontainerisasi produksi di latar belakang
docker compose -f docker-compose.prod.yml up -d

# Periksa status kesehatan seluruh kontainer
docker compose -f docker-compose.prod.yml ps
```

**Daftar Layanan & Parameter Operasional:**
1. **`app`** (`superweb_app`): Server aplikasi web utama (PHP 8.4, port `8000`, non-root user `www-data`).
2. **`worker`** (`superweb_worker`): Worker antrean asinkron (`php artisan queue:work --tries=3 --timeout=90`).
3. **`scheduler`** (`superweb_scheduler`): Scheduler periodik platform (`php artisan schedule:work`).
4. **`mysql`** (`superweb_mysql`): Database relasional MySQL 8.4 dengan isolasi transaksi `READ-COMMITTED` dan `innodb_lock_wait_timeout=50`.
5. **`redis`** (`superweb_redis`): Redis 7 Alpine untuk high-throughput cache, rate-limiter, dan broker antrean.
6. **`prometheus`** (`superweb_prometheus`): Scraper metrik real-time port `9090`.
7. **`grafana`** (`superweb_grafana`): Visualisasi dashboard port `3000`.

---

## 13. Observabilitas Real-Time Prometheus & Grafana 46 Modul

Observabilitas platform SuperWeb mencakup metrik telemetri real-time lintas seluruh 46 modul kanonikal, kepatuhan arsitektur A1–A13, command audit, dan konsistensi buku besar finansial.

### 13.1 Pengambilan Metrik Prometheus via Artisan CLI
```bash
# Cetak metrik Prometheus Exposition Format (text/plain; version=0.0.4)
php artisan metrics:prometheus
```

### 13.2 Endpoint Observabilitas HTTP
Akses endpoint metrik melalui HTTP:
```bash
curl -s http://127.0.0.1:8000/ops/metrics
```
Output menyediakan format standar:
- `superweb_modules_registered_total`: Total modul kanonikal aktif (46 modul).
- `superweb_arch_scan_violations_total{rule="A1..A13"}`: Pelanggaran arsitektur per aturan (target wajib: `0`).
- `superweb_audit_commands_registered_total`: Total command audit dan rekonsiliasi yang terdaftar.
- `superweb_routes_registered_total`: Total rute aplikasi yang aktif.
- `superweb_ledger_accounts_total` & `superweb_ledger_entries_total`: Total akun dan jurnal pembukuan berimbang.
- `superweb_module_tables_count{module="..."}`: Distribusi tabel database untuk seluruh 46 modul kanonikal.

### 13.3 Konfigurasi Scrape & Dashboard Grafana
- **Konfigurasi Prometheus**: `ops/prometheus/prometheus.yml` (mengekstrak metrik dari `app:8000/ops/metrics` setiap 10 detik).
- **Dashboard Grafana**: `ops/grafana/dashboards/superweb-46-modules-overview.json` siap impor di `http://localhost:3000` (User: `admin`, Password: `superweb_grafana_secret`).

---

## 14. Validasi & Regenerasi Spesifikasi Kontrak OpenAPI 3.1

Dokumentasi antarmuka publik dan gateway platform divalidasi terhadap kontrak arsitektur dan rute aktual menggunakan OpenAPI 3.1.0:

### 14.1 Memvalidasi Kontrak Spesifikasi
```bash
php artisan openapi:validate
```
**Parameter Validasi Wajib**:
- Kepatuhan versi spesifikasi OpenAPI 3.1.0.
- 100% rute aplikasi (`api/*`) terdaftar pada spesifikasi.
- Cakupan penuh 46/46 modul kanonikal pada tags dokumen.
- Format mata uang non-float IEEE 754 (`integer` / `int64` minor unit).
- Header idempotensi (`Idempotency-Key`) dan tracing (`X-Correlation-Id`).
- Skema galat standar RFC 7807 Problem Details.

### 14.2 Regenerasi Dokumen Spesifikasi Resmi
```bash
# Menghasilkan ulang dokumen JSON, YAML, dan Interactive Explorer
php artisan openapi:generate
```

---

## 15. Verifikasi Ketahanan Konkurensi & Simulasi Chaos Engineering

Untuk memastikan ketahanan platform menghadapi beban transaksi ekstrem dan kegagalan komponen tak terduga:

### 15.1 Stress Testing Konkurensi Transaksi Finansial
```bash
# Menjalankan stress test konkurensi pada transaksi buku besar dan subledger
php artisan platform:stress-concurrency --iterations=25

# Mode cepat untuk lingkungan CI:
php artisan platform:stress-concurrency --iterations=10
```
**Invarian yang Dijamin**:
- Saldo kas buku besar tidak pernah bernilai negatif (`no negative balances`).
- Tidak terjadi deadlock atau locking timeout (`zero deadlocks`).
- Integritas debet/kredit tetap seimbang sempurna ($\sum \text{debet} = \sum \text{kredit}$).

### 15.2 Verifikasi Resiliensi & Fault Injection (Chaos Verification)
```bash
# Menjalankan suite verifikasi chaos engineering
php artisan platform:chaos-verify
```
**Cakupan Pengujian**:
- Injeksi kegagalan transaksi jaringan dan kegagalan commit parsial.
- Mekanisme idempotensi transaksi berulang (idempotency token deduplication).
- Pemulihan otomatis idempotency outbox dan quarantine state machine.

---

## 16. Operasi Keamanan Siber, Manajemen Kunci & Respons Insiden

### 16.1 Prosedur Rotasi APP_KEY (Master Encryption Key)
Kunci aplikasi `APP_KEY` mengamankan seluruh atribut Eloquent yang menggunakan casting `encrypted` (NIK, NPWP, formula, dan webhook secret). Jika terjadi rotasi terjadwal atau kompromi kunci:

1. **Persiapan Backup Penuh**:
   ```bash
   # Buat salinan database produksi sebelum rotasi
   mysqldump -u autoserve -p autoserve_prod > /backup/pre_key_rotation_$(date +%Y%m%d%H%M%S).sql
   ```
2. **Re-enkripsi Kolom Basis Data**:
   Simpan kunci lama pada konfigurasi fallback, lalu jalankan re-enkripsi:
   ```bash
   php artisan key:rotate --old-key="base64:OLD_KEY_HERE"
   ```
3. **Pembaruan Konfigurasi & Restart Worker**:
   - Perbarui `APP_KEY` di secrets manager (Vault / AWS Secrets Manager).
   - Jalankan `php artisan config:cache`.
   - Restart seluruh background queue worker: `php artisan queue:restart`.

### 16.2 Prosedur Rotasi BLIND_INDEX_PEPPER
Blind index digunakan untuk pencarian eksak pada data terenkripsi tanpa membocorkan plaintext (`App\Security\BlindIndex`):
1. **Pembaruan Pepper di Lingkungan**:
   - Tetapkan `BLIND_INDEX_PEPPER_NEW` di secrets manager.
2. **Jalankan Rekalkulasi Blind Index**:
   ```bash
   # Jalankan re-indexing bertahap dengan chunking
   php artisan security:reindex-blind-index --chunk=500
   ```
3. **Verifikasi Pencarian Eksak**:
   ```bash
   php artisan test tests/Keamanan/R3/R3VerificationTest.php --filter=test_r3_2_encryption_and_blind_index_behavior
   ```

### 16.3 Protokol Tanggap Darurat Kebocoran Kredensial (Incident Response Plan)
Jika terdeteksi insiden kebocoran kredensial, token akses, atau database:
1. **Fase 1 — Isolasi (Containment)**:
   - Blokir akses API eksternal yang terdampak: `php artisan intg:revoke-client {client_id}`.
   - Cabut sesi aktif seluruh pengguna: `php artisan session:flush-all` atau flush database sessions.
   - Batasi akses ingress di Web Application Firewall (WAF) Cloudflare.
2. **Fase 2 — Eradikasi (Eradication)**:
   - Rotasi instan `APP_KEY`, `BLIND_INDEX_PEPPER`, dan `JWT_SECRET`.
   - Reset kredensial database dan Redis di `.env` / Kubernetes secrets.
   - Paksa perubahan kata sandi untuk akun administratif: `php artisan user:force-password-reset --role=admin`.
3. **Fase 3 — Pemulihan (Recovery) & Verifikasi**:
   - Jalankan audit lengkap integritas rantai nilai: `php artisan chain:audit-all`.
   - Jalankan audit rekonsiliasi buku besar: `php artisan bank:reconcile`.
   - Jalankan suite keamanan: `./vendor/bin/pest tests/Keamanan`.

### 16.4 Matriks Kontak Darurat Tim Keamanan & SRE
| Peran | Pihak Bertanggung Jawab | Kanal Komunikasi | SLA Respons |
|---|---|---|---|
| **Incident Commander** | Lead Platform Engineer | Hotline / PagerDuty P1 | < 15 Menit |
| **AppSec Lead** | Security Operations Center (SOC) | security@autoserve.test | < 30 Menit |
| **Data Protection Officer (DPO)**| Legal & Compliance Officer | dpo@autoserve.test | < 60 Menit |
| **Infra & Database Admin** | SRE Lead | pagerduty:sre-oncall | < 15 Menit |

### 16.5 Konfigurasi Session Secure Cookie (K-13)
Pada lingkungan produksi (`APP_ENV=production`), transmisi session cookie wajib dienkripsi via HTTPS dengan parameter cookie `secure: true`.
- Nilai default pada `config/session.php`: `'secure' => env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production')`.
- Di lingkungan produksi, `SESSION_SECURE_COOKIE` WAJIB bernilai `true` (atau dibiarkan default otomatis mengikuti `APP_ENV=production`).
- Di lingkungan lokal/pengembangan, variabel `SESSION_SECURE_COOKIE` dikosongkan/dikomentari di `.env` sehingga bernilai `false`, memungkinkan login sesi bekerja pada HTTP biasa.



