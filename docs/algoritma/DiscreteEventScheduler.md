# Algoritma A1: Discrete Event Scheduler (Binary Min-Heap)

Dokumen ini mendokumentasikan spesifikasi teknis, rancangan struktur data, kompleksitas asimtotik, pembuktian invariansi, dan implementasi algoritma **A1 Discrete Event Scheduler** pada Kernel Simulasi Superweb.

---

## 1. Latar Belakang & Masalah

Simulasi diskret membutuhkan pengeksekusian jutaan peristiwa (*discrete events*) yang dijadwalkan pada waktu virtual yang berbeda-beda. Pendekatan naive yang menggunakan pengurutan array linier setiap kali event baru dimasukkan memiliki kompleksitas $O(N \log N)$ atau $O(N)$ untuk penyisipan, yang menjadi hambatan performa kritis (*bottleneck*) ketika jumlah event mencapai puluhan ribu.

Algoritma A1 menerapkan struktur data antrean prioritas berbasis pohon biner terurut (Binary Min-Heap) dalam array datar berbasis indeks nol ($0$-indexed flat array), memberikan kompleksitas penyisipan dan ekstraksi bernilai logaritmik $O(\log N)$.

---

## 2. Struktur Kunci Pengurutan (Tie-Breaking Rule)

Setiap simpul dalam heap merepresentasikan objek `DiscreteEvent` yang memiliki tuple kunci terurut tiga tingkat:
$$\mathcal{K} = (\text{virtual\_second}, \text{priority}, \text{sequence})$$

### Komparasi Deterministik:
Untuk dua event $A$ dan $B$, operator komparasi $A \prec B$ didefinisikan sebagai:
1. **Waktu Virtual Primer:** Jika $A.\text{virtual\_second} \neq B.\text{virtual\_second}$, maka $A \prec B \iff A.\text{virtual\_second} < B.\text{virtual\_second}$.
2. **Prioritas Sekunder:** Jika waktu virtual sama, event dengan nilai prioritas lebih kecil didahulukan: $A.\text{priority} < B.\text{priority}$.
3. **Urutan Penyisipan (FIFO Tie-Breaker):** Jika waktu dan prioritas identik, event yang dijadwalkan lebih awal (memiliki `sequence` lebih rendah) dieksekusi lebih dahulu: $A.\text{sequence} < B.\text{sequence}$.

Aturan tie-breaking tiga tingkat ini menjamin bahwa urutan eksekusi bersifat deterministik 100% dan tidak pernah bergantung pada alokasi memori internal runtime PHP.

---

## 3. Invarian Min-Heap

Struktur min-heap memenuhi dua invarian struktural:
1. **Bentuk Pohon Biner Lengkap (Complete Binary Tree):** Seluruh level pohon terisi penuh, kecuali kemungkinan level terdalam yang terisi dari kiri ke kanan tanpa celah. Untuk elemen pada indeks $i$:
   - Induk (*Parent*): $\lfloor \frac{i - 1}{2} \rfloor$
   - Anak Kiri (*Left Child*): $2i + 1$
   - Anak Kanan (*Right Child*): $2i + 2$
2. **Heap Invariant (Order Property):** Untuk setiap simpul $i > 0$:
   $$\text{heap}[\text{parent}(i)] \preceq \text{heap}[i]$$

Setiap pelanggaran terhadap relasi $\preceq$ ini dideteksi oleh `HeapInvarianceVerifier` dan melempar `InvalidAlgorithmCertificateException`.

---

## 4. Operasi Primitif & Kompleksitas

### 4.1. Penyisipan (`schedule` / `siftUp`)
Elemen baru ditambahkan di akhir array heap (indeks $N$), kemudian dilakukan perambatan ke atas (*sift up*):
```
siftUp(index):
    while index > 0:
        parent = (index - 1) / 2
        if heap[index] < heap[parent]:
            swap(heap[index], heap[parent])
            index = parent
        else:
            break
```
- **Kompleksitas Waktu:** $O(\log N)$ terburuk, $O(1)$ amortized rata-rata.
- **Kompleksitas Ruang:** $O(1)$ memori tambahan.

