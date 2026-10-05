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

## 2026-09-30: Integrasi Lintas Lini Bisnis (Holding Multi-Module Architecture)
- **Context:** Fase 16 mengintegrasikan lini bisnis yang sebelumnya independen: RM Sari Ranah (Resto) dan AutoServe Express (Bengkel) beroperasi sebagai tenant Duta Mall, validasi tiket parkir mall langsung dari POS kasir Resto, voucher dan Duta Points berlaku lintas modul (Resto, Store, Mall), transfer kepemilikan kendaraan di AutoDex otomatis membatalkan membership parkir pemilik lama, dashboard P&L holding konsolidasi real-time dari buku besar umum dengan query budget $\le 30$ query, serta navigasi terpadu dan global search `Ctrl+K`.
- **Decision:**
  1. **Integrasi Tenant Sales via Tagged Service Providers (`mall.tenant_sales_provider`):** Modul Mall menyediakan contract `Modules\Mall\Contracts\TenantSalesProvider`. Modul Resto mendaftarkan `RestoTenantSalesProvider` (mengkonsolidasi omzet dari `resto_daily_summaries` atau pesanan POS berbayar untuk outlet yang cocok dengan `external_ref`), dan AutoServe mendaftarkan `AutoServeTenantSalesProvider`. Saat `mall:generate-invoices` dijalankan, `TenantSalesService` mengiterasi penyedia yang terdaftar secara otomatis tanpa manipulasi manual.
  2. **Validasi Parkir POS via Contract `ParkingValidator`:** Kasir Resto dapat memvalidasi tiket parkir pelanggan saat pelunasan hidang/pesanan. Modul Resto hanya bergantung pada `Modules\Mall\Contracts\ParkingValidator` (tidak mengimpor Domain/Application Mall secara langsung). Tiket parkir mencatat jam gratis, dan potongan tarif ditagihkan kembali ke tenant saat invoice bulanan terbit (`InvoiceLineType::PARKING_VALIDATION`).
  3. **Poin & Voucher Lintas Modul via Contract `LoyaltyLedger`:** Duta Points dan Voucher Belanja Mall dapat digunakan di Resto POS dan Toko Onderdil (Store). Modul Resto dan Store mengonsumsi contract `Modules\Mall\Contracts\LoyaltyLedger`. Penukaran poin untuk diskon langsung membukukan double-entry: mendebet `PTS` user dan mengkredit `liability:mall:points:PTS`, serta mendebet `expense:mall:loyalty:IDR` dan mengkredit `liability:mall:voucher:IDR`. Penggunaan voucher membukukan potongan harga dengan mencairkan klaim ke tenant pada siklus settlement mingguan.
  4. **Pembersihan Keanggotaan Parkir Otomatis Pada Transfer Kendaraan:** Modul Core memancarkan event `VehicleOwnershipTransferred`. Modul Mall mendaftarkan event listener `CancelParkingMembershipOnVehicleTransfer` yang otomatis menonaktifkan (`MemberStatus::CANCELLED`) keanggotaan parkir aktif pemilik lama atas kendaraan tersebut, mencegah penyalahgunaan kartu akses parkir oleh pemilik sebelumnya.
  5. **Dashboard Konsolidasi Holding P&L Real-Time:** `ConsolidatedPlQuery` mengagregasi seluruh buku besar pendapatan (`revenue:*`) dan beban (`expense:*`) dari tabel `bank_ledger_entries` dan `bank_ledger_accounts` ke dalam 4 pilar usaha (Otomotif & Bengkel, Keuangan & Perbankan, Kuliner RM Sari Ranah, dan Properti Duta Mall) beserta tren harian 30 hari dalam **hanya 2 query database** (jauh di bawah batas anggaran 30 query). Controller `GroupDashboardController` mematuhi aturan arch-test tanpa memanggil facade DB secara langsung.
  6. **Navigasi Terpadu & Global Search (Ctrl+K):** Menu navigasi di seluruh service provider distandarisasi ke dalam 5 grup besar (`Grup & Admin`, `Otomotif`, `Keuangan`, `Kuliner`, `Properti`). `GlobalSearchQuery` menyediakan pencarian fuzzy lintas modul mencakup menu navigasi, booking servis, unit mobil, produk onderdil, menu & pesanan resto, tenant, unit mall, kontrak sewa, dan tiket parkir, yang dapat diakses instan melalui shortcut keyboard `⌘K` / `Ctrl+K`.
- **Reason:** Menjaga isolasi arsitektural modular monolith (setiap modul hanya berkomunikasi melalui Contracts/Events), menjamin integritas rekonsiliasi double-entry ledger (`bank:reconcile` 0 selisih), dan memberikan visibilitas eksekutif holding menyeluruh dalam performa query optimal.

## 2026-09-30: Skala, Hardening & Observabilitas (Fase 17)
- **Context:** Menghadapi beban data produksi berskala tinggi (150.000 parkir, 60 tenant, 3 outlet resto, 12 bulan penagihan), platform memerlukan kepastian batas query SQL, ketahanan keamanan (IDOR, mass assignment, PIN lockout, signed URL, XSS), kepatuhan arsitektur batas modul tanpa ketergantungan konkrit, dan sistem observabilitas komprehensif.
- **Decision:**
  1. **Seeder Demo Skala Besar (`DemoLargeSeeder`):**
     - Memasukkan 150.000 sesi parkir dalam chunk 500 baris di dalam satu transaksi database (`DB::transaction`), mengeksekusi dalam **3,94 detik** tanpa melanggar batasan variabel parameter SQLite (32.766).
     - Mengisi data historis 12 bulan penagihan mall dengan `paid_amount = 0` (status `OVERDUE` dan `ISSUED`), merefleksikan piutang berumur (aging receivables) tanpa menciptakan entri pembukuan siluman (phantom ledger entries), sehingga `mall:audit-billing` tetap lolos 0 selisih.
  2. **Anggaran Query SQL (`QueryBudgetTest`):**
     - Menetapkan ambang batas query ketat pada 9 rute utama: Dashboard ($\le 25$), AutoDex ($\le 15$), Store ($\le 15$), Resto POS ($\le 20$), Mall Site Plan ($\le 20$), Mall Billing ($\le 25$), Mall Parking ($\le 20$), Group Dashboard ($\le 15$), dan Global Search API ($\le 15$). Seluruh rute lulus uji dengan eager loading teroptimasi dan alias relasi `MenuCategory::items()`.
  3. **Penyelesaian Utang Teknis: Promosi Contract `VerifiesWalletPin`:**
     - Menghilangkan kopling langsung modul eksternal (`AutoServe`, `Resto`, `Mall`, `Crypto`, `Finance`, `Store`) ke action internal `Modules\Banking\Application\Actions\VerifyPinAction`.
     - Dibuat contract `Modules\Banking\Contracts\VerifiesWalletPin` yang diimplementasikan oleh `VerifyPinAction` dan di-bind di `BankingServiceProvider`.
     - Arch test `ModuleBoundariesTest` memverifikasi secara otomatis bahwa tidak ada modul eksternal yang mengimpor `VerifyPinAction` secara konkrit.
  4. **Pengerasan Keamanan (`SecurityTest`):**
     - Pencegahan IDOR: Tenant dilarang mengakses invoice milik tenant lain via portal mandiri (`abort(403)`).
     - Perlindungan Mass Assignment: Kolom sensitif pada `Invoice`, `Order`, dan `Vehicle` dilindungi via `$fillable`.
     - Proteksi PIN Brute Force: Akun otomatis terkunci selama 15 menit setelah 5 kali gagal memasukkan PIN berturut-turut (`PinLockedException`).
     - Verifikasi Signed URL: Menolak permintaan tanpa signature atau dengan signature yang dimanipulasi pada rute sensitif.
     - Pencegahan XSS: Seluruh input pengguna diescape otomatis menjadi entitas HTML pada rendering Blade template (`{{ ... }}`).
  5. **Observabilitas & Diagnosa Terpadu (`super:health-check` & Admin Health View):**
     - Tabel `core_audit_logs` append-only merekam seluruh audit trail diagnostik sistem dan event operasional.
     - Artisan command `super:health-check` dan Controller `HealthCheckController` memindai 7 pilar arsitektur platform secara serentak (Koneksi Database, Cache, Storage, Double-Entry Ledger, Paspor Kendaraan, Billing Mall, Shift Resto) dengan laporan visual glassmorphic modern di `/admin/health`.
  6. **Konfigurasi Memori CLI Test Suite:**
     - Ditambahkan `<ini name="memory_limit" value="512M" />` di `phpunit.xml` untuk mencegah kegagalan memory allocation pada parsing AST statis Pest di suite berukuran besar (297 test, 1317 asersi).
- **Reason:** Menjamin keandalan operasional, meminimalisir risiko keamanan finansial, menjaga isolasi batas modul, dan memberikan transparansi observabilitas penuh bagi tim DevOps & manajemen holding.

## 2026-09-30: Penutupan Arsitektur & Dokumentasi Komprehensif (Fase 18)
- **Context:** Menyelesaikan seluruh fase pengembangan ekspansi platform (Fase 0 s/d 18), menyiapkan dokumentasi teknis dan operasional yang sinkron dengan kode aktif, serta memastikan seluruh tolok ukur *Definition of Done* terpenuhi tanpa kompromi.
- **Decision:**
  1. **Dokumentasi Lengkap 5 Lini Bisnis:** Memperbarui `README.md` dan `docs/ARCHITECTURE.md` dengan menyertakan diagram urutan (sequence diagram Mermaid), konvensi multi-aset double-entry ledger (`IDR`, `PTS`, Kripto), peta akun sistem, serta seluruh 12 modul aktif.
  2. **Runbook Operasional Komprehensif:** Menyusun `docs/RUNBOOK.md` berisi SOP diagnosa harian, panduan penanganan insiden moneter/teknis (5 Incident Playbooks), tabel crontab scheduler produksi, dan prosedur Disaster Recovery.
  3. **Kode Bersih Tanpa Artefak Debug:** Dipastikan 0 `dd()`, 0 `dump()`, 0 `TODO`, 0 `FIXME` di seluruh pohon kode aplikasi (`modules/`, `app/`, `tests/`).
  4. **Pencapaian Kualitas Mutlak:** Memverifikasi seluruh tolok ukur kualitas lolos serentak: `php artisan test` (297 passed, 1317 assertions), `vendor/bin/pint --test` (clean), `npm run build` (clean), `bank:reconcile` (0 selisih), `core:verify-passports` (valid), `resto:close-day --check` (valid), `mall:audit-billing` (0 selisih), dan `super:health-check` (7/7 HEALTHY).
- **Reason:** Menghadirkan sistem kelas enterprise yang tangguh, teruji, terdokumentasi rapi, dan siap beroperasi di lingkungan produksi holding konglomerasi.

