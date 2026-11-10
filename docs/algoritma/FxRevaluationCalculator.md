# Dokumentasi Algoritma: `FxRevaluationCalculator`

**Namespace**: `Modules\Treasury\Domain\Algorithms\FxRevaluationCalculator`  
**Fase Terkait**: Fase 48 — Multi-Currency & Treasury  
**Sertifikat Terkait**: `FxRevaluationCertificate`, `FxRevaluationCertificateVerifier`  

---

## 1. Intuisi Bisnis & Landasan Teori
Sesuai PSAK 10 (Pengaruh Perubahan Kurs Valuta Asing), entitas wajib mengukur kembali pos moneter dalam mata uang asing pada akhir setiap periode pelaporan menggunakan kurs penutupan (*closing rate*). Selisih antara nilai tercatat buku (*carrying amount*) dengan nilai wajar yang dihitung ulang pada tanggal pelaporan harus diakui sebagai keuntungan atau kerugian selisih kurs belum terealisasi (*unrealized gain/loss*). 

Dalam sistem enterprise berintegritas tinggi, kalkulasi ini tidak boleh menggunakan tipe data floating-point (standar IEEE 754) yang rentan terhadap galat pembulatan fraksional (misal \$0.01 per juta transaksi). Algoritma ini menggunakan aritmetika rasional integer murni: kurs dinyatakan sebagai fraksi pembilang dan penyebut bilangan bulat positif $\frac{n}{d}$.

---

## 2. Formulasi Matematis

Diberikan:
- $S$: Saldo valuta asing dalam unit minor (integer; misal sen untuk USD/EUR, integer satuan untuk JPY).
- $C$: Nilai tercatat fungsional IDR buku saat ini (integer rupiah).
- $n, d$: Pembilang dan penyebut kurs rasional ($n > 0, d > 0$; IDR per 1 unit mata uang dasar).
- $m$: Jumlah desimal mata uang asal (misal $m=2$ untuk USD sehingga 1 unit dasar = $10^2 = 100$ unit minor).
- $T$: Indikator jenis akun ($T \in \{\text{ASSET}, \text{LIABILITY}\}$).

### A. Perhitungan Nilai Tercatat Baru (Revalued Amount)
Skala pembagi desimal:
$$\text{Scale} = 10^m$$
Nilai fungsional terhitung sebelum pembulatan:
$$V_{\text{exact}} = \frac{S \times n}{d \times \text{Scale}}$$
Nilai fungsional berputar (*rounded half away from zero*):
$$V = \operatorname{sgn}(V_{\text{exact}}) \times \left\lfloor |V_{\text{exact}}| + \frac{1}{2} \right\rfloor$$
Secara integer murni untuk bilangan positif:
$$V = \left\lfloor \frac{2 \times (S \times n) + (d \times \text{Scale})}{2 \times (d \times \text{Scale})} \right\rfloor$$

### B. Perhitungan Keuntungan / Kerugian Belum Terealisasi
Selisih revaluasi $\Delta = V - C$.
- Untuk Aset:
  - Jika $\Delta > 0 \implies \text{Gain} = \Delta$, $\text{Loss} = 0$.
  - Jika $\Delta < 0 \implies \text{Loss} = |\Delta|$, $\text{Gain} = 0$.
- Untuk Liabilitas:
  - Jika $\Delta > 0 \implies \text{Loss} = \Delta$, $\text{Gain} = 0$ (kewajiban bertambah).
  - Jika $\Delta < 0 \implies \text{Gain} = |\Delta|$, $\text{Loss} = 0$ (kewajiban berkurang).

### C. Pelunasan Parsial (Realized Gain/Loss)
Jika dilakukan pelunasan sebesar $S_{\text{settled}}$ dari total saldo $S_{\text{total}}$ pada kurs pelunasan $\frac{n_s}{d_s}$:
$$\text{CarryingAllocated} = \operatorname{round\_half\_away\_from\_zero}\left( \frac{S_{\text{settled}} \times C}{S_{\text{total}}} \right)$$
$$\text{SettlementValue} = \operatorname{round\_half\_away\_from\_zero}\left( \frac{S_{\text{settled}} \times n_s}{d_s \times \text{Scale}} \right)$$
$$\text{RealizedGainLoss} = \text{SettlementValue} - \text{CarryingAllocated} \quad (\text{untuk Aset})$$

