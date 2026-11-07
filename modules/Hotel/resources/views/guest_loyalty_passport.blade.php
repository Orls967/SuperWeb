@extends('layouts.app')

@section('title', 'Paspor Loyalitas Tamu & Riwayat Stay — Superweb Hotel')

@section('content')
<div class="min-h-screen bg-slate-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-5xl mx-auto space-y-6">
        <!-- Header Profile -->
        <div class="bg-gradient-to-r from-slate-900 to-indigo-950 rounded-3xl shadow-xl text-white p-8 relative overflow-hidden">
            <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 rounded-2xl bg-white/10 backdrop-blur border border-white/20 flex items-center justify-center text-amber-400">
                        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1.323l3.954 1.582 1.599-.8a1 1 0 01.894 1.79l-1.233.616 1.738 5.42a1 1 0 01-.285 1.05A3.989 3.989 0 0115 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.715-5.349L11 6.477V16h2a1 1 0 110 2H7a1 1 0 110-2h2V6.477L6.237 7.582l1.715 5.349a1 1 0 01-.285 1.05A3.989 3.989 0 015 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.738-5.42-1.233-.617a1 1 0 01.894-1.788l1.599.799L9 4.323V3a1 1 0 011-1z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold tracking-wide uppercase bg-amber-400 text-slate-950">
                            Platinum Executive Member
                        </span>
                        <h1 class="text-2xl font-bold tracking-tight mt-1">Paspor Loyalitas & Riwayat Menginap</h1>
                        <p class="text-xs text-slate-300 font-mono mt-0.5">Guest ID: {{ $profile['guest_id'] ?? 'GST-44819' }}</p>
                    </div>
                </div>
                <div class="text-right">
                    <span class="text-xs uppercase tracking-widest text-slate-400 block">Saldo Poin Aktif (PTS)</span>
                    <span class="text-3xl font-bold font-mono text-amber-400 mt-1 block">48.500 PTS</span>
                    <span class="text-xs text-emerald-400 mt-0.5 block">Dapat ditukar 3 Free Nights</span>
                </div>
            </div>
        </div>

        <!-- Tier Progress & Privileges -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Tier Progress -->
            <div class="md:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-900">Kemajuan Tingkat Keanggotaan (Tier Progression)</h3>
                    <span class="text-xs font-semibold px-2.5 py-1 bg-slate-100 text-slate-700 rounded-lg">55 / 50 Malam Terpenuhi</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-3 overflow-hidden">
                    <div class="bg-indigo-600 h-3 rounded-full" style="width: 100%"></div>
                </div>
                <div class="grid grid-cols-4 text-center text-xs pt-1">
                    <div>
                        <span class="font-bold text-slate-700 block">Basic</span>
                        <span class="text-slate-400 text-[11px]">0-9 Malam</span>
                    </div>
                    <div>
                        <span class="font-bold text-slate-700 block">Silver</span>
                        <span class="text-slate-400 text-[11px]">10-24 Malam</span>
                    </div>
                    <div>
                        <span class="font-bold text-slate-700 block">Gold</span>
                        <span class="text-slate-400 text-[11px]">25-49 Malam</span>
                    </div>
                    <div>
                        <span class="font-bold text-indigo-700 block">Platinum</span>
                        <span class="text-indigo-500 text-[11px]">50+ Malam ✓</span>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 space-y-2">
                    <span class="text-xs font-semibold text-slate-900 block">Keistimewaan Eksklusif Platinum Anda:</span>
                    <ul class="text-xs text-slate-600 space-y-1.5 list-disc list-inside">
                        <li>Sarapan pagi prasmanan gratis untuk 2 orang tamu setiap hari menginap</li>
                        <li>Prioritas peningkatan kamar (Complimentary Upgrade) hingga kategori Suite Room</li>
                        <li>Akses penuh ke Executive Club Lounge dengan teh sore dan koktail malam</li>
                        <li>Waktu check-out diperpanjang hingga pukul 16:00 tanpa biaya tambahan</li>
                    </ul>
                </div>
            </div>

            <!-- Guest Preferences Profile -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
                <h3 class="text-base font-bold text-slate-900">Preferensi Menginap</h3>
                <div class="space-y-3 text-xs">
                    <div class="p-3 bg-slate-50 rounded-xl">
                        <span class="text-slate-400 block">Zona & Lantai Kamar:</span>
                        <span class="font-semibold text-slate-800 mt-0.5 block">{{ $profile['preferred_floor'] ?? 'High Floor, Quiet Wing' }}</span>
                    </div>
                    <div class="p-3 bg-slate-50 rounded-xl">
                        <span class="text-slate-400 block">Jenis Bantal Favorit:</span>
                        <span class="font-semibold text-slate-800 mt-0.5 block">{{ $profile['pillow_type'] ?? 'Hypoallergenic Foam' }}</span>
                    </div>
                    <div class="p-3 bg-slate-50 rounded-xl">
                        <span class="text-slate-400 block">Catatan Alergi Makanan:</span>
                        <div class="flex flex-wrap gap-1 mt-1">
                            @foreach(($profile['dietary_allergies'] ?? ['None']) as $a)
                                <span class="px-2 py-0.5 bg-rose-50 text-rose-700 border border-rose-200 rounded text-[11px] font-semibold">{{ $a }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RFM Radar & Lifetime Value Component -->
        @include('hotel::components.guest_rfm_radar_card', ['profile' => $profile])
    </div>
</div>
@endsection