### 4.2. Ekstraksi Minimum (`popNext` / `siftDown`)
Elemen akar (indeks $0$) diambil sebagai hasil minimum. Elemen terakhir (indeks $N-1$) dipindahkan ke akar, lalu dilakukan perambatan ke bawah (*sift down*):
```
siftDown(index):
    size = heap.length
    while (2 * index + 1) < size:
        left = 2 * index + 1
        right = 2 * index + 2
        smallest = index

        if heap[left] < heap[smallest]:
            smallest = left
        if right < size and heap[right] < heap[smallest]:
            smallest = right

        if smallest != index:
            swap(heap[index], heap[smallest])
            index = smallest
        else:
            break
```
- **Kompleksitas Waktu:** $O(\log N)$.
- **Kompleksitas Ruang:** $O(1)$.

### 4.3. Penolakan Waktu Lampau (Past Event Guard)
Jika sebuah event memiliki $\text{virtual\_second} < \text{current\_virtual\_second}$, algoritma menolak penjadwalan dan melempar `PastEventScheduledException` dengan kode `PAST_EVENT`. Hal ini menjaga invarian kausalitas fisik sistem.

---

## 5. Ringkasan Kompleksitas Asimtotik

| Operasi | Kompleksitas Waktu Terbaik | Rata-rata | Terburuk | Kompleksitas Ruang |
|---|---|---|---|---|
| `schedule(event)` | $O(1)$ | $O(\log N)$ | $O(\log N)$ | $O(1)$ |
| `popNext()` | $O(1)$ | $O(\log N)$ | $O(\log N)$ | $O(1)$ |
| `peekNext()` | $O(1)$ | $O(1)$ | $O(1)$ | $O(1)$ |
| `count()` | $O(1)$ | $O(1)$ | $O(1)$ | $O(1)$ |
| `verifyHeap()` | $O(N)$ | $O(N)$ | $O(N)$ | $O(1)$ |

---

## 6. Pembuktian Formal Monotonisitas Eksekusi

**Teorema:** Jika seluruh event yang disisipkan memenuhi syarat $t_e \ge t_{\text{current}}$, maka urutan event yang dikeluarkan oleh `popNext()` membentuk barisan tak-turun monoton:
$$t_1 \le t_2 \le \dots \le t_m$$

*Bukti:*
Berdasarkan heap order property, simpul akar $\text{heap}[0]$ selalu bernilai minimum dari seluruh elemen yang ada di pohon pada setiap saat:
$$\text{heap}[0] = \min_{i \in [0, N-1]} \text{heap}[i]$$
Ketika elemen akar dikeluarkan pada waktu $t_k$, sisa elemen di dalam heap memiliki waktu $\ge t_k$. Elemen baru yang disisipkan setelah langkah $k$ diwajibkan memiliki waktu $t_{\text{new}} \ge t_k$. Dengan demikian, seluruh elemen yang ada di dalam heap sebelum maupun sesudah penyisipan memiliki waktu $\ge t_k$. Oleh karena itu, elemen berikutnya yang diekstraksi pada langkah $k+1$ pasti memiliki waktu $t_{k+1} \ge t_k$. Secara induksi matematika, barisan waktu eksekusi terbukti monoton tak-turun. $\blacksquare$

---

## 7. Verifikasi dan Pengujian Unit

Algoritma A1 diverifikasi secara ketat melalui:
1. **Vektor Uji Prompt Resmi:** Pengujian dengan urutan event acak dan pembuktian urutan keluaran persis sesuai spesifikasi.
2. **Uji Verifier Korupsi:** Modifikasi sengaja terhadap properti heap array yang memicu kegagalan sertifikat kriptografis.
3. **Uji Tekanan Volume Tinggi:** Penjadwalan $10.000$ event acak dengan verifikasi bahwa tidak ada pelanggaran urutan waktu virtual yang terjadi.

