# Algoritma A2: Subscriber Topological Order (Kahn's Algorithm & Lexicographical Tie-Breaking)

Dokumen ini mendokumentasikan spesifikasi teknis, rancangan struktur data, analisis kompleksitas asimtotik, penanganan siklus dependensi, dan implementasi algoritma **A2 Subscriber Topological Order** pada Kernel Simulasi Superweb.

---

## 1. Latar Belakang & Masalah

Pada arsitektur modular berorientasi domain, modul-modul bisnis saling bergantung satu sama lain dalam siklus harian. Sebagai contoh:
- Akrual kamar hotel (`hotel_night_audit`) membutuhkan status keluar pasien rumah sakit (`hospital_discharge`) untuk rekonsiliasi transfer billing tamu rawat inap.
- Denda penumpukan logistik (`logistics_demurrage`) membutuhkan data transaksi harian kamar untuk staf operasional terminal pelabuhan.
- Posting buku besar (`finance_ledger_close`) membutuhkan penutupan transaksi dari seluruh modul operasional.

Jika urutan pemanggilan handler harian dilakukan secara acak atau berdasarkan urutan pendaftaran Service Provider yang tidak teratur, hasil komputasi antar hari akan berfluktuasi secara non-deterministik dan memicu diskrepansi saldo ledger.

Algoritma A2 menyelesaikan masalah ini dengan menyusun graf berarah asiklik (*Directed Acyclic Graph* - DAG) dari dependensi handler dan mengekstrak urutan topologis linier yang legal dan deterministik.

---

## 2. Definisi Graf Dependensi & Derajat Masuk (In-Degree)

Graf dependensi didefinisikan sebagai $G = (V, E)$ di mana:
- $V$: Himpunan simpul yang merepresentasikan nama-nama unik `SimTickHandler`.
- $E$: Himpunan busur berarah $(u, v)$ yang bermakna bahwa handler $u$ harus dieksekusi **sebelum** handler $v$ ($u \to v$). Jika handler $v$ menyatakan `dependencies() = ['u']`, maka busur berarah mengalir dari $u$ ke $v$.

### In-Degree:
Untuk setiap simpul $v \in V$, derajat masuk $\text{deg}^-(v)$ dihitung sebagai jumlah prasyarat yang belum selesai dieksekusi:
$$\text{deg}^-(v) = |\{u \in V \mid (u, v) \in E\}|$$

Simpul dengan $\text{deg}^-(v) = 0$ merupakan simpul sumber (*source nodes*) yang tidak memiliki dependensi aktif dan siap untuk dieksekusi segera.

---

## 3. Algoritma Kahn dengan Tie-Breaking Leksikografis

Algoritma Kahn klasik menggunakan antrean (*queue*) standar. Namun, jika terdapat lebih dari satu simpul dengan $\text{deg}^-(v) = 0$ secara bersamaan, urutan pemrosesan antrean standar dapat bervariasi bergantung pada urutan iterasi hash table (yang non-deterministik di berbagai platform).

Algoritma A2 mengatasi kelemahan ini dengan menggunakan antrean berurutan leksikografis (Min-Priority Queue / Sorted Array berdasarkan string `strcmp`).

### Pseudocode Deterministik A2:
```
function topologicalSort(dependencyGraph):
    // 1. Inisialisasi himpunan simpul dan in-degree
    V = all_nodes(dependencyGraph)
    for each v in V:
        inDegree[v] = 0
        adjacency[v] = []

    // 2. Bangun adjacency list dan hitung in-degree
    for each v in V:
        for each u in dependencyGraph[v]: // u adalah prasyarat v
            adjacency[u].append(v)
            inDegree[v]++

    // 3. Masukkan semua simpul berderajat nol ke koleksi siap
    readyQueue = []
    for each v in V:
        if inDegree[v] == 0:
            readyQueue.append(v)

    // 4. Proses simpul dalam urutan alfabetis
    order = []
    while readyQueue is not empty:
        sort_alphabetically(readyQueue) // Jaminan tie-breaker deterministik
        u = readyQueue.pop_front()
        order.append(u)

        for each v in adjacency[u]:
            inDegree[v]--
            if inDegree[v] == 0:
                readyQueue.append(v)

    // 5. Verifikasi siklus
    if order.length != V.length:
        cycleNodes = V \ order
        throw CyclicDependencyException(cycleNodes)

    return order
```

---

## 4. Deteksi & Penanganan Siklus Dependensi

