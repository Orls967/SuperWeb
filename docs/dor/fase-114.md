# Definition of Ready (DoR) — Fase 114: MICE, Banquet BEO & Global Event Sales Engine

## 1. Ikhtisar Fase 114
Fase 114 melengkapi ekosistem Perhotelan (G7-P6) dengan mesin komprehensif untuk mengelola penjualan event skala global, MICE (Meetings, Incentives, Conferences, and Exhibitions), jamuan banquet korporat, serta pernikahan mewah (*luxury weddings*). Engine ini mengintegrasikan siklus hidup pemesanan dari hulu ke hilir:
- Penjualan B2B MICE multi-komponen dengan penguncian harga penawaran (*quotation timelock*) dan validasi margin kotor guardrail $\ge 40\%$.
- Mesin kamar blok (*block allotment engine*) dengan pelepasan otomatis bertingkat (*staged automatic release*) pada interval H-30, H-14, dan H-7 untuk mencegah *dead inventory* dan menjamin ketersediaan kamar publik.
- Banquet Event Order (BEO) & lembar kerja produksi (*production sheet*) dengan penelusuran BOM (*Bill of Materials*) resep, varians penuangan minuman (*beverage pouring variance*), tenaga kerja katering, serta integrasi langsung ke modul Resto dan Procurement.
- Manajemen spesifik pernikahan (*luxury wedding lifecycle*) yang mencakup jadwal pembayaran milestone termin berurutan, upgrade kamar pengantin (*bridal suite*), alokasi blok kamar keluarga, dan koordinasi vendor dekorasi.
- Konektor pameran dan trade show untuk penyewaan booth stand expo terintegrasi (Fase 78 connector), utilitas listrik/Wi-Fi add-on, simulasi footfall sensor, dan laporan ROI pameran.
- Audit rekonsiliasi MICE komprehensif dua arah dengan pencatatan pembukuan ganda (*double-entry bookkeeping*) ke General Ledger.

---

## 2. Kriteria Penerimaan (Acceptance Criteria)
1. **Pipeline B2B MICE & Wedding Sales:**
   - Proposal MICE mendukung komponen multi-tier: sewa ballroom/ruang rapat, kamar blok peserta, paket katering banquet, paket AV teknis, dan booth pameran.
   - Setiap proposal memiliki batas waktu penawaran (*quotation timelock*). Jika melewati tanggal kadaluarsa, harga otomatis terkunci dan memerlukan re-evaluasi GM/Revenue Manager.
   - Guardrail margin kotor diterapkan secara ketat: proposal dengan gross margin $< 40\%$ otomatis ditolak oleh sistem dan membutuhkan eskalasi otorisasi khusus Direksi Hotel.

2. **Kamar Blok (Block Allotment Engine):**
   - Mendukung reservasi grup 100 hingga 1.000 kamar dengan batas kuota terlindungi (*protected allotment quota*).
   - Pelepasan kuota otomatis bertingkat:
     * Pada H-30 sebelum kedatangan (cutoff 1), minimal 25% dari sisa kamar yang belum di-pickup dilepas kembali ke inventori publik.
     * Pada H-14 sebelum kedatangan (cutoff 2), minimal 50% dari sisa kamar belum di-pickup dilepas ke inventori publik.
     * Pada H-7 sebelum kedatangan (cutoff 3 / final cutoff), 100% dari seluruh kamar yang tidak memiliki nama tamu (*unnamed room list*) dilepaskan seluruhnya ke inventori publik.
   - Integritas anti-oversell dijamin dengan isolasi transaksi dan update kuota atomik.

3. **Banquet Event Order (BEO) & Production Sheet:**
   - Perhitungan BOM resep banquet menghitung kebutuhan bahan baku per pax dengan memperhitungkan faktor susut bahan (*recipe yield loss factor*) dan varians penyusutan penuangan minuman beralkohol/minuman dingin (*beverage pouring shrinkage variance*).
   - Perhitungan biaya tenaga kerja banquet (*banquet casual labor cost*) dan sewa peralatan AV eksternal dihitung per shift/sesi acara.
   - Terintegrasi dengan modul Resto untuk pemotongan batch stok bahan baku dapur secara otomatis saat status BEO berubah menjadi `PRODUCTION_COMMENCED`.

4. **Wedding Contract & Milestone Payment Schedule:**
   - Penjadwalan termin pembayaran berjenjang:
     * Termin I (Deposit Awal): 20% dari total estimasi kontrak saat penandatanganan SPK.
     * Termin II (Mid-term Booking): 30% pada H-90 hari acara.
     * Termin III (Pre-event Final Guarantee): 30% pada H-30 hari acara saat finalisasi menu tasting dan rooming list keluarga.
     * Termin IV (Pelunasan Akhir): 20% pada H-7 hari acara sebelum technical meeting final.
   - Fasilitas upgrade kamar bridal suite gratis jika total nilai kontrak mencapai ambang batas tier Platinum Wedding ($\ge \text{IDR } 250.000.000$).
   - Pembayaran bersifat idempoten dengan verifikasi transaksi per terminal pembayaran.

5. **Exhibition & Trade Show Connector:**
   - Penyewaan booth pameran berukuran standar (3x3m, 6x3m, pulau khusus) dengan pemetaan zona hall konvensi.
   - Add-on utilitas terstandarisasi: daya listrik (kVA), kabel LAN dedicated/Wi-Fi berkecepatan tinggi, pass masuk kontraktor dekorasi, dan kebersihan booth harian.
   - Simulasi penghitungan footfall pengunjung berdasarkan luas booth dan durasi pameran untuk menghasilkan laporan metrik ROI sponsor/peserta pameran.

6. **Rekonsiliasi Ledger & Penutupan Event:**
   - Seluruh pendapatan sewa venue MICE, F&B banquet, kamar blok, dan utilitas booth dicatat secara terpisah pada sub-ledger yang sesuai.
   - Tidak ada selisih (*zero discrepancy*) antara total tagihan master folio event dengan total mutasi pada buku besar kas/bank dan piutang korporat (*City Ledger AR*).

---

## 3. Batasan Teknis & Standar Arsitektur (W8)
- **Zero Raw Queries:** Semua operasi database wajib menggunakan Eloquent ORM atau Query Builder resmi Laravel.
- **Kompensasi Saga:** Seluruh transaksi pembatalan event atau perubahan kuota kamar blok wajib memiliki kompensasi balik (*compensating ledger adjustment*) untuk menjaga integritas pembukuan.
- **Idempotency Standards (A4):** Idempotency key harus deterministik, tidak boleh menggunakan generator acak non-deterministik (`now()`, `uniqid()`) pada saat posting mutasi akuntansi.
- **Foreign Key Standards (A12):** Seluruh kolom relasi ke luar modul menggunakan format `*_reference` (misal: `organizer_user_reference`, `vendor_reference`).
- **Identifier MySQL (64 Chars):** Semua nama indeks database kustom tidak boleh melebihi 64 karakter.
- **Linting & Code Quality:** 100% kepatuhan terhadap PHP Pint Laravel dan PHPStan.