---

## 3. Contoh Perhitungan Langkah Demi Langkah (Vektor Uji)

### Vektor 1: Piutang USD 1.000 (100.000 Minor)
- $S = 100.000$, $m = 2 \implies \text{Scale} = 100$.
- $C = 15.500.000$ IDR.
- Kurs penutupan: $\frac{n}{d} = \frac{15650}{1}$.
- $V = \frac{100.000 \times 15650}{1 \times 100} = 15.650.000$ IDR.
- $\Delta = 15.650.000 - 15.500.000 = +150.000$ IDR.
- Karena Aset, $\Delta > 0 \implies \text{Unrealized Gain} = 150.000$ IDR.

### Vektor 2: Utang USD 500 (50.000 Minor)
- $S = 50.000$, $m = 2$, $C = 7.750.000$ IDR.
- Kurs penutupan: $\frac{n}{d} = \frac{15650}{1}$.
- $V = \frac{50.000 \times 15650}{100} = 7.825.000$ IDR.
- $\Delta = 7.825.000 - 7.750.000 = +75.000$ IDR.
- Karena Liabilitas, kenaikan kewajiban $\implies \text{Unrealized Loss} = 75.000$ IDR.

### Vektor 3: EUR 123,45 (12.345 Minor), Kurs Fraksional
- $S = 12.345$, $m = 2$, kurs $\frac{n}{d} = \frac{346670}{20} = 17.333,5$.
- Pembilang: $12.345 \times 346.670 = 4.279.641.150$.
- Penyebut: $20 \times 100 = 2.000$.
- $V_{\text{exact}} = \frac{4.279.641.150}{2.000} = 2.139.820,575$.
- Pembulatan menjauhi nol: $2.139.821$ IDR.

### Vektor 4: Kasus Pembulatan Menjauhi Nol Simetris
- 1 minor USD pada 15650/1: $\frac{1 \times 15650}{100} = 156,5 \implies 157$ IDR.
- -1 minor USD pada 15650/1: $\frac{-1 \times 15650}{100} = -156,5 \implies -157$ IDR.

### Vektor 5: Pelunasan Penuh Piutang
- Pelunasan 100.000 minor pada kurs 15700/1:
- Nilai pelunasan: $\frac{100.000 \times 15700}{100} = 15.700.000$ IDR.
- Carrying historis: $15.500.000$ IDR.
- Realized Gain: $15.700.000 - 15.500.000 = +200.000$ IDR.
- Jurnal pembalik revaluasi periode sebelumnya (-150.000) telah diposting terpisah di awal periode, sehingga total pengakuan bersih di laba rugi adalah $+200.000 - 150.000 = +50.000$ IDR (sesuai kenaikan kurs dari 15.650 ke 15.700).

### Vektor 6: Pelunasan Parsial 40.000 Minor
- Pelunasan 40.000 minor dari total 100.000 minor pada kurs 15700/1:
- Carrying proporsional: $\operatorname{round}\left(\frac{40.000 \times 15.500.000}{100.000}\right) = 6.200.000$ IDR.
- Nilai pelunasan: $\frac{40.000 \times 15700}{100} = 6.280.000$ IDR.
- Realized Gain: $6.280.000 - 6.200.000 = +80.000$ IDR.

---

## 4. Kompleksitas & Kasus Tepi
- **Kompleksitas Waktu**: $O(1)$ untuk satu lot moneter.
- **Kompleksitas Ruang**: $O(1)$ memori tambahan.
- **Kasus Tepi**:
  - Kurs 0 atau negatif ($n \le 0$ atau $d \le 0$): Ditangani dengan exception `InvalidArgumentException('INVALID_RATE')`.
  - Saldo minor 0: Mengembalikan revalued 0 dan gain/loss -carrying.
  - Skala desimal non-standar: Mendukung parameter desimal mata uang 0..6 (misal JPY 0 desimal, USDT 6 desimal).
- **Lokasi Berkas Kode**:
  - `modules/Treasury/Domain/Algorithms/FxRevaluationCalculator.php`
  - `modules/Treasury/Domain/Algorithms/Certificates/FxRevaluationCertificate.php`
  - `modules/Treasury/Domain/Algorithms/Certificates/FxRevaluationCertificateVerifier.php`

