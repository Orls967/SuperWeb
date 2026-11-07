# Audit Kriptografi Barcode Berputar & Pencegahan Serangan Pemutaran Ulang (Anti-Replay)

## 1. Landasan Keamanan & Vektor Serangan
Pada sistem pertunjukan musik konvensional, tiket digital umumnya diterbitkan dalam bentuk barcode Statis 2D (seperti PDF417 atau QR Code) yang dikirimkan melalui surel atau aplikasi seluler. Kelemahan fatal dari tiket statis pada pasar sekunder adalah:
1. **Penyalinan Ilegal (Screenshot Duplication):** Penjual lama dapat menyimpan tangkapan layar barcode sebelum menjual tiketnya, kemudian datang lebih awal ke lokasi venue untuk masuk mendahului pembeli sah.
2. **Serangan Pemutaran Ulang (Replay Attack):** Mengirimkan ulang paket HTTP pemindaian gerbang yang telah direkam sebelumnya untuk meloloskan banyak pengunjung dengan satu tiket fisik.

Superweb Venue Fase 116 mengimplementasikan arsitektur pertahanan kriptografis berlapis dengan **Barcode Berotasi Dinamis & Protokol Anti-Replay**.

---

## 2. Arsitektur Kriptografi Barcode Berotasi
Daur hidup integritas token tiket diatur oleh prinsip-prinsip berikut:

### 2.1. Immutabilitas Tiket Asli
Sesuai aturan PR5 sistem, kolom `ticket_hash` asli pada model `EntertainmentTicket` bersifat *append-only immutable fact*. Kolom ini tidak pernah diubah agar riwayat penerbitan perdana dari promotor tetap dapat diverifikasi di kemudian hari.

### 2.2. Penerbitan Rotated Token Hash Sekunder
Ketika transaksi sekunder dinyatakan `SETTLED`:
1. Sistem menghitung token derivasi baru dengan fungsi hash kriptografis berstandar industri:
   $$\text{RotatedHash} = \operatorname{SHA256}\left(\text{"VENUE-SEC-ROTATED"} \parallel \text{TicketId} \parallel \text{ResaleId} \parallel \text{BuyerId} \parallel \text{Timestamp} \parallel \text{RandomSalt}_{128}\right)$$
2. Nilai hash baru disimpan pada kolom `ven_ticket_resales.new_ticket_hash`.
3. Mesin gerbang akses (`GateScanController`) memeriksa keberadaan relasi tiket sekunder:
   - Bila tiket memiliki catatan resale berstatus `SETTLED`, token barcode lama pada `ticket_hash` otomatis **dinyatakan usang (INVALID_OLD_BARCODE)**.
   - Pintu gerbang hanya merespons dan membuka palang pintu jika kode yang dipindai cocok secara matematis dengan `new_ticket_hash` milik pembeli baru.

---

## 3. Protokol Anti-Replay di Pintu Gerbang Fisik
Setiap pemindaian tiket di gerbang masuk fisik memverifikasi stempel waktu dan nonce:
1. Kode QR bergerak (*dynamic animated QR*) pada aplikasi seluler tamu memperbarui token sementara setiap 30 detik (*time-based rotating token*).
2. Perangkat pemindai genggam (*handheld barcode reader*) mendekripsi token menggunakan kunci publik gerbang.
3. Stempel waktu token diverifikasi memiliki simpangan waktu (*clock drift*) tidak lebih dari $\pm 60$ detik.
4. Nonce token dicatat ke cache terdistribusi berkecepatan tinggi selama 24 jam untuk menolak seketika upaya pemindaian ulang dari rekaman foto atau video.

