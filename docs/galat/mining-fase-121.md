# Katalog Kode Galat & Penanganan Eksepsi: Smelter & Metals Trading (Fase 121)

## 1. Ikhtisar Penanganan Galat

Sistem Fase 121 menerapkan penanganan galat terstruktur berbasis pengecualian domain (*domain exceptions*) untuk mencegah inkonsistensi transaksi finansial, pelanggaran batas keselamatan tanur bersuhu tinggi, dan cacat hukum registrasi resi gudang. Seluruh respons kegagalan API mengembalikan struktur JSON konsisten dengan pesan human-readable dan kode diagnostik unik.

---

## 2. Tabel Kode Galat Domain Fase 121

| Kode Galat | HTTP Status | Nama Eksepsi Domain | Deskripsi Penyebab | Tindakan Korektif Pengguna / Sistem |
| :--- | :--- | :--- | :--- | :--- |
| `ERR-SMELT-001` | 422 Unprocessable | `InvalidMiningStateTransitionException` | Mencoba melakukan tapping pada tanur yang masih berstatus `DRAFT` atau `CHARGING`. | Lakukan pengisian umpan dan pemanasan hingga temperatur mencapai minimal 1.400°C sebelum memulai proses penuangan. |
| `ERR-SMELT-002` | 422 Unprocessable | `DomainException` | Efisiensi termal tanur hasil kalkulasi neraca energi di luar batas toleransi ($< 40\%$ atau $> 96\%$). | Periksa akurasi data meteran daya listrik (MWh), laju umpan batubara reduktor, atau kadar air bijih laterit. |
| `ERR-SMELT-003` | 400 Bad Request | `InvalidArgumentException` | Nilai kuantitas umpan basah bijih nikel (ore_feed_wmt) bernilai $\le 0$ ton. | Masukkan angka tonase umpan basah positif yang valid dari timbangan jembatan conveyor. |
| `ERR-SMELT-004` | 422 Unprocessable | `DomainException` | Kadar nikel hasil uji spektrometri laboratorium berada di bawah batas komersial absolut 8.0% Ni. | Produk leburan dinyatakan *off-spec*. Karantina lot dan jadwalkan peleburan ulang (*re-melting*). |
| `ERR-SMELT-005` | 400 Bad Request | `InvalidArgumentException` | Rasio basicity terak $(\text{MgO} + \text{CaO}) / \text{SiO}_2$ menghasilkan erosi korosif refraktori tanur. | Sesuaikan laju injeksi flux silika atau batu kapur calciner. |
| `ERR-TRAD-001` | 422 Unprocessable | `InvalidMiningStateTransitionException` | Mencoba mengaktifkan posisi lindung nilai (`ACTIVE_HEDGED`) tanpa setoran margin awal (`initial_margin_idr`). | Setorkan margin awal minimal 12% dari nilai nosional kontrak ke rekening kliring. |
| `ERR-TRAD-002` | 400 Bad Request | `InvalidArgumentException` | Harga tetap kontrak (fixed_price_usd_cents_per_ton) bernilai $\le 0$. | Tentukan harga kesepakatan LME positif dalam satuan sen dolar per metrik ton. |
| `ERR-TRAD-003` | 422 Unprocessable | `DomainException` | Panggilan margin variasi gagal dipenuhi sebelum batas waktu penyelesaian kliring. | Tutup sebagian posisi derivatif (*unwind*) atau lakukan likuidasi darurat posisi hedging. |
| `ERR-TRAD-004` | 404 Not Found | `ModelNotFoundException` | Kontrak perdagangan logam atau posisi derivatif dengan ID yang diminta tidak ditemukan. | Pastikan nomor kontrak valid dan terdaftar di database `min_metals_trading_contracts`. |
| `ERR-SRG-001` | 422 Unprocessable | `InvalidMiningStateTransitionException` | Mencoba memverifikasi resi gudang tanpa adanya tautan sertifikat assay laboratorium (`assay_id`). | Pastikan pengujian laboratorium mutu telah selesai dan tersertifikasi sebelum menerbitkan SRG. |
| `ERR-SRG-002` | 422 Unprocessable | `InvalidMiningStateTransitionException` | Mencoba menjaminkan resi gudang ke bank SCF tanpa menentukan bank mitra atau plafond kredit bernilai 0. | Tentukan nama bank mitra penyedia fasilitas SCF dan pastikan nilai haircut kolateral menghasilkan LTV valid. |
| `ERR-SRG-003` | 403 Forbidden | `DomainException` | Segel digital kriptografi resi gudang atau tanda tangan HMAC tidak cocok (*tampered hash*). | Batalkan resi gudang terkait, lakukan audit integritas database, dan terbitkan ulang dokumen legal. |
| `ERR-BYPR-001` | 400 Bad Request | `InvalidArgumentException` | Kuantitas pengiriman terak slag (dispatch_tons) bernilai $\le 0$ atau melebihi stok yang tersedia. | Sesuaikan tonase pengiriman dengan stok riil terak nikel yang tersisa di stockpile smelter. |
| `ERR-BYPR-002` | 502 Bad Gateway | `DomainException` | Manifest lingkungan pengangkutan limbah terak slag ditolak oleh sistem SIMPEL KLHK. | Periksa kelengkapan nomor izin pengangkutan transporter dan izin pemanfaatan offtaker semen. |

---

## 3. Strategi Kompensasi Saga Hilirisasi

