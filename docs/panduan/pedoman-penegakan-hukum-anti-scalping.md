# Pedoman Penegakan Hukum & Kepatuhan Anti-Scalping

## 1. Landasan Hukum Nasional Republik Indonesia
Praktek percaloan tiket secara daring (*online ticket scalping*) yang tidak terkendali melanggar berbagai peraturan perundang-undangan di Indonesia:
1. **Undang-Undang Nomor 8 Tahun 1999 tentang Perlindungan Konsumen:**
   Pasal 4 menjamin hak konsumen atas kenyamanan, keamanan, keselamatan, serta informasi yang benar, jelas, dan jujur mengenai kondisi barang atau jasa yang diperdagangkan.
2. **Undang-Undang Nomor 19 Tahun 2016 tentang Informasi dan Transaksi Elektronik (UU ITE):**
   Pasal 30 dan Pasal 32 melarang peretasan, intersepsi data, atau penggunaan perangkat otomatis tanpa hak untuk memanipulasi sistem elektronik penyelenggara acara.
3. **Undang-Undang Nomor 1 Tahun 2022 tentang HKPD:**
   Menetapkan kewajiban pemungutan Pajak Barang dan Jasa Tertentu (PBJT) sebesar 10% atas penyerahan jasa kesenian dan hiburan.

Superweb Venue menegakkan kepatuhan hukum ini secara terprogram (*compliance by design*).

---

## 2. Parameter Pembatasan Harga & Aturan Transaksi
Sistem menetapkan tiga aturan keras yang tidak dapat dikesampingkan oleh pengaturan konfigurasi pengguna:

### 2.1. Plafon Atas Harga Maksimum (120% Price Cap)
Harga penawaran tiket sekunder dibatasi paling banyak $120\%$ dari nilai nominal tiket asli yang tercetak pada tiket (*face value*):
- Biaya tambahan $20\%$ tersebut semata-mata dialokasikan untuk menutupi biaya pemotongan pajak daerah PBJT (10%), biaya operasional escrow platform (8%), dan royalti promotor (2%).
- Penjual asli tidak diizinkan meraup keuntungan spekulatif yang membebani pembeli akhir.

### 2.2. Larangan Pemindahtanganan Berulang (Single Transfer Rule)
Tiket yang dibeli melalui pasar sekunder berstatus *non-transferable*:
- Tiket ditandai secara permanen dengan nilai `transfer_count = 1`.
- Upaya mendaftarkan kembali tiket tersebut ke bursa sekunder akan ditolak seketika dengan pesan `SINGLE_TRANSFER_RULE_VIOLATION`.
- Aturan ini memutus rantai spekulasi calo bertingkat (*arbitrage chain*).

### 2.3. Larangan Transaksi Semu (Anti-Wash Trading)
Sistem melarang transaksi di mana penjual dan pembeli terafiliasi pada entitas atau akun yang sama:
- Pembeli dengan `buyer_user_id == seller_user_id` ditolak oleh `SecondaryTicketPurchaseSaga`.
- Analisis kesamaan nomor NIK KTP dan kesamaan sidik jari perangkat digunakan untuk mendeteksi akun ganda (*sybil accounts*).

---

## 3. Prosedur Audit Bersama Aparat & Regulator
Platform menyediakan portal pelaporan khusus bagi auditor Badan Pendapatan Daerah (Bapenda) dan aparat penegak hukum untuk menginspeksi:
- Daftar transaksi penjualan sekunder lengkap dengan bukti pemotongan PBJT.
- Catatan audit forensik upaya manipulasi bot yang diblokir oleh sistem.
- Sertifikat kriptografis tanda tangan SHA-256 yang membuktikan ketiadaan pengubahan data sepihak oleh penyelenggara.

