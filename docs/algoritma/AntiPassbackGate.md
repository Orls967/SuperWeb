# Algoritma V2: AntiPassbackGate

## 1. Intuisi Bisnis & Masalah
*Passback* adalah modus kecurangan yang umum terjadi pada tempat hiburan dan konser, di mana seorang penonton yang telah berhasil masuk area gerbang memberikan tiket fisik atau membagikan tangkapan layar kode QR miliknya kepada rekan di luar gerbang agar rekannya tersebut dapat masuk tanpa membeli tiket. Algoritma `AntiPassbackGate` menegakkan integritas akses dua arah dengan memastikan status pergerakan tiket bergantian secara ketat (`IN` $\to$ `OUT` $\to$ `IN`), membatasi toleransi keluar-masuk (*re-entry*) maksimal sebanyak $N$ kali (fixture: $N=2$), serta menolak pemindaian ulang dengan *nonce* yang sama (*anti-replay attack*).

## 2. Definisi Matematis & Mesin Status Transisi Akses
Untuk setiap tiket digital $T$, sistem memelihara riwayat pemindaian append-only:
$$\mathcal{S}_T = \langle s_1, s_2, \dots, s_k \rangle$$
di mana setiap elemen pemindaian direpresentasikan sebagai tupel:
$$s_i = (d_i, \tau_i, \nu_i, h_i)$$
dengan:
- $d_i \in \{\text{IN}, \text{OUT}\}$: Arah pemindaian fisik gerbang.
- $\tau_i \in \mathbb{N}$: Cap waktu pemindaian (*timestamp* UTC).
- $\nu_i \in \{0, 1\}^{256}$: Token acak sekali pakai (*nonce*).
- $h_i$: Hash kanonik pindaian ke-$i$.

### Aturan Evaluasi Validitas Pindaian Baru $s_{k+1} = (d, \tau, \nu)$:
1. **Pemeriksaan Serangan Replay (*Anti-Replay Rule*)**:
   $$\forall s_j \in \mathcal{S}_T, \quad \nu \ne \nu_j$$
   Jika terdapat $\nu_j = \nu$, pemindaian ditolak dengan alasan `REPLAY_ATTACK`.
2. **Pemeriksaan Urutan Arah Bolak-Balik (*Direction Inversion Rule*)**:
   - Jika $d = \text{IN}$:
     - Status terakhir tiket wajib $d_k = \text{OUT}$ (atau $\mathcal{S}_T = \emptyset$).
     - Jika $d_k = \text{IN}$, pemindaian ditolak dengan alasan `PASSBACK_VIOLATION`.
   - Jika $d = \text{OUT}$:
     - Status terakhir tiket wajib $d_k = \text{IN}$.
     - Jika $d_k = \text{OUT}$ atau $\mathcal{S}_T = \emptyset$, pemindaian ditolak dengan alasan `EXIT_WITHOUT_ENTRY`.
3. **Pemeriksaan Batas Keluar-Masuk (*Re-entry Limit Rule*)**:
   Definisikan jumlah pindaian masuk ulang yang telah berhasil:
   $$R_k = \max\left(0, \left( \sum_{j=1}^k \mathbf{1}_{\{d_j = \text{IN}\}} \right) - 1\right)$$
   Jika $d = \text{IN}$ dan $R_k \ge N_{\text{max}}$ (di mana $N_{\text{max}} = 2$), pemindaian ditolak dengan alasan `REENTRY_LIMIT_EXCEEDED`.

## 3. Rantai Hash Kriptografis & Pembuktian Sertifikat
Setiap pindaian sukses membentuk simpul rantai hash kanonik:
$$h_{k+1} = \text{HMAC-SHA256}(h_k \parallel d \parallel \nu \parallel \tau, K_{\text{venue}})$$
dengan nilai dasar $h_0 = \text{SHA-256}(\text{ticket\_number} \parallel \text{event\_id})$.

Sertifikat `AntiPassbackCertificate` membuktikan bahwa:
$$\sum_{j=1}^{k+1} \mathbf{1}_{\{d_j = \text{IN}\}} - \sum_{j=1}^{k+1} \mathbf{1}_{\{d_j = \text{OUT}\}} \in \{0, 1\}$$
Setiap saat, jumlah orang fisik yang berada di dalam area per tiket adalah tepat 0 atau 1.

## 4. Contoh Vektor Pengujian Langkah Demi Langkah ($N_{\text{max}}=2$)

| Langkah | Aksi | Waktu | Nonce | Status Terakhir | Hasil Evaluasi | Alasan / Kode Penolakan |
|---|---|---|---|---|---|---|
| 1 | MASUK | 20:00 | `nc-01` | *Belum ada* | **DITERIMA** | Pindaian perdana sah ($\Sigma IN=1, \Sigma OUT=0$) |
| 2 | MASUK | 20:05 | `nc-02` | `IN` | **DITOLAK** | `PASSBACK_VIOLATION` (Tiket sudah di dalam) |
| 3 | KELUAR | 21:00 | `nc-03` | `IN` | **DITERIMA** | Tamu keluar area ($\Sigma IN=1, \Sigma OUT=1$) |
| 4 | MASUK | 21:10 | `nc-04` | `OUT` | **DITERIMA** | Re-entry ke-1 sah ($1 \le 2$, $\Sigma IN=2, \Sigma OUT=1$) |
| 5 | MASUK | 21:10 | `nc-04` | `IN` | **DITOLAK** | `REPLAY_ATTACK` (Nonce `nc-04` telah digunakan) |
| 6 | KELUAR | 22:00 | `nc-05` | `IN` | **DITERIMA** | Tamu keluar area ($\Sigma IN=2, \Sigma OUT=2$) |
| 7 | MASUK | 22:30 | `nc-06` | `OUT` | **DITERIMA** | Re-entry ke-2 sah ($2 \le 2$, $\Sigma IN=3, \Sigma OUT=2$) |
| 8 | KELUAR | 23:00 | `nc-07` | `IN` | **DITERIMA** | Tamu keluar area ($\Sigma IN=3, \Sigma OUT=3$) |
| 9 | MASUK | 23:10 | `nc-08` | `OUT` | **DITOLAK** | `REENTRY_LIMIT_EXCEEDED` (Percobaan re-entry ke-3 $> 2$) |

## 5. Kompleksitas & Karakteristik
- **Kompleksitas Waktu**: $\mathcal{O}(1)$ operasi pencarian index hash dan evaluasi guard status.
- **Kompleksitas Ruang**: $\mathcal{O}(1)$ alokasi memori per transaksi pemindaian.
- **Ketahanan Konkurensi**: Evaluasi menggunakan mekanisme *pessimistic row locking* (`SELECT ... FOR UPDATE`) pada baris tiket di basis data untuk mencegah *race condition* pindaian paralel di dua pintu gerbang terpisah.
- **Lokasi Kode**: `Modules\Shared\Domain\Algorithms\Access\AntiPassbackGate`.
