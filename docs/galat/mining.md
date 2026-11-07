# Katalog Galat Domain Pertambangan (Mining Domain Exceptions)

Dokumen ini memetakan seluruh exception domain khusus pertambangan, penyebab kemunculannya, dan tindakan perbaikan (troubleshooting) bagi pengguna dan operator sistem.

---

## 1. `InvalidMiningStateTransitionException`
- **Pesan Galat**: `Transisi status {machine_name} dari '{from}' ke '{to}' tidak diizinkan.`
- **Penyebab**: Percobaan mengubah status state machine entitas tambang (MinePlan, HaulCycle, Equipment, atau FuelAnomalyCase) di luar jalur transisi yang sah.
  - Contoh: Mencoba mengubah `HaulCycle` dari `QUEUED` langsung ke `COMPLETED` tanpa melewati status `ASSIGNED`, `LOADING`, dan `HAULING`.
- **Tindakan Pengguna / Operator**:
  - Ikuti alur kerja standar operasi lapangan.
  - Untuk kendala darurat, gunakan menu **Manual Override** dispatcher yang mencatat alasan eksplisit ke log riwayat append-only.

---

## 2. `InvalidArgumentException: Alokasi armada melebihi unit truk tersedia`
- **Pesan Galat**: `Jumlah unit truk yang ditugaskan ({n}) melebihi armada aktif ({available}) di site {site_code}.`
- **Penyebab**: Algoritma dispatch M2 mendeteksi penugasan truk aktif yang lebih banyak dari ketersediaan fisik armada di lokasi penambangan.
- **Tindakan Pengguna / Operator**:
  - Periksa apakah ada unit truk yang mengalami breakdown tetapi statusnya belum diperbarui di sistem.
  - Ubah status armada yang siap beroperasi menjadi `available` pada menu **Heavy Equipment**.

---

## 3. `InvalidArgumentException: Stok solar tangki tidak mencukupi`
- **Pesan Galat**: `Stok solar tangki {storage_code} ({current} L) tidak mencukupi untuk pengisian {requested} L.`
- **Penyebab**: Operator fuel truck / depot mencoba mengisi bahan bakar melebihi sisa fisik solar yang tercatat di tangki penyimpanan.
- **Tindakan Pengguna / Operator**:
  - Lakukan sounding tangki fisik untuk memverifikasi apakah ada penerimaan BBM baru dari pemasok yang belum di-input ke sistem.
  - Catat penerimaan solar baru melalui menu inventaris bahan bakar.

---

## 4. `InvalidArgumentException: Parameter ban melebihi batas kritis TKPH`
- **Pesan Galat**: `Suhu ban {tire_position} ({temp}°C) melebihi batas kritis 95°C.`
- **Penyebab**: Telemetri sensor ban mendeteksi panas berlebih akibat muatan berlebih (overload) atau kecepatan tempuh terlalu tinggi pada rute hauling panjang.
- **Tindakan Pengguna / Operator**:
  - Operator truk wajib segera menghentikan unit di area parkir aman (cool-down bay).
  - Kurangi target muatan ritase berikutnya dan turunkan batas kecepatan hauling.

---

## 5. `InvalidArgumentException: Sertifikat verifikasi IDW / Kriging korup`
- **Pesan Galat**: `Sertifikat verifikasi IDW / Ordinary Kriging korup atau tidak cocok.`
- **Penyebab**: Integritas hasil komputasi interpolasi geostatistik voxel tidak memenuhi kondisi batas matematis (convex hull atau kondisi unbiased).
- **Tindakan Pengguna / Operator**:
  - Periksa apakah titik sampel borehole memiliki koordinat duplikat atau kadar nikel bernilai negatif.
  - Pastikan titik sampel berada di dalam batas polygon konsesi IUP pit.
