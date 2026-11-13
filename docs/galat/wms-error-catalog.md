# Katalog Galat Modul WMS (Fase 41)

Dokumen ini mendaftar seluruh kode galat, pengecualian domain (`DomainException` / `InvalidArgumentException`), penyebab akar, dan tindakan pemulihan yang terstandarisasi di modul `Wms`.

---

## 1. Galat Algoritma & Kapasitas (H1–H3)

| Kode / Pesan Galat | Kelas Sumber | Penyebab | Tindakan Pemulihan |
|---|---|---|---|
| `INVALID_QUANTITY` | `PutawaySlotter::plan` | Kuantitas putaway `q <= 0` | Pastikan input `qty_base` berupa integer positif (`>= 1`) setelah normalisasi `QuantityNormalizer` |
| `NO_CAPACITY` | `PutawaySlotter::plan` | Total `freeCapacity` seluruh bin aktif lebih kecil dari `q` | Bebaskan bin yang tertahan, aktifkan rak cadangan, atau pecah batch GRN ke gudang lain |
| `Kelas ABC tidak valid` | `PutawaySlotter` / `CycleCountScheduler` / `SlottingFitnessCalculator` | Nilai `abcClass` di luar `'A'`, `'B'`, `'C'` | Lakukan klasifikasi ulang via `AbcVelocityClassifier` sebelum memanggil algoritma |
| `EMPTY_PICK_LIST` | `PickPathPlanner::plan` | Daftar titik pick kosong (`[]`) | Pastikan wave picking memiliki minimal satu baris order yang siap diambil |
| `Dimensi lorong harus lebih dari 0` | `PickPathPlanner::plan` | `aisleWidth <= 0` atau `aisleLength <= 0` | Periksa konfigurasi `WarehouseDimensions` untuk gudang terkait |
| `Kapasitas cycle count tidak boleh negatif` | `CycleCountScheduler::schedule` | `capacity < 0` | Gunakan `capacity >= 0` sesuai `CycleCountPolicy::$dailyBinCapacity` |
| `Stok FEFO layak tidak mencukupi` | `FefoLotAllocator::allocate` | Total stok `available` dengan sisa umur simpan cukup lebih kecil dari permintaan | Periksa stok di karantina (`/wms/quarantine`) atau kurangi alokasi wave |

---

## 2. Galat State Machine & Eksekusi Tugas (D41-1)

| Kode / Pesan Galat | Kelas Sumber | Penyebab | Tindakan Pemulihan |
|---|---|---|---|
| `Transisi tugas gudang tidak diizinkan` | `WarehouseTaskStateMachine::nextState` | Aksi tidak valid untuk status saat ini (mis. mencoba `assign` pada tugas berstatus `closed` atau `cancelled`) | Periksa riwayat `wms_task_events` via `WmsTaskLifecycleService::getEventHistory()` |
| `Mode resolusi tidak valid` | `ResolveTaskExceptionAction::resolve` | Parameter `mode` bukan `'resolve'`, `'retry'`, atau `'cancel'` | Kirim salah satu dari tiga mode resolusi resmi untuk status `exception` |
| `Kode bin hasil scan tidak cocok` | `WmsHandheldOperationService::confirmScanAndComplete` | Operator memindai barcode bin yang berbeda dari target `from_bin_id` / `to_bin_id` | Arahkan operator ke lorong/rak yang benar sesuai label pada `/wms/tasks` |
| `Produk hasil scan tidak cocok` | `WmsHandheldOperationService::confirmScanAndComplete` | Barcode SKU yang dipindai tidak sesuai dengan `task->product_id` | Verifikasi fisik barang sebelum konfirmasi |

---

## 3. Galat Penerimaan, Reservasi, Karantina, & Dock (X41-1..X41-4)

| Kode / Pesan Galat | Kelas Sumber | Penyebab | Tindakan Pemulihan |
|---|---|---|---|
| `Kuantitas expected harus > 0 dan received >= 0` | `ReceivingToleranceEvaluator::evaluate` | Input GRN tidak valid | Perbaiki kuantitas pada formulir `/wms/receiving` |
| `Penerimaan melebihi batas toleransi over-receipt` | `ReceiveInboundShipmentAction::execute` | `variance_bps > over_tolerance_bps` (default 500 bps / 5%) tanpa persetujuan supervisor | Minta supervisor (`admin`) meninjau fisik dan mengaktifkan `approve_over_receipt` |
| `Stok tersedia tidak cukup` | `StockReservationService::reserve` | `available_base < qtyBase` karena stok terikat reservasi lain atau dikarantina | Jalankan `php artisan wms:reservation-sweep` untuk melepas reservasi kedaluwarsa |
| `Reservasi tidak berstatus active` | `StockReservationService::commit` | Mencoba commit reservasi yang sudah `expired`, `released`, atau `committed` | Buat reservasi baru bila stok masih tersedia |
| `BinStock tidak dalam status quarantine` | `LotQuarantineService::releaseFromQuarantine` | Mencoba melepas stok yang berstatus `available` atau `blocked` | Periksa status aktual pada `/wms/quarantine` |
| `Tidak ada stok untuk di-write-off` | `LotQuarantineService::writeOffBinStock` | `qty_base <= 0` pada baris bin stock | Abaikan baris kosong; tidak perlu posting write-off bernilai 0 |
| `Terjadi bentrok jadwal pada dock` | `DockAppointmentScheduler::scheduleSlot` | Interval `[startMinute, endMinute)` bertabrakan dengan jadwal lain di `dock_bay` yang sama | Pilih pintu dock alternatif atau geser waktu kedatangan |
| `Wave tidak siap rilis` | `WmsOutboundDispatchWorkflowService::dispatchWave` | Salah satu syarat gagal: `WAVE_EMPTY_LINES`, `INSUFFICIENT_AVAILABLE_STOCK`, `DOCK_BAY_NOT_ASSIGNED`, atau `CARRIER_CUTOFF_PASSED` | Tangani kode pada `blocking_reasons` sebelum mengulang rilis wave |

---

## 4. Galat Buku Besar & Integritas Sertifikat

| Kode / Pesan Galat | Kelas Sumber | Penyebab | Tindakan Pemulihan |
|---|---|---|---|
| `Nominal posting WMS tidak boleh negatif` | `WmsLedgerPoster::postBalanced` | Parameter `amountIdr < 0` | Gunakan metode posting yang sesuai arah (mis. `postCostRevaluationMarkDown` alih-alih nilai negatif) |
| `Sertifikat putaway melampaui kapasitas bin` | `PutawaySuggestionCertificateVerifier::verify` | Alokasi melebihi `freeCapacity` input bin | Laporkan bug integritas; batalkan transaksi |
| `Total jarak lintasan tidak memilih strategi minimum` | `PickSequenceCertificateVerifier::verify` | `totalDistance != min(sShape, largestGap)` | Periksa perhitungan jarak integer pada `PickPathPlanner` |
| `Tie-breaking wajib memilih S_SHAPE` | `PickSequenceCertificateVerifier::verify` | Jarak `S_SHAPE == LARGEST_GAP` namun strategi terpilih bukan `S_SHAPE` | Terapkan tie-breaker wajib `S_SHAPE` |
