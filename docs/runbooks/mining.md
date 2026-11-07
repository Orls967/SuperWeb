# Runbook Operasional Modul Pertambangan (Mining)

Runbook ini memuat panduan eksekusi perintah terjadwal (cron/schedule), command operasional manual, penanganan kegagalan, dan prosedur pemulihan (disaster recovery) untuk modul Pertambangan.

---

## 1. Daftar Artisan Commands & Jadwal Eksekusi

### 1.1 `mining:phase-plan {month}`
- **Tujuan**: Memecah target produksi bulanan ke dalam alokasi shift harian berdasarkan ketersediaan jam efektif (M1 Weighted Time Phasing).
- **Jadwal Eksekusi**: Bulanan, setiap tanggal 28 pukul 23:00 UTC (menjelang bulan baru).
- **Idempotensi**: Idempoten penuh. Jika dijalankan ulang untuk bulan yang sama, rencana yang sudah `active` tidak ditimpa; hanya shift yang belum dimulai yang disinkronkan.
- **Perintah Eksekusi**:
  ```bash
  php artisan mining:phase-plan 2026-11
  ```
- **Pemulihan Bila Gagal**: Periksa apakah tabel `min_external_factors` memiliki data jam hujan untuk site tersebut. Jika kosong, isi parameter default.

### 1.2 `mining:ingest-telemetry`
- **Tujuan**: Memproses batch telemetri IoT armada alat berat (suhu engine, tekanan hidrolik, jam operasi, getaran) secara idempoten.
- **Jadwal Eksekusi**: Setiap 5 menit (`*/5 * * * *`).
- **Idempotensi**: Idempoten melalui unique constraint `(equipment_id, sequence_number)` pada tabel `min_equipment_telemetries`.
- **Perintah Eksekusi**:
  ```bash
  php artisan mining:ingest-telemetry
  ```
- **Pemulihan Bila Gagal**: Periksa konektivitas MQTT/IoT broker dan keutuhan format payload JSON.

### 1.3 `mining:shift-close {date} {shift}`
- **Tujuan**: Menutup shift kerja tambang, merekonsiliasi total tonase siklus hauling, dan membukukan tagihan kontraktor angkut ke buku besar.
- **Jadwal Eksekusi**: 3 kali sehari di akhir shift:
  - Shift 1: 15:15 WITA (`07:15 UTC`)
  - Shift 2: 23:15 WITA (`15:15 UTC`)
  - Shift 3: 07:15 WITA (`23:15 UTC`)
- **Idempotensi**: Idempoten melalui idempotency key `min:shift_bill:{pit_id}:{shift_date}:{shift_code}` pada ledger posting.
- **Perintah Eksekusi**:
  ```bash
  php artisan mining:shift-close 2026-11-07 SHIFT_1
  ```
- **Pemulihan Bila Gagal**: Periksa apakah semua siklus hauling telah berstatus `COMPLETED` atau `ABORTED`. Jika ada siklus yang menggantung di status `LOADING`, gunakan menu override dispatcher sebelum menjalankan ulang command.

### 1.4 `mining:fuel-anomaly-scan {date}`
- **Tujuan**: Memindai seluruh pemakaian solar alat berat harian menggunakan regresi linier baseline M3, mendeteksi kasus deviasi $> 15\%$, dan memicu saga penahanan dana kontraktor (hold).
- **Jadwal Eksekusi**: Harian, pukul 01:00 UTC.
- **Idempotensi**: Idempoten melalui case number unik `FAC-{shift_date}-{equipment_code}`.
- **Perintah Eksekusi**:
  ```bash
  php artisan mining:fuel-anomaly-scan 2026-11-07
  ```

### 1.5 `min:audit` (atau `mining:audit`)
- **Tujuan**: Audit dua sumber untuk memverifikasi konsistensi status armada, keutuhan hash-chain timbangan, kesetimbangan saldo hold bahan bakar terhadap buku besar, dan deteksi anomali tanpa bukti.
- **Jadwal Eksekusi**: Harian sebelum settlement dan otomatis di CI gate.
- **Perintah Eksekusi**:
  ```bash
  php artisan min:audit
  ```
- **Tindakan Bila Exit Code $\ne 0$**: Tinjau pesan error pada tabel audit. Jika saldo hold tidak seimbang, periksa apakah ada manipulasi langsung pada tabel `min_contractor_payment_holds` tanpa melalui `FuelAnomalyHoldSaga`.
