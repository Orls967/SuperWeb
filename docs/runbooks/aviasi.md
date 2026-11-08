# Runbook Operasional Modul Aviation (Lini 26 / Fase 177)

Runbook ini menjadi rujukan teknis resmi bagi tim Site Reliability Engineering (SRE), DevOps, dan Chief Dispatcher dalam mengelola modul Aviasi di lingkungan produksi.

---

## 1. Daftar Perintah Artisan & Jadwal Cron

Modul Aviasi menyediakan 11 perintah Artisan untuk automasi, pemantauan kelaikan, rekonsiliasi buku besar, dan pembersihan data sesuai UU PDP:

| Perintah Artisan | Frekuensi Cron | Deskripsi & Tujuan | Toleransi Kegagalan |
|---|---|---|---|
| `php artisan avi:audit` | Setiap 1 jam (`0 * * * *`) | Rekonsiliasi dua sumber: kapasitas inventori vs tiket, kargo hash chain, refund vs ledger, W&B departures. | Nol toleransi (exit code != 0 memicu PagerDuty P2). |
| `php artisan avi:expire-holds` | Setiap 5 menit (`*/5 * * * *`) | Melepaskan kursi held yang melewati batas TTL (15 menit) kembali ke inventori komersial. | Idempoten, aman diulang kapan saja. |
| `php artisan avi:slot-usage-evaluate` | Mingguan (Minggu `02:00`) | Menghitung rasio utilisasi slot 80/20 IATA WASG untuk musim depan (`W26`/`S27`). | Peringatan jika data penerbangan belum lengkap. |
| `php artisan avi:sync-factors` | Setiap 6 jam (`0 */6 * * *`) | Sinkronisasi harga avtur Jet A-1 dari MOPS Pertamina dan status peringatan debu vulkanik VONA. | Menggunakan nilai cache terakhir bila feed timeout. |
| `php artisan avi:purge-pii` | Bulanan (Tgl 1 `03:00`) | Redaksi data identitas penumpang (nama lengkap, NIK/paspor) yang telah terbang > 90 hari demi kepatuhan UU PDP No. 27/2022. | Append-only audit log harus tetap utuh. |
| `php artisan avi:manifest {flight_number}` | Ad-hoc (D-1 Jam keberangkatan) | Menghasilkan file CSV manifes resmi penumpang dan muatan kargo untuk otoritas bandara dan navigasi udara. | Wajib tersedia sebelum status penerbangan `airworthy_cleared`. |
| `php artisan avi:dispatch-scan` | Setiap 15 menit | Memeriksa kepatuhan limit waktu dispatch seluruh penerbangan hari berjalan. | Peringatan pra-keberangkatan. |
| `php artisan avi:maintenance-forecast` | Harian (`04:00`) | Proyeksi pemeliharaan berkala (A-Check jam terbang dan C-Check siklus pendaratan). | Notifikasi ke MRO Planner. |
| `php artisan avi:refund-settle` | Harian (`23:30`) | Pemrosesan kliring batch pencairan refund tiket yang telah disetujui. | Terkunci pada transaksi ledger bank. |
| `php artisan avi:cargo-manifest` | Ad-hoc per penerbangan | Verifikasi muatan kontainer ULD dan kargo transit. | Wajib dicocokkan sebelum pintu kargo ditutup. |
| `php artisan avi:slot-season-plan` | Musiman | Inisialisasi pool slot kosong untuk koordinasi musim baru. | Diawasi langsung oleh Slot Coordinator. |

---

## 2. Prosedur Tanggap Darurat (Emergency Runbooks)

### 2.1 Peringatan Debu Vulkanik (VONA Alert RED)
1. **Identifikasi Dampak:**
   - Cek rute dan bandara yang berada dalam radius abu vulkanik aktif:
     ```bash
     php artisan tinker --execute="Modules\Aviation\Domain\Models\AviationExternalFactor::where('factor_type', 'VONA_ASH_ALERT')->latest()->first();"
     ```
2. **Penutupan Bandara Terdampak:**
   - Koordinasikan dengan NOTAM otoritas AirNav Indonesia.
   - Set status penerbangan terdampak ke `cancelled`:
     ```bash
     # Penerbangan yang dibatalkan akan otomatis memicu FlightCancellationMassRefundSaga
     ```
3. **Eksekusi Saga Refund Massal:**
   - Jalankan rekonsiliasi refund massal untuk membebaskan unearned revenue dan membukukan kewajiban `avi:refund_payable:IDR`.
4. **Penyediaan Akomodasi IRROPS:**
   - Sistem otomatis memesan blok kamar hotel bandara melalui `HotelIrropsAccommodationService` bagi penumpang transit terdampak penundaan bermalam (PM 89/2015).

### 2.2 Penanganan Kegagalan Transaksi Pembukuan Buku Besar (Ledger Invariant Breach)
Bila perintah `php artisan avi:audit` menghasilkan peringatan `STATUS: GAGAL`:
1. Periksa log detail selisih:
   ```bash
   php artisan avi:audit --json > /var/log/aviation_audit_failure.json
   ```
2. Identifikasi apakah anomali berasal dari:
   - **Kapasitas Kursi:** Kursi terjual melampaui kapasitas kabin fisik + batas oversell. Jalankan `php artisan avi:expire-holds` untuk membersihkan penahanan fiktif.
   - **Kargo Tanpa Bukti Kustodi:** Periksa AWB kargo yang belum memiliki tanda tangan hash chain genesis. Hubungi supervisor gudang kargo lini satu.
   - **Selisih Refund vs Ledger:** Bandingkan mutasi tabel `avi_refunds` terhadap baris jurnal akun `avi:refund_payable:IDR` di modul Banking.

---

## 3. Kebijakan Retensi & Privasi Data Pribadi (UU No. 27/2022)

- Seluruh data nama penumpang disimpan menggunakan enkripsi simetris aplikasi (`encrypted` Eloquent cast).
- Pencarian data penumpang untuk keperluan pelaporan atau manifes menggunakan `pii_blind_index` (HMAC/SHA-256 ber-kunci).
- Tiket dengan status `flown` yang telah melewati 90 hari kalender akan dibersihkan bidang nama aslinya menjadi `[REDACTED_PDP]` secara permanen melalui perintah `avi:purge-pii`.
- Riwayat transisi status tiket pada tabel `avi_ticket_transitions` tetap dipertahankan sebagai bukti audit forensik tanpa mengekspos PII penumpang.