## 2026-09-30: Penstabilan Demo Seeder, Rute Kripto Asli, dan Penghapusan Kolom PIN (Fase 19)
- **Context:** Fase 18 menyisakan celah pada seeder: trade kripto tidak memakai alur pasar asli melainkan quote acak yang memicu saldo minus saat seeder dieksekusi, pinjaman HODL-to-Drive berisiko margin call jika harga kripto berfluktuasi tanpa buffer kolateral, `users.pin` masih ada di skema database padahal PIN dompet sudah dipindahkan ke `bank_wallet_pins`, serta `resto:close-day` dan `mall:audit-billing` melewati quality gate karena database kosong alih-alih memvalidasi transaksi riil.
- **Decision:**
  1. **Alur Perdagangan Kripto Asli (Real Trade Flow, Bukan Genesis Injection):**
     Di `DemoCustomerSeeder`, pembelian kripto customer dialirkan melalui alur perdagangan riil: `PriceEngineService->getQuote()` dengan parameter eksplisit (`fromAsset`, `toAsset`, `amount`, `isBuy`), memverifikasi kecukupan saldo IDR customer, lalu mengeksekusi `TradeCryptoAction->execute()`. Untuk menjamin determinisme 100% antar-run pengujian, generator harga kripto di-seed secara deterministik (`mt_srand(12345)`).
  2. **Buffer Kolateral Pinjaman HODL-to-Drive (1.6x Buffer, Bebas Margin Call):**
     `OpenLoanAction` diperluas dengan parameter opsional `collateralQty`. Di `DemoCustomerSeeder`, pinjaman kripto mengunci 1.6x buffer kolateral di atas kebutuhan minimum sehingga rasio LTV awal berada pada level ~31.25% (jauh di bawah batas margin call 70% dan batas likuidasi 85%), menjamin ketiga pinjaman aktif customer tetap dalam kondisi sehat dan stabil.
  3. **Konvensi Akun Demo Terstandarisasi:**
     20 akun customer demo (`customer01@autoserve.test` s/d `customer20@autoserve.test`) dibuat dengan password terstandarisasi `password` dan 6-digit PIN dompet `123456` yang terdaftar langsung di tabel `bank_wallet_pins` dengan proteksi brute force (5x lockout). Masing-masing customer memiliki saldo IDR, minimal 1 kendaraan berpaspor digital terverifikasi, dan riwayat transaksi booking servis yang terhubung ke buku besar umum.
  4. **Penghapusan Kolom `users.pin` (Single Source of Truth):**
     Kolom legacy `pin` pada tabel `users` dihapus melalui migrasi `2026_09_30_180001_drop_pin_from_users_table`. Seluruh modul wajib memvalidasi PIN melalui contract `Banking\Contracts\VerifiesWalletPin` yang mengarah ke `bank_wallet_pins`.
  5. **Gate Harian Wajib Berisi Data Usaha Non-Nol:**
     Default `DatabaseSeeder` kini mengeksekusi minimal 1 hari usaha Resto yang ditutup via `CloseBusinessDayAction` (dengan 5 transaksi POS terbayar bernilai non-nol Rp 170.000) dan minimal 1 penagihan bulanan Mall yang terbit via `GenerateMonthlyBillingAction` dengan pembayaran parsial invoice via `PayInvoiceAction` (Rp 20.000.000). Dengan demikian `resto:close-day --check` dan `mall:audit-billing` memverifikasi data riil, bukan lolos semu akibat tabel kosong.
  6. **Kompatibilitas Seeder Demo Skala Besar (`DemoLargeSeeder`):**
     Offset penomoran unit (`sprintf('%s-%03d', $floor, $unitIdx + 100)`) dan nomor kontrak sewa mall (`sprintf('LSE-DM-2026-%03d', $idx + 10)`) diterapkan di `DemoLargeSeeder` agar penambahan 60 tenant demo skala besar dapat berjalan harmonis di atas data dasar tanpa bentrok unique constraint.
- **Reason:** Memastikan seluruh data seeder mencerminkan alur domain dan invarian moneter riil, melindungi integritas double-entry ledger, dan menghilangkan segala bentuk ambiguitas autentikasi PIN.

## 2026-09-30: Perhitungan Berat Tertagih (Chargeable Weight) Multimoda (Fase 21)
- **Context:** Penetapan tarif logistik memerlukan standarisasi berat tertagih (chargeable weight) yang memperhitungkan volume kargo (berat volumetrik) vs berat aktual barang agar utilisasi kapasitas moda transportasi darat, laut, dan udara optimal dan adil secara ekonomi.
- **Decision:**
  1. `ChargeableWeightCalculator` mengimplementasikan formula `max(berat aktual, berat volumetrik)` secara murni menggunakan `Brick\Math\BigDecimal` untuk mencegah galat floating-point.
  2. **Darat / Kurir (SameDay, Express, Regular, Economy, LTL):** Pembagi volumetrik $6.000\text{ cm}^3/\text{kg}$. Pembulatan ke atas (`RoundingMode::Up`) ke bilangan bulat $1\text{ kg}$ terdekat, batas minimum $1\text{ kg}$.
  3. **Udara (AirFreight / IATA):** Pembagi volumetrik $6.000\text{ cm}^3/\text{kg}$. Pembulatan ke atas ke kelipatan $0,5\text{ kg}$ terdekat (`(raw * 2)->toScale(0, RoundingMode::Up) / 2`), batas minimum $5,0\text{ kg}$.
  4. **Laut LCL (W/M - Weight or Measurement):** $1\text{ CBM} = 1.000\text{ kg}$ ($1\text{ Revenue Ton}$ / RT). Berat tertagih adalah $\max(\text{ton aktual}, \text{CBM})$, batas minimum $1\text{ RT}$ ($1.000\text{ kg}$).
  5. **FTL & FCL:** Berbasis unit kargo penuh (per truk / per kontainer), bukan berbasis berat tertagih.
## 2026-09-30: Perencana Rute (Route Planner) Murni & Pembatasan DG / Reefer (Fase 22)
- **Context:** Operasi jaringan kargo multimoda memerlukan perencana rute (route planner) otomatis yang menentukan itinerary transfer antarmoda (darat, laut, udara) secara cepat, aman, dan mematuhi batasan operasional transit serta keselamatan muatan berbahaya (Dangerous Goods) dan rantai pendingin (Cold Chain).
- **Decision:**
  1. **Pure Domain Service (`RoutePlanner`):** Algoritma menerima graf dalam memori (`RouteGraph`) dan `RouteRequest`, tanpa query database di dalam loop pathfinding, dengan target kinerja < 300 ms untuk 300 lokasi × 10.000 jadwal (terbukti lolos benchmark ~190 ms).
  2. **Aturan Moda per Tingkat Layanan (`ServiceLevel`):**
     - Express / SameDay / AirFreight: Diperbolehkan moda UDARA dan DARAT (feeder). Ditolak melalui laut.
     - Economy / LCL / FCL: Diperbolehkan moda LAUT dan DARAT (first/last mile). Ditolak melalui udara.
     - LTL / FTL / Regular: Diperbolehkan moda DARAT.
  3. **Waktu Transfer Minimum (Minimum Connection Time):** Transfer antar-leg pada hub transit wajib memenuhi `leg[i+1].etd >= leg[i].eta + hub.min_connection_minutes` serta cut-off jadwal.
  4. **Matriks Pembatasan Dangerous Goods (DG):**
     - Kargo dengan DG Kelas 1 (Explosives) dan Kelas 7 (Radioactive) dilarang keras diangkut melalui moda UDARA. Kargo DG ini harus dialihkan melalui moda Darat atau Laut bersertifikasi.
  5. **Armada Berpendingin (Reefer):** Paket yang memiliki spesifikasi suhu (`temp_min_c10` / `temp_max_c10`) hanya boleh dialokasikan pada leg dengan aset berpendingin (`isReeferCapable = true`).
## 2026-09-30: Konsolidasi Kargo (Load Planning), Segregasi DG, dan Wajib SOLAS VGM (Fase 22)
- **Context:** Pemuatan kontainer laut, ULD udara, dan truk konsolidasi memerlukan algoritma optimasi muatan yang ketat, aturan segregasi muatan berbahaya (IMDG Code), pencegahan pemuatan ganda pada kontainer yang sama, serta kepatuhan konvensi maritim SOLAS Chapter VI mengenai Verified Gross Mass (VGM).
- **Decision:**
  1. **Eksklusivitas Kontainer Aktif:** Satu kontainer/ULD/truk fisik tidak boleh terdaftar pada lebih dari satu load aktif (`planning`, `consolidating`, `sealed`, `loaded`, `in_transit`). Pelanggaran ditolak dengan `ActiveLoadConflictException`.
  2. **FCL vs LCL:**
     - FCL (Full Container Load): Dibatasi secara ketat tepat 1 shipment per kontainer (`max_shipments = 1`). Penambahan shipment kedua ditolak.
     - LCL (Less than Container Load): Konsolidasi di CFS menggunakan algoritma First-Fit-Decreasing (FFD) berbasis pengurutan volume dan batas berat kargo.
  3. **Matriks Segregasi Dangerous Goods (DG - IMDG Code):**
     - Kelas 1 (Explosives) TIDAK BOLEH dikonsolidasi dalam satu kontainer/ruang muat bersama: Kelas 2.1 (Flammable Gas), Kelas 3 (Flammable Liquids), Kelas 4.1/4.2/4.3 (Flammable Solids), Kelas 5.1/5.2 (Oxidizing Substances & Organic Peroxides), dan Kelas 8 (Corrosive Substances).
     - Kelas 3 (Flammable Liquids) TIDAK BOLEH dicampur dengan Kelas 5.1 (Oxidizing Substances).
     - Barang non-DG dapat dikonsolidasi bersama DG yang kompatibel.
  4. **Pemisahan Kargo Dingin (Reefer):** Paket reefer hanya boleh dimasukkan ke kontainer berpendingin aktif (`is_reefer = true`) dengan rentang suhu yang sesuai.
  5. **Penegakan Wajib SOLAS VGM (Verified Gross Mass):**
     - Sebelum kontainer diizinkan dimuat ke kapal (`LoadOntoScheduleAction` pada moda laut), data VGM wajib tercatat: berat total (`vgm_kg`), metode penimbangan (`method_1` penimbangan kontainer terisi atau `method_2` penimbangan isi + tara), sertifikasi nama penanggung jawab, dan stempel waktu.
     - Upaya pemuatan kontainer ke kapal tanpa VGM terverifikasi ditolak mutlak dengan `MissingSolasVgmException`.
- **Reason:** Menjamin keselamatan pelayaran internasional (SOLAS VI/2), mencegah kebakaran atau reaksi kimia berbahaya di laut, dan mengoptimalkan utilisasi ruang muat kontainer.








