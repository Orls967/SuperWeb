# Desain Teknis — Fase 174: Lini 24 Waste, Recycling & Industrial Circularity Marketplace (P1.2)

## 1. Ringkasan & Konteks Arsitektur
Modul `Circular` (`cir_`) bertindak sebagai marketplace B2B sekunder untuk limbah industri dan bahan daur ulang (misalnya scrap besi/logam, oli bekas, jelantah/UCO, plastik gilingan, biomassa). Sesuai pilar KONSEP Lini 24:
1. **Tidak ada entitas shipment independen:** Seluruh pengiriman fisik diwajibkan menggunakan modul `Logistics` melalui tipe `reverse` dengan alasan `material_sekunder` yang dibangun pada Fase 79 (P1.1).
2. **Kebenaran tonase faktual:** Tonase akhir pesanan ditentukan saat penerimaan dan penimbangan di pabrik pembeli melalui kontrak K1 `RecycledMaterialReceiver`. Input bebas tonase klaim tanpa bukti timbangan dilarang keras.
3. **Escrow & Double-Entry Balancing:** Pembayaran pembeli ditahan (*hold*) di akun escrow sistem. Settlement ke penjual hanya dapat dipicu setelah status penerimaan faktual terjadi. Bila terjadi susut timbangan, selisih dana dikembalikan secara proporsional ke pembeli sebelum settlement diposting.
4. **Lelang Periodik Harga Seragam (Call Auction):** Algoritma deterministic `CallAuctionClearing` mengeksekusi kliring periodik dengan sertifikat matematis yang dapat diverifikasi secara independen.

---

## 2. Keputusan Arsitektur (D24-1 s.d. D24-5)

### D24-1: Modul Circular Baru (`cir_`) & Skema Database Relasional
Modul `Circular` dibuat di `modules/Circular` dengan namespace `Modules\Circular` dan prefix tabel `cir_`.
Tabel relasional dengan foreign key nyata:
- `cir_listings`: Penawaran limbah/material sekunder (penjual `party_id`, material, grade, kuantitas gram integer, harga satuan per kg integer rupiah, mode `fixed` atau `auction`, status `active`, `sold`, `closed`).
- `cir_auction_rounds`: Ronde lelang periodik untuk kombinasi material & grade tertentu (idempotency key, status `open`, `closed`, `settled`, clearing price per kg, total cleared volume grams, waktu penutupan).
- `cir_bids`: Penawaran beli dari calon pembeli (ronde id, pembeli `party_id`, kuantitas gram integer, harga maksimum per kg integer rupiah, status `active`, `allocated`, `unfilled`, alokasi kuantitas gram integer, timestamp pendaftaran).
- `cir_orders`: Pesanan transaksi yang mengikat pembeli dan penjual (listing id, ronde lelang opsional, buyer party id, seller party id, gram dipesan, gram diterima faktual, harga satuan per kg, total escrow held, refund susut, status `escrow_held`, `in_transit`, `received`, `settled`, `cancelled`, FK ke `reverse_shipment_id`, FK ke `plant_receipt_id`).
- `cir_order_lines`: Rincian baris pesanan (tipe baris `material_charge`, `handling_fee`, kuantitas gram, harga satuan, subtotal).
- `cir_settlements`: Bukti penyelesaian transaksi (order id, nominal bruto, fee marketplace bps, nominal neto penjual, timestamp posting).

### D24-2: Alur Transaksi 6 Tahap End-to-End
1. **Listing:** Penjual mendaftarkan lot limbah industri (`CreateListingAction`).
2. **Pembelian / Kliring:**
   - Mode Fixed: Pembeli memilih listing dan memesan kuantitas (`PlaceOrderAction`).
   - Mode Auction: Pembeli mengajukan bid ke ronde lelang (`SubmitBidAction`). Operator/admin menutup ronde (`CloseAuctionRoundAction`) yang memicu algoritma kliring dan secara otomatis menginstansiasi order untuk setiap alokasi yang berhasil.
3. **Escrow Hold:** Dana pembeli senilai `round_half_up(ordered_grams × price_per_kg / 1000)` dipindahkan dari wallet pembeli ke akun escrow sistem via `LedgerService`.
4. **Pengiriman Reverse Logistics:** Pesanan membuat pengiriman `reverse` di modul `Logistics` dengan `reverse_reason = 'material_sekunder'`, `source_party_id = seller_party_id`, dan titik tujuan pabrik pembeli.
5. **Penerimaan Faktual (K1):** Saat shipment tiba di pabrik pembeli, petugas penimbangan mencatat timbangan neto gram integer dan grading via K1 `RecycledMaterialReceiver`. Fakta gram diterima dicatat pada `cir_orders.accepted_grams`. Jika terjadi susut (`accepted_grams < ordered_grams`), dana susut dikembalikan seketika ke wallet pembeli:
   $$\text{Refund} = \mathrm{round\_half\_up}\left(\frac{(\text{ordered\_grams} - \text{accepted\_grams}) \times \text{price\_per\_kg}}{1000}\right)$$
