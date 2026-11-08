@props([
    'contractAmount',
    'isComplimentary' => false,
    'suiteNumber' => 'Presidential Suite #801'
])

<div class="p-4 bg-gradient-to-r from-purple-950/40 via-pink-950/30 to-purple-950/40 border border-purple-500/40 rounded-xl space-y-2 font-sans">
    <div class="flex items-center justify-between">
        <h4 class="text-xs font-bold text-purple-200 uppercase tracking-wider">Fasilitas Kamar Pengantin (Bridal Suite)</h4>
        <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $isComplimentary ? 'bg-purple-500/20 text-purple-300 border border-purple-400/30' : 'bg-slate-700 text-slate-300' }}">
            {{ $isComplimentary ? 'COMPLIMENTARY UPGRADE' : 'STANDAR DISKON' }}
        </span>
    </div>

    <div class="text-sm font-semibold text-white">{{ $suiteNumber }}</div>
    <p class="text-xs text-slate-300">
        @if($isComplimentary)
            Selamat! Nilai kontrak Anda (IDR {{ number_format($contractAmount, 0, ',', '.') }}) melebihi IDR 250 Juta, berhak atas upgrade gratis 2 malam Presidential Suite, honeymoom floral amenities, dan late check-out jam 16:00.
        @else
            Tingkatkan nilai kontrak ke IDR 250.000.000 untuk membuka fasilitas gratis Presidential Bridal Suite.
        @endif
    </p>
</div>

