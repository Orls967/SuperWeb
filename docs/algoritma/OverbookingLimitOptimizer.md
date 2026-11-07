# Algoritma H1: Overbooking Limit Optimizer

## 1. Masalah Bisnis
Hotel menghadapi risiko pembatalan menit terakhir dan *no-show* (tamu tidak hadir tanpa kabar). Bila menjual kamar tepat sejumlah kapasitas fisik (100%), kamar akan kosong dan pendapatan hangus. Sebaliknya, bila menjual terlalu banyak (*oversell* berlebih), tamu yang hadir tidak mendapatkan kamar fisik sehingga harus di-relokasi (*walk*) ke properti setara/lebih tinggi, membayar transportasi, dan memberikan kompensasi ganti rugi yang mahal.

## 2. Rumus Matematis & Teori Keputusan
Tujuan optimasi adalah memilih bilangan bulat $k \in [0, k_{\text{max}}]$ (kamar tambahan yang dijual di atas kapasitas fisik $C$) yang memaksimalkan ekspektasi keuntungan bersih:
$$\max_{k} \quad \mathbb{E}[\text{Nilai Bersih}] = \mathbb{E}[\text{Pendapatan Kamar Terhuni}] - \mathbb{E}[\text{Biaya Walk}]$$
Di mana:
- Kamar Terjual: $S = C + k$
- Jumlah Kamar Terhuni pada skenario no-show $ns$: $O(ns, k) = \min(C, S - ns)$
- Jumlah Tamu yang di-Walk: $W(ns, k) = \max(0, S - ns - C)$
- Ekspektasi Nilai:
$$\mathbb{E}[\text{Nilai}(k)] = \sum_{ns} P(ns) \times \left( O(ns, k) \times R - W(ns, k) \times W_{\text{cost}} \right)$$
- Batasan Kebijakan: $k \le \lfloor C \times \text{PolicyMaxBps} / 10.000 \rfloor$ (kebijakan hotel: 3% kapasitas, sehingga untuk $C=100$, $k \le 3$).
- Tie-break: Jika nilai ekspektasi sama, pilih $k$ terkecil untuk meminimalkan risiko friksi tamu.

## 3. Vektor Uji Prompt & Contoh Langkah Demi Langkah
- Kapasitas: $C = 100$
- Distribusi no-show $ns \in [0, 6]$: $[500, 1.000, 2.000, 2.500, 2.000, 1.500, 500]$ bps
- Pendapatan kamar per malam $R = \text{Rp } 1.000.000$
- Biaya walk $W_{\text{cost}} = \text{Rp } 2.500.000$
- Evaluasi per nilai $k = 0..6$:
  - $k=0 \to \text{Rp } 96.900.000, \quad \mathbb{E}[\text{walk}] = 0$
  - $k=1 \to \text{Rp } 97.725.000, \quad \mathbb{E}[\text{walk}] = 0.05$
  - $k=2 \to \text{Rp } 98.200.000, \quad \mathbb{E}[\text{walk}] = 0.20$
  - $k=3 \to \text{Rp } 97.975.000, \quad \mathbb{E}[\text{walk}] = 0.55$
  - $k=4 \to \text{Rp } 96.875.000, \quad \mathbb{E}[\text{walk}] = 1.15$
  - $k=5 \to \text{Rp } 95.075.000, \quad \mathbb{E}[\text{walk}] = 1.95$
  - $k=6 \to \text{Rp } 92.750.000, \quad \mathbb{E}[\text{walk}] = 2.90$
- Keputusan Optimal: $k = 2$ dengan nilai ekspektasi $\text{Rp } 98.200.000$ dan ekspektasi walk $1/5$ kamar.

## 4. Kompleksitas & Kasus Tepi
- Waktu: $\mathcal{O}(K \times N)$ di mana $K$ adalah batas overbooking dan $N$ titik probabilitas.
- Ruang: $\mathcal{O}(K)$ untuk menyimpan kurva nilai.
- Kasus Tepi: Kapasitas $\le 0$ melempar `InvalidArgumentException`.
