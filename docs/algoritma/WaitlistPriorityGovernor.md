# Algoritma WaitlistPriorityGovernor (V11)

## 1. Latar Belakang & Prinsip Keadilan Antrean
Ketika tiket acara musik berkapasitas terbatas habis terjual (*sold-out*), sistem membuka daftar tunggu resmi (*waiting list*). Pendekatan antrean tradisional *First-Come First-Served* (FIFO) rentan dimanipulasi oleh bot jaringan berlatensi rendah yang mendaftar di milidetik pertama pembukaan penjualan.

Untuk menjamin keadilan alokasi bagi komunitas penggemar setia, Superweb Venue menerapkan **WaitlistPriorityGovernor (V11)**. Algoritma ini menggabungkan bobot loyalitas historis, keanggotaan fan club resmi, frekuensi kehadiran sebelumnya, serta waktu pendaftaran relatif dengan mekanisme pencegahan kelaparan antrean (*anti-starvation aging*).

## 2. Model Penilaian Multi-Faktor (Composite Priority Score)
Diberikan profil masukan pendaftar antrean daftar tunggu:
- $L \in \mathbb{N}_0$: Poin loyalitas terakumulasi (*Loyalty Points*),
- $\Delta t \in \mathbb{N}_0$: Durasi waktu sejak mendaftar antrean dalam detik (*Registration Seconds Ago*),
- $M \in \{0, 1\}$: Status keanggotaan fan club resmi (*Fan Club Membership Flag*),
- $H \in \mathbb{N}_0$: Jumlah frekuensi kehadiran di konser sebelumnya (*Historical Attendance Count*).

### 2.1. Normalisasi Bobot Komponen
Setiap dimensi masukan dihitung menggunakan fungsi pembobotan terukur:
1. **Sub-skor Loyalitas ($S_L$):**
   $$S_L = \min\left(400, \left\lfloor \frac{L}{10} \right\rfloor \right)$$
2. **Sub-skor Waktu Tunggu / Anti-Starvation Aging ($S_T$):**
   $$S_T = \min\left(250, \left\lfloor \frac{\Delta t}{60} \right\rfloor \times 2 \right)$$
3. **Sub-skor Keanggotaan Fan Club ($S_M$):**
   $$S_M = \begin{cases} 200, & \text{jika } M = 1 \\ 0, & \text{jika } M = 0 \end{cases}$$
4. **Sub-skor Loyalitas Kehadiran Historis ($S_H$):**
   $$S_H = \min(150, H \times 25)$$

### 2.2. Agregasi Skor Komposit Total
Skor prioritas akhir $S_{\text{total}}$ merupakan jumlahan tertimbang berbatas atas $1.000$ poin:
$$S_{\text{total}} = \min(1000, S_L + S_T + S_M + S_H)$$

Urutan antrean disusun secara menurun (*descending*) berdasarkan $S_{\text{total}}$. Jika terdapat dua kandidat dengan skor identik, pemecah kesetaraan (*tie-breaker*) ditentukan berdasarkan nilai $\Delta t$ tertinggi (siapa yang mendaftar lebih awal).

## 3. Protokol Penawaran Jendela Waktu (15-Minute Countdown Window)
Saat tiket hasil pembatalan atau tiket pelepasan kuota tersedia:
1. Algoritma memilih kandidat nomor 1 dalam antrean terurut.
2. Status antrean dialihkan dari `WAITING` menjadi `OFFERED`.
3. Diterbitkan token kriptografis unik `reservation_token` dengan masa kedaluwarsa tepat **15 menit (900 detik)**.
4. Notifikasi instan dikirim melalui SMS dan Webhook push ke perangkat pengguna.
5. Jika pengguna tidak melakukan klaim sebelum waktu kedaluwarsa habis, status otomatis menjadi `EXPIRED`, dan tiket dialokasikan secara instan ke kandidat berikutnya dalam antrean.

## 4. Sertifikasi Integritas Antrean (WaitlistPriorityCertificate)
Integritas penentuan ranking antrean diverifikasi secara matematis menggunakan sertifikat bertanda tangan HMAC-SHA256:
$$\text{Signature} = \operatorname{HMAC-SHA256}(K, L \parallel \Delta t \parallel M \parallel H \parallel S_{\text{total}} \parallel \text{Timestamp})$$

Verifier memastikan tidak ada intervensi manual dari operator internal yang mengubah urutan antrean tanpa perhitungan bobot resmi.