## 2026-10-02: Dispatch Validation Lives in AssignScheduleResourcesAction, Not the Controller
- **Context:** Fase 22.7 requires assigning truck + driver with SIM, working-hour, maintenance and vehicle-passport checks.
- **Decision:** One transactional action locks schedule, truck and driver rows (`lockForUpdate`) and validates in order: schedule mode/status, truck status (maintenance/retired/not available), Vehicle Passport hash-chain via Core's `VerifyPassportAction`, schedule overlap for truck and driver, then `Driver::validateAssignment` (SIM validity evaluated on the trip's ETD, class, 8h/day and 4h continuous limits). The daily limit additionally counts the driver's other active same-day assignments, because `daily_driving_minutes` is only updated when a trip actually runs. Reassigning releases the previous truck inside the same transaction; every assignment/release is kept in `lgx_dispatch_assignments` for audit.
- **Reason:** Controllers stay thin (arch test forbids DB in controllers) and two dispatchers racing for the same truck/driver cannot both succeed.

## 2026-10-02: Delivery OTP Is Hashed, Delivered Through the Shipper Notification, and Rate Limited
- **Context:** Fase 22.8 requires a 6-digit OTP stored as a hash. The consignee has no platform account.
- **Decision:** `StartDeliveryAction` generates the OTP, stores only `Hash::make($otp)` on the shipment (hidden attribute), and sends the plaintext once to the shipper via `NotificationService` (simulated SMS/WhatsApp relay). The OTP never appears in tracking events or the public timeline. `CompleteDeliveryAction` limits wrong guesses to 5 per hour per shipment (`RateLimiter`), since a 6-digit space is brute-forceable by a driver. The OTP hash is cleared on Delivered and ReturnToSender.
- **Reason:** Keeps the secret out of the database, logs and the append-only custody chain while still giving the consignee a code the driver cannot see.

## 2026-10-02: Failed Delivery Keeps OutForDelivery Until the Third Attempt
- **Context:** The shipment state machine does not allow `OutForDelivery -> AtHub`, and the spec says 3 failures -> ReturnToSender.
- **Decision:** Attempts 1-2 are recorded in `lgx_delivery_attempts` + `DELIVERY_FAILED` custody events while the status stays `OutForDelivery` (driver may resend the OTP and retry). Attempt 3 transitions to `ReturnToSender` and writes `RETURN_TO_SENDER`.
- **Reason:** No state-machine change is needed and the attempt counter (`failed_delivery_attempts`) is the single source of truth.

## 2026-10-02: Exceptions Are Separate Records; SLA Detection Is Idempotent by Dedupe Key
- **Context:** Fase 22.9 needs structured exceptions and an idempotent `lgx:detect-late`.
- **Decision:** `lgx_shipment_exceptions` stores type/severity/status with a unique `dedupe_key` (`late:{shipment}`, `missort:{shipment}:{event}`, `delivery_failed:{shipment}:{attempt}`). Late exceptions do not change shipment status and do not write custody events (internal signal only); manual blocking types (damaged, address invalid, vehicle breakdown) move the shipment to `Exception` and write `EXCEPTION_RAISED`/`EXCEPTION_RESOLVED` events. Closing the last open exception of a shipment in `Exception` status requires an explicit resume status. SLA hours per service level live in `config/logistics.php` (`sla_hours`), due time = `booked_at + hours`. `lgx:detect-late` is scheduled every 15 minutes with `withoutOverlapping` and auto-closes late exceptions of finished shipments.
- **Reason:** Re-running the command can never create duplicates, and public tracking is not polluted by internal SLA signals.

## 2026-10-02: Fase 23 — Konvensi Ledger Logistik dan Tafsiran Test (a)–(e)
- **Context:** DoD Fase 19–25 meminta "setiap alur uang di tabel Fase 23 punya test (a)–(e)" tanpa mendefinisikan huruf-hurufnya.
- **Decision:** Dipakai lima dimensi seragam di setiap file test alur uang: (a) jurnal dan saldo benar pada jalur utama, (b) idempoten/tidak ganda, (c) `bank:reconcile` tetap 0 selisih, (d) penolakan keadaan/input tidak valid tanpa efek samping, (e) otorisasi dan cakupan akses per peran. Konvensi tanda ledger yang dipakai seluruh alur: kredit positif, debit negatif, total per jurnal 0. Seluruh posting lewat `LogisticsLedger` (menjamin akun `lgx:*` ada) dan memakai kunci idempotensi deterministik.
- **Reason:** Tafsiran ini bisa diaudit oleh `lgx:audit-billing` dan menutup risiko transaksi ganda, selisih buku besar, dan akses tidak sah.

## 2026-10-02: Pengakuan Pendapatan Terjadi di Dalam Transaksi Pengantaran
- **Context:** Fase 23.1 mengakui pendapatan saat `ShipmentDelivered`.
- **Decision:** Event dipicu di dalam transaksi `CompleteDeliveryAction` dan listener sinkron memposting jurnal. Prabayar: debit `unearned_freight`, kredit `freight_revenue`. Pascabayar: debit piutang `lgx:ar:{shipper}`, kredit `freight_revenue`; pembayaran invoice mengkredit AR sehingga saldo AR kembali 0. Nilai yang diakui adalah `total_amount_idr` penuh (PPN masih simulasi dan belum dipisah ke akun utang pajak).
- **Reason:** Jika posting ledger gagal, pengantaran ikut dibatalkan, sehingga tidak pernah ada resi Delivered tanpa pendapatan. `revenue_recognized_at` dan kunci `lgx:revenue:{id}` menjaga idempotensi.

## 2026-10-02: Quote Menyimpan Nilai Barang, Asuransi, dan COD; Fee COD Dipotong Saat Settlement
- **Context:** `QuoteShipmentAction` menerima `declaredValueIdr`, `insured`, `codAmountIdr` tetapi tidak menyimpannya sehingga resi tidak pernah membawa COD/asuransi. Selain itu surcharge `COD_FEE` pada quote akan menggandakan fee COD yang dipotong saat settlement.
- **Decision:** Kolom `declared_value_idr`, `insured`, `cod_amount_idr` ditambahkan ke `lgx_quotes` (ikut hash payload anti-tamper) dan disalin ke resi oleh kedua action booking. Surcharge `COD_FEE` tidak lagi diterapkan di quote; fee COD (config `cod_fee_rate`, minimum `cod_fee_min_idr`, maksimum sebesar dana COD) dipotong saat `lgx:settle-cod` dan masuk `cod_fee_revenue`.
- **Reason:** Satu titik pengenaan fee dan data COD/asuransi benar-benar sampai ke alur uang.

## 2026-10-02: COD Tiga Tahap dengan Setoran Persis
- **Decision:** Collect (debit kas driver, kredit titipan COD shipper) terjadi di transaksi pengantaran dan wajib dikonfirmasi driver; setoran di hub harus sama persis dengan total pengumpulan driver yang belum disetor (selisih ditolak, tidak ada jurnal); `lgx:settle-cod` mencairkan D+N (config `cod_settlement_days`, default 2) ke dompet shipper dikurangi fee. Kas tetap tercatat di akun kas hub sebagai aset perusahaan.

## 2026-10-02: Carrier Subkontrak — Akrual Saat Leg Selesai, Bayar Setelah Termin
- **Decision:** Biaya hanya diakrual saat leg selesai (`CompleteShipmentLegAction`, satu transaksi): debit `carrier_cost`, kredit `carrier_payable:{carrier}`. `lgx:pay-carriers` (mingguan) membayar leg yang melewati `payment_terms_days` terhadap `clearing:external:IDR`, dengan kunci jurnal dari hash daftar leg sehingga tidak dapat terbayar dua kali. Margin shipment = pendapatan diakui − biaya carrier yang sudah diakrual.

## 2026-10-02: Klaim — Pembuat, Pengaju, dan Penyetuju Harus Tiga Orang Berbeda
- **Context:** Spec menulis "penyetuju != pengaju != pembuat".
- **Decision:** Tiga aktor: pembuat membuat draft (shipper pemilik atau staf), pengaju (staf, bukan pembuat) mengajukan, penyetuju (admin/admin logistik, bukan pembuat maupun pengaju) memutuskan; pembayaran oleh admin. Batas ganti rugi: berasuransi = nilai deklarasi; tidak berasuransi = 10x ongkir (maks. nilai deklarasi); keterlambatan = ongkir. Anti bayar ganda berlapis: kolom `active_key` unik per resi (dibebaskan hanya saat ditolak), kunci jurnal `lgx:claim_pay:{id}`, dan status `paid` terminal.

## 2026-10-02: D&D — Hari Kalender Zona Waktu Lokasi, Akrual Kumulatif, Invoice Terpisah
- **Decision:** Hari dihitung per tanggal kalender pada zona waktu lokasi (hari mulai = hari ke-1), dikurangi free days, tarif eskalasi opsional; tarif dipilih berdasarkan kekhususan (lokasi+ukuran > lokasi > ukuran > umum). Akrual kumulatif memposting selisih (debit AR shipper, kredit `dd_revenue`) dengan kunci yang memuat total kumulatif, sehingga `lgx:accrue-dd` aman diulang. Invoice D&D memakai `lgx_invoices.kind='dd'` agar pembayaran memakai alur invoice yang sama dan tidak menghalangi invoice freight periode yang sama.

## 2026-10-02: Bea Cukai adalah Simulasi dengan Jalur Merah Otomatis
- **Decision:** BM = nilai x tarif; PPN dan PPh 22 (API 2,5% / non-API 7,5%, dapat dikonfigurasi per HS) dihitung atas (nilai + BM), pembulatan half-up per baris. Jalur merah bila ada HS lartas (`requires_inspection`) atau nilai total >= `customs_red_lane_threshold_idr`: resi masuk `CustomsHold` (hanya dari PickedUp/InTransit/AtHub) dengan exception `customs_hold`. Bea dibayar shipper dari dompet (PIN) ke `customs_duty_payable`; hanya admin yang meloloskan dan resi kembali ke status sebelumnya.

## 2026-10-02: BBM Memakai Integer dan Anomali Ketat > 30%
- **Decision:** Liter x1000, jarak meter, km/l x100, deviasi basis poin. Metode isi penuh; baseline = rata-rata hingga 5 log terakhir yang tidak anomali (minimal 2 sampel); anomali bila |deviasi| > 3.000 bp (tepat 30% bukan anomali). Odometer wajib naik dan memperbarui truk. Driver hanya boleh mencatat untuk truk pada trip aktifnya.

## 2026-10-02: lgx:audit-billing dan Seed Demo Bernilai Non-Nol
- **Decision:** 15 pemeriksaan membandingkan dokumen dengan saldo ledger (unearned vs resi belum Delivered, Delivered vs pengakuan, invoice, AR per shipper, D&D, bea cukai, COD, carrier, klaim, BBM); command gagal (exit 1) bila ada selisih. `LogisticsFinanceSeeder` membangun seluruh alur lewat action produksi (bukan jurnal manual) sehingga audit pada seed default memeriksa 29 dokumen non-nol.

