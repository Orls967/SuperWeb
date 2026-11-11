# Spesifikasi Algoritma — Tarjan Strongly Connected Components (SCC) Deterministik

**ID Algoritma**: `R4-ALG-01`  
**Kelas Implementasi**: `Modules\Core\Domain\Algorithms\Boundaries\StronglyConnectedComponents`  
**Kelas Verifier**: `Modules\Core\Domain\Algorithms\Boundaries\SccCertificateVerifier`  
**Rujukan Teoretis**: Robert E. Tarjan (1972), *Depth-First Search and Linear Graph Algorithms*, SIAM Journal on Computing, 1(2):146–160.

---

## 1. Masalah & Tujuan
Dalam monolit modular berukuran besar, dependensi antar modul bisnis dapat membentuk siklus terarah ($u \to v \to \dots \to u$). Siklus dependensi ini:
- Melanggar *Acyclic Dependencies Principle* (Robert C. Martin, 1996).
- Mencegah inisialisasi modul yang bersih dan modular service provider registration.
- Menyebabkan kegagalan ketergantungan migrasi basis data.

Tujuan algoritma adalah:
1. Menemukan seluruh Komponen Terhubung Kuat (*Strongly Connected Components / SCC*) pada graf berarah $G = (V, E)$, di mana $V$ adalah himpunan modul dan $E$ adalah dependensi statis lintas modul.
2. Membentuk graf kondensasi $G^{\text{SCC}} = (V^{\text{SCC}}, E^{\text{SCC}})$ yang terbukti merupakan Directed Acyclic Graph (DAG).
3. Melaporkan setiap komponen dengan ukuran $|SCC| > 1$ sebagai pelanggaran siklus terlarang.
4. Menjamin keluaran yang deterministik penuh melalui pengurutan alfabetis simpul pada inisialisasi DFS dan pengurutan komponen.

---

## 2. Pseudokode Algoritma Deterministik

```text
Algorithm StronglyConnectedComponents(nodes, adjacencyList):
    Input:
        nodes: List of module names (string)
        adjacencyList: Map of module name -> List of target module names (string)
    Output:
        sccs: List of Strongly Connected Components, each sorted alphabetically
        condensationDag: Map of scc_id -> List of target scc_ids
        hasCycles: Boolean indicating whether any |SCC| > 1 exists

    1. Sort nodes lexicographically (alphabetical order) to guarantee deterministic DFS traversal:
       sortedNodes = sort(nodes)

    2. Initialize state variables:
       index = 0
       stack = empty stack
       indices = map()
       lowlink = map()
       onStack = set()
       components = list()

    3. Define StrongConnect(v):
       indices[v] = index
       lowlink[v] = index
       index = index + 1
       stack.push(v)
       onStack.add(v)

       // Sort neighbors deterministically
       neighbors = sort(adjacencyList[v] or [])
       for each w in neighbors:
           if w is not in indices:
               StrongConnect(w)
               lowlink[v] = min(lowlink[v], lowlink[w])
           else if w is in onStack:
               lowlink[v] = min(lowlink[v], indices[w])

       // If v is a root node, pop the stack and generate an SCC
       if lowlink[v] == indices[v]:
           component = list()
           repeat:
               w = stack.pop()
               onStack.remove(w)
               component.append(w)
           until w == v

           // Sort nodes within component alphabetically
           component = sort(component)
           components.append(component)

    4. Execute StrongConnect for each unvisited node:
       for each node in sortedNodes:
           if node is not in indices:
               StrongConnect(node)

    5. Sort components deterministically by representative node (first node):
       components = sort_by(components, key = component[0])

    6. Build condensation DAG:
       sccMap = map each node to its scc_id
       condensationEdges = map()
       for each u in sortedNodes:
           for each v in adjacencyList[u]:
               if sccMap[u] != sccMap[v]:
                   condensationEdges[sccMap[u]].insert(sccMap[v])

    7. Return Certificate(components, condensationEdges)
```

---

## 3. Kompleksitas & Analisis
- **Kompleksitas Waktu**: $\mathcal{O}(V \log V + E \log E + V + E)$
  - Pengurutan simpul dan tetangga membutuhkan waktu $\mathcal{O}(V \log V + E \log E)$.
  - Penelusuran Tarjan DFS berjalan dalam $\mathcal{O}(V + E)$ karena setiap simpul dan busur dikunjungi tepat satu kali.
  - Untuk ukuran sistem $V \approx 67$ dan $E \approx 400$, algoritma selesai dieksekusi dalam $< 15$ milidetik.
- **Kompleksitas Ruang**: $\mathcal{O}(V + E)$ untuk menyimpan struktur graf, array `lowlink`, `indices`, dan stack.

---

## 4. Sertifikat & Verifikasi (`SccCertificateVerifier`)
Untuk menjamin bahwa hasil algoritma valid dan bebas manipulasi, `SccCertificateVerifier` menjalankan pemeriksaan formal:
1. **Partisi Lengkap**: Setiap simpul $v \in V$ termuat tepat dalam 1 komponen SCC.
2. **Kondensasi Asiklik**: Graf kondensasi $G^{\text{SCC}}$ diuji menggunakan algoritma deteksi siklus Kahn (Topological Sort). Jika seluruh simpul kondensasi berhasil disortir secara topologis, maka graf kondensasi terbukti asiklik.
3. **Invarian Siklus**: Jika $|SCC_i| > 1$, verifier memastikan terdapat jalur siklus tertutup di antara anggota komponen tersebut.

