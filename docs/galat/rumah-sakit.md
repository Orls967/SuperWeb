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


---

## 2. Kamus Kode Galat Terstruktur (HSP-ERR-001 s.d. HSP-ERR-030)

| Kode Galat | Kategori | Ringkasan Masalah | Dampak Sistem | Rekomendasi Solusi & Prosedur Operator |
| :--- | :--- | :--- | :--- | :--- |
| `HSP-ERR-001` | Admisi & Ranjang | Konflik kuncian ranjang konkuren | Admisi dibatalkan | Muat ulang daftar ranjang, pilih ranjang alternatif via B1 |
| `HSP-ERR-002` | State Machine | Pelanggaran transisi status ranjang | Transisi ditolak | Lakukan pembersihan kamar via housekeeping sebelum `AVAILABLE` |
| `HSP-ERR-003` | Rekam Medis | Integritas paspor medis rusak (tamper) | Paspor dibekukan | Lakukan audit forensik dan verifikasi rekam medis fisik |
| `HSP-ERR-004` | Kedaruratan | Pemicuan ganda alarm Code Blue | Panggilan kedua diabaikan | Teruskan penanganan resusitasi pada insiden aktif |
| `HSP-ERR-005` | Keuangan | Kegagalan pendebitan uang muka deposit | Saga admisi kompensasi | Arahkan penjamin ke kasir, ranjang dilepas kembali |
| `HSP-ERR-006` | Algoritma | Hash sertifikat NEWS2 tidak valid | Observasi gagal disimpan | Validasi ulang input tanda vital dan jalankan verifier |
| `HSP-ERR-007` | Algoritma | Sertifikat B1 Bed Assignment cacat | Alokasi ranjang dibatalkan | Verifikasi keutuhan payload optimasi ranjang |
| `HSP-ERR-008` | Pathway | Keterlambatan order klinis kritis | Alert perawat dipicu | Segera laksanakan tindakan stat di instalasi penunjang |
| `HSP-ERR-009` | Sanitasi | Ranjang kotor melebihi batas 120 menit | Eskalasi housekeeping | Kerahkan personel sanitasi cadangan ke bangsal terkait |
| `HSP-ERR-010` | Buku Besar | Akun COA hospital tidak ditemukan | Jurnal akuntansi gagal | Jalankan seeder akun standar `LedgerAccountsSeeder` |
| `HSP-ERR-011` | Buku Besar | Saldo debit/kredit tidak seimbang | Transaksi dibatalkan | Periksa rumus posting di `HospitalLedgerPostingService` |
| `HSP-ERR-012` | Kedaruratan | Timeout eskalasi dokter jaga (2 menit) | Panggilan RRT broadcast | Tim gawat darurat cito menuju lokasi kejadian |
| `HSP-ERR-013` | Kedaruratan | Timeout eskalasi tim resusitasi (5 menit) | Sirine sentral rumah sakit | Aktivasi protokol evakuasi dan bantuan hidup lanjut |
| `HSP-ERR-014` | Paspor | Duplikasi NIK pada pendaftaran pasien | Pendaftaran ditolak | Gunakan fitur penggabungan rekam medis (*merge MRN*) |
| `HSP-ERR-015` | Isolasi | Pelanggaran penempatan kuman resistan | Pasien ditolak di bangsal | Tempatkan pasien di ruang isolasi bertekanan negatif |
| `HSP-ERR-016` | Telemetri | Sensor SpO2 lepas (*probe dislodged*) | Sinyal terputus | Perawat memeriksa pemasangan oksimeter pada jari pasien |
| `HSP-ERR-017` | Kepatuhan | Diagnosis ICD-10 tidak sesuai SatuSehat | Ekspor FHIR gagal | Perbaiki kode diagnosis dengan kode 4 karakter spesifik |
| `HSP-ERR-018` | Interoperabilitas | Token OAuth SatuSehat kedaluwarsa | Sinkronisasi tertunda | Jalankan perbaruan token otomatis via API Kemenkes |
| `HSP-ERR-019` | Hak Kelas | Pasien hak BPJS Kelas 3 ditagih upgrade | Audit penagihan gagal | Terapkan subsidi selisih tarif kamar oleh rumah sakit |
| `HSP-ERR-020` | Farmasi | Resep obat melanggar riwayat alergi | Resep dikunci otomatis | Dokter penanggung jawab wajib memilih alternatif obat aman |
| `HSP-ERR-021` | Resusitasi | Rantai audit replay henti jantung rusak | Laporan insiden ditolak | Verifikasi kembali urutan log defibrilasi dan kompresi |
| `HSP-ERR-022` | Paspor | Akses berkas rekam medis tanpa hak (IDOR) | Akses diblokir HTTP 403 | Batasi akses sesuai penugasan dokter/perawat bangsal |
| `HSP-ERR-023` | Kontingensi | Lonjakan DBD melebihi kapasitas cadangan | Status darurat dinaikkan | Alihkan pasien rujukan ke rumah sakit jejaring sekunder |
| `HSP-ERR-024` | Logistik | Oksigen sentral di bawah tekanan 4 bar | Alarm teknis berbunyi | Teknisi beralih ke manifold tabung oksigen darurat |
| `HSP-ERR-025` | Pemulangan | Pemulangan dipaksa saat NEWS2 > 2 | Pemulangan diblokir | Stabilkan tanda vital minimal 24 jam sebelum kepulangan |
| `HSP-ERR-026` | Pemulangan | Resume medis belum ditandatangani DPJP | Berkas KRS tertunda | Hubungi dokter penanggung jawab untuk tanda tangan digital |
| `HSP-ERR-027` | Handover | Serah terima tugas jaga tanpa SBAR | Validasi shift gagal | Perawat wajib mengisi 4 pilar situasi, latar, asesmen, usul |
| `HSP-ERR-028` | Diet | Pasien diet khusus salah menu dapur | Distribusi makanan dicegat | Sesuaikan pesanan diet gizi klinik di modul dapur instalasi |
| `HSP-ERR-029` | Sanitasi | Disinfeksi UV belum selesai diverifikasi | Ranjang terkunci | Tunggu siklus sterilisasi UV selesai (minimal 30 menit) |
| `HSP-ERR-030` | Audit | Inkonsistensi data operasional vs akuntansi | Exit status audit non-zero | Lakukan rekonsiliasi folio ranjang harian dengan jurnal kas |
