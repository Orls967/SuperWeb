# Decisions Log

## 2026-09-29: Database Engine
- **Context:** Spec says MySQL, but project uses SQLite.
- **Decision:** Keep SQLite for development simplicity. Use `DB_CONNECTION=sqlite` in .env and `:memory:` for tests. The spec's MySQL references are treated as "relational DB" generically. SQLite supports all features needed (transactions, foreign keys, indexes). Enum columns will use string columns with validation since SQLite doesn't support native enums well in migrations — we'll use PHP Enum classes for type safety instead.
- **Reason:** Project was initialized with SQLite. Switching to MySQL would require local MySQL setup that may not exist. SQLite is sufficient for all features described.

## 2026-09-29: Pest vs PHPUnit
- **Context:** Spec requires Pest. Project currently has PHPUnit with Breeze tests.
- **Decision:** Install Pest alongside PHPUnit. Migrate existing Breeze tests to Pest syntax. All new tests will use Pest.
- **Reason:** Spec explicitly allows pestphp/pest. Pest provides cleaner syntax and arch testing support.

## 2026-09-29: Money Storage — decimal vs integer
- **Context:** Spec says "IDR stored as integer rupiah" but also says "ledger amounts decimal(36,18)".
- **Decision:** Regular IDR columns (prices, costs, totals) stay as integer (rupiah, no decimals). Ledger entries use decimal(36,18) as specified for multi-asset uniformity. The Money value object handles conversion.
- **Reason:** Follows spec literally. IDR has no subunits in practice; ledger needs decimal for crypto precision.

## 2026-09-29: SQLite enum handling
- **Context:** SQLite doesn't support MySQL ENUM type. Existing migrations use `$table->enum()` which SQLite handles as string with CHECK constraint.
- **Decision:** For new tables, use `$table->string()` columns with PHP Enum validation. This is more portable and aligns with the "use PHP Enum" pattern in the spec.
- **Reason:** Better compatibility and type safety through application-level enforcement.

## 2026-09-30: Genesis Liquidity Seeding via Double-Entry Ledger
- **Context:** System exchange accounts need initial liquidity for simulated exchange and trading.
- **Decision:** Seed initial liquidity via `LedgerService->post()` with `clearing:external:{ASSET}` as the source account (with `allow_negative=true`), rather than directly inserting arbitrary balance numbers into accounts.
- **Reason:** Guarantees that `bank:reconcile` passes with 0 discrepancy: global `SUM(entries) == 0` for all assets and `cached_balance == SUM(entries)` for every account from the very start.

## 2026-09-30: Atomicity & Lock Ordering in LedgerService
- **Context:** Concurrent transactions transferring between multiple accounts can deadlock if locks are acquired in arbitrary order.
- **Decision:** Collect all distinct involved account IDs and lock them via `whereIn('id', $ids)->orderBy('id', 'asc')->lockForUpdate()`. Balance calculations track running balances per account within the atomic block.
- **Reason:** Prevents database deadlocks and race conditions completely.

## 2026-09-30: Payment Gateway Hold-Capture Remainder Return & Invoice Payable
- **Context:** When capturing a payment intent that was held, the final amount may be less than the held amount (e.g. estimate higher than actual cost).
- **Decision:** `PaymentGatewayService::capture` executes an atomic ledger transaction that: (1) moves the final amount from `escrow:payment:IDR` to `revenueSplits`, and (2) immediately credits any remaining difference (`held - final`) back to the payer's wallet account.
- **Reason:** Ensures funds are never stranded in escrow and the ledger remains perfectly balanced and reconciled without requiring manual customer refund requests.

