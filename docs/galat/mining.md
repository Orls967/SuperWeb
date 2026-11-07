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


---

### 7. `AccessDeniedHttpException` — Pelanggaran Kebijakan Otorisasi Per-Objek (`MiningFase93AccessPolicy`)
- **Kelas Pengawal**: `Modules\Mining\Http\Policies\MiningFase93AccessPolicy`
- **Contoh Pesan Galat**:
  - `"Akses ditolak: hanya perencana tambang (mine_planner) atau admin yang berwenang."`
  - `"Akses ditolak: hanya petugas dispatch tambang (dispatcher_mine) yang berwenang."`
  - `"Akses ditolak (Anti-IDOR): Kasus anomali BBM ini milik pihak kontraktor lain."`
- **Penyebab Teknis**:
  1. Pengguna yang sedang login tidak memiliki peran RBAC yang disyaratkan pada rute terkait (`/mining/plans`, `/mining/dispatch`, `/mining/equipment`, `/mining/fuel`).
  2. Akun kontraktor (`contractor`) di portal `/contractor/mining` mengirimkan `case_id` pada `POST /contractor/mining/dispute` yang memiliki nilai `contractor_party_ref` berbeda dari ID pengguna yang sedang login (percobaan eksploitasi *Insecure Direct Object Reference* / IDOR).
- **Tindakan Pengguna / Operator**:
  1. Pastikan pengguna menggunakan akun dengan peran yang sesuai (periksa penugasan peran melalui `RbacService`).
  2. Bagi pihak kontraktor, pastikan hanya mengajukan sanggahan pada nomor kasus (`FAC-...`) yang tercantum di tabel miliknya sendiri pada halaman `/contractor/mining`.

### 8. `InvalidArgumentException` — Kegagalan Pembukuan Buku Besar (`MiningFase93LedgerPostingService`)
- **Kelas Pengawal**: `Modules\Mining\Ledger\MiningFase93LedgerPostingService` dan `Modules\Mining\Application\Actions\DispenseFuelToEquipmentAction`
- **Contoh Pesan Galat**:
  - `"Nominal posting tagihan kontraktor angkut harus bernilai positif (diterima: 0)."`
  - `"Idempotency-Key wajib diisi untuk pembukuan tagihan kontraktor angkut."`
  - `"Stok tangki TANK-KALTIM-01 (150000 ml) kurang dari 500000 ml."`
  - `"Kunci idempotensi sama dengan volume berbeda."`
- **Penyebab Teknis**:
  1. Penutupan shift (`SettleShiftHaulingBillingAction`) dipanggil tanpa menyertakan `idempotency_key` atau tarif per ton-km bernilai nol/negatif.
  2. Pengisian solar (`DispenseFuelToEquipmentAction`) meminta volume pengeluaran (`volume_ml`) yang melebihi saldo fisik `current_stock_ml` di tabel `min_fuel_storages`.
  3. Permintaan ulang pengisian solar menggunakan pembacaan flowmeter akhir (`flowmeter_end`) yang sama tetapi mengirimkan angka `volume_ml` berbeda (konflik idempotensi).
- **Tindakan Pengguna / Operator**:
  1. Periksa saldo fisik tangki solar di halaman `/mining/fuel` sebelum mencatat pengeluaran baru.
  2. Pastikan setiap transaksi pengiriman ulang memakai nilai payload yang identik dengan transaksi asli, atau gunakan nomor pembacaan flowmeter baru jika merupakan transaksi fisik yang berbeda.

### 9. `InvalidArgumentException` — Evaluasi TKPH Ban & Kondisi Jalan Tambang (`RecordTireTemperaturePressureAction` & `HaulRoadFleetMaintenanceService`)
- **Kelas Pengawal**: `Modules\Mining\Application\Actions\RecordTireTemperaturePressureAction` dan `Modules\Mining\Application\Services\HaulRoadFleetMaintenanceService`
- **Contoh Pesan Galat**:
  - `"Evaluasi TKPH ban hanya berlaku untuk armada HAUL_TRUCK, diterima: EXCAVATOR"`
  - `"Panjang segmen jalan tambang harus lebih besar dari 0 meter."`
- **Penyebab Teknis**:
  1. Operator memilih unit berjenis `EXCAVATOR` (beroda rantai/crawler) untuk kalkulasi beban termal ban karet TKPH.
  2. Parameter panjang segmen jalan angkut (`roadLengthMeters`) diisi `0` atau negatif saat mengevaluasi *Rolling Resistance*.
- **Tindakan Pengguna / Operator**:
  1. Pilih unit dengan tipe `HAUL_TRUCK` saat menjalankan diagnostik ban.
  2. Masukkan panjang segmen koridor jalan angkut dalam satuan meter bulat positif (misal `5000` untuk 5 km).
