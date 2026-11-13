# Panduan Pengguna (`procurement`) — Fase 279: Evaluasi CAPEX & ROI Otomasi Robotika

## 1. Peran & Ruang Lingkup Akses Procurement
Sebagai **Procurement**, Anda berperan dalam meninjau kelayakan finansial proposal pengadaan armada AMR/AGV dan infrastruktur zona sebelum diajukan ke administrator untuk persetujuan final:
- `/wms/robotics/roi` — Akses baca portofolio proposal ROI otomasi (`RoboticsRoiPortfolioViewModel`).

*Catatan*: Peran `procurement` tidak memiliki akses ke kendali operasional lantai gudang (`/wms/robotics/zones`, `/wms/robotics/waves`) maupun tombol eksekusi persetujuan akhir (`POST /wms/robotics/roi/{proposal}/approve` khusus `admin`).

## 2. Membaca Metrik Evaluasi Investasi (`J3`)
Setiap baris pada `/wms/robotics/roi` menampilkan metrik deterministik hasil `AutomationNpvCalculator`:
1. **CAPEX (`capex_idr`)**: Nilai investasi awal dalam IDR (dikonversi dari satuan minor integer tanpa pembulatan `float`).
2. **NPV (`npv_idr`)**: Net Present Value berdasarkan arus kas tahunan dan `RationalRate`.
   - Jika `npv_minor >= 0`: Proyek layak secara finansial pada asumsi suku bunga dasar.
   - Jika `npv_minor < 0`: Ditandai `is_negative_npv = true`; wajib disertai `justification` tertulis apabila tetap diajukan untuk persetujuan strategis.
3. **IRR (`irr_bps`)**: Tingkat pengembalian internal dalam basis points (`1500 bps = 15,00%`).
4. **Payback Period (`payback_months`)**: Estimasi waktu balik modal dalam bulan bulat.

## 3. Memeriksa Matriks Sensitivitas (`RoboticsRoiSensitivityMatrixBuilder`)
Untuk membandingkan penawaran vendor yang berbeda, tinjau grid sensitivitas 3x3 yang menguji skenario:
- Kenaikan harga peralatan `+15%` (`capex_multiplier_pct = 115`) akibat fluktuasi kurs impor.
- Penurunan biaya pengadaan `-10%` (`capex_multiplier_pct = 90`) melalui negosiasi kontrak volume.
- Pergeseran biaya modal `±500 bps` (`±5%`).
Proposal yang tetap memiliki `npv_minor > 0` pada skenario `115% CAPEX / +500 bps` merupakan kandidat pengadaan paling tangguh.