## 2026-10-02: Fase 24 — Integrasi Lintas Lini Logistik (Store, AutoServe, Resto, Mall, Finance)
- **Context:** Sistem modular monolith SuperWeb menghubungkan modul Logistik dengan lini bisnis lainnya tanpa merusak boundary architecture (arch tests).
- **Decision:**
  1. **Store -> Logistik**: Event `OrderPaid` didengarkan oleh `CreateShipmentOnOrderPaid` yang mengeksekusi `BookShipmentForOrderAction`. Biaya kirim diposting ke `lgx:unearned_freight`, dan nomor resi disimpan di `store_orders.tracking_number`.
  2. **Pengiriman Mobil & Paspor Digital**: `DeliverVehicleByCarrierAction` membuat shipment FTL car carrier. Saat terkirim, event `DELIVERED_BY_CARRIER` dicatat ke hash-chain paspor kendaraan (`core_vehicles`) dengan integritas kriptografis SHA-256.
  3. **Armada -> AutoServe**: Pembaruan odometer truk yang melebihi interval servis memicu `FleetServiceDue`, membuat janji servis di AutoServe secara otomatis, dan mengubah status truk menjadi `Maintenance`. Setelah servis selesai, `CompleteFleetMaintenanceAction` mengembalikan status truk menjadi `Available` dan memperbarui paspor kendaraan.
  4. **Resto Cold Chain**: Replenishment bahan baku dari Dapur Sentral (CK-01) menggunakan truk reefer berpendingin. Pembacaan suhu disimpan di `lgx_temperature_readings`; suhu di luar rentang aman memicu alert deviasi suhu. Penerimaan di outlet memperbarui stok via Inventory Resto.
  5. **Mall Loading Dock**: `lgx_dock_appointments` menyediakan slot waktu bongkar muat di Duta Mall dengan lock pencegahan overlap waktu. Portal tenant dapat memesan slot dan petugas keamanan dapat melakukan check-in / check-out truk.
  6. **Finance & Observabilitas**: Pendapatan logistik dikonsolidasikan ke Group Dashboard P&L tanpa menambah anggaran query (tetap 2 query). `SystemHealthService` menambahkan pilar ke-8 untuk audit billing dan rantai kustodi logistik.
- **Reason:** Menjaga isolasi domain modul melalui contracts dan domain events serta mencegah coupling langsung antar domain.

## 2026-10-03: Fase 25 — Skala, API v1, Webhook Outbox, dan Control Tower
- **Context:** Penutupan ekspansi platform logistik skala enterprise memerlukan seeder skala besar, API RESTful publik/mitra, keandalan webhook outbox, penegakan anggaran kinerja, dan dashboard analitik eksekutif.
- **Decision:**
  1. **LogisticsLargeSeeder**: Menginisialisasi data multimodal skala besar (ratusan truk, puluhan kapal, ribuan kontainer, dan rantai lacak balak) menggunakan teknik batch insert deterministik dan seimbang dengan ledger.
  2. **Anggaran Kinerja (QueryBudgetTest)**: Lookup resi publik dibatasi <= 3 query (p95 < 50ms), dispatch board <= 10 query, control tower dashboard <= 12 query, dan lgx:accrue-dd < 30 detik.
  3. **API v1 (Laravel Sanctum)**: Endpoint `/api/v1/logistics` dilindungi token bearer dengan granular abilities (`quote:create`, `shipment:create`, `shipment:read`), rate limit bertingkat (60 req/min auth, 30 req/min public), serta dukungan header `Idempotency-Key` pada pembuatan shipment.
  4. **Webhook Outbox Pattern**: Menggunakan tabel `lgx_webhook_endpoints` dan `lgx_webhook_deliveries` dengan signature `X-SRX-Signature: sha256=<hmac>`, exponential backoff (maksimal 8 kali retry), penanganan dead-letter, dan command terjadwal `lgx:retry-webhooks`.
  5. **Antrean Idempoten**: `ProcessBulkShipmentUploadJob` menerapkan `ShouldBeUnique` berbasis `batchId` untuk mencegah eksekusi impor CSV ganda. Seluruh scheduler logistik terdaftar di `routes/console.php`.
  6. **Control Tower**: Antarmuka pusat kendali untuk `logistics_admin` menampilkan metrik On-Time In-Full (OTIF), utilisasi armada, dwell time kontainer pelabuhan/depot, saldo titipan COD kasir/driver, dan margin per jalur transportasi (lane).
- **Reason:** Memastikan platform logistik siap skala produksi, aman dari gangguan jaringan atau replay ganda, serta memiliki observabilitas operasional menyeluruh.

## 2026-10-03: Fase 26.1 — Autentikasi API dengan Sanctum Asli
- **Context:** Pemeriksaan Fase 24–25 menemukan `auth:sanctum` Logistics v1 bukan guard Sanctum: provider membuat alias test helper dan guard berbasis session web. API tidak memvalidasi bearer token atau token abilities.
- **Decision:** Pasang `laravel/sanctum ^4.3`, migrasi `personal_access_tokens`, gunakan `HasApiTokens` pada `User`, dan terbitkan token bernama dengan abilities/expiry opsional melalui UI profil (`POST /profile/api-tokens`). Token hanya dapat dicabut oleh pemilik lewat profil. Set `sanctum.guard` kosong agar API v1 hanya menerima bearer token dan tidak mewarisi session web; ability diperiksa lewat `tokenCan()` dari Sanctum. Hapus alias, guard custom, shim helper, dan array ability in-memory.
- **Verification:** Security tests membuktikan bearer token valid, no-token/session-only/revoked/expired => 401, ability tidak cocok => 403, serta issuance/revocation profil berhasil.

## 2026-10-03: Fase 26.2 — Dokumentasi Quality Gate Fase 25
- **Context:** `docs/AUDIT.md` belum memuat gate final Fase 25, dan README mencantumkan assertion count lama serta belum memiliki ringkasan API v1.
- **Decision:** Tambahkan catatan Fase 25 di AUDIT, sinkronkan README ke 545 tests / 3203 assertions setelah perubahan Fase 26.1, dan jelaskan Logistics commands, token API, abilities, expiry, rate limits, serta respons autentikasi. Perbarui `CODEBASE.md` sesuai protokol orientasi.
- **Verification:** Full Pest suite (545 passed, 3203 assertions, 0 skipped), Pint, Vite, `bank:reconcile`, Logistics billing/custody/capacity, Mall billing, Vehicle Passport, dan `super:health-check` lulus.

## 2026-10-03: Fase 26.3 — Sweep Kebenaran Seluruh Action
- **Context:** Audit menyeluruh 143 Action lintas 8 modul terhadap 4 konvensi (transaksi, key idempotensi deterministik, event afterCommit, lockForUpdate) menemukan 110 cacat nyata; 2 klaim dari putaran audit awal terbukti salah dan dibuang setelah verifikasi langsung ke kode.
- **Decision:**
  1. **Gateway pembayaran dirombak total**: `charge/hold/capture/release/refund` masing-masing satu `DB::transaction` + `lockForUpdate` pada baris intent + kunci deterministik (`tx_cap_/tx_rel_/tx_ref_` + id intent) + penanganan `UniqueConstraintViolationException`. Partial refund kini membalik pendapatan **proporsional** terhadap pecahan refund (sebelumnya membalik seluruh split → `UnbalancedTransactionException`), dengan pelacakan `refunded_amount` (kolom baru) dan penolakan over-refund kumulatif.
  2. **Event ditunda ke commit**: 8 titik (`VehicleAcquired`, `VehicleOwnershipTransferred`, `BookingCompleted`, `ShipmentDelivered` ×2, `PaymentCaptured/Held/Released/Refunded`) memakai `DB::afterCommit`, sehingga listener tidak pernah membaca state belum-commit dan tidak berjalan saat rollback.
  3. **Guard dibawa ke dalam lock**: limit kredit B2B, ketersediaan quote, status pembatalan resi, status hub scan, alokasi kapasitas, poin/voucher, PO/suplai, status estimasi & pesanan — semuanya dipindahkan ke dalam transaksi setelah `lockForUpdate` dengan re-check terhadap baris terkunci (TOCTOU ditutup). `ReserveCapacityAction` juga mengunci baris driver/aset karena kunci baris jadwal sendiri ternyata tidak cukup untuk jadwal berbeda dengan driver sama.
  4. **Kunci acak diganti deterministik di 38 titik** (turunan id intent, id resi, nomor struk, id batch urut) dengan guard replay; operasi manual (transfer, top-up, bayar PO, setoran kas, bayar mall, redeem voucher, collateral, buka pinjaman) diberi `idempotency_key` tersembunyi di formulir agar double-submit tidak menggandakan pencatatan.
  5. **Koreksi keamanan PIN**: `VerifyPinAction` di bawah `lockForUpdate` dengan inkrementasi atomik — counter yang di-read-modify-write bersamaan sebelumnya tidak pernah mencapai 5, sehingga brute-force PIN dompet tidak terbatas.
  6. **Integritas kepemilikan & escrow**: penyelesaian pesanan Store (status + akuisisi kendaraan) dibungkus satu transaksi dengan perbaikan saat retry; alur C2C dibuat dapat diulang (release escrow yang sudah terlanjur commit tidak lagi membuat pesanan tak terbatal).
- **Verification:** 554 tests / 3233 assertions 0 skipped; 5 test race berurutan baru (`ActionConcurrencyRegressionTest`) + 4 test regresi Payment; pint clean; `bank:reconcile`, `lgx:audit-billing`, `mall:audit-billing` = 0 selisih.
- **Reason:** 110 temuan semuanya berpotensi menggandakan uang, stok, atau limit kredit pada retry/ketidaksamaan; audit juga membuktikan sejumlah tuduhan tidak benar, jadi laporan mencatat yang tertutup dan yang ternyata bukan cacat.

## 2026-10-04: Fase 26.5 — RBAC Granular (Multi-Role, Scope Entitas, Gate/Policy Integrasi)
- **Context:** Sistem otorisasi sebelumnya hanya mengandalkan kolom string enum `users.role` (admin, mekanik, customer, dsb) yang kaku, tidak mendukung multi-role, tidak mendukung hak akses granular per aksi/modul, dan tidak memiliki scoping per entitas.
- **Decision:**
  1. **Schema RBAC:** Tambahkan tabel `roles`, `permissions`, `role_permission`, dan `user_role` dengan dukungan scoping entitas (`entity_type`, `entity_id`).
  2. **Backward Compatibility:** Pertahankan kolom `users.role` sebagai fallback dan cermin (mirror). `CheckRole` middleware diperbarui untuk mengecek tabel RBAC terlebih dahulu sebelum jatuh kembali ke `users.role`.
  3. **Gate/Policy Integration:** Daftarkan `Gate::before` di `CoreServiceProvider` yang mengecek `RbacService::userHasPermission($user, $ability)` untuk mengintegrasikan permission RBAC secara transparan ke seluruh otorisasi Laravel.
  4. **Seeder & Backfill:** `RbacSeeder` memetakan permission default untuk setiap role sistem dan secara otomatis mem-backfill seluruh user lama dari nilai `users.role` masing-masing.
  5. **Admin UI:** Sediakan `RbacController` dan view Blade di `/admin/rbac` untuk mengelola role, permission matrix per modul, dan assignment user.
