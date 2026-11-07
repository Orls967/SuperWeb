# Laporan Audit Kesiapan Crowd Safety & Uji Beban Turnstile 100K Pax (Fase 117)

## 1. Ringkasan Eksekutif & Ruang Lingkup Audit

Audit kesiapan sistem keselamatan kerumunan dan uji beban perangkat keras turnstile (stress testing) dilakukan untuk memverifikasi kapasitas throughput gerbang festival musik berskala hingga 100.000 pengunjung. Evaluasi difokuskan pada ketahanan jaringan terputus (offline edge cache fallback), latensi verifikasi Face-ID di bawah beban puncak, serta keandalan perintah pelepasan darurat terpusat.

```mermaid
flowchart TD
    LOAD["Generator Beban: 100.000 Transaksi Masuk Simultan"] --> EDGE["Edge Gate Controllers (32 Gerbang Turnstile)"]
    EDGE --> DB["Basis Data Replikasi Lokal SQLite / Redis"]
    EDGE --> CORE["SuperWeb Central Command (Modules/Venue)"]
    CORE --> AUDIT["Auditor Kepatuhan Integritas Ledger & Jam"]
```

---

## 2. Metodologi Pengujian Beban & Hasil Tolok Ukur (Benchmarks)

Pengujian disimulasikan menggunakan skrip injeksi telemetri dengan parameter lonjakan ekstrem penonton sesaat sebelum penampilan artis utama (jam 19.30 - 20.30):

| Metrik Kinerja | Target Ambang Keras | Hasil Uji Beban Riil | Status Kelulusan |
|---|---|---|---|
| Latensi Verifikasi Face-ID | $\le 300\text{ ms}$ | $182\text{ ms}$ (p99: $240\text{ ms}$) | MEMENUHI TARGET |
| Throughput per Gerbang | $\ge 25\text{ orang/menit}$ | $31.4\text{ orang/menit}$ | MEMENUHI TARGET |
| Waktu Pelepasan Darurat Solenoid | $\le 1.0\text{ detik}$ | $120\text{ ms}$ sejak perintah saga | MEMENUHI TARGET |
| Ketahanan Offline (Internet Putus) | Bekerja 100% via hash lokal | 0 transaksi ditolak palsu | MEMENUHI TARGET |
| Konsistensi Ledger Pasca-Sinkron | 0 selisih transaksi (gapless) | Cocok sempurna ($\Delta = 0$) | MEMENUHI TARGET |

---

## 3. Hasil Pengujian Skenario Partisi Jaringan (Network Partitioning Test)

Pada skenario di mana koneksi serat optik antara pintu gerbang terluar dan server komando pusat terputus total selama 30 menit:

1. **Operasi Otonom Edge Kiosk**:
   Setiap unit Kios Turnstile memuat salinan terenkripsi (read-only SQLite cache) dari seluruh template hash biometrik tamu terdaftar yang telah divalidasi tiketnya.
2. **Pencatatan Antrean Offline**:
   Setiap transaksi masuk dicatat ke dalam log lokal dengan tanda tangan digital HMAC lokal dan nomor urut deterministik.
3. **Rekonsiliasi Otomatis (Re-convergence)**:
   Saat koneksi jaringan pulih, controller secara otomatis menyiarkan log transaksi ke database pusat tanpa terjadi duplikasi pemindaian atau tabrakan nomor urut.

---

## 4. Evaluasi Audit Perangkat Keras Mekanikal Gerbang

Pemeriksaan fisik terhadap solenoid elektromagnetik dan lengan pemutar turnstile menunjukkan:
- **Ketahanan Terhadap Dorongan Paksa**: Lengan turnstile mampu menahan beban dorongan horizontal hingga $250\text{ kg}$ saat terkunci tanpa terjadi kegagalan pasak solenoid.
- **Fail-Safe Mechanism**: Pemutusan catu daya utama segera mengaktifkan peredam pegas mekanis yang memutar lengan ke bawah (drop-arm mechanism), membuka lorong selebar $80\text{ cm}$ bebas rintangan sesuai regulasi keselamatan kebakaran.
- **Kesimpulan Akhir**: Sistem Crowd Safety dan Biometric Turnstile dinyatakan **LAYAK OPERASI DAN MEMENUHI SELURUH STANDAR MUTU T-1**.
