# Konsep Dynamic Bundling & Batas Pengaman Laba (Floor Guardrail)

## 1. Landasan Filosofis & Arsitektur Nilai
Dalam industri hiburan dan festival modern, pengalaman konsumen tidak lagi terbatas pada menonton pertunjukan musik di dalam gedung pertunjukan. Perjalanan tamu (*guest journey*) mencakup akomodasi tempat menginap, transportasi penjemputan dari dan ke lokasi pertunjukan, konsumsi makanan dan minuman, serta suvenir eksklusif.

Pendekatan penjualan terpisah (*unbundled services*) seringkali menimbulkan ketidaknyamanan bagi pengunjung luar kota akibat kesulitan sinkronisasi logistik transportasi dan lonjakan tarif kamar hotel di sekitar lokasi acara.

Melalui arsitektur **Dynamic Bundling (Fase 116)**, Superweb Venue menghadirkan kapabilitas kurasi paket komprehensif yang menghubungkan tiga lini industri secara langsung:
1. **Lini Hiburan & Venue:** Tiket konser kelas festival atau VIP lounge.
2. **Lini Perhotelan (G7-K20):** Kamar penginapan berbintang dengan jaminan ketersediaan tanggal acara melalui `HotelPackageAvailabilityValidator`.
3. **Lini Logistik & Transportasi (G7-K21):** Armada shuttle bus eksekutif bandara-venue terjadwal melalui `EventShuttleFleetScheduler`.

---

## 2. Struktur Biaya & Penegakan Batas Bawah Harga (Floor Guardrail)
Meskipun bundling dirancang untuk menawarkan potongan harga promosi menarik kepada konsumen (hingga 50%), operator venue menghadapi risiko finansial jika diskon menekan harga jual di bawah biaya pokok produksi gabungan mitra (*combined cost of goods sold* / COGS).

### 2.1. Anatomi Komposisi Biaya
Setiap komponen dalam paket bundling memiliki dua atribut nilai moneter:
- **Harga Eceran Resmi (Retail Price):** Harga pasar standar jika dibeli secara terpisah oleh konsumen.
- **Harga Pokok Modal (Cost / Transfer Price):** Biaya riil yang wajib dibayarkan oleh venue kepada mitra penyedia (seperti biaya kamar hotel nett atau biaya operasional bahan bakar armada bus).

### 2.2. Peran Floor Guardrail
Batas pengaman laba (*Floor Price IDR*) dikonfigurasi sebagai jumlah seluruh biaya modal ditambah marjin keselamatan minimum:
$$P_{\text{floor}} = \sum \text{Cost}_i + \text{Safety Margin}$$

Algoritma `DynamicBundlePricer` menjamin secara deterministik bahwa meskipun tim pemasaran menerapkan kode promo diskon agresif, harga final transaksi tidak akan pernah menembus batas bawah $P_{\text{floor}}$.

---

## 3. Alokasi Pendapatan Lintas Modul (Inter-Module Revenue Settlement)
Ketika sebuah paket bundling berstatus `PUBLISHED` dibeli oleh tamu:
1. Modul Venue menerima dana pembayaran total dari pembeli.
2. Nilai diskon promosi didistribusikan secara proporsional berdasarkan bobot nilai eceran masing-masing komponen.
3. Modul Venue menerbitkan order penyelesaian lintas divisi:
   - Mentransfer alokasi dana kamar ke akun perhotelan mitra via modul Hotel.
   - Mentransfer alokasi tiket bus ke akun operasional armada via modul Logistik.
   - Mengkreditkan sisa pendapatan kotor ke kas penjualan tiket pertunjukan.
4. Setiap transaksi tercatat dalam buku besar pembukuan ganda untuk pelaporan laba rugi divisi.