## 2026-10-04: Fase 26.6 — Audit Trail Generik (Append-Only, Impactful Actions, Admin UI)
- **Context:** Sistem membutuhkan pencatatan jejak audit (audit trail) generik yang standar dan terpusat untuk setiap aksi ber-impact (mutasi uang, perubahan state kritis, transfer kepemilikan aset, dan konfigurasi keamanan) yang dijamin append-only (tidak dapat diedit maupun dihapus).
- **Decision:**
  1. **Schema & Model:** Tambahkan kolom `correlation_id` (index) dan `impact_type` (index: `financial`, `state`, `ownership`, `security`) pada tabel `core_audit_logs`. Model `AuditLog` menegakkan immutability mutlak (`static::updating` dan `static::deleting` melempar `RuntimeException`).
  2. **Kontrak & Layanan:** Buat `AuditTrailInterface` dan `AuditTrailService` yang terdaftar sebagai singleton di container Core.
  3. **BaseAction Helper:** Tambahkan helper `audit()` pada `BaseAction` sehingga seluruh Action di semua lini bisnis dapat mencatat audit log dengan mudah tanpa melanggar batasan arsitektur (boundary decoupling).
  4. **Penerapan Aksi Kritis:** Terapkan pencatatan audit pada `TransferAction` (keuangan), `TransferVehicleOwnershipAction` & `AcquireVehicleAction` (kepemilikan), `UpdateOrderStatusAction` (state), dan `RbacService` (keamanan/hak akses).
  5. **Admin UI:** Sediakan `AuditLogController` dan antarmuka Blade di `/admin/audit-logs` dengan kemampuan pencarian teks bebas, penyaringan berdasarkan aksi, tipe dampak, pengguna, dan rentang tanggal, serta visualisasi perbandingan *Old Values* vs *New Values*.
## 2026-10-04: Fase 26.7 — Outbox & Event Bus Generik (Transactional Outbox, Dispatcher, Dead-Letter & Replay)
- **Context:** Pengiriman event dan webhook ke pihak luar (mitra, sistem eksternal) sebelumnya hanya tersedia secara khusus di modul Logistics, rentan kehilangan pesan saat kegagalan jaringan atau crash aplikasi jika dipanggil langsung di tengah transaksi.
- **Decision:**
  1. **Schema & Model:** Buat tabel `core_outbox`, `core_outbox_subscriptions`, dan `core_outbox_dispatches`. Model `OutboxMessage`, `OutboxSubscription`, dan `OutboxDispatch`.
  2. **Kontrak & Layanan:** Buat `OutboxBusInterface` dan `OutboxBusService` yang menangani pencatatan idempoten (`record` dengan `idempotency_key`), dispatch bertarget (webhook dengan tanda tangan HMAC-SHA256, listener), exponential backoff retry, dan transisi ke dead-letter setelah 5 kali gagal.
  3. **BaseAction Helper:** Tambahkan helper `outbox()` pada `BaseAction` sehingga semua action transaksi dapat menulis ke outbox transaksional secara terstandarisasi.
  4. **Migrasi Modul:** Integrasikan `DispatchWebhookAction` (Logistics) agar otomatis mencatat event ke bus generik `core_outbox`.
  5. **Console Command:** Sediakan `core:process-outbox` dengan opsi `--limit` dan `--retry` untuk eksekusi terjadwal via worker/cron.
  6. **Replay Mechanism:** Metode `replay()` mereset status pesan dead-letter menjadi pending untuk dicoba kembali setelah pihak penerima pulih.
## 2026-10-04: Fase 26.8 — Document Numbering & Document Store (Gapless Sequence & Secure Storage)
- **Context:** Setiap transaksi legal, faktur, klaim, atau kontrak membutuhkan penomoran resmi yang urut tanpa celah (gapless) per entitas dan periode (bulanan/tahunan) di bawah konkurensi tinggi. Dokumen lampiran (faktur PDF, bukti bayar, foto serah terima, ID kyc) juga membutuhkan media penyimpanan aman yang ber-checksum (SHA-256), bervalidasi ekstensi, dan memiliki masa retensi jelas.
- **Decision:**
  1. **Schema & Model:**
     - Tabel `core_document_sequences` dengan model `DocumentSequence` dan unique index `(entity_code, document_type, year, month)`.
     - Tabel `core_documents` dengan model `DocumentAttachment` (`uuid`, `polymorphic documentable`, `checksum_sha256`, `mime_type`, `file_size_bytes`, `retention_until`).
  2. **Gapless Numbering Service:** `DocumentNumberingService` mengunci baris urutan dengan `lockForUpdate()` dalam `DB::transaction`, menghasilkan nomor berurutan tanpa celah dan zero-padded (default 5 digit) dengan prefix template dinamis.
  3. **Document Store Service:** `DocumentStoreService` menyimpan file ke storage lokal/S3, menghasilkan SHA-256 checksum untuk deteksi integritas/tampering, memblokir ekstensi berbahaya (executable/script), serta mencatat waktu jatuh tempo retensi (retention policy).
- **Verification:** 5 test baru (`DocumentServicesTest.php`, 19 assertions). Full suite: **592 passed / 3361 assertions / 0 skipped**.





## 2026-10-04: Fase 28 — Kontrak Inti (Modul `ctr_`)
- **Context:** Rantai nilai Fase 29–57 membutuhkan kontrak sebagai objek utama: nomor gapless, negosiasi ber-versi, persetujuan berjenjang, dan keterkaitan ke pihak (Fase 27) serta dokumen (Fase 26.8).
- **Decision:**
  1. **Modul Contract** (`modules/Contract`, tabel `ctr_*`): `ContractService` memakai `DocumentNumberingInterface` untuk nomor `CTR/{entity}/YYYY-NNNNN` (gapless, lock) dan `ApprovalEngineInterface` untuk persetujuan berjenjang dengan aturan nilai (≥ Rp 100 juta menuntut dua langkah: legal lalu admin). State machine digarap ketat: ≥2 pihak sebelum keluar dari Draft, `Signed` hanya boleh dari `Approved`, dan `terminate`/`suspend` wajib beralasan; pelanggaran melempar exception domain khusus.
  2. **Versioning hash-chain append-only**: setiap versi (creation/negotiation/amendment/clause_update) membawa `prev_hash` → `hash` SHA-256 berantai seperti Vehicle Passport; update & delete pada `ContractVersion` diblokir; `contracts:verify-chain` memverifikasi seluruh rantai; halaman diff membandingkan dua versi. Kunci rantai dihitung di dalam transaksi dengan `lockForUpdate` agar dua revisi bersaing tidak pernah membuat cabang.
  3. **Lampiran lewat Core DocumentStore**: `ctr_contract_attachments` hanya *tautan* (kontrak ↔ pihak ↔ entitas hukum) sementara berkas, checksum, dan retensi 7 tahun tetap pada `core_documents` — satu pintu penyimpanan, validasi ekstensi, dan verifikasi checksum untuk seluruh platform. Unggah dibatasi status Draft/Negosiasi agar kontrak aktif tidak bisa diubah diam-diam.
  4. **Pengingat ganda (in-app + outbox)**: `ctr:remind` mengirim notifikasi in-app ke pembuat kontrak + pemegang role `contract_manager`/`legal` dan merekam event ke Outbox generik Fase 26.7 dengan **idempotency key deterministik** (`contract_expiring:{id}:{hari}`, `milestone_reminder:{id}:{tanggal}`) sehingga cron harian tidak pernah menggandakan event; milestone menandai `reminder_sent` agar pengiriman terbatas sekali.
  5. **Role granular**: `contract_manager`, `legal`, `party_manager` didaftarkan di RBAC Fase 26.5 dengan permission `contract.{view,manage,approve}` / `party.{view,manage,legal_entity.manage}`; guard rute `/contracts` memakai ketiganya dan `/party` ditambah `party_manager`. Dilengkapi matriks akses di `RouteSmokeTest`.
- **Verification:** 642 tests / 3558 assertions 0 skipped; Pint, Vite, arch test lulus; `contracts:verify-chain` (2 kontrak seed valid), `ctr:remind` (0 duplikat pada retry), `bank:reconcile`, `lgx:audit-billing`, `mall:audit-billing` = 0 selisih, `super:health-check` 8 pilar HEALTHY.
- **Reason:** kontrak adalah fondasi untuk Fase 29 (keuangan kontrak) dan integrasi Logistics/Mall/Resto; pendekatan hash-chain + approval engine memakai ulang kerja Fase 26 tanpa meniru.

## 2026-10-04: Fase 29 — Kontrak Lanjutan (Keuangan, Kepatuhan & Integrasi)
- **Context:** Fase 28 menghasilkan kontrak sebagai objek bisnis; Fase 29 harus menjadikannya instrumen keuangan nyata — termin, uang muka, retensi, denda, eskalasi, plafon pemakaian, serta integrasi ke Logistics/Mall/Resto.
- **Decision:**
  1. **Jadwal pembayaran & uang muka** (`ContractFinanceService::buildSchedule`): termin dibentuk dalam satu transaksi dengan lock kontrak; pemanggilan ulang idempoten (mengembalikan jadwal yang ada), kecuali eksplisit `replaceUnpaid` untuk amandemen (menolak mengganti termin yang sudah terbayar). Retensi per termin dihitung proporsional (integer, `RoundingMode::HalfUp`), uang muka dibayar dari dompet dengan cap sisa.
  2. **Posting ledger deterministik**: pembayaran termin memakai key dari caller (hidden `idempotency_key` di formulir), advance memakai key eksplisit, dan replay guard ada sebelum guard status agar retry tidak double-count `advance_paid_idr` maupun saldo dompet. Akun ledger dibuat lazily lewat `ensureAccounts()`; refund/penalty terpisah dari termin.
  3. **Denda & waiver (29.2)**: aturan `per_day_fixed` atau `percent_per_day` (basis point) dengan `grace_days` dan `cap_amount_idr`; denda dihitung dari saldo termin, dibayar dari dompet, dan **pembebasan melewati ApprovalEngine dua mata** (`legal` → `admin`) — waiver tidak mengembalikan denda yang sudah terbayar.
  4. **Eskalasi harga (29.3)**: formula disimpan sebagai teks dan dievaluasi oleh **parser shunting-yard mini** yang hanya menerima angka/operator (`base`, `index`, `index_base`) — tanpa `eval`, sesuai larangan eksekusi kode. Faktor di-cap ±`escalation_cap_percent` dan konversi float→BigDecimal memakai sprintf (aturan no-float uang).
  5. **Amandemen (29.4)**: perubahan nilai/jangka menghasilkan versi hash-chain baru + baris `ctr_amendments` ber-diff (old/new per field) + regenerate jadwal termin belum dibayar; gagal bila kontrak bukan aktif atau tidak ada perubahan.
  6. **Rekonsiliasi plafon (29.5)**: `ctr_usage_ledger` unique per `source_type+source_id` (replay aman), cache `used_value_idr` dihitung ulang setelah setiap catatan, threshold `80%` (early warning) & `100%` (melebihi). Sumber diakses lewat query tabel (bukan import Domain) untuk menjaga boundary; sumber dikunci ke peran `second_party` agar kontrak dua arah tidak menghitung dua kali.
  7. **Integrasi 29.6 via interface, bukan import**: `Logistics\Contracts\RateCardOverrideResolver` (parameter string, tanpa enum Domain) diikat `ContractRateResolver` — **rate card kontrak mengalahkan tarif standar** sebelum pencocokan lane. Tautan lease/royalty/rate-card memakai kolom nullable non-breaking (`linked_*`), tanpa FK ke modul lain.
  8. **Skor risiko (29.7) & laporan (29.8)**: 7 aturan simulasi (0–100) disimpan sebagai `risk_flags` JSON; `ContractReportService` menghitung eksposur per tipe/pihak (agregat SQL), aging obligasi, kontrak kedaluwarsa, dan `ctr:audit` yang memverifikasi cache==ledger serta jadwal ≤ plafon (exit 1 bila selisih, dengan `--sync` untuk rekonsiliasi sumber).
