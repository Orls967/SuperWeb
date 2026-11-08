# ADR-0177: Arsitektur Modul Aviation Core, Alokasi Slot IATA, Dispatch Kelaikan, Reservasi Penumpang, dan Kargo Udara (Fase 177)

## Status
Diterima (Accepted) — 2026-11-08

## Konteks
Kebutuhan mendirikan lini bisnis aviasi komersial (Lini 26, Fase 177) yang mencakup manajemen armada pesawat, koordinasi slot musiman bandara berstandar IATA WASG, dispatch kelaikan terbang berantai ganda, perhitungan berat dan keseimbangan (*Weight and Balance*), pemesanan tiket penumpang dengan sagas terdistribusi, serta kepatuhan penerimaan kargo barang berbahaya (*Dangerous Goods*).

## Keputusan
1. **Modul Baru `Aviation` (`avi_`):**
   - Mendirikan modul mandiri `Modules\Aviation` dengan prefiks tabel `avi_`, didaftarkan di `config/modules.php`, `tests/Architecture/baselines/config-allowlists.json`, dan `bootstrap/providers.php`.
2. **Master Data Bandara & Armada:**
   - Bandara dan pesawat menggunakan kode unik IATA/ICAO sebagai identitas alami. Pesawat terhubung ke modul `Asset` via `AssetRegistryLookupInterface`.
3. **Koordinasi Slot Musiman (A1 & SM):**
   - Menerapkan algoritma A1 `SlotCoordinationAllocator` mengacu pada aturan IATA WASG 80/20 (slot historis dipertahankan jika terpakai $\ge 80\%$, kolam slot baru dicadangkan $50\%$ untuk pendatang baru).
   - Dikelola melalui State Machine `SlotRequestStateMachine` (8 status: `submitted` hingga `expired`).
4. **Dispatch Kelaikan & W&B (A2 & A3):**
   - Penerbangan divalidasi oleh A2 `AirworthinessDispatchGate` (kalender sertifikat, jam A-check, siklus penerbangan, MEL deferral).
   - Lembar muat divalidasi oleh A3 `LoadAndBalanceChecker` (MTOW dan batas titik berat CG depan/belakang).
   - State Machine `FlightStateMachine` (8 status: `planned` hingga `cancelled`).
5. **Reservasi Tiket & Hold Inventori (K26, A4, & Saga):**
   - Menetapkan kontrak `Modules\Aviation\Contracts\SeatInventoryReserver` (G7-K26) dengan implementasi `SeatInventoryReserverService`.
   - Orkestrasi alur pemesanan melalui `PassengerBookingSaga` (hold inventori $\to$ validasi Party KYC $\to$ pembayaran Payment $\to$ terbit tiket). Jika pembayaran gagal, hold kursi otomatis dilepas.
   - Kebijakan pembatalan dan refund menggunakan algoritma A4 `FareRefundCalculator` berdasarkan kelas tarif (FLEX, STD, PROMO) dan pita waktu, dengan pemrosesan idempoten.
   - State Machine `TicketStateMachine` (8 status: `held` hingga `no_show`).
6. **Kargo Udara & Regulasi Barang Berbahaya (A5 & Lgx):**
   - Penerimaan kargo memvalidasi kelayakan muatan melalui A5 `DangerousGoodsEligibilityChecker` (UN number, batas gram PAX/CAO, waktu holdover pendingin kontainer ULD).
   - Rantai kustodi kargo terhubung ke modul `Logistics` via `CustodyVerifier`.
   - State Machine `CargoConsignmentStateMachine` (7 status: `booked` hingga `rejected`).
7. **Perawatan Pesawat & Integrasi Asset (K9):**
   - Pelacakan jam dan siklus penerbangan secara append-only. Pemeliharaan berkala memicu Work Order ke modul Asset via `MaintenanceWorkOrderRequester`.
   - State Machine `MaintenanceVisitStateMachine` (7 status: `planned` hingga `closed`).
8. **Kepekaan Eksternal & Parameter Berversi:**
   - Layanan `AviationExternalFactors` membaca tabel `avi_external_factors` (harga avtur, penutupan bandara abu vulkanik).
9. **Penjurnalan Akrual Double-Entry:**
   - Penjualan tiket dikreditkan ke kewajiban pendapatan diterima di muka `avi:passenger_unearned:IDR`. Pengakuan pendapatan `avi:ticket_revenue:IDR` dan hutang pajak `avi:tax_payable:IDR` baru dijurnal saat penerbangan berstatus `departed`/`flown`.
10. **Audit Dua Sumber `avi:audit`:**
    - Perintah audit merekonsiliasi 6 dimensi konsistensi (kapasitas kursi, bukti lolos A2/A3 pra-terbang, rantai kustodi kargo, kesesuaian nilai refund buku besar, integritas pendapatan flown, dan batas slot kapasitas bandara).

## Konsekuensi
Menyediakan fondasi aviasi komersial yang patuh regulasi keselamatan internasional dan standar audit finansial ketat.
