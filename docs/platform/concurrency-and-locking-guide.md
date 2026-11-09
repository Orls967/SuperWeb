# Panduan Manajemen Konkurensi & Kunci Terdistribusi Pipeline

Dokumen ini menjelaskan strategi orkestrasi kontrol konkurensi, penguncian lingkungan (environment locking), dan pencegahan kondisi balapan (race condition) pada subsistem penerapan Platform.

---

## 1. Urgensi Kontrol Konkurensi pada Sistem Rilis

Dalam ekosistem rekayasa perangkat lunak multi-tim:
- Dua atau lebih tim dapat mencoba menerapkan perubahan ke lingkungan produksi atau staging pada waktu yang bersamaan.
- Penerapan tumpang tindih (overlapping deployments) dapat menyebabkan konflik versi artefak, korupsi migrasi skema database yang berjalan paralel, dan kegagalan verifikasi metrik canary.
- Diperlukan mekanisme penguncian terdistribusi yang adil (fair lock leasing) untuk menjamin eksklusivitas lingkungan deployment selama fase kritis berlangsung.

---

## 2. Arsitektur Lease-Based Distributed Locking

Modul Platform mengimplementasikan penguncian terdistribusi menggunakan cache store (Redis/Database) melalui kelas `PipelineConcurrencyLockManager`:

```
[ Inisiasi Penerapan Pipeline ]
              │
              ▼
[ Periksa Ketersediaan Kunci Lingkungan: plt:pipeline:lock:{env} ]
              │
      ┌───────┴───────┐
      ▼               ▼
 (Tersedia)       (Terkunci)
      │               │
      │               ├─► Masuk Antrean Tunggu (Queue)
      │               │   atau Tolak Permintaan Bersamaan
      │               ▼
      │        [ Kirim Notifikasi Konflik ]
      ▼
 [ Buat Lease Token Unik: lease-{id}-{hash} ]
      │
      ▼
 [ Set TTL: 1800 Detik (30 Menit) ]
      │
      ▼
 [ Eksekusi Tahapan Penerapan ]
      │
      ▼
 [ Lepaskan Kunci Menggunakan Lease Token Asli ]
```

### Properti Desain Kunci:
1. **Atribut Lease Token**: Setiap permintaan akuisisi kunci menghasilkan token lease acak berentropi tinggi. Pelepasan kunci hanya diizinkan jika pemegang lease menyertakan token yang cocok, mencegah pelepasan kunci yang salah oleh proses lain yang mengalami timeout.
2. **Automatic Expiration (TTL)**: Kunci secara otomatis kadaluarsa setelah 30 menit jika pekerja pelaksana mengalami kegagalan fatal (crash), memastikan lingkungan tidak terkunci secara permanen.
3. **Heartbeat Refresh**: Pekerja yang sedang memproses deployment panjang dapat memperpanjang masa aktif lease secara periodik.

---

## 3. Matriks Kompatibilitas Lingkungan Penerapan

Aturan eksklusivitas ditentukan berdasarkan tingkat isolasi lingkungan:

| Lingkungan Target | Batas Konkurensi Maksimum | Alasan Kebijakan |
|---|---|---|
| `production` | 1 deployment aktif | Mencegah kontaminasi telemetri canary dan konflik DDL |
| `staging` | 1 deployment aktif | Memastikan pengujian penerimaan pengguna berjalan stabil tanpa pergantian versi mendadak |
| `sandbox` | 5 deployment aktif | Memungkinkan pengujian mandiri oleh banyak pengembang secara paralel |
| `test` | Tidak terbatas (ephemeral) | Setiap build CI menjalankan container terisolasi secara mandiri |

---

## 4. Penanganan Deadlock dan Pemulihan Kegagalan Kunci

Jika sebuah deployment mengalami penghentian paksa (abrupt process termination):
1. **Deteksi Kunci Yatim (Orphan Lock)**:
   Sistem pemantau latar belakang memindai kunci yang tidak memiliki proses aktif terdaftar.
2. **Paksa Pelepasan oleh Operator Berwenang (Admin Force Release)**:
   Administrator rilis dapat memutus kunci lingkungan yang macet melalui CLI:
   ```bash
   php artisan platform:environment-unlock --environment=staging --reason="Pekerja CI mati karena OOM"
   ```
3. **Pencatatan Audit Pelanggaran Kunci**:
   Setiap intervensi manual dicatat ke audit ledger sistem untuk pertanggungjawaban operasional.

---

## 5. Sinkronisasi Antara Job Backfill dan Jendela Deployment

Selain penguncian lingkungan aplikasi, operasi migrasi data massal (`DataBackfill`) memiliki interaksi erat dengan kunci penerapan:
- Ketika sebuah tabel sedang menjalani proses DDL terdistribusi atau backfill masif, `MigrationLockContentionEstimator` akan memberikan peringatan jika deployment baru berusaha mengubah skema tabel yang sama.
- Sistem secara otomatis menunda inisiasi backfill baru sampai deployment canary yang aktif selesai dipromosikan ke produksi penuh.