- **Verification:** 653 tests / 3602 assertions 0 skipped; pint, vite, arch (12) lulus; `ctr:audit` 0 selisih, `bank:reconcile`, `lgx:audit-billing`, `mall:audit-billing` 0 selisih, `contracts:verify-chain` valid, `super:health-check` 8 pilar HEALTHY. 11 test regresi baru di `ContractObligationsTest` (jadwal/advance/retensi, formula denda grace+bp+cap, parser eskalasi+cap, amandemen→chain+regen, usage idempoten+threshold, skor risiko, `ctr:audit`, rate-card override E2E).
- **Reason:** termin/denda/eskalasi menyangkut uang nyata — semua key deterministik dan lock berada di dalam transaksi; integrasi antar-modul memakai contract/interface sehingga arch test tetap hijau.

## 2026-10-04: Fase 30 — Aset Inti (Modul `ast_`)
- **Context:** Fase 31–52 membutuhkan register aset tunggal untuk penyusutan, pemeliharaan, sewa (PSAK 73), intercompany, dan konsolidasi grup; data aset lama terpencar di `mall_assets`, armada Logistics, dan aset Resto.
- **Decision:**
  1. **Kategori PSAK 16 (simulasi)** (`AssetCategoryCode` enum → `ast_categories`): umur ekonomis & metode default per kategori (tanah 0/none, bangunan 20 th, mesin 10 th, kendaraan 5 th, peralatan declining balance, IT 4 th, intangible 5 th). Nilai disimpan sebagai data sehingga bisa disesuaikan tanpa kode; semua angka ditandai simulasi.
  2. **Register & kapitalisasi atomik**: `AssetService::register` satu transaksi — nomor gapless via `DocumentNumberingInterface` dengan template `AST/{ENT}/` (memakai `{ENT}` sehingga sequence berbeda per sumber tidak pernah menghasilkan string nomor sama — ini ditemukan saat backfill gagal `UNIQUE asset_number`), posting ledger `ast:fixed_assets` dengan key deterministik `ast:acquire:{assetId}`, book value = perolehan + landed − depresiasi.
  3. **Riwayat hash-chain**: `ast_events` meniru Vehicle Passport (prev_hash → SHA-256 kanonik, `HasUuids`, update/delete diblokir di `booted()`), nomor urut per aset dikunci `lockForUpdate` agar dua event bersaing tidak membuat cabang; `ast:verify-chain` memvalidasi urutan, prev_hash, dan digest.
  4. **Konsolidasi non-breaking**: 6 tabel legacy (`mall_assets`, `lgx_trucks/trailers/vessels/aircraft/containers`) menerima kolom `asset_id` nullable; `ast:backfill-links` berjalan idempoten (berhenti pada baris sudah tertaut), menghasilkan 348 tautan pada seed dev dan **0 tambahan saat dijalankan ulang**. Tidak ada FK lintas modul — hanya kolom kait, sesuai batas arsitektur.
  5. **Mutasi & opname ber-approval**: mutasi lokasi mengajukan `ApprovalEngine` (four-eyes) sebelum eksekusi `executeMove`; stok opname menandai selisih `missing/unexpected` sebagai `adjustment_status=pending` (tidak langsung mengubah ledger); hasil scan dalam siklus sama idempoten.
  6. **Penugasan & asuransi**: check-out/in eksklusif (baris `status=out` dikunci; check-out kedua dan check-in ganda ditolak); polis asuransi mencatat event ke rantai. Dokumen/foto aset disimpan lewat `DocumentStoreInterface` (checksum + retensi) — bukan penyimpanan lokal sendiri.
  7. **Role**: `asset_manager` (view/manage/approve asset) dan `auditor` (read-only) ditambahkan ke RBAC; guard `/assets` = `admin,asset_manager`; menu Master Data → Aset.
- **Verification:** 664 tests / 3662 assertions 0 skipped; pint, vite, arch test hijau; `ast:verify-chain` 697 event valid, `ast:backfill-links` idempoten (0 pada replay), `bank:reconcile`, `lgx:audit-billing`, `mall:audit-billing`, `ctr:audit` = 0 selisih, `super:health-check` 8 pilar HEALTHY. 11 test baru `AssetCoreTest` (kategori PSAK, kapitalisasi+ledger idempoten, nomor gapless, deteksi manipulasi chain, append-only, mutasi approval, backfill idempoten, opname per siklus, check-out eksklusif, asuransi).
- **Reason:** satu register aset dengan rantai kriptografis meniru pola yang sudah teruji (Vehicle Passport) dan menjaga seluruh uang dalam integer IDR dengan key deterministik — tanpa mengimpor Domain modul lain.

## 2026-10-04: Fase 31 — Penyusutan, Pemeliharaan, Revaluasi & Disposal Aset
- **Context:** Register Fase 30 perlu lifecycle finansial/operasional: depresiasi, impairment/revaluasi, pelepasan, perawatan, sewa PSAK 73 simulasi, dan TCO.
- **Decision:**
  1. **Penyusutan dua buku (31.1–31.2):** `DepreciationService` menyediakan straight-line (`cost-salvage` / useful-life-bulan), double-declining balance, dan units-of-production; idempoten per (asset, period, method, book), posting debit expense/kredit kontra-aset `ast:accumulated_depreciation`, buku fiskal tersendiri (`ast:fiscal_*`) tidak mengubah book value komersial. Aset `legacy_backfill` tidak disusutkan oleh ledger ast karena acquisition value-nya tetap tercatat di modul asal (menghindari saldo kontra-aset tanpa aset debit).
  2. **Revaluasi, impairment, disposal (31.3–31.4):** semua pengajuan lewat ApprovalEngine four-eyes (`asset_manager` → `admin`) sebelum nilai/status berubah; laba/rugi disposal = proceeds − book value; event selalu masuk hash-chain. Jurnal final spesifik GL (surplus revaluasi, laba/rugi disposal) akan diperdalam di fase finance grup — angka buku/register kini konsisten.
  3. **WO pemeliharaan (31.5):** trigger tanggal atau penggunaan (jam/km), nomor gapless; biaya `expense` → `maintenance:asset:IDR`, biaya `capitalized` → debit `ast:fixed_assets` dan landed cost; unique key `ast:wo:{id}` membuat penyelesaian ulang aman.
  4. **Sewa PSAK 73 (31.6, simulasi):** nilai kini pembayaran sederhana membentuk ROU asset/liabilitas; tiap periode memisah bunga dan pokok, posting ledger idempoten `ast:lease:pay:{paymentId}`; jadwal multi-periode menggunakan bunga implisit tersimpan.
  5. **TCO (31.7):** agregat penyusutan komersial + biaya WO + premi polis (+ BBM yang dicatat pada deskripsi WO); rekomendasi ganti jika TCO ≥60% biaya perolehan, pemeliharaan ≥30%, atau kondisi poor/broken (heuristik simulasi).
  6. **Audit (31.8):** `ast:audit` mengecek akumulasi depresiasi per aset, formula book value, ledger vs nilai buku **hanya untuk aset yang benar-benar diposting `ast:acquire:*`**. Aset legacy/backfill dikecualikan dari rekonsiliasi GL ast karena nilainya sudah di GL modul pemilik lama. Pilar `assets` ditambah ke `super:health-check` (total 9).
- **Verification:** 680 tests / 3733 assertions 0 skipped; Pint, Vite, arch test; `ast:audit` dan `ast:verify-chain` 0 selisih/utuh; `bank:reconcile`, `lgx:audit-billing`, `mall:audit-billing`, `ctr:audit` 0 selisih; `super:health-check` 9 pilar HEALTHY.
- **Reason:** penyusutan/disposal menyentuh buku besar; idempotency per aset-periode, transaksi+lock, dan pemisahan data legacy mencegah duplikasi serta false discrepancy.

## 2026-10-05: Fase 32 — Produsen & Pemasok (Modul `sup_`)
- **Context:** Fase 33 (procurement) membutuhkan master pemasok: kualifikasi, harga, skor, dan risiko, dengan tautan ke Party (Fase 27) dan kontrak (Fase 28/29).
- **Decision:**
  1. **Onboarding ber-guard**: `candidate → approved → preferred → probation → disqualified` dievaluasi `SupplierStatus::canTransitionTo()`; setiap transisi wajib beralasan dan menulis `sup_status_histories` (jejak audit). Sanksi/skoring tetap simulasi.
  2. **Kualifikasi lewat ApprovalEngine**: kuesioner/audit lokasi menghasilkan skor rata-rata (pass ≥70, fail <50) lalu mengajukan approval dua mata (`procurement` → `admin`); persetujuan otomatis menaikkan status candidate→approved.
  3. **Harga bertingkat & kontrak**: `resolvePrice` menghormati MOQ dan periode aktif tanpa overlap; tier membawa `contract_id` nullable (kolom kait, tanpa FK lintas modul) sehingga **harga kontrak kerangka mengalahkan harga katalog** saat konteks kontrak diberikan — pengujian membuktikan 10.000 (katalog) vs 8.500 (kontrak).
  4. **Portal terisolasi**: `sup_suppliers.owner_user_id` (FK users) menjadi sumber kebenaran akses portal — lebih andal daripada mencocokkan nama/email, dan `entity_id` RBAC bertipe numerik sehingga tidak bisa menampung UUID supplier. Admin dapat membuka portal untuk inspeksi; supplier lain menolak dengan 403 (diuji per-arah: A→A, B→B).
  5. **Portal ASN & dokumen**: ASN dibuat draft → shipped (guard status), nomor via `DocumentNumberingInterface`; unggah COA/sertifikat memakai `DocumentStoreInterface` (checksum SHA-256 + retensi 7 tahun), tautan lewat `sup_documents`.
  6. **Skor & risiko**: `score()` menghitung OTD/kualitas/harga/respons (0–100) dengan aksi `none/review/corrective/scar` (<70 korektif, <50 SCAR); `saveScorecard` upsert idempoten per (supplier, periode). `scanRisks` membuka flag: sertifikat kedaluwarsa/<60 hari, skor di bawah ambang, sanksi Party (via tabel `pty_sanctions_checks`), dan single-source (konsentrasi) — pertama disimpan dengan `firstOrCreate`, ulangi aman.
  7. **Integrasi Resto (32.8)**: kontrak antar-modul `Supplier\Contracts\ReferenceCostUpdater` diikat `Resto\IngredientReferenceCostUpdater` (update `moving_avg_cost_per_base_unit` + `last_purchase_cost` milik sendiri) — Supplier tidak pernah mengimpor Domain Resto (arch test hijau).
  8. **Role RBAC**: `supplier` (portal saja) dan `procurement` (kelola + approve) ditambahkan; guard rute memakai keduanya.
