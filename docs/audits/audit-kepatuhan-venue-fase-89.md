# Laporan Audit Kepatuhan & Keamanan Venue Ops Fase 89

Dokumen ini merangkum hasil verifikasi kepatuhan arsitektur, perlindungan privasi data pribadi (PDP), integritas hash-chain tiket, dan kepatuhan perpajakan daerah untuk Fase 89.

---

## 1. Kepatuhan Perlindungan Data Pribadi (UU PDP No. 27/2022)

### 1.1 Prinsip Zero Raw DOB
- Sistem gerbang Venue (`modules/Venue/Application/Services/GateAgeVerificationPdpService.php`) tidak pernah menyimpan, menampilkan, atau mencatat tanggal lahir (DOB) maupun Nomor Induk Kependudukan (NIK) tamu dalam log basis data.
- Layar pemindai gerbang mobile 375px (`gate.blade.php`) hanya menampilkan indikator biner:
  - Hijau: "USIA TERVERIFIKASI (≥ 21 TAHUN)"
  - Merah: "AKSES DITOLAK: USIA DI BAWAH BATAS LEGAL"
- Verifikasi dilakukan menggunakan token kriptografi `HMAC-SHA256` bertanda waktu yang hanya membuktikan status kelayakan hukum tanpa mengekspos atribut identitas pribadi.

---

## 2. Integritas Kriptografi Tiket & Hash-Chain Gapless

### 2.1 Skema Hash Canonical
Setiap tiket diterbitkan dengan nomor urut gapless per event (`DocumentNumbering`) dan diikat ke dalam rantai hash tak terputus:

```text
H_0 = SHA-256("GENESIS:VENUE:EVENT_ID")
H_n = SHA-256(H_{n-1} || TicketNumber || PriceIDR || AttendeeHash)
```

### 2.2 Uji Deteksi Sabotase (Deliverable Minus)
- Jika satu karakter pada catatan tiket diubah di dalam basis data (misal status diubah tanpa transaksi sah atau harga dimanipulasi), verifier `CanonicalHashChain` mendeteksi anomali pada indeks yang persis sama.
- Command audit `php artisan ven:audit` memverifikasi seluruh integritas hash tiket dan mengembalikan exit code 1 bila terdeteksi modifikasi liar.

---

## 3. Kepatuhan Pajak Barang dan Jasa Tertentu (PBJT) 10%

Sesuai UU Hubungan Keuangan antara Pemerintah Pusat dan Pemerintahan Daerah (HKPD):
1. **Dasar Pengenaan Pajak (DPP)**: Total penerimaan bruto penjualan tiket dan tagihan minimum spend meja VIP.
2. **Tarif Efektif**: 10.00% (1.000 bps).
3. **Akuntansi Buku Besar**: Pajak dicatat ke akun kewajiban `ven:entertainment_tax_payable:IDR` saat transaksi penjualan berlangsung dan disetorkan ke kas daerah setiap akhir bulan kalender.
4. **Pembulatan**: Dibulatkan ke bawah ke unit rupiah utuh (mematuhi prinsip G7-D32).

---

## 4. Matriks Mitigasi Ancaman Keamanan (STRIDE)

| Kategori Ancaman | Kerentanan Potensial | Mitigasi yang Diimplementasikan |
| :--- | :--- | :--- |
| **Spoofing** | Duplikasi QR code tiket via tangkapan layar | Dynamic QR HMAC berputar tiap 15 detik + Anti-screenshot watermark dinamis pada portal tamu. |
| **Tampering** | Perubahan saldo dompet cashless wristband di database | Validasi double-entry ledger otomatis pada setiap debit/kredit dan penguncian row database (`lockForUpdate`). |
| **Repudiation** | Bantahan pemesanan botol meja VIP oleh tamu | Validasi segel elektronik RFID (`rfid_seal_code`) saat botol dibuka di hadapan saksi staf bar. |
| **Information Disclosure** | IDOR pada endpoint `/guest/tickets/{ticket_uuid}` | Kebijakan isolasi `ticket->user_id === Auth::id()` dengan penolakan HTTP 403. |
| **Denial of Service** | Lonjakan kerumunan ekstrem di turnstile gerbang | Auto-switch turnstile ke mode rotasi cepat (solenoid 1.800 ms) dan balancing ke lajur cadangan. |
| **Elevation of Privilege** | Staf gerbang mengakses kontrol penutupan event | Isolasi RBAC ketat via middleware `role:venue_manager,admin`. |
