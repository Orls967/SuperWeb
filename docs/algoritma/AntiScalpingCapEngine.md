# Algoritma AntiScalpingCapEngine (V9)

## 1. Latar Belakang & Landasan Hukum
Pasar sekunder tiket hiburan dan pertunjukan musik seringkali menjadi sasaran spekulasi liar oleh pihak perantara tidak resmi (*scalper* atau calo digital). Praktik ini merugikan penonton riil melalui penggelembungan harga ekstrem yang dapat mencapai 500% hingga 1.000% dari harga asli (*face value*).

Untuk melindungi konsumen dan menjaga integritas ekonomi industri hiburan nasional, Superweb mengimplementasikan **AntiScalpingCapEngine (V9)**. Algoritma ini menegakkan plafon harga keras (*hard legal cap*) sebesar **120% dari Face Value** (12,000 basis points) serta memberlakukan aturan mutlak **Single Transfer Limit** (pembatasan pemindahtanganan tiket hanya satu kali seumur hidup).

## 2. Definisi Matematika & Formalisasi
Diberikan parameter masukan transaksi sekunder:
- $F \in \mathbb{N}^+$: Nilai nominal tiket asli (*Face Value IDR*),
- $P \in \mathbb{N}^+$: Nilai penawaran penjualan sekunder (*Proposed Resale Price IDR*),
- $T \in \mathbb{N}_0$: Jumlah frekuensi pemindahtanganan sebelumnya (*Transfer Count*).

### 2.1. Formula Plafon Maksimum Legal
Plafon harga maksimum yang diizinkan dihitung dengan pembagian bulat integer bebas dari galat titik kambang (*floating-point rounding error*):
$$P_{\max} = \left\lfloor \frac{F \times 120}{100} \right\rfloor$$

### 2.2. Fungsi Keputusan Evaluasi Kelayakan
Fungsi kelayakan boolean $E(F, P, T) \in \{0, 1\}$ didefinisikan secara tegas:
$$E(F, P, T) = \begin{cases}
1, & \text{jika } P \le P_{\max} \land T \le 1 \\
0, & \text{jika } P > P_{\max} \lor T > 1
\end{cases}$$

Bila $E(F, P, T) = 0$, transaksi ditolak seketika oleh lapisan validasi domain dengan pesan galat spesifik:
1. `PRICE_EXCEEDS_LEGAL_CAP` apabila $P > P_{\max}$,
2. `SINGLE_TRANSFER_LIMIT_EXCEEDED` apabila $T > 1$.

## 3. Struktur Sertifikat Kriptografis (AntiScalpingCertificate)
Setiap evaluasi menghasilkan bukti kriptografis yang tidak dapat diubah (*immutable certificate*) dengan tanda tangan SHA-256:
$$\text{Signature} = \operatorname{HMAC-SHA256}(K, F \parallel P \parallel P_{\max} \parallel E \parallel \text{Timestamp})$$

Struktur payload sertifikat:
- `face_value_idr`: Nilai nominal dasar tiket dalam Rupiah.
- `proposed_price_idr`: Harga penawaran yang diajukan penjual.
- `max_allowed_price_idr`: Plafon atas hasil kalkulasi algoritma.
- `is_allowed`: Boolean status kelayakan hasil evaluasi.
- `reason`: Alasan penolakan atau konfirmasi kepatuhan legal.
- `signature`: Hash tanda tangan bukti sertifikasi.
- `evaluated_at`: Stempel waktu ISO-8601 evaluasi algoritma.

## 4. Mekanisme Verifikasi Kriptografis (AntiScalpingVerifier)
Komponen `AntiScalpingVerifier` memvalidasi integritas sertifikat sebelum order resale dapat disimpan ke basis data:
1. Rekonstruksi ulang kanonikal string dari field sertifikat.
2. Hitung ulang hash HMAC-SHA256 menggunakan kunci internal modul Venue.
3. Bandingkan secara *timing-safe* (`hash_equals`) terhadap `certificate->signature`.
4. Jika tidak cocok, lempar `CertificateCorruptionException` dan batalkan transaksi.

## 5. Kompleksitas Komputasi
- **Kompleksitas Waktu:** $\mathcal{O}(1)$ operasi aritmatika integer murni dan satu putaran hashing SHA-256.
- **Kompleksitas Ruang:** $\mathcal{O}(1)$ alokasi memori objek DTO tanpa overhead koleksi dinamis.
- **Keamanan Konkurensi:** Bersifat murni *stateless pure function*, aman dieksekusi secara paralel pada ratusan thread *worker queue*.