Jika graf $G$ memiliki siklus berarah (misal: $A \to B \to C \to A$), maka simpul-simpul dalam siklus tersebut tidak akan pernah mencapai $\text{deg}^-(v) = 0$. Akibatnya, ukuran hasil urutan $|L| < |V|$.

Untuk memberikan diagnosis yang presisi bagi pengembang dan operator sistem, algoritma mengisolasi komponen siklik melalui penelusuran DFS Tiga Warna (*Three-Color DFS Cycle Trace*):
- **PUTIH (0):** Simpul belum pernah dieksplorasi.
- **ABU-ABU (1):** Simpul sedang dalam tumpukan rekursi (*active call stack*).
- **HITAM (2):** Simpul dan seluruh keturunannya telah selesai ditelusuri.

Ketika penelusuran DFS menemukan busur balik (*back-edge*) menuju simpul berwarna **ABU-ABU**, siklus dependensi terbukti ada. Jalur siklus direkonstruksi dan disajikan di dalam `CyclicDependencyException`.

---

## 5. Analisis Kompleksitas Asimtotik

Misalkan $V$ adalah jumlah handler dan $E$ adalah total dependensi terdaftar:
1. **Pembangunan Graf & In-Degree:** Mengunjungi setiap simpul dan busur membutuhkan waktu $O(V + E)$.
2. **Pengurutan Antrean Siap:** Pada setiap langkah $i$, jika terdapat $k$ simpul siap, pengurutan leksikografis membutuhkan $O(k \log k)$ dengan $k \le V$. Akumulasi seluruh langkah: $O(V \log V)$.
3. **Reduksi In-Degree:** Setiap busur diuji tepat satu kali: $O(E)$.

$$\text{Total Kompleksitas Waktu} = O(V \log V + E)$$
$$\text{Total Kompleksitas Ruang} = O(V + E)$$

Karena jumlah handler dalam arsitektur berkisar puluhan hingga ratusan simpul ($V < 500$), eksekusi pengurutan topologis selesai dalam waktu kurang dari $1$ milidetik.

---

## 6. Pembuktian Formal Ketunggalan Urutan Deterministik

**Teorema:** Diberikan graf berarah asiklik $G = (V, E)$, algoritma Kahn dengan tie-breaker leksikografis menghasilkan satu-satunya urutan topologis linier yang unik (*unique canonical topological order*).

*Bukti:*
1. Karena $G$ asiklik, selalu ada minimal satu simpul dengan $\text{deg}^-(v) = 0$. Himpunan simpul ini $Z_1 \subseteq V$ bersifat unik dan terdefinisi pasti oleh topologi graf.
2. Operator pengurutan leksikografis $\text{sort}_{\text{lex}}(Z_1)$ merupakan relasi urutan total (*total order*) pada string nama handler. Akibatnya, elemen pertama $u_1 \in Z_1$ ditentukan secara unik dan tanpa ambiguitas.
3. Penghapusan $u_1$ dan penyesuaian derajat masuk menghasilkan sub-graf $G_1 = G \setminus \{u_1\}$ yang juga asiklik, dengan himpunan derajat nol berikutnya $Z_2$ yang juga terdefinisi unik.
4. Melalui induksi hingga $|V|$ langkah, setiap pilihan $u_i$ ditentukan secara deterministik oleh fungsi $(\text{deg}^-(v) = 0) \wedge \min_{\text{lex}}$. Tidak ada ruang acak atau indeterminisme dalam alur eksekusi. Oleh karena itu, urutan yang dihasilkan bersifat tunggal dan stabil di setiap platform. $\blacksquare$

---

## 7. Rangkuman Pengujian & Verifikasi

Algoritma A2 diverifikasi dengan:
- **Vektor Uji Graf Baku:** Verifikasi kesesuaian output dengan contoh resmi prompt:
  - Input: $B \to A, C \to A, D \to B, D \to C$. Hasil: `[A, B, C, D]` atau `[A, C, B, D]` sesuai tie-breaker leksikografis.
- **Uji Graf Terputus (Disconnected Components):** Penanganan graf dengan beberapa sub-pohon terpisah tanpa keterkaitan dependensi.
- **Uji Pengecualian Siklus:** Memastikan `CyclicDependencyException` terlempar jika siklus sengaja disisipkan.
- **Uji Korupsi Sertifikat:** Deteksi pemalsuan sertifikat urutan A2 via `InvalidAlgorithmCertificateException`.