## 2026-09-30: Inventory Reservation & Checkout Morph Resolution
- **Context:** Store checkout required simultaneous inventory reservation, wallet balance verification with PIN, payment gateway charging, order fulfillment (including auto-acquiring cars to user's garage), and rollback on failure.
- **Decision:** Reservation deducts cached stock with `RESERVATION` reason movement. If payment succeeds, reservation is committed to `SALE` reason. If checkout fails, reservations are immediately released via `RESERVATION_RELEASE`. In `PaymentGatewayService`, `payable->getMorphClass()` is used so morph maps resolve properly to alias strings (e.g. `store_order`).
- **Reason:** Keeps inventory counts strictly truthful without race conditions, guarantees zero stock leakage, and integrates seamlessly with double-entry revenue splits and car ownership transfer.

## 2026-09-30: 5-Entry Double-Entry Crypto Trading & 15-Second Locked Quotes
- **Context:** Crypto trading required market order simulation with 15-second price lock, bi-directional conversions, 0.2% exchange fees, and multi-asset ledger integrity across fiat (IDR) and coins (BTC, ETH, SOL, BNB, USDT).
- **Decision:** Trades execute as an atomic 5-entry ledger transaction: (1) user IDR debit/credit, (2) system exchange IDR credit/debit, (3) exchange fee credit to `fee:banking:IDR`, (4) exchange crypto debit/credit, and (5) user crypto wallet credit/debit. Quotes enforce a strict 15-second expiration timestamp.
- **Reason:** Guarantees that both IDR and crypto assets balance individually to zero sum on every transaction (`SUM(IDR) = 0`, `SUM(ASSET) = 0`), ensuring `bank:reconcile` remains pristine while preventing slippage through locked quotes.

## 2026-09-30: C2C Used-Car Sale Uses Dedicated Buy-Now Flow, Not the Cart
- **Context:** Fase 5A.4 requires buying a used car from another user with escrow (hold → handover → confirm → capture), while the regular cart charges the wallet immediately.
- **Decision:** C2C listings (`productable_type = 'core_vehicle'`, `seller_id` set) are excluded from the normal catalog and cart (`CartService::addItem` throws). They are bought through a dedicated route `/store/mobil-bekas/{product:slug}/beli` handled by `PurchaseC2cVehicleAction`, which creates a single-item order and calls `PaymentGateway::hold`.
- **Reason:** Mixing escrow-held and immediately-charged items in one cart would make a single order need two conflicting payment modes. A dedicated flow keeps both paths simple and each order has exactly one payment intent.

## 2026-09-30: C2C Escrow Statuses, Platform Fee and Shipping
- **Context:** The existing `OrderStatus` enum only modelled platform-fulfilled orders (paid → processing → shipped → completed).
- **Decision:** Added `awaiting_handover`, `awaiting_confirmation` and `disputed`. Revenue split for a C2C order is `wallet:user:{seller}:IDR` for 99% and `revenue:store:IDR` for the 1% platform fee, computed with `intdiv` so the two integers always sum exactly to `grand_total`. C2C orders carry `shipping_fee = 0` because the car is handed over directly between the two users.
- **Reason:** The fee must not introduce rounding drift, otherwise `bank:reconcile` would report a discrepancy. A zero shipping fee keeps the captured amount identical to the listed price, so the seller and buyer both see the exact number they agreed on.

## 2026-09-30: C2C Escrow Expiry Handled at Order Level, Not Payment Intent
- **Context:** `payment:release-expired-holds` automatically releases any held intent whose `expires_at` has passed — which would refund a C2C buyer while the seller waits to hand over the car.
- **Decision:** C2C holds are created with `expires_at = null`. The 3-day confirmation window lives on the order (`auto_capture_at`) and is processed by the new `store:auto-capture-c2c` command (hourly), which *captures* to the seller instead of releasing. Opening a dispute clears `auto_capture_at` so funds stay in escrow until an admin decides.
- **Reason:** Auto-release and auto-capture are opposite outcomes; keeping the C2C deadline on the order prevents the generic payment job from resolving the deal the wrong way.

## 2026-09-30: Ownership Transfer Exposed Through a Core Contract
- **Context:** Store must move a `core_vehicles` row to the buyer and append an `ownership_transferred` block to the passport, but the hash-chain logic lives in Core.
- **Decision:** Added `Modules\Core\Contracts\TransfersVehicleOwnership`, implemented by `TransferVehicleOwnershipAction` (locks the vehicle row, reassigns `user_id`, clears the buyer's wishlist entry, records the passport block). Store resolves it from the container, mirroring the existing `AcquiresVehicle` contract.
- **Reason:** Keeps the append-only chain logic in one place and lets Finance (Fase 5C) reuse the same transfer path without duplicating hash-chain code.

## 2026-09-30: View Modul Duplikat Dihapus — Namespace `serve::` / `dex::` Jadi Sumber Tunggal
- **Context:** Setelah refactor Fase 0.5, view AutoServe/AutoDex ada dua salinan: `resources/views/{bookings,services,spareparts,autodex}` dan di dalam modul. Controller memanggil `view('bookings.show')` sehingga salinan app-level yang dirender, dan salinan modul menjadi kode mati. Akibatnya fitur yang hanya ditambahkan di salinan modul (tautan Paspor Digital di My Garage 5A.3 dan tombol "Beli di Store" 3.6) tidak pernah tampil di aplikasi.
- **Decision:** Salinan app-level dihapus; controller memakai view bernamespace (`serve::bookings.show`, `dex::garage`, dst). Salinan modul adalah superset dari salinan app sehingga tidak ada konten yang hilang. Satu assertion pada characterization test diperbarui dari `assertViewIs('bookings.invoice')` menjadi `assertViewIs('serve::bookings.invoice')` — assertion perilaku (status 200 dan perubahan status booking) tetap utuh.
- **Reason:** Duplikasi diam-diam ini membuat setiap perubahan UI modul berisiko tidak berefek. Satu sumber kebenaran mencegah bug senyap berulang, sekaligus menegakkan konvensi `view('{modul}::...')` dari spesifikasi.

## 2026-09-30: Estimate Sebagai Payable dengan Nilai Posting Tersimpan
- **Context:** Satu estimasi bisa menghasilkan dua posting ledger berbeda: capture escrow (sebesar dana ditahan) dan tagihan selisih bila biaya aktual melebihi estimasi. `PaymentGatewayService` membaca ulang payable dari database saat capture, sehingga split tidak bisa dititipkan lewat properti sementara.
- **Decision:** `serve_estimates` menyimpan `final_service_total` dan `final_parts_total` yang selalu berisi pembagian untuk posting berikutnya; `revenueSplits()` dan `payableAmount()` membacanya (fallback ke nilai estimasi bila belum diisi). Bagian escrow dihitung proporsional terhadap biaya akhir (`intdiv`), sisanya menjadi tagihan selisih, sehingga total kedua posting persis sama dengan biaya aktual.
- **Reason:** Menjaga invarian `payableAmount() == Σ revenueSplits()` yang dibutuhkan `charge()`, tanpa membuat model Payable kedua, dan menjamin `bank:reconcile` tetap nol.

## 2026-09-30: Backorder Sparepart Dibayar Akun Beban Bengkel
- **Context:** Saat estimasi disetujui tetapi stok sparepart kurang, spesifikasi meminta order internal yang dibayar akun sistem bengkel, bukan dompet customer.
- **Decision:** Ditambahkan akun sistem `expense:autoserve:parts:IDR` (kind baru `expense`, `allow_negative = true`). Pembelian backorder diposting sebagai `expense:autoserve:parts:IDR −biaya` dan `clearing:external:IDR +biaya`, dengan `store_order` internal berstatus `processing` atas nama akun admin bengkel. Saat barang diterima, stok masuk lewat movement `purchase` dan booking kembali dari `waiting_parts` ke `in_progress`.
- **Reason:** Arus kas ke pemasok tetap tercatat double-entry (reconcile nol), terpisah dari escrow customer, dan stok bengkel/toko tetap satu angka yang sama.

## 2026-09-30: Konvensi Tanda Akun `loan_receivable:IDR`
- **Context:** Spesifikasi 5C.2 meminta pencairan "loan_receivable:IDR → wallet user", tetapi akun itu semula `allow_negative = false` dan bersaldo 0, sehingga posting pertama selalu gagal.
- **Decision:** `loan_receivable:IDR` diubah menjadi `allow_negative = true` dengan konvensi: **saldo negatif = total pokok yang masih beredar di tangan peminjam**. Pencairan mengkredit dompet dan mendebit piutang (−pokok); setiap cicilan mengembalikan bagian pokok (+principal_part) sehingga saldo bergerak menuju nol saat lunas. Bunga masuk `fin:interest:IDR`, denda keterlambatan 0,1%/hari masuk `fin:penalty:IDR` (dua akun revenue baru).
- **Reason:** Menjaga posting tetap dua sisi dan `bank:reconcile` nol tanpa perlu menyuntik dana awal fiktif ke akun piutang, sekaligus membuat sisa pokok seluruh portofolio terbaca langsung dari satu akun.

## 2026-09-30: Pelunasan Awal Membayar Seluruh Sisa Cicilan
- **Context:** Bunga pembiayaan bersifat flat, sehingga "pelunasan awal" bisa diartikan hanya membayar sisa pokok atau membayar pokok + seluruh sisa bunga.
- **Decision:** Tombol "Lunasi Lebih Awal" membayar seluruh cicilan yang belum terbayar (pokok + bunga sesuai jadwal) dalam satu transaksi, lalu kolateral langsung dilepas.
- **Reason:** Konsisten dengan skema bunga flat yang sudah ditetapkan di awal akad dan membuat jumlah yang dibayar persis sama dengan jadwal yang dilihat peminjam — tidak ada perhitungan diskon bunga yang tidak pernah dijanjikan.

## 2026-09-30: Likuidasi Tidak Menandai Cicilan Sebagai Lunas
- **Context:** Saat kolateral dilikuidasi, sisa pokok dilunasi dari hasil penjualan, tetapi cicilan-cicilan yang belum jatuh tempo masih berstatus `scheduled`.
- **Decision:** Status cicilan dibiarkan apa adanya; pinjaman berpindah ke `liquidated` (atau `defaulted` bila hasil jual tidak menutup pokok) dan `closed_at` diisi. Scheduler `finance:charge-installments` hanya memproses pinjaman berstatus terbuka sehingga tidak ada penagihan ganda.
- **Reason:** Menandai cicilan "paid" tanpa transaksi ledger akan memalsukan riwayat pembayaran. Riwayat tetap jujur: cicilan itu memang tidak pernah dibayar, pinjamannya ditutup lewat likuidasi.

## 2026-09-30: Risk Monitor Lewat Domain Event `PricesTicked`
- **Context:** Finance perlu mengevaluasi LTV setiap harga kripto berubah, tanpa Crypto mengetahui keberadaan modul Finance.
- **Decision:** `PriceEngineService::tick()` mendispatch `Modules\Crypto\Domain\Events\PricesTicked`; `FinanceServiceProvider` mendaftarkan listener `MonitorLoanRisk` yang memanggil `EvaluateLoanRiskAction::evaluateAll()`.
- **Reason:** Domain event adalah mekanisme lintas modul yang diizinkan spesifikasi; Crypto tetap tidak punya ketergantungan ke Finance, dan modul lain bisa ikut mendengarkan tick tanpa mengubah price engine.

## 2026-09-30: In-App Notification Hub & Unread Counter
- **Context:** Sistem memerlukan notifikasi in-app untuk pembaruan status servis, pembayaran, transfer saldo, margin call, dan pesanan toko secara real-time/semi-real-time tanpa memperkenalkan ketergantungan infrastruktur eksternal (seperti Pusher atau WebSocket daemon).
- **Decision:** Diimplementasikan `core_notifications` dengan payload terstruktur (`type`, `title`, `message`, `action_url`, `read_at`), dikelola oleh `NotificationService`. UI menggunakan Alpine.js polling ringan ke endpoint `/notifications/recent` dengan debounce 30 detik untuk memperbarui badge counter merah di navbar dan popup modal.
- **Reason:** Menyediakan UX interaktif tanpa konfigurasi infrastruktur server tambahan, berjalan mulus di lingkungan production standar maupun local testing.

## 2026-09-30: Cross-Module Activity Logging
- **Context:** Diperlukan audit trail aktivitas pengguna yang terpadu mencakup beragam interaksi lintas modul (booking servis, transfer perbankan, transaksi trading kripto, pinjaman dana, pembelian toko).
- **Decision:** Dibuat `ActivityLogger` di Core yang menyimpan rekaman ke `core_activity_logs` dengan relasi polimorfik opsional ke entitas subjek (`subject_type`, `subject_id`). Action penting di modul-modul lain memanggil `ActivityLogger::log()` atau mendengarkan domain event.
- **Reason:** Menghindari fragmentasi log riwayat per modul dan memungkinkan satu tampilan feed aktivitas komprehensif bagi customer maupun audit admin.

## 2026-09-30: Dashboard Berbasis Peran Terintegrasi
- **Context:** Setiap peran (Admin, Mekanik, Customer) memiliki konteks kerja yang sangat berbeda dan membutuhkan ringkasan data dari berbagai modul di satu halaman utama (`/dashboard`).
- **Decision:** `DashboardController` mengecek peran pengguna (`Auth::user()->role`) dan merender view spesifik:
  - `Customer`: Menggabungkan saldo wallet IDR & crypto portfolio, ringkasan mobil di garasi, servis aktif, pinjaman aktif, serta pesanan terbaru.
  - `Admin`: Menampilkan omzet gabungan (bengkel, store, fee), antrean servis, status order pending, dan ringkasan risiko pembiayaan.
  - `Mekanik`: Fokus pada antrean pekerjaan servis yang ditugaskan (*assigned*), tracking suku cadang, dan tombol perubahan status pengerjaan cepat.
- **Reason:** Mengoptimalkan alur kerja pengguna berdasarkan perannya dan mengeliminasi kebutuhan navigasi berulang ke modul-modul berbeda untuk melihat status terkini.

## 2026-09-30: Demo Seeder Terpadu Lintas Seluruh Fase
- **Context:** Penguji dan evaluator memerlukan status aplikasi yang siap pakai dengan data representatif di seluruh modul (kendaraan dengan paspor valid, saldo dompet, transaksi kripto, produk toko, estimasi servis, pinjaman berjalan).
- **Decision:** Dibuat `PlatformSeeder` yang dijalankan otomatis di akhir `DatabaseSeeder`, membuat skenario realistis untuk akun demo `admin@autoserve.test`, `mekanik@autoserve.test`, dan `customer@autoserve.test`, lengkap dengan paspor genesis, pesanan toko, notifikasi belum dibaca, dan aktivitas terkini.
- **Reason:** Menjamin kemudahan demonstrasi instan setelah `php artisan migrate:fresh --seed` tanpa konfigurasi manual.

## 2026-09-30: Satuan Bahan Dasar & Resep BOM Bertingkat (Modul Resto)
- **Context:** Masakan Padang memiliki struktur bumbu dasar (bumbu merah, bumbu gulai) yang dimasak dalam jumlah besar di dapur dan dipakai sebagai bahan untuk berbagai lauk, serta satuan pembelian (kg, ikat, liter) yang berbeda dari takaran resep.
- **Decision:** (1) Semua bahan baku disimpan dalam satuan dasar terkecil (`gram`, `ml`, `pcs`) menggunakan presisi `decimal(18,6)`. Konversi dari satuan dagang (`kg`, `liter`, `ikat`) dipetakan lewat `resto_unit_conversions`. (2) Resep dimodelkan sebagai Bill of Materials (BOM) bertingkat di mana satu baris resep dapat merujuk ke bahan mentah ataupun sub-resep `Recipe` lain. (3) `RecipeCostCalculator` melakukan kalkulasi HPP rekursif dengan moving average cost per outlet dan memperhitungkan waste factor. Jika terjadi referensi sirkular (A $\to$ B $\to$ A), dilempar `RecipeCycleDetected`.
- **Reason:** Menjamin keakuratan perhitungan HPP hingga 6 desimal tanpa pembulatan mengambang (float), mencegah loop tak terbatas, dan merefleksikan proses memasak nyata rumah makan Padang.

## 2026-09-30: Perluasan Kontrak `InventoryService` untuk Bahan Baku Resto
- **Context:** Aturan arsitektur melarang modul Resto mengakses domain Inventory secara langsung, dan aturan modul melarang breaking changes pada kontrak publik yang ada (`InventoryService`). Bahan baku restoran memiliki satuan desimal (`decimal(18,6)`) dan dikelola per outlet, berbeda dengan `store_products` yang berupa integer global.
- **Decision:** Kontrak `Modules\Inventory\Contracts\InventoryService` diperluas dengan 4 method baru non-breaking: `availableIngredient`, `deductIngredient`, `addIngredient`, dan `adjustIngredient`. Data pergerakan disimpan pada `resto_ingredient_movements` dan saldo per outlet di `resto_ingredient_stocks`. Di dalam implementasi `InventoryService`, mutasi dilakukan lewat query tabel tanpa mengimpor kelas domain Resto.
- **Reason:** Mematuhi batas modul modular monolith (lintas modul hanya via kontrak/event), mempertahankan backward compatibility untuk Store & Finance, dan mendukung presisi bahan baku masakan per outlet.

## 2026-09-30: Konvensi Posting Ledger Produksi Batch (Internal Inventory Transfer)
- **Context:** Saat bahan mentah dimasak menjadi porsi jadi (batch produksi), nilai bahan ditransfer menjadi nilai barang siap hidang di outlet yang sama. Total nilai persediaan outlet tidak berkurang sampai porsi tersebut terjual (COGS) atau dibuang (Waste).
- **Decision:** `CookBatchAction` melakukan posting double-entry pada akun `inventory:resto:{outlet}:IDR`:
  - Kredit `inventory:resto:{outlet}:IDR` sebesar `-$cost_total` (pengurangan nilai bahan mentah)
  - Debet `inventory:resto:{outlet}:IDR` sebesar `+$cost_total` (penambahan nilai barang jadi / porsi matang)
  Saldo bersih persediaan outlet tidak berubah, jumlah entri = 0, dan `bank:reconcile` terbukti tetap 0 selisih.
- **Reason:** Merefleksikan standar akuntansi persediaan di mana konversi bahan baku ke barang jadi adalah perpindahan sub-kategori aset, bukan pengakuan beban (beban baru diakui saat terjual lewat `expense:resto:cogs` atau rusak lewat `expense:resto:waste`).

## 2026-09-30: Siklus Etalase Hidang Padang & Batas Resirkulasi 3 Kali
- **Context:** Tradisi rumah makan Padang meletakkan piring-piring lauk di meja tamu ("sistem hidang"). Piring yang tidak disentuh dapat kembali ke etalase, sedangkan piring yang disentuh sebagian dihitung terjual penuh. Perlu aturan higienitas ketat agar piring tidak berulang kali keluar-masuk meja tanpa batas.
- **Decision:** (1) Ditetapkan konstanta pada model `DisplayTray`: `MAX_RECIRCULATION = 3` dan `MAX_DISPLAY_HOURS = 6`. (2) Piring yang dikembalikan utuh dari meja dinaikkan `recirculation_count`-nya. Jika mencapai batas maksimal atau melewati 6 jam sejak dimasak, piring ditolak masuk kembali dan dialihkan ke `discarded` (waste). (3) Nilai HPP sisa porsi yang dibuang otomatis diposting ke `expense:resto:waste:IDR` dan dikreditkan dari `inventory:resto:{outlet}:IDR`.
- **Reason:** Memastikan standar higienitas pangan masakan Padang modern, mencegah penyajian makanan basi, dan menjaga akurasi pembukuan waste operasional.

## 2026-09-30: Konvensi Tanda Double-Entry Pembayaran Tunai & Kasir Resto
- **Context:** Transaksi kasir POS dapat berupa tunai (cash fisik di laci) atau non-tunai (wallet pelanggan). Akun pendapatan (`revenue:resto:{outlet}:*`) memiliki sifat saldo non-negatif (`allow_negative=false`), sementara total mutasi per aset harus selalu bernilai 0 (`SUM(amount) = 0`).
- **Decision:**
  - Penjualan Tunai: `cash:drawer:{outlet}:IDR` bertindak sebagai sumber aliran dana (`-$grand_total`) yang diimbangi dengan kredit akun pendapatan (`+$split`).
  - Setoran Kas ke Bank (`SettleCashAction`): dana ditransfer dari laci kasir ke rekening kliring bank eksternal, di mana `cash:drawer` di-debet `+$amount` (mengembalikan saldo laci kasir ke titik impas/nol) dan `clearing:external:IDR` dikredit `-$amount`.
  - Selisih Kas Shift (`CloseShiftAction`): selisih (`variance = counted - expected`) diposting ke `expense:resto:cash_variance:IDR` (`+$variance`) dan diimbangi pada `cash:drawer:{outlet}:IDR` (`-$variance`).
- **Reason:** Menjamin saldo akun pendapatan selalu positif sesuai aturan bisnis akuntansi, memfasilitasi rekonsiliasi kas fisik harian per outlet, dan memastikan `bank:reconcile` selalu seimbang dengan 0 selisih global per aset.

## 2026-09-30: Perhitungan PB1 10% & Pembulatan Rp 100 Terdekat
- **Context:** Pajak Restoran (PB1) di Indonesia adalah 10% dari nilai konsumsi makanan/minuman setelah potongan diskon. Nilai transaksi kasir fisik sering menghasilkan angka pecahan rupiah ganjil yang tidak memiliki uang koin riil.
- **Decision:** Sistem menghitung PB1 10% setelah dikurangi diskon (`round(subtotalAfterDiscount * 0.10)`), kemudian menjumlahkan total sementara, dan membulatkan ke kelipatan Rp 100 terdekat (`round(rawTotal / 100) * 100`). Selisih pembulatan (positif atau negatif) disimpan pada kolom `rounding` pada model `Order` dan diserap secara proporsional ke dalam pembagian pendapatan (`revenueSplits`) agar jumlah entri ledger sama persis dengan `grand_total`.
- **Reason:** Mengeliminasi masalah kembalian uang koin receh di meja kasir restoran, sekaligus menjaga integritas pembukuan double-entry tanpa selisih 1 rupiah pun.

## 2026-09-30: Moving Average Cost (MAC) Presisi Tinggi & Akuntansi In-Transit Rantai Pasok
- **Context:** Harga pembelian bahan baku restoran dari pemasok fluktuatif di pasar, dan transfer bahan dari Dapur Sentral (Central Kitchen) ke outlet cabang memakan waktu pengiriman antarkota/antarwilayah.
- **Decision:**
  - MAC dihitung menggunakan `BigDecimal` dengan 6 angka di belakang koma (`RoundingMode::HalfUp`): `((old_stock * old_cost) + (received_qty * unit_cost)) / (old_stock + received_qty)` dan diperbarui otomatis pada setiap `ReceiveGoodsAction`.
  - Transfer bahan baku antarcabang tidak pernah menurunkan total aset persediaan pada neraca kelompok usaha. Selama perjalanan, nilai barang ditampung di `inventory:resto:transit:IDR` (`AccountKind::INVENTORY`).
  - Saat diterima di cabang tujuan, akun transit dikosongkan. Jika ada selisih fisik (misal: telur pecah, kemasan bocor), nilai selisih langsung dibukukan sebagai kerugian susut ke `expense:resto:waste:IDR`.
  - Utang usaha pemasok (`ap:supplier:{id}:IDR`) dikreditkan saat barang diterima fisik (`ReceiveGoodsAction`) dan didebet saat pelunasan kas/bank (`PaySupplierAction`). Umur utang dipilah menjadi bucket `0-30`, `31-60`, dan `60+` hari berdasarkan `terms_days` pemasok oleh `PayableAgingQuery`.
- **Reason:** Menjamin neraca keuangan grup selalu akurat dan transparan, meminimalkan kerugian transfer tanpa jejak, dan menjaga audit kesesuaian double-entry ledger 0 selisih.

## 2026-09-30: Penutupan Harian & Sumber Data Laporan Analitik
- **Context:** Query analitik yang memindai tabel transaksi mentah (`resto_orders`) ribuan kali per hari dapat menurunkan performa basis data secara signifikan.
- **Decision:** Command terjadwal `resto:close-day` (berjalan tiap 23:59) merangkum performa harian outlet ke tabel `resto_daily_summaries` (`gross_sales`, `discount`, `pb1`, `net_sales`, `cogs`, `waste_value`, `gross_margin`, `transactions`, `guests`, `cash_variance`, `top_items`). Seluruh dasbor dan laporan analitik diwajibkan hanya membaca dari `resto_daily_summaries`. Opsi `--check` memvalidasi angka ringkasan harian terhadap riwayat entri buku besar (ledger) untuk mendeteksi kecurangan atau inkonsistensi data.
- **Reason:** Memberikan respons halaman dasbor instan dengan budget query sangat rendah (O(1)), sembari menyediakan mekanisme audit harian yang ketat.

## 2026-09-30: Delivery, Katering Bertahap & Royalti Waralaba Resto
- **Context:** Penjualan delivery jarak jauh dan katering skala besar memerlukan aturan operasional yang berbeda dari dine-in (biaya kemasan nasi bungkus terpisah, ongkir berjenjang radius km, risiko pembatalan mendadak, serta pemotongan royalti holding grup).
- **Decision:**
  1. **Delivery & Bungkus:** Menggunakan `takeaway_price` dari `resto_menu_items`. Bahan baku kertas bungkus (`ING-KERTAS-BUNGKUS`) dipotong otomatis lewat `InventoryService::deductIngredient` dengan alasan `sale`. Ongkir berjenjang (Rp10.000 untuk 3 km pertama + Rp2.500/km ekstra). Jika pengiriman gagal kirim, hanya harga makanan (+PB1) yang dikembalikan ke dompet pelanggan; ongkir tetap ditahan sebagai kompensasi kurir.
  2. **Katering & Escrow:** `CateringOrder` mengimplementasikan `Payable`. Saat pesanan disetujui, deposit 30% ditahan di escrow (`PaymentGateway::hold()`). Pada hari H pengiriman, deposit dicairkan (`capture()`) dan sisa 70% didebet dari dompet pemesan. Kebijakan pembatalan: pembatalan $\ge$ H-3 melepaskan hold kembali ke pelanggan, sedangkan pembatalan $<$ 3 hari mengeksekusi penyitaan deposit (capture denda pembatalan ke pendapatan katering outlet).
  3. **Royalti Franchise:** Command `resto:post-royalty` membebankan royalti dan marketing fee harian dari omzet bersih `DailySummary`. Jurnal buku besar mencatat `expense:resto:franchise_royalty:IDR` (debit) dan mengkredit rekening holding `revenue:group:royalty:IDR` serta `revenue:group:marketing:IDR`, seimbang tanpa selisih (sum = 0).
- **Reason:** Menjamin manajemen kas dan persediaan akurat tanpa celah kebocoran dana operasional restoran dan holding.

## 2026-09-30: Mall Leasing, Deposit Liability & Anti-Overlap Invariant
- **Context:** Pengelolaan sewa unit mall (leasing) membutuhkan perlindungan terhadap tumpang-tindih tanggal sewa (double leasing), akuntansi uang jaminan sewa (security deposit) yang merupakan kewajiban (liabilitas) dan bukan pendapatan instan, serta eskalasi sewa multi-tahun.
- **Decision:**
  1. **Anti-Overlap Invariant:** Model `Unit` memvalidasi ketersediaan unit melalui `isAvailableBetween($start, $end)`. `CreateLeaseAction` menolak pembuatan draf sewa jika unit sudah terikat draf atau kontrak aktif lain pada rentang tanggal yang bersinggungan (`UnitAlreadyLeasedException`).
  2. **Deposit Liability Accounting:** Saat kontrak diaktifkan (`ActivateLeaseAction`), deposit didebet dari dompet tenant (`wallet:user:{id}:IDR`) dan dikreditkan ke `liability:mall:tenant_deposit:IDR` (`AccountKind::LIABILITY`, `allow_negative=true`).
  3. **Penyelesaian Terminasi (`TerminateLeaseAction`):** Uang deposit digunakan terlebih dahulu untuk melunasi tagihan tertunggak tenant (diakui sebagai pendapatan `revenue:mall:rent_settlement:IDR`), dan sisa deposit dikembalikan utuh ke saldo dompet tenant. Liabilitas deposit di-debet penuh kembali ke 0.
  4. **Eskalasi Sewa Multi-Tahun:** Sewa dengan model `fixed` atau `greater_of` mengalami eskalasi majemuk tahunan: $\text{MonthlyRent}(Y) = \text{base\_monthly\_rent} \times (1 + \text{escalation\_percent}/100)^{Y-1}$.
- **Reason:** Memastikan integritas fisik ketersediaan unit komersial, kepatuhan standar akuntansi keuangan (deposit sebagai liabilitas yang dapat dikembalikan), dan otomatisasi perhitungan sewa multi-tahun.

## 2026-09-30: Mall Billing, Alokasi Pelunasan Terurut & Rekonsiliasi Double-Entry
- **Context:** Tagihan bulanan mall menggabungkan beberapa komponen pendapatan heterogen (sewa pokok, top-up bagi hasil omzet, service charge, utilitas listrik/air berjenjang, lembur AC operasional, dan denda keterlambatan). Saat tenant membayar sebagian (cicilan), diperlukan aturan bisnis prioritas pelunasan yang baku dan pembukuan double-entry yang presisi.
- **Decision:**
  1. **Idempotensi Invoicing & Revenue Share Top-Up:** `mall:generate-invoices` membuat/memperbarui tagihan bulanan secara idempoten pada kunci `[lease_id, period_month]`. Untuk model `greater_of`, bila omzet bagi hasil melebihi sewa pokok minimum, sistem menerbitkan dua baris sewa: baris `base_rent` sebesar batas minimum dan baris `revenue_share_topup` sebesar selisihnya, sehingga total sewa sama persis dengan persentase omzet.
  2. **Tarif Utilitas Berjenjang (Tiered Tariffs):** Tagihan listrik dan air dihitung berjenjang per rentang kWh/m³ ditambah biaya beban tetap (abonemen) melalui `UtilityTariffCalculator`.
  3. **Alokasi Pelunasan Terurut (Priority Allocation):** Saat terjadi pembayaran penuh maupun parsial via `AllocatePaymentAction`, dana pembayaran dialokasikan secara ketat mengikuti urutan: (1) Denda Keterlambatan $\to$ (2) Utilitas (AC Overtime, Air, Listrik) $\to$ (3) Service Charge $\to$ (4) Sewa Pokok & Bagi Hasil. Pembukuan double-entry mendebet saldo dompet tenant `wallet:user:{id}:IDR` dan mengkreditkan secara proporsional ke akun pendapatan spesifik masing-masing baris (`revenue:mall:*`), menjamin `SUM(amount) = 0`.
  4. **Denda Harian & Penangguhan (Suspension H+30):** Command `mall:apply-penalties` membebankan denda 0,1%/hari dari sisa tagihan yang belum lunas. Jika keterlambatan melebihi 30 hari kalender, status kontrak unit otomatis diubah menjadi `suspended`.
  5. **Auto-Debit Tanpa PIN vs Portal Dengan PIN:** Penagihan otomatis kontrak via `MallAutoDebitAction` mendebet saldo dompet tenant tanpa memerlukan interaksi PIN, sedangkan pembayaran manual mandiri oleh tenant via portal mewajibkan verifikasi 6 digit PIN dompet pengguna serta pengamanan isolasi IDOR.
  6. **Audit Billing Otomatis (`mall:audit-billing`):** Didaftarkan sebagai gerbang kualitas (quality gate) wajib untuk memverifikasi kesesuaian matematis setiap invoice terhadap entri buku besar (ledger) dan memastikan tidak ada selisih saldo global per aset.
- **Reason:** Menjamin keadilan pengakuan pendapatan, akurasi pelunasan bertahap, dan transparansi mutlak antara tagihan tenant dengan neraca buku besar perusahaan.




## 2026-09-30: Parkir Progresif, Validasi Tenant sebagai Piutang & Perbaikan PIN Dompet
- **Context:** Fase 14 menambahkan operasional parkir Duta Mall (gate masuk/keluar, tarif progresif, langganan member, validasi belanja tenant, footfall). Dalam pengerjaannya ditemukan beberapa cacat nyata pada kode fase sebelumnya yang harus diperbaiki ke akar masalahnya.
- **Decision:**
  1. **Tarif progresif & pembulatan jam:** `ParkingTariffCalculator` membulatkan durasi ke jam penuh berikutnya (61 menit = 2 jam), menerapkan masa tenggang 15 menit, dan membatasi tarif per siklus 24 jam pada `max_daily_rate`. Seluruh aritmetika memakai `BigDecimal`; kasus 12 jam (5.000 + 11×3.000 = 38.000) dipotong menjadi batas harian 30.000.
  2. **Validasi tenant disimpan sebagai jam, bukan rupiah:** Saat tenant memvalidasi tiket, sistem menyimpan `validation_free_hours` (bukan nominal), karena durasi akhir baru diketahui di gate keluar. Nominal potongan dihitung saat kendaraan keluar lewat `discountForFreeHours()`.
  3. **Potongan validasi = piutang tenant, bukan kehilangan pendapatan:** Nominal yang ditanggung tenant ditagihkan pada invoice bulanan sebagai baris `parking_validation` yang dikreditkan ke `revenue:mall:parking:IDR`. Kolom `validation_invoice_id` menandai sesi yang sudah ditagih sehingga tidak dobel; sesi yang belum tertagih otomatis ikut pada siklus berikutnya agar piutang tidak hilang.
  4. **`mall_tenants.external_ref` ditambahkan:** Kolom ini diminta spesifikasi Fase 12 tetapi terlewat. Berupa string (mis. `DM-01` untuk outlet Resto, `AUTOSERVE-DM` untuk bengkel), BUKAN foreign key, agar modul Mall tidak bergantung pada tabel modul lain. Dipakai `ParkingValidator` sekarang dan `TenantSalesProvider` pada Fase 16.1.
  5. **Kas parkir mengikuti konvensi kas Resto:** `cash:mall:parking:IDR` (`AccountKind::CASH`, `allow_negative=true`) dicatat negatif saat menerima tunai dan pendapatan dikreditkan positif, sama seperti `cash:drawer:{outlet}:IDR`.
  6. **PIN dompet: satu sumber kebenaran.** `User::verifyPin()` DIHAPUS karena mengandung lubang keamanan: bila user belum punya PIN, method itu menerima PIN default `'123456'`, dan selain itu membandingkan PIN plaintext. `Mall\PayInvoiceAction` dialihkan ke `Banking\VerifyPinAction` (hash + lockout 5× gagal) seperti yang sudah dipakai AutoServe, Resto, dan Crypto. Kolom `users.pin` kini tidak dipakai jalur autentikasi apa pun dan dijadwalkan dihapus pada Fase 17.4.
  7. **Perbandingan kolom tanggal wajib `whereDate()`:** Cast `date` Eloquent menyimpan nilai lengkap `Y-m-d H:i:s`, sehingga `where('start_date','<=','2026-09-30')` bernilai salah pada SQLite (perbandingan string). Pencarian member parkir dan `ApplyLatePenaltiesAction` diubah memakai `whereDate()` supaya konsisten di MySQL maupun SQLite.
  8. **Command terjadwal fase 9–13 didaftarkan:** `resto:close-day`, `resto:post-royalty`, `resto:check-stock`, `mall:generate-invoices`, `mall:auto-debit`, `mall:apply-penalties`, `mall:audit-billing`, dan `mall:renew-parking-members` sebelumnya tidak pernah masuk `routes/console.php` sehingga seluruh otomatisasi harian/bulanan tidak berjalan. Sekarang terjadwal.
  9. **Utang teknis yang dicatat untuk Fase 17:** (a) Modul Resto/AutoServe/Crypto/Mall mengimpor `Banking\Application\Actions\VerifyPinAction` langsung, melanggar aturan "lintas modul hanya via Contracts" — perlu dipromosikan menjadi contract `Banking\Contracts\VerifiesWalletPin` di 17.6. (b) `PayOrderAction` Resto menandai pesanan lunas untuk metode `voucher`/`points` tanpa posting ledger; lubang ini ditutup pada 16.3 ketika voucher dan poin menjadi aset ledger.
- **Reason:** Menjaga agar pendapatan parkir tidak bocor lewat validasi tenant, memastikan perhitungan tarif tahan terhadap pembulatan dan zona waktu, serta menutup lubang keamanan PIN sebelum lini bisnis baru ikut memakainya.

## 2026-09-30: Duta Points Multi-Asset Ledger, Voucher Breakage & Facility Work Order
- **Context:** Fase 15 menambahkan program loyalitas Duta Points, voucher belanja mall, pemesanan atrium event, dan manajemen fasilitas (Preventive Maintenance & Work Order). Program loyalty dan voucher melibatkan penerbitan nilai kuasi-uang yang berisiko merusak integritas rekonsiliasi buku besar bila dicatat sebagai counter statis di database.
- **Decision:**
  1. **Duta Points Sebagai Aset Buku Besar (`PTS`):** Poin dicatat secara double-entry menggunakan mata uang aset `PTS`. Akun pengguna `points:user:{id}:PTS` bertambah (`allow_negative=false`) dan diimbangi oleh akun penampung kewajiban `liability:mall:points:PTS` (`allow_negative=true`). Pelacakan kedaluwarsa poin 12 bulan memakai `mall_point_batches` (FIFO).
  2. **Siklus Hidup Voucher & Breakage:**
     - Penukaran poin ke voucher: mendebet `PTS` user dan mengkredit `liability:mall:points:PTS`, sekaligus menerbitkan komitmen moneter IDR: mengkredit `liability:mall:voucher:IDR` dan mendebet beban promosi `expense:mall:loyalty:IDR`.
     - Penyelesaian voucher saat dipakai di tenant (`mall:settle-vouchers`): mencairkan dana dari `liability:mall:voucher:IDR` langsung ke dompet tenant `wallet:user:{id}:IDR`.
     - Voucher kedaluwarsa (`mall:expire-vouchers`): kewajiban voucher yang tidak dipakai (`liability:mall:voucher:IDR`) dipindahkan sebagai pendapatan unearned breakage ke `revenue:mall:voucher_breakage:IDR`.
  3. **Pemesanan Atrium & Perlindungan Jadwal Bentrok:** Pemesanan ruang event (`EventSpace`) memvalidasi tumpang tindih waktu untuk pemesanan berstatus `CONFIRMED` atau `ONGOING` (`EventScheduleConflictException`). Tarif dihitung berdasarkan tarif harian ruang + sewa booth bazaar.
  4. **Pemeliharaan Preventif (PM) & Tagihan Perbaikan Tenant:** Aset fasilitas gedung dipantau jadwal servis berkala (`next_pm_date`). Generator harian menerbitkan `WorkOrder` otomatis berstatus `SCHEDULED` dan memperpanjang jadwal PM berikutnya. Work order akibat kerusakan tenant dapat dibebankan ke invoice sewa tenant sebagai `InvoiceLineType::REPAIR_COST` (prioritas alokasi ke-6, setelah parkir dan sebelum service charge).
- **Reason:** Menjamin keutuhan audit moneter program loyalitas di buku besar umum (`bank:reconcile` bersih), mengeliminasi risiko bentrok jadwal acara mall, serta memastikan seluruh biaya operasional fasilitas tertagih dengan tertib.

