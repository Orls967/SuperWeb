# Kamus Galat & Penanganan Pengecualian Domain Rumah Sakit

Dokumen ini memuat daftar representasi exception domain baru yang diperkenalkan pada modul Rumah Sakit (Fase 87 & 88), akar penyebab, serta langkah perbaikan bagi pengguna dan operator.

---

## 1. Daftar Exception Domain

### 1.1 `BedAlreadyReservedException`
- **Pesan Galat**: "Ranjang tempat tidur #{bed_id} telah dipesan atau ditempati oleh pasien lain."
- **Akar Penyebab**: Percobaan alokasi tempat tidur konkuren pada saat ranjang yang sama telah dikunci oleh transaksi admisi lain.
- **Tindakan Pengguna**: Muat ulang papan ranjang `/hospital/beds` dan jalankan kembali alokasi otomatis (B1) untuk memperoleh ranjang bebas berikutnya.

### 1.2 `InvalidBedClassTransitionException`
- **Pesan Galat**: "Transisi status ranjang dari {from_status} ke {to_status} tidak diizinkan oleh state machine."
- **Akar Penyebab**: Upaya mengubah status ranjang di luar tabel transisi resmi (misal: langsung dari `occupied` ke `available` tanpa melalui fase `cleaning`).
- **Tindakan Operator**: Pastikan alur operasional mengikuti siklus sanitasi housekeeping standar.

### 1.3 `CompromisedHealthPassportException`
- **Pesan Galat**: "Integritas kriptografis paspor kesehatan pasien {patient_id} tidak valid (hash mismatch)."
- **Akar Penyebab**: Rantai CanonicalHashChain mendeteksi manipulasi data langsung pada kolom basis data atau ketidaksesuaian tanda tangan digital.
- **Tindakan Pengguna / Operator**: Bekukan rekam medis pasien untuk audit forensik keamanan informasi; hubungi administrator kepatuhan medis.

### 1.4 `CodeBlueAlreadyActiveException`
- **Pesan Galat**: "Alarm Code Blue telah aktif pada episode perawatan #{encounter_id}."
- **Akar Penyebab**: Percobaan memicu alarm Code Blue berulang pada pasien yang saat ini sedang dalam penanganan resusitasi aktif.
- **Tindakan Pengguna**: Lanjutkan penanganan klinis pada alarm yang sedang berjalan di `/hospital/critical-care`.

### 1.5 `EscrowDepositFailedException`
- **Pesan Galat**: "Gagal mengunci deposit pasien sebesar {amount} IDR pada rekening escrow penjamin."
- **Akar Penyebab**: Saldo dompet atau batas kredit penjamin tidak mencukupi saat proses saga admisi berjalan.
- **Tindakan Pengguna**: Arahkan keluarga pasien ke loket kasir pendaftaran untuk memilih metode penjaminan alternatif (tunai/asuransi). Kompensasi saga otomatis melepas ranjang yang sempat dikunci.

### 1.6 `InvalidNews2CalculationException`
- **Pesan Galat**: "Hasil perhitungan skor NEWS2 tidak sesuai dengan sertifikat matematis."
- **Akar Penyebab**: Verifikasi sertifikat menemukan inkonsistensi antara nilai parameter tanda vital dengan skor poin komposit.
- **Tindakan Operator**: Periksa kembali validitas nilai fisiologis input (laju napas, nadi, tensi) di formulir perawat.
