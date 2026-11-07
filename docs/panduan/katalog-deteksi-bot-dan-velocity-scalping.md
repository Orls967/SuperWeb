# Katalog Deteksi Bot & Velocity Scalping

## 1. Ikhtisar Keamanan & Ancaman
Operasi penjualan tiket acara berskala besar (*high-demand ticketing*) kerap menghadapi serangan otomasi terdistribusi oleh calo digital (*scalper bots*). Metode serangan mencakup pemindaian bursa sekunder berlatensi sangat rendah (*scraping*), pemesanan tiket massal menggunakan skrip otomatis, penggunaan jaringan proxy residensial dan pusat data (*datacenter proxies*), serta manipulasi identitas pembeli (*identity spoofing*).

Dokumen ini mendokumentasikan taksonomi indikator risiko, ambang batas deteksi (*detection thresholds*), dan respons otomatis yang dijalankan oleh mesin pertahanan `AntiScalpingBotDetectorService`.

---

## 2. Taksonomi Indikator Risiko Bot
Sistem mengevaluasi empat pilar utama intelijen ancaman secara real-time:

### 2.1. Tingkat Kecepatan Akses (Request Velocity RPM)
- **Normal:** $\le 30$ permintaan per menit. Karakteristik penelusuran manusia normal.
- **Waspada (Elevated):** $31 - 60$ permintaan per menit. Pengguna melakukan penyegaran halaman berulang (*rapid refresh*).
- **Serangan Bot (Flood Attack):** $> 60$ permintaan per menit. Pola otomatisasi skrip peramban tanpa jeda waktu antar-interaksi.

### 2.2. Integritas Perangkat & Sidik Jari (Device Fingerprint Anomaly)
- **Valid:** Sidik jari peramban lengkap dengan dukungan Canvas API, AudioContext, WebGL vendor strings, dan header HTTP yang konsisten.
- **Anomali Ringan:** Perubahan User-Agent secara dinamis dalam satu sesi IP yang sama.
- **Kritis / Terindikasi Bot:** Absennya sidik jari perangkat, atau terdeteksi lingkungan peramban *headless* (seperti Puppeteer, Playwright, Selenium WebDriver, PhantomJS, atau pustaka `python-requests`).

### 2.3. Reputasi Jaringan & Alamat IP (Network IP Reputation)
Melalui kontrak aditif `IdentityVelocityRiskScorer` (G7-K22) pada modul Core:
- Deteksi alamat IP yang berasal dari blok ASN hosting pusat data (*datacenter / hosting ASNs*).
- Deteksi simpul keluar jaringan anonimitas (*Tor exit nodes* dan *open VPN proxies*).
- Rasio pembuatan akun per alamat IP yang melampaui batas wajar.

### 2.4. Frekuensi Pendaftaran Penjualan Sekunder Massal
- Pengguna yang mendaftarkan $\ge 4$ tiket sekunder dalam kurun waktu 24 jam dengan pola waktu yang berdekatan ditandai sebagai calo terorganisasi (*organized scalping ring*).

---

## 3. Matriks Skor Komposit & Aksi Respons Otomatis

| Skor Komposit | Kategori Risiko | Indikator Utama | Tindakan Sistem |
| :---: | :---: | :---: | :--- |
| **0 – 24** | *Normal Fan Activity* | Kecepatan wajar, sidik jari peramban valid, IP residensial | Akses penuh tanpa hambatan |
| **25 – 49** | *Low Anomaly* | Refresh agak cepat, perangkat seluler umum | Pemantauan latar belakang pasif |
| **50 – 74** | *Medium Velocity Caution* | 35–50 RPM, subnet IP dinamis | Pembatasan laju (*rate limiting*) lembut |
| **75 – 89** | *High Scalping Suspect* | $> 60$ RPM, perangkat headless sebagian | Penolakan pesanan sekunder seketika |
| **90 – 100** | *Critical Bot Attack* | Serangan terkoordinasi, Puppeteer/Selenium, IP hosting | Pemblokiran permanen otomatis (*Auto-Blacklist*) |

---

## 4. Prosedur Pencabutan Blokir (Unblock Appeal Runbook)
Bila akun pengguna riil terblokir akibat false positive:
1. Pengguna menghubungi meja bantuan dengan menyertakan tanda pengenal resmi (KTP/Paspor terverifikasi).
2. Operator keamanan meninjau log riwayat pada tabel `ven_scalper_blacklist_logs`.
3. Verifikasi ketiadaan riwayat penjualan sekunder berulang dengan harga spekulatif.
4. Administrator berwenang menjalankan reset status blacklist melalui konsol manajemen keamanan venue.