Pada transaksi orkestrasi `MetalsHilirisasiLifecycleSaga`:
1. **Kegagalan Neraca Termal (Langkah 2)**: Jika $\eta_{\text{thermal}} \notin [40\%, 96\%]$, sistem memicu kompensasi pembatalan run tanur (`compensateFurnaceRun`), mencatat alasan `ABORTED` ke metadata, dan mematikan perintah pengumpanan material baru.
2. **Kegagalan Uji Mutu Laboratorium (Langkah 5)**: Jika kadar nikel primer $< 8.0\%$, sistem menghentikan proses penerbitan resi gudang digital, mengisolasi balok ingot, dan menolak pengajuan kredit SCF.
3. **Penolakan Registrasi Bappebti (Langkah 7)**: Jika ISWARE mengembalikan kegagalan pendaftaran, status SRG dibatalkan, jaminan fidusia bank SCF tidak dicatat, dan jurnal pencairan pinjaman modal kerja di buku besar dibatalkan secara otomatis.

---

## 4. Penanganan Galat Integrasi Gateway Eksternal

Integrasi eksternal pada Fase 121 mencakup tiga sistem eksternal kritis yang beroperasi secara asinkron dan memerlukan mekanisme *circuit breaker* dan *graceful degradation*:

### 4.1 London Metal Exchange (LME) FIX Gateway (`G7-K15`)
- **Kegagalan Sambungan Socket / Heartbeat Timeout**: Ketika koneksi FIX protocol terputus selama lebih dari 30 detik, valuasi MTM beralih ke *Fallback Mode* menggunakan harga kuotasi penutupan (*official closing price*) hari kerja bursa terakhir (T-1) dengan penambahan penalti spread volatilitas 1.5%.
- **Anomali Fluktuasi Harga (Circuit Breaker LME)**: Jika selisih harga spot LME intraday melebihi batas batas ambang dinamis $\pm 15\%$ dari batas penutupan sebelumnya, transaksi otomatis ditandai `UNDER_INVESTIGATION` dan pemicu likuidasi otomatis ditangguhkan hingga verifikasi manusia oleh Head of Trading Desk.

### 4.2 Pusat Registrasi Resi Gudang Bappebti / ISWARE Gateway (`G7-K16`)
- **Network Glitch / 504 Gateway Timeout**: Ketika endpoint REST API ISWARE tidak merespons, status resi gudang beralih ke `PENDING_REGISTRATION_ACK`. Sistem menjadwalkan *exponential backoff retry* melalui antrean antrian worker latar belakang (1m, 5m, 15m, 1h).
- **Penolakan Validasi Kustodian**: Jika kode sertifikat pengelola gudang tidak terakreditasi, sistem menandai resi gudang sebagai `REJECTED_BY_REGISTRY` dan menerbitkan notifikasi audit kepatuhan ke Compliance Officer.

### 4.3 Pemanfaatan Terak Semen & KLHK SIMPEL Gateway (`G7-K17`)
- **Penolakan Dokumen Limbah B3/Non-B3 Terdaftar**: Apabila nomor izin lingkungan offtaker telah kedaluwarsa, gateway memblokir proses penerbitan surat jalan timbangan (*weighbridge gate pass*) dan mengembalikan kode `ERR-BYPR-002`.

---

## 5. Matriks Pemulihan Galat Runtime & Fallback Otomatis

| Kategori Insiden | Indikator | Mekanisme Pemulihan Otomatis | Prosedur Eskalasi Manual |
| :--- | :--- | :--- | :--- |
| **Furnace Overheating Risk** | Suhu lelehan $> 1.680^\circ\text{C}$ | Pemotongan daya elektroda seketika oleh PLC interlock, injeksi gas argon pendingin | Operator lapangan memeriksa sensor thermocouple optik dan aliran jaket air pendingin tanur |
| **Slag Basicity Imbalance** | $B_2 < 0.8$ (sangat asam) | Injeksi otomatis flux batu kapur ($\text{CaCO}_3$) dari bunker penyimpanan harian | Metallurgy Engineer menghitung ulang neraca stoikiometri terak di laboratorium |
| **Trading Margin Shortfall** | Ekuitas akun jatuh $< 110\%$ batas pemeliharaan | Pembuatan notifikasi Margin Call elektronik seketika dan penangguhan pembukaan posisi baru | Treasury Officer menghubungi pihak counterpart untuk top-up margin kas atau jaminan aset fisik |
| **Collateral Haircut Breach** | Nilai likuidasi pasar jatuh di bawah plafond pinjaman bank | Pembekuan opsi pelepasan barang di gudang berikat dan konversi status jaminan ke lelang sita | Tim Legal dan Risk Management mengeksekusi klausa default fasilitas SCF |

---

## 6. Standar Format Respons Galat RFC 7807 (Problem Details)

Seluruh endpoint API Fase 121 mengembalikan payload kegagalan sesuai spesifikasi RFC 7807:

```json
{
  "type": "https://erp.mining.id/errors/ERR-SMELT-002",
  "title": "Thermal Efficiency Feasibility Violation",
  "status": 422,
  "detail": "Calculated pyrometallurgical thermal efficiency (98.4%) exceeds theoretical upper ceiling of 96.0%.",
  "instance": "/api/v1/mining/smelter/furnaces/FURNACE-RKEF-01/energy-balance",
  "invalid_params": [
    {
      "name": "power_consumption_mwh",
      "reason": "Power consumption input appears artificially depressed relative to slag and metal tap weights."
    }
  ],
  "timestamp": "2026-11-07T15:45:00Z"
}
```
