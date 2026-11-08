@props([
    'expiresAt',
    'status' => 'ACTIVE'
])

@php
    $isExpired = false;
    $remainingDays = 14;
    if ($expiresAt) {
        $exp = new \DateTimeImmutable($expiresAt);
        $now = new \DateTimeImmutable();
        $diff = (int) $now->diff($exp)->format('%r%a');
        if ($diff < 0) {
            $isExpired = true;
            $remainingDays = 0;
        } else {
            $remainingDays = $diff;
        }
    }
@endphp

<div class="p-3 bg-slate-900/60 border {{ $isExpired ? 'border-rose-500/40' : 'border-amber-500/40' }} rounded-xl space-y-1 font-sans">
    <div class="flex items-center justify-between text-xs">
        <span class="font-semibold {{ $isExpired ? 'text-rose-400' : 'text-amber-400' }}">
            {{ $isExpired ? 'TIMELOCK KADALUARSA' : 'TIMELOCK PENAWARAN AKTIF' }}
        </span>
        <span class="font-mono text-white text-[11px]">{{ $isExpired ? 'Expired' : 'Sisa '.$remainingDays.' Hari' }}</span>
    </div>
    <p class="text-[11px] text-slate-400">
        {{ $isExpired ? 'Harga penawaran telah kadaluarsa. Memerlukan perhitungan ulang margin kotor.' : 'Harga bahan baku dan sewa venue dijamin terkunci hingga '.$expiresAt.'.' }}
    </p>
</div>
