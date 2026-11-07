# Manual Prosedur: Night Audit & Rekonsiliasi Poin Harian — Fase 112

## 1. Peran Night Audit dalam Ekosistem Loyalitas
Proses Night Audit hotel memegang peranan krusial sebagai titik penutupan hari bisnis (business date change) yang dipimpin oleh `HotelBusinessDateService`. Selain menutup folio harian dan memposting room charge, Night Audit Fase 112 bertanggung jawab menjalankan tiga proses otomatis loyalitas:
1. **Pengkreditan Poin Menginap (Nightly Points Accrual):**
   Memeriksa seluruh folio kamar yang aktif pada malam tersebut dan mengkreditkan poin menginap ke Travel Pass tamu sesuai tier yang berlaku.
2. **Evaluasi Rekualifikasi Tier Bergerak (Rolling Tier Evaluation):**
   Mengevaluasi akumulasi malam menginap dan pembelanjaan dalam 365 hari terakhir menggunakan algoritma `GlobalTierQualifier` (H13).
3. **Pembersihan Batch Kedaluwarsa (Batch Expiry Sweeping):**
   Memindai batch yang masa berlakunya berakhir pada tanggal bisnis tersebut dan menandainya untuk pengakuan breakage.

---

## 2. Langkah-Langkah Eksekusi Night Audit Poin
Proses dijalankan secara berurutan dan idempoten:
```bash
php artisan hotel:night-audit-loyalty --property-id="PROP-001" --business-date="2026-11-08"
```

### Tahapan Internal:
1. **Verifikasi Kunci Idempotensi:**
   Sistem memastikan perintah belum pernah dieksekusi untuk pasangan properti dan tanggal bisnis yang sama guna mencegah akrual ganda.
2. **Akrual Poin Kamar:**
   $$\text{Points} = \left\lfloor \frac{\text{Room Charge (IDR)}}{1.000} \times \gamma_{tier} \right\rfloor$$
   Dibuatkan batch baru dengan nomor `BTH-YYYYMMDD-PASSID` dan tanggal kedaluwarsa $T + 730\text{ hari}$.
3. **Penyusutan Liabilitas Saat Check-Out:**
   Bila ada tamu yang check-out dari reservasi award night, posting kliring internal disahkan ke buku besar akuntansi.
4. **Pencatatan Berita Acara Audit:**
   Hasil Night Audit dicatat dalam berkas log terenkripsi yang memuat ringkasan jumlah kamar, total poin yang diterbitkan, total poin yang hangus, dan saldo liabilitas penutupan.

