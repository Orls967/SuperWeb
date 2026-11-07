# Audit Keselarasan Keamanan Kerumunan & Biometrik — Fase 117

## 1. Lingkup & Sasaran Audit
Command audit `php artisan venue:audit-crowd` menjalankan verifikasi integritas sistem menyeluruh terhadap tiga komponen operasional penting:
1. **Verifikasi Integritas Okupansi (Zero Headcount Drift):**
   - Membandingkan akumulasi log scan masuk dikurangi scan keluar terhadap headcount zona riil saat ini.
   - Setiap selisih non-zero diinvestigasi untuk mendeteksi tailgating atau sensor error.
2. **Verifikasi Ketunggalan Hash Biometrik (Zero Biometric Collision):**
   - Memastikan tidak ada dua pengguna berbeda yang memiliki hash templat biometrik aktif yang identik di dalam vault.
   - Memastikan seluruh pengguna yang terdaftar memiliki rekaman persetujuan privasi berstempel waktu sah.
3. **Verifikasi Integritas Status Gerbang & Insiden:**
   - Memastikan tidak ada insiden berstatus `GATE_RESTRICTED` atau `EVACUATION_STANDBY` yang terbengkalai tanpa catatan tindakan petugas lebih dari 6 jam setelah acara berakhir.

## 2. Mekanisme Fixture Sabotase (`VenCrowdAuditCorruptionFixture`)
Fixture sabotase menguji keandalan sistem audit dengan menyuntikkan dua bentuk kerusakan data:
1. **Injeksi Drift Headcount:** Mengubah headcount zona menjadi melebihi batas fisik kapasitas legal tanpa adanya rekaman scan masuk yang sah.
2. **Injeksi Kredensial Tanpa Persetujuan:** Menyisipkan templat biometrik aktif dengan kolom `consented_at = NULL`.

Command audit `venue:audit-crowd` mendeteksi anomali ini seketika, mencetak pesan galat berwarna merah, dan mengembalikan status keluar `Exit 1`. Saat fixture dipulihkan (`rollbackCorruption()`), command kembali menghasilkan status `HEALTHY` dengan `Exit 0`.

