# Pedoman Lengkap Tata Kelola Transfer Pricing dan Settlement Antar-Properti (Fase 112)

## 1. Latar Belakang & Konteks Ekosistem
Dalam jaringan properti perhotelan multi-unit (SuperWeb Global Hospitality), anggota loyalitas sering kali mengakumulasikan poin di satu properti (misalnya resor bintang lima di Bali) dan menukarkan poin tersebut untuk menginap gratis (*award night redemption*) di properti lain (misalnya hotel bisnis pusat kota Jakarta). Hal ini menuntut adanya mekanisme *transfer pricing* dan kliring internal yang adil, transparan, dan dapat diaudit secara akuntansi.

## 2. Prinsip Penetapan Harga Penggantian Biaya (Reimbursement Pricing)
1. **Marginal Cost Floor Protection**: Properti penerima tamu penukaran kamar (*redeeming property*) wajib mendapatkan penggantian biaya operasional marginal kamar (*housekeeping, laundry, amenities, breakfast, utilities*) minimal Rp 300.000 per malam.
2. **Dynamic Occupancy Uplift**: Bila tingkat okupansi properti penerima melebihi ambang batas 85%, nilai penggantian dinaikkan sebesar 25% hingga 40% mendekati Best Available Rate (BAR) terendah agar properti tidak merugi akibat *opportunity cost*.
3. **Program Administration Margin**: Kantor pusat loyalitas mengenakan biaya jasa administrasi sebesar 5% (500 bps) atas setiap transaksi kliring antar-properti guna mendanai operasional platform digital dan server cloud.

## 3. Alur Kliring Finansial Antar-Properti
1. **Identifikasi Batch Poin**: Mesin FIFO mengidentifikasi batch poin yang digunakan tamu dan mencatat properti penerbit (*issuing property*).
2. **Kalkulasi Kewajiban**: Nilai liabilitas poin per batch (Rp 100 per poin) didebitkan dari akun sentral liabilitas loyalitas (`htl:loyalty_points_liability:IDR`).
3. **Pengkreditan Properti Penerima**: Properti penerima menerima kredit pendapatan kamar (`htl:room_revenue:IDR`) sejumlah nilai penggantian bersih setelah dikurangi margin program.
4. **Pencatatan Pendapatan Jasa Platform**: Kantor pusat mencatat kredit biaya administrasi pada akun pendapatan jasa transfer (`htl:transfer_fee_revenue:IDR`).

## 4. Jadwal Kliring dan Rekonsiliasi Akhir Bulan
Kliring antar-properti dijalankan secara harian pada proses *Night Audit* otomatis dan ditutup secara final pada tanggal terakhir setiap bulan kalender. Setiap *General Manager* properti menerima ringkasan mutasi kliring bersih (*net inter-company clearing balance*) sebelum transfer antar rekening bank hotel dieksekusi oleh tim Treasury.

