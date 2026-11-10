# Spesifikasi Teknis: Scale Provisioning Engine & Outbox Partitioning (Fase 67 & R8)

Dokumen ini mendefinisikan spesifikasi teknis, rancangan pembagian skala bertingkat (*tiered capacity planning*), arsitektur partisi basis data outbox, dan kalkulasi alokasi sumber daya pada subsistem **Scale Provisioning Engine** platform Superweb.

---

## 1. Latar Belakang & Kebutuhan Skalabilitas

Pada fase simulasi jangka panjang (hingga 1.825 hari virtual atau 5 tahun buku) serta beban event spine enterprise yang padat, volume event yang diproduksi oleh outbox dan state twin dapat mencapai jutaan baris dalam satu sesi eksekusi.

Pendekatan monolitik dengan satu tabel outbox tunggal tanpa partisi akan menghadapi kendala performa kritis:
- Tingkat persaingan penulisan indeks (*index contention*) yang tinggi pada tabel `core_outbox`.
- Waktu pencarian (*query scan time*) yang melambat secara eksponensial seiring bertambahnya ukuran tabel.
- Beban memori buffer cache yang tidak proporsional saat melakukan batching tick harian.

Scale Provisioning Engine menyelesaikan tantangan ini melalui model kapasitas terdefinisi (*Scale Capacity Configuration*) dan pembagian partisi logis berbasis hashing kunci agregat.

---

## 2. Model Tingkatan Skala (Scale Capacity Tiers)

Platform mendefinisikan tiga tingkatan skala resmi:

| Parameter Kapasitas | Tier Light | Tier Medium | Tier Heavy (Enterprise) |
|---|---|---|---|
| **Maksimum Hari Virtual** | 30 Hari | 365 Hari | 1.825 Hari (5 Tahun) |
| **Jumlah Partisi Outbox** | 2 Partisi | 8 Partisi | 32 Partisi |
| **Ukuran Batch Twin** | 50 Entitas / Batch | 200 Entitas / Batch | 1.000 Entitas / Batch |
| **Interval Checkpoint** | Setiap 1 Hari | Setiap 7 Hari (Mingguan) | Setiap 30 Hari (Bulanan) |
| **Maksimum Stream Konkuren** | 5 Aliran | 20 Aliran | 100 Aliran |
| **Cache Memori Runtime** | Non-aktif | Aktif (L1 Cache) | Aktif (L1 + Redis L2) |
| **Estimasi RAM Basis** | 64 MB – 128 MB | 256 MB – 512 MB | 1.024 MB – 2.048 MB |

---

## 3. Arsitektur Partisi Hash Outbox

Untuk mendistribusikan beban penulisan dan pembacaan event secara merata, kunci agregat di-hash ke dalam partisi $0 \dots K-1$ di mana $K$ adalah jumlah partisi sesuai tier yang aktif.

### Algoritma Pemetaan Partisi:
1. Diberikan kunci agregat $A$ (misal: UUID sesi atau ID entitas bisnis).
2. Hitung nilai hash integer 32-bit:
   $$H = \text{CRC32}(A)$$
3. Tentukan indeks partisi:
   $$P = (H \pmod K) \quad \text{dengan jaminan } 0 \le P < K$$

### Rentang Alokasi Hash Virtual:
Rencana partisi membagi ruang heksadesimal 16-bit ($0\text{x}0000$ hingga $0\text{xFFFF}$) secara proporsional:
$$\text{Rentang per Partisi} = \left\lfloor \frac{65536}{K} \right\rfloor$$
Setiap partisi mengelola sub-rentang hash yang terisolasi, memastikan tidak ada tumpang tindih (*zero collision overlap*) antar partisi.

---

## 4. Model Estimasi Kebutuhan Sumber Daya

Scale Provisioning Engine menggunakan formula analitis matematis untuk memperkirakan kebutuhan perangkat keras sebelum simulasi dimulai:

### 4.1. Estimasi Kebutuhan RAM:
$$\text{RAM}_{\text{MB}} = \text{RAM}_{\text{basis}} + (K \times 8) + (\text{BatchSize} \times 0.1)$$
- $\text{RAM}_{\text{basis}} = 64 \text{ MB}$.
- Setiap partisi aktif membutuhkan alokasi buffer query $8 \text{ MB}$.
- Setiap entitas dalam batch pemrosesan twin membutuhkan rata-rata $100 \text{ KB}$ memori objek Eloquent.

### 4.2. Estimasi Pertumbuhan Disk:
Untuk setiap 100 hari virtual simulasi:
$$\text{Disk}_{\text{MB}} = \left(\frac{100 \times N_{\text{handlers}} \times 0.5 \text{ KB}}{1024}\right) + \left(\frac{100 \times \text{TwinCount} \times 1.0 \text{ KB}}{1024}\right)$$
Di mana rata-rata satu entri checkpoint mengonsumsi $0.5 \text{ KB}$ dan satu rekaman snapshot twin mengonsumsi $1.0 \text{ KB}$ data JSON terkompresi.

---

## 5. Perintah Operasional CLI (`scale:provision`)

Eksekusi provisi kapasitas dilakukan melalui antarmuka konsol resmi:
```bash
# Menampilkan rencana dan memverifikasi kapasitas untuk tier medium
php artisan scale:provision medium
```

### Keluaran Diagnostik Standar:
- Konfirmasi nama tier terpilih.
- Jumlah partisi outbox yang akan dialokasikan.
- Estimasi kebutuhan RAM dan ruang penyimpanan disk per 100 hari.
- Ukuran batching pemrosesan yang direkomendasikan.
- Matriks alokasi rentang hash per partisi dari `0x0000` hingga `0xffff`.

---

## 6. Verifikasi Keseimbangan Distribusi Partisi

Untuk memastikan bahwa fungsi hashing tidak menghasilkan pemusatan data pada satu partisi (*data skewing*), kelas `ScalePartitionValidatorService` menguji sampel kunci representatif:
1. Hitung frekuensi baris pada masing-masing partisi $C_0, C_1, \dots, C_{K-1}$.
2. Hitung target ideal $C_{\text{ideal}} = \frac{N}{K}$.
3. Hitung deviasi persentase maksimum:
   $$\text{Deviasi}_{\text{max}} = \frac{\max |C_i - C_{\text{ideal}}|}{C_{\text{ideal}}} \times 100\%$$
4. Distribusi dinyatakan seimbang jika $\text{Deviasi}_{\text{max}} \le 25\%$ pada sampel representatif $\ge 1.000$ kunci.