6. **Settlement Penjual:** `SettleOrderAction` memindahkan dana escrow bersih ke utang penjual dikurangi fee marketplace. Upaya memicu settlement sebelum order berstatus `received` akan ditolak dengan error 422.

### D24-3: Algoritma Call Auction Clearing (`Market\CallAuctionClearing`)
Mekanisme lelang periodik harga seragam:
1. **Aturan Penentuan Harga Kliring ($p^*$):**
   - Pilih $p$ yang memaksimalkan volume transaksi tereksekusi:
     $$V(p) = \min(D(p), S(p))$$
   - Bila terdapat beberapa harga kandidat dengan volume maksimal yang sama: pilih yang meminimalkan ketimpangan penawaran dan permintaan $|D(p) - S(p)|$.
   - Bila masih terdapat rentang harga $[p_{\min}, p_{\max}]$: ambil titik tengah $(p_{\min} + p_{\max})/2$, dibulatkan half-up ke kelipatan Rp 10/kg terdekat.
2. **Aturan Prioritas Alokasi:**
   - Sisi Pembeli (Bid): Urutkan harga tertinggi lebih dulu ($p_{\text{bid}} \downarrow$), lalu timestamp masuk lebih awal ($t \uparrow$).
   - Sisi Penjual (Ask): Urutkan harga terendah lebih dulu ($p_{\text{ask}} \uparrow$), lalu timestamp masuk lebih awal ($t \uparrow$).
   - Penawar marginal yang berada tepat pada harga kliring dapat dialokasikan sebagian (*partial fill*).
3. **Sertifikat Matematis Validitas Kliring:**
   - Seluruh bid yang tereksekusi memenuhi $p_{\text{bid}} \ge p^*$.
   - Seluruh ask yang tereksekusi memenuhi $p_{\text{ask}} \le p^*$.
   - Bid tak tereksekusi tertinggi memiliki harga $< p^*$ atau tidak ada penawar yang tersisa; ask tak tereksekusi terendah memiliki harga $> p^*$ atau tidak ada penawaran yang tersisa.
   - Total volume kliring sama persis dengan $\min(D(p^*), S(p^*))$.
4. **Idempotensi Penutupan Ronde:**
   - Ronde lelang yang ditutup ulang dengan kunci idempotensi yang sama tidak mengeksekusi ulang alokasi dan menghasilkan keluaran identik.

### D24-4: Aturan Pembukuan Double-Entry (Posting)
| Kejadian | Debit | Kredit | Keterangan |
|---|---|---|---|
| **Bayar (Escrow Hold)** | `wallet:user:{pembeli}:IDR` | `escrow:payment:IDR` | Nilai = $\text{ordered\_grams} \times \text{price} / 1000$ |
| **Susut Timbangan (Refund)** | `escrow:payment:IDR` | `wallet:user:{pembeli}:IDR` | Nilai = $(\text{ordered} - \text{accepted}) \times \text{price} / 1000$ |
| **Settlement Penjual** | `escrow:payment:IDR` | `ap:cir:seller:{party}:IDR` + `cir:fee_revenue:IDR` | Fee default 250 bps (2.5%) dari nilai neto penerimaan |
| **Payout ke Penjual** | `ap:cir:seller:{party}:IDR` | `wallet:user:{penjual}:IDR` | Pemindahan saldo dari utang AP ke dompet penjual |

### D24-5: Audit Dua Sumber `cir:audit`
Perintah Artisan `cir:audit` memvalidasi tiga invarian:
1. **Invarian Tonase:** Untuk setiap order berstatus selesai (`received` atau `settled`), tonase terjual = tonase dikirim pada shipment reverse = tonase diterima timbangan K1.
2. **Invarian Escrow:** Saldo akun `escrow:payment:IDR` pada buku besar harus sama persis dengan total nominal order yang belum diselesaikan (status `escrow_held` atau `in_transit`).
3. **Invarian Fee Revenue:** Total saldo akun `cir:fee_revenue:IDR` harus sama persis dengan akumulasi $\sum (\text{gross\_settlement} \times \text{fee\_bps} / 10000)$ seluruh transaksi yang telah diselesaikan.

---

## 3. Rute & Matriks Otorisasi
- `/circular/listings` (GET, POST): `material_seller`, `admin`
- `/circular/market` (GET): `material_buyer`, `admin`
- `/circular/orders` (POST): `material_buyer`, `admin`
- `/circular/orders/{order}` (GET): `material_buyer`, `material_seller`, `admin` (dengan kontrol anti-IDOR)
- `/circular/auctions` (GET): `circular_admin`, `admin`
- `/circular/auctions/bids` (POST): `material_buyer`, `admin`
- `/circular/auctions/{round}/close` (POST): `circular_admin`, `admin`
- `/circular/orders/{order}/settle` (POST): `circular_admin`, `admin`

