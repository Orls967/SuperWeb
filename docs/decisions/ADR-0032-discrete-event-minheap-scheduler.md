# ADR-0032: Penjadwalan Event Diskret Berbasis Binary Min-Heap dengan Tie-Breaking Deterministik (Algoritma A1)

- **Status:** DITERIMA
- **Tanggal:** 2026-11-10
- **Pengambil Keputusan:** Tim Arsitektur Core Platform & Pelaksana W8-P3
- **Konteks:** Eksekusi ribuan peristiwa diskret dalam satu hari virtual memerlukan performa tinggi dan jaminan urutan eksekusi yang konsisten di seluruh platform.

---

## 1. Konteks & Masalah

Penggunaan struktur antrean array linier yang diurutkan ulang setiap kali event baru dijadwalkan memiliki kompleksitas waktu $O(N \log N)$, yang membebani kinerja saat volume event melonjak. Selain itu, jika dua event terjadi pada detik virtual yang sama, urutan eksekusi dapat menjadi non-deterministik jika aturan *tie-breaking* tidak didefinisikan secara tegas.

## 2. Pilihan yang Dipertimbangkan

1. **Opsi A:** Menggunakan `SplPriorityQueue` bawaan PHP SPL. (DITOLAK: Secara internal `SplPriorityQueue` adalah Max-Heap yang tidak stabil dalam tie-breaking FIFO jika prioritas identik, serta memiliki inkonsistensi perilaku antar versi minor PHP).
2. **Opsi B:** Membangun `DiscreteEventScheduler` khusus berbasis Binary Min-Heap murni dengan array datar $0$-indexed dan tie-breaker 3 tingkat. (DITERIMA).

## 3. Keputusan

Kami memutuskan untuk mengimplementasikan Algoritma A1 `DiscreteEventScheduler` berbasis binary min-heap dengan kunci pengurutan tiga tingkat:
$$\mathcal{K} = (\text{virtual\_second} \text{ ASC}, \text{priority} \text{ ASC}, \text{sequence} \text{ ASC})$$
Menjadwalkan event ke waktu lampau ($\text{virtual\_second} < \text{current\_virtual\_second}$) melempar `PastEventScheduledException` (`PAST_EVENT`).

## 4. Konsekuensi

- **Positif:** Kompleksitas penyisipan dan ekstraksi bernilai logaritmik $O(\log N)$. Jaminan stabilitas urutan eksekusi bersifat matematis dan deterministik 100%.
- **Negatif:** Membutuhkan pemeliharaan kelas struktur data khusus dalam basis kode `Modules\Core\Domain\Algorithms\Simulation\`.

