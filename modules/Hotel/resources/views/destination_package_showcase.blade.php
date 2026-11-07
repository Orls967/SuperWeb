@extends('layouts.app')

@section('title', 'Etalase Paket Destinasi Multi-Vendor & Escrow — Superweb Hotel')

@section('content')
<div class="min-h-screen bg-slate-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Header -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="p-2.5 bg-emerald-50 text-emerald-700 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Etalase Paket Destinasi Multi-Vendor (PSAK 72 / IFRS 15)</h1>
                    <p class="text-sm text-slate-500">Bundling Kamar Hotel + Beach Club + Tiket Festival + Shuttle Transportasi dalam Satu Harga & Rekening Escrow</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                    Satu Pembayaran • Satu Faktur • Pelepasan Escrow Bertahap
                </span>
            </div>
        </div>

        <!-- Package Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($packages as $pkg)
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col justify-between hover:shadow-md transition">
                    <div class="p-6 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="px-2.5 py-1 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-lg text-xs font-mono font-bold">
                                {{ $pkg->package_code }}
                            </span>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded bg-blue-50 text-blue-700">
                                4 Komponen Vendor
                            </span>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">{{ $pkg->title ?? $pkg->package_name }}</h3>
                            <p class="text-xs text-slate-500 mt-1">Paket liburan premium lengkap mencakup akomodasi resor mewah, akses VIP Beach Club, antar-jemput bandara dan voucher kuliner khas Bali.</p>
                        </div>

                        <!-- Vendor breakdown -->
                        <div class="space-y-2 pt-2 border-t border-slate-100">
                            <span class="text-xs font-medium text-slate-500 block">Alokasi Nilai Wajar Komponen (SSP):</span>
                            @if(is_array($pkg->vendor_shares))
                                <div class="space-y-1.5">
                                    @foreach($pkg->vendor_shares as $share)
                                        <div class="flex items-center justify-between text-xs py-1 px-2.5 bg-slate-50 rounded-lg">
                                            <span class="font-medium text-slate-700">{{ $share['vendor'] ?? 'VENDOR' }}</span>
                                            <span class="font-mono text-slate-900 font-semibold">IDR {{ number_format((float) ($share['amount'] ?? 0), 0, ',', '.') }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-xs text-slate-500">Harga Total Bundel:</span>
                            <span class="text-xl font-bold font-mono text-emerald-600">IDR {{ number_format((float) ($pkg->total_price_idr ?? $pkg->single_price_idr ?? 0), 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between gap-3">
                        <span class="text-xs text-slate-500">Escrow: <strong class="text-emerald-700 font-mono">DILINDUNGI</strong></span>
                        <button class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold transition flex items-center gap-1.5">
                            Pesan Paket Lengkap
                        </button>
                    </div>
                </div>
            @empty
                <div class="col-span-3 bg-white p-12 text-center rounded-2xl border border-slate-200 text-slate-400">
                    Belum ada paket destinasi yang diterbitkan.
                </div>
            @endforelse
        </div>

        <!-- Escrow Policy Box -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 space-y-3">
            <h3 class="text-base font-bold text-slate-900">Mekanisme Rekening Penampungan Escrow & Pelepasan Bertahap</h3>
            <p class="text-xs text-slate-600 leading-relaxed">
                Seluruh dana pembayaran paket wisata disimpan di akun penampungan terpusat <code class="font-mono text-slate-800 bg-slate-100 px-1.5 py-0.5 rounded">escrow:destination_package:IDR</code>. Dana tidak langsung dicairkan ke masing-masing pihak. Pelepasan dana dilakukan bertahap secara otomatis ketika tamu menyelesaikan milestone: check-in hotel melepaskan bagian akomodasi, pemindaian QR tiket beach club melepaskan bagian venue (K16 & K21), dan konfirmasi penjemputan shuttle melepaskan bagian logistik transportasi.
            </p>
        </div>

        <!-- Package Vendor Settlement Ledger Component -->
        @include('hotel::components.package_vendor_settlement_ledger')
    </div>
</div>
@endsection