- **Verification:** 696 tests / 3786 assertions 0 skipped; pint, vite, arch (10); `sup:scan-risks` sukses; `bank:reconcile`, `ast:audit`, `ctr:audit` 0 selisih; `super:health-check` 9 pilar HEALTHY. 16 test baru `SupplierManagementTest` (kode gapless, transisi guard, approval, tier harga + kontrak, skor idempoten, flag idempoten, portal IDOR per-arah, ASN, upload dokumen).
- **Reason:** pemasok adalah simpul yang menyentuh hampir semua lini (procurement, resto, kontrak, aset) — isolasi akses pakai FK pemilik, bukan kecocokan teks, agar portal tidak bocor antar-tenant.

## 2026-10-05: Fase 33 — Procurement (PR → RFQ → Tender → PO)
- **Context:** Setelah master pemasok (Fase 32), dibutuhkan siklus pengadaan penuh: PR berjenjang, pemilihan pemasok berbasis data, tender buta, PO bersiapan GRN (Fase 34), serta penguncian anggaran.
- **Decision:**
  1. **Encumbrance per pusat biaya (33.6):** `prc_budget_encumbrances` unik per (source_type, source_id) sehingga replay aman; PR/PO mengunci dana saat approved/dibuat dan melepasnya saat close/cancel; anggaran terlampaui = **peringatan, bukan error** (sesuai brief), terlihat di dashboard.
  2. **PR approval berjenjang (33.1):** total < 50jt cukup tahap `procurement`, ≥ 50jt menuntut `admin`; alur lewat ApprovalEngine (four-eyes) yang sudah ada.
  3. **RFQ (33.2):** matriks komposit harga 50% / lead time 20% / skor pemasok 32.6 30%; penawaran di-upsert per (rfq, supplier) sehingga kirim ulang tidak menggandakan; **penetapan wajib beralasan** dan hanya satu pemenang (flag direset untuk lainnya).
  4. **Tender (33.3):** penawaran disimpan sebagai **segel SHA-256** sebelum tenggat (blind), `offer` baru terbaca setelah `openBids` yang hanya boleh dijalankan lewat tenggat; evaluasi berbobot menolak berjalan bila ada segel belum dibuka; penetapan pemenang tetap melewati approval dua mata.
  5. **PO (33.4):** revisi menaikkan `version` (bisnis) dan merekam snapshot riwayat memakai **ordinal sendiri** `max(version)+1` — tanpa ini close/cancel (tanpa revisi) melanggar `unique(po_id, version)` (bug ditemukan oleh test). Blanket PO menghasilkan call-off yang menunjuk induk.
  6. **PO impor (33.5):** profil Incoterm + kurs + freight/asuransi/bea; landed cost estimasi = nilai barang + ketiganya (simulasi; perhitungan riil di Fase 48–49).
  7. **Integrasi Logistics (33.7):** `InboundShipmentService` memakai kontrak `ShipmentBooking` (tanpa import Domain), guard replay per `procurement_po` + PO.id, dan hanya menolak PO batal/tutup. Untuk menampung UUID PO, `lgx_shipments.source_id` diubah menjadi string **dengan accessor yang mengembalikan int untuk nilai numerik lama** — mengembalikan kompatibilitas Store (test lama tetap `=== 999` lulus); kontrak `ShipmentBooking::cancelForOrder` dilonggarkan ke `string|int`. Booking menolak `amount_idr = 0` karena ledger menuntut ≥2 entri (ongkir simulasi 1 rupiah sampai GRN Fase 34).
  8. **Portal pemasok & RBAC:** pemasok boleh membaca dashboard terbatas, kirim quote, dan mengirim segel tender; seluruh aksi mutasi (approve, award, seal open, close/cancel PO) dibatasi `role:admin,procurement` per-rute.
- **Verification:** 713 tests / 3852 assertions 0 skipped; Pint, Vite, arch test; `bank:reconcile`, `lgx:audit-billing`, `mall:audit-billing`, `ctr:audit`, `ast:audit` 0 selisih; `super:health-check` 9 pilar HEALTHY. 17 test baru `ProcurementTest`.
- **Reason:** pengadaan menyentuh uang dan komitmen anggaran — idempotency per sumber, lock baris, dan alasan wajib pada setiap penetapan menjaga jejak audit tanpa menghambat operasional.

## 2026-10-05: Fase 34 — Penerimaan Barang, Hutang Usaha & Pembayaran Pemasok
- **Context:** Setelah PO (Fase 33), siklus penerimaan sampai bayar harus tertutup dengan pemeriksaan tiga arah dan subledger AP yang bisa diaudit, semuanya dalam simulasi pajak.
- **Decision:**
  1. **GRN parsial & toleransi (34.1):** `ReceivingService::receive` berjalan dalam satu transaksi dengan `lockForUpdate` pada PO + baris PO; akumulasi `received_qty` mencegah double-receipt, toleransi over-delivery default 5% (ceil), lot/batch & kedaluwarsa tersimpan per baris; stok masuk **hanya lewat kontrak `InventoryService`** (tanpa import Domain); PO berubah `partially_received` → `received`.
  2. **Inspeksi & retur (34.2):** setiap baris menerima record `Inspection`; unit ditolak → `quarantine=true` (hook QMS Fase 39) dan otomatis menerbitkan `SupplierReturn` (debit note) yang tercatat ke subledger AP — tanpa mengubah status akhir PO.
  3. **3-way match (34.3):** varian harga & qty dihitung dalam persen terhadap PO/GRN; di luar toleransi (harga 2%, qty 5%) invoice masuk `held` dan mengajukan ApprovalEngine; `approveHeldInvoice` baru menerbitkan jurnal. `evaluateThreeWayMatch` dipisah sebagai fungsi murni agar bisa diuji tanpa side effect.
  4. **Akuntansi (34.4):** GRN mengkredit `inv:grir` dan mendebit `inventory:procurement`; invoice mendebit GR/IR + PPV bila ada varian, mendebit `ap:ppn_input` (11% simulasi), mengkredit `ap:pph23_withheld` (2% simulasi), dan mengkredit `ap:supplier:{id}` sebesar net. **Konvensi tanda khas proyek:** entri ledger dikredit dengan nilai negatif, sehingga saldo ledger = debit − kredit dan audit memakai rumus `pembayaran − tagihan` (ditemukan dan diperbaiki saat menguji `proc:audit`).
  5. **Batch payment (34.5):** `PaymentBatch` wajib melalui ApprovalEngine sebelum `executePaymentBatch`; posting per invoice memakai key deterministik `proc:payment:{batch}:{invoice}` sehingga eksekusi ulang aman; diskon pembayaran dini (simulasi) mengkredit `revenue:early_payment_discount` sehingga Σ entri tetap 0 (bug sign diskon ditemukan lewat `bank:reconcile` saat implementasi).
  6. **Uang muka & kredit memo (34.6):** key idempotency dibebankan pada pemanggil; kompensasi mengunci advance + invoice dan mengurangi sisa dengan `min()` — tidak pernah melebihi.
  7. **Landed cost (34.7):** alokasi value/weight/qty memakai pembulatan HalfUp untuk baris awal dan **baris terakhir menyerap selisih** sehingga Σ alokasi == total persis (diuji dengan total ganjil 1.000.001).
  8. **`proc:audit` (34.8):** agregat SQL (bukan memuat seluruh baris) memverifikasi subledger AP == ledger per pemasok dan GR/IR nol untuk PO received; pilar ke-10 `procurement` ditambahkan ke `super:health-check`.
- **Verification:** 726 tests / 3906 assertions 0 skipped; Pint, Vite, arch (12); `proc:audit`, `bank:reconcile`, `lgx:audit-billing`, `ctr:audit`, `ast:audit` 0 selisih; `super:health-check` 10 pilar HEALTHY. 13 test baru `ReceivingAndPayablesTest`.
- **Reason:** uang pemasok disentuh di banyak titik (GRN → invoice → bayar) — semua key deterministik dan seluruh perubahan dalam satu transaksi agar gagal tengah tidak meninggalkan subledger vs ledger tidak sinkron.

## 2026-10-05: Fase 35 — Pabrik: Master Data Manufaktur (Modul `mfg_`)

- **Context:** Fase 36–40 (MRP, shop floor, costing, QMS, OKE/K3) membutuhkan master plant, BOM, routing, formula, dan tenaga kerja. BOM Resto sudah berjalan mandiri dan tidak boleh diubah.
- **Decision:**
  - Modul baru `modules/Manufacturing` (16 tabel `mfg_*`), service tunggal `ManufacturingService` dipakai controller dan seeder.
  - BOM multi-level ber-versi: nomor versi = `max(version)+1` per material output; validasi saat `createBom` — siklus langsung (A→A) dan multi-level (A→B→A) lewat DFS leluhur, qty ≤ 0, UoM tanpa jalur konversi, alokasi co-product ≠ 100%.
  - Formula: hash-chain `prev_hash/hash` (canonical `prev|version|sha256(body)`), status `draft → pending_approval (MFG_FORMULA_CHANGE, four-eyes) → approved`; `approveFormula` hanya dari `pending_approval` dan meneruskan ke `ApprovalEngineInterface::approve` (creator ≠ approver).
  - CK-01: plant `central_kitchen` + `mfg_resto_adapters` (pointer outlet + stempel `last_synced_at/last_sync_key`, replay idempoten). Query mentah `resto_outlets` di seeder — arsitektur melarang import Domain lintas modul. BOM/HPP Resto tidak tersentuh.
  - `mfg_work_centers.asset_id` & `mfg_routing_operations.work_center_id` bertipe `uuid` agar tidak melanggar FK tabel `ast_assets` / `mfg_work_centers` yang PK-nya UUID.
  - RBAC: 3 role baru (`planner`, `operator`, `qc_inspector`) + grup permission `manufacturing.*`; ekspektasi `RbacTest` 19 → 22. Sekaligus memperbaiki duplikasi kunci `'procurement'` di `$rolePermissionMap` (kunci kedua menimpa yang pertama) — dua set permission digabung.
  - Seeder `ManufacturingSeeder` menanam `PLT-JKT` + `CK-01` dan mengaitkan adapter ke outlet Resto CK-01 (urutan `RestoSeeder` sebelumnya di `DatabaseSeeder`); tes tabrak kode disesuaikan (`ck-99`).
