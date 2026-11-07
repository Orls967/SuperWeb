# Algoritma H2: Best Available Rate (BAR) Calculator

## 1. Masalah Bisnis
Pendapatan hotel ditentukan oleh yield management dinamis: menaikkan harga saat permintaan tinggi/menjelang hari-H, dan memberikan diskon terukur saat okupansi rendah atau pemesanan jauh hari, namun tetap menjaga margin terendah (floor rate) dan kewajaran batas atas (ceiling rate), serta mematuhi kontrak korporat berharga tetap yang tidak boleh diubah oleh mesin algoritma.

## 2. Rumus Matematis
$$\text{BAR} = \text{Dasar} \times F_{\text{okupansi}} \times F_{\text{lead}} \times F_{\text{event}}$$
Di mana faktor pengali (bps):
- $F_{\text{okupansi}}$:
  - $< 50\% \to 9.000 \text{ bps} (-10\%)$
  - $50\% - 79\% \to 10.000 \text{ bps} (0\%)$
  - $80\% - 89\% \to 11.500 \text{ bps} (+15\%)$
  - $\ge 90\% \to 13.000 \text{ bps} (+30\%)$
- $F_{\text{lead}}$:
  - $\le 3 \text{ hari} \to 10.500 \text{ bps} (+5\%)$
  - $4 - 30 \text{ hari} \to 10.000 \text{ bps} (0\%)$
  - $> 30 \text{ hari} \to 9.500 \text{ bps} (-5\%)$
- $F_{\text{event}}$:
  - Ada festival/event kota: $11.500 \text{ bps} (+15\%)$
  - Normal: $10.000 \text{ bps}$
- Aturan Pembulatan: Pembagian bertahap ke bawah (integer division), lalu dibulatkan ke bawah ke kelipatan $\text{Rp } 1.000$.
- Rentang Clamping: $\text{BAR} = \max(\text{Floor}, \min(\text{Ceiling}, \text{BAR}))$.
- Override Korporat: Bila ada kontrak tarif korporat, tarif kontrak menang mutlak.

## 3. Vektor Uji Prompt
Floor $\text{Rp } 450.000$, Ceiling $\text{Rp } 3.000.000$:
1. Dasar $\text{Rp } 1.200.000$, okupansi 92%, lead 2, festival $\to \text{Rp } 1.883.000$.
2. Dasar $\text{Rp } 1.200.000$, okupansi 40%, lead 45 hari $\to \text{Rp } 1.026.000$.
3. Dasar $\text{Rp } 1.200.000$, okupansi 85%, lead 10 hari $\to \text{Rp } 1.380.000$.
4. Kanal korporat (kontrak $\text{Rp } 950.000$) $\to \text{Rp } 950.000$.
5. Dasar $\text{Rp } 300.000$, okupansi 10%, lead 60 hari $\to \text{Rp } 450.000$ (floor).