- **Reason:** satu service/penyimpanan untuk seluruh master produksi; validasi BOM dilakukan sekali di sisi server agar modul berikutnya (MRP/shop floor) tidak perlu mengulang; tanpa ledger di Fase 35 (posting produksi baru di Fase 37–38).
- **Tests:** `modules/Manufacturing/tests/Feature/ManufacturingPhase35Test` (7 tes / 26 asersi) + `RbacTest` 22 role. Gate: 733 test / 3932 assertion.

## 2026-10-05: Fase 36 — Perencanaan Produksi (MPS / MRP / CRP)

- **Context:** Fase 35 menyediakan BOM, routing, material; perencanaan perlu demand per bucket, netting stok/supply, usulan produksi/pembelian, dan beban work center. Resto/Inventory tetap pemilik stok outlet; saldo `mfg_material_balances` adalah stok material pabrik.
- **Decision:**
  - Tambah 12 tabel planning `mfg_*`: parameter, forecast scenarios/lines, MPS headers/lines, balance, scheduled receipts, MRP run/requirements, planned orders, reservations, CRP loads.
  - Forecast skenario ber-versi, hanya satu `active`; MPS juga ber-versi, satu `active`, dan baris within `freeze_days` diberi `frozen`.
  - `PlanningService::runMrp`: snapshot MPS/forecast/parameter/balance/receipt + horizon/bucket → SHA-256 `run_key`; replay `completed` dengan key sama mengembalikan run terdahulu. MRP mode scenario ikut key, menulis requirement/summary saja, tanpa planned orders atau CRP rows.
  - Ledakan BOM bertingkat menggunakan level-relaxation topological order (induk sebelum komponen), netting per bucket (`on_hand − reserved + scheduled receipts − gross demand`), semua qty kalkulasi disimpan sebagai decimal 6 via bc-math. BOM effective per bucket, qty line dinormalisasi `bom.output_qty`, scrap ditambahkan.
  - Lot sizing: `l4l`, `fixed`, `periodic`, `eoq` (EOQ memakai `sqrt(2DS/H)`, dibulatkan ke atas 6 desimal); `moq` floor selalu diterapkan. Receipt terjadwal dialokasikan berdasar bucket due date.
  - Planned order purchase → requisition via contract `Modules\Procurement\Contracts\MrpRequisitionProposer`; Procurement yang membentuk PR dan approval. Tidak ada import Domain lintas modul. Adapter interface dapat dipakai modul lain bila mengusulkan material purchase.
  - Firming membuat reservasi BOM (soft/hard); `resolveAllocationConflicts` alokasi ulang per material berdasarkan due date lalu creation (earliest due wins), shortfall dicatat; simulasi what-if menyimpan `is_scenario=true` dan tidak menulis order nyata.
  - CRP menghitung setup + run time terhadap kapasitas work center ter-adjust efisiensi; load > capacity atau capacity=0 ditandai bottleneck. `mfg:run-mrp` harian 04:45.
- **Reason:** MRP dapat diulang deterministik terhadap snapshot yang sama, tidak langsung memutasi inventory/PO, dan semua lintas modul memakai contract; what-if tidak boleh merusak parameter/data nyata.
- **Tests:** `modules/Manufacturing/tests/Feature/ProductionPlanningTest` (10 tes); full gate 743 test / 3971 assertions, Pint, Vite, audit bank/proc/asset/contract/logistics/mall, 10 pilar HEALTHY.

## 2026-10-05: Fase 37 — Eksekusi Produksi (Shop Floor)

- **Context:** Fase 36 menghasilkan planned/firm order; shop floor harus mengeksekusinya dengan akurasi lot, ketertelusuran operasi, dan invarian kuantitas yang dapat diuji.
- **Decision:**
  - 9 tabel baru `mfg_*` shop floor + 1 tabel alokasi `mfg_material_issue_lots` (bahan issue dicatat per lot → rekonsiliasi Σ per lot).
  - `ProductionService::transition` state machine: `planned→released→in_progress→completed→closed|cancelled`, transisi lompat ditolak, replay idempoten. Nomor gapless `MPO/{ENT}/` via `DocumentNumberingInterface`.
  - Issue bahan FIFO (`produced_at`) / FEFO (`expiry`): konsumsi lot berurutan, kolom `alert` mencatat `shortage`/`no_lot` bila tidak cukup; saldo `mfg_material_balances` tidak pernah negatif (guard hard + `DB::transaction` + `lockForUpdate`). Sisa kebutuhan yang tidak punya lot ditarik dari saldo tanpa alokasi lot.
  - Backflush otomatis pada transisi ke `completed` dengan `kind=backflush`; hitungan "sudah di-issue" hanya `kind IN (issue, backflush)`.
  - Guard FG: `receiveFg` menolak Σ receipt > `qty_completed` (hasil lapangan) — menjaga invarian 37.9.
  - Downtime 5 kode alasan (`machine_down, material_wait, setup, break, other`) sebagai bahan OEE Fase 40; end idempoten.
  - WIP: transfer `in_transit → received` (idempoten) + `mfg:wip` laporan per order.
  - Scrap/rework: flag `ncr_required` bila scrap kumulatif > `scrap_tolerance_percent` → hook QMS Fase 39; scrap mengurangi `qty_completed`.
  - Subkontrak 37.8: kirim bahan lewat contract `ShipmentBooking` Logistics (source_type `manufacturing_subcontract`, idempoten per order), hasil olahan menambah `qty_completed` + stok FG, biaya jasa → PR via contract `MrpRequisitionProposer`.
  - FEFO ditulis `fefo` (bukan `fefe`) pada kode, validasi controller, dan UI.
- **Reason:** ketertelusuran lot per issue membuat invarian dapat dibuktikan; stok pabrik dipisah dari stok outlet Resto agar HPP resto tidak terpengaruh; seluruh integrasi lintas modul memakai Contract/Domain-event yang sah (arch 12).
- **Tests:** `modules/Manufacturing/tests/Feature/ShopFloorTest` (14 tes / 63 asersi). Gate: 757 test / 4034 assertion, Pint, Vite, arch (12), audit bank/lgx/proc/ast/ctr/mall 0 selisih, 10 pilar HEALTHY.

## 2026-10-05: Fase 38 — Biaya Produksi (Costing)

- **Context:** Shop floor Fase 37 menghasilkan qty & lot tanpa nilai rupiah. Dibutuhkan HPP (COGM/COGS), standar biaya ber-versi, dan varians — tanpa merusak ledger Resto/Procurement yang sudah berjalan.
- **Decision:**
  - **Konvensi tanda ledger (38.3)** — debit positif, kredit negatif (konsisten `PostingEntryDTO` yang memakai `->negated()` untuk kredit):
    - Issue bahan: `DR inv:wip` / `CR inv:materials`
    - Konversi (tenaga+mesin+overhead): `DR inv:wip` / `CR clearing:external`
    - Scrap: `DR expense:mfg_scrap` / `CR inv:wip`
    - Penerimaan FG: `DR inv:finished_goods` / `CR inv:wip`
    - COGS penjualan: `DR expense:mfg_cogs` / `CR inv:finished_goods`
    - Varians post: `DR expense:mfg_variance` / `CR clearing:external`; capitalize: `DR inv:wip` / `CR clearing:external`
  - 4 tabel baru `mfg_cost_*`/`mfg_variances` + kolom biaya per lot (`mfg_material_lots.unit_cost_idr`) dan per receipt (`mfg_fg_receipts.unit_cost_idr`).
  - Standard cost: `CostVersion` draft → `submitCostVersion` (ApprovalEngine four-eyes) → approved (versi lama → retired). Roll-up level: bahan baku ← `baseCosts`, output BOM ← Σ(input × level) termasuk scrap, konversi ← routing menit × biaya/jam WC (ceil integer, tanpa float).
  - Actual cost per order (`OrderCost`) dihitung ulang setiap transisi `completed`/`closed`: bahan (alokasi lot × unit_cost + sisa tanpa lot × standar), tenaga/mesin/overhead (menit laporan × tarif WC), subkontrak, dikurangi nilai by-product (standar × qty).
  - **FG transfer proporsional**: setiap receipt memindahkan `total_cost × (kumulatif_qty_receipt / qty_completed) − yang sudah keluar`; receipt terakhir menyerap pembulatan. (BUG awal: memindahkan sisa penuh setiap receipt → penerimaan parsial menguras seluruh WIP; ditangkap tes 38.3.)
  - Varians (38.4): harga = Σ qty×(lot−std); pemakaian = Σ (qty aktual − qty std BOM) × harga std — **terpisah** agar tidak menumpuk; tenaga/mesin, overhead volume, yield. Policy `post` → akun varians, `capitalize` → WIP; idempoten per `mfg:variance:{order}:{kind}`. (BUG awal: pemakaian dihitung dari total material aktual → ganda dengan harga; ditangkap tes 38.4.)
  - COGS (38.5) lewat listener `OrderPaid` Store (dipublish `Order::onPaymentCaptured`); SKU produk = kode material; FIFO dari lot FG; query mentah `store_order_items`/`store_products` (tanpa import Domain Store — arsitektur); key `mfg:cogs:{order}:{item}`.
  - Settle order `closed`: sisa WIP + varians capitalize − yang sudah keluar ke FG diserap via `mfg:settle:{order}` sehingga audit 38.8 menemukan 0 sisa.
  - Laporan 38.7 (`/manufacturing/costing`): margin per barang jadi (revenue dari `store_order_items`, HPP = unit cost aktual × qty terjual) + drill-down `OrderCost` per order.
- **Reason:** seluruh jurnal idempotent dan dapat diaudit ulang; roll-up & netting memakai integer/bc-math mengikuti konvensi proyek (tanpa float untuk nilai tersimpan); keputusan varians & tanda terdokumentasi agar modul 39–40 konsisten.
- **Tests:** `modules/Manufacturing/tests/Feature/ProductionCostingTest` (7 tes / 34 asersi). Gate: 764 test / 4070 assertion, Pint, Vite, arch (12), audit bank/proc/ast/ctr/lgx/mall + `mfg:audit-costing` 0 selisih, 10 pilar HEALTHY.
