@extends('layouts.app')

@section('title', 'Folio Split Multi-Payer Workbench — Superweb Hotel')

@section('content')
<div class="min-h-screen bg-slate-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Header -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <span class="p-2.5 bg-indigo-50 text-indigo-700 rounded-xl">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z"/>
                        </svg>
                    </span>
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Folio Split Multi-Payer Workbench</h1>
                        <p class="text-sm text-slate-500">Folio ID: <span class="font-mono font-medium text-slate-700">{{ $folio->id }}</span> • Reservasi: <span class="font-mono text-indigo-600 font-semibold">{{ $folio->reservation_id }}</span></p>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-semibold {{ $folio->status === 'settled' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800 animate-pulse' }}">
                    Status: {{ strtoupper($folio->status) }}
                </span>
                <a href="{{ route('hotel.front_desk') }}" class="px-4 py-2 border border-slate-300 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Kembali ke Front Desk
                </a>
            </div>
        </div>

        <!-- Metric Grid -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Tagihan Kamar</span>
                <p class="text-2xl font-bold text-slate-900 mt-1">IDR {{ number_format((float) $folio->total_room_charges, 0, ',', '.') }}</p>
                <span class="text-xs text-slate-400 mt-1 block">Tarif menginap & pajak PBJT</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Insidental & Add-On</span>
                <p class="text-2xl font-bold text-indigo-600 mt-1">IDR {{ number_format((float) $folio->total_addon_charges, 0, ',', '.') }}</p>
                <span class="text-xs text-indigo-400 mt-1 block">F&B, Minibar, Spa, Laundry</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Pembayaran Diterima</span>
                <p class="text-2xl font-bold text-emerald-600 mt-1">IDR {{ number_format((float) $folio->total_paid, 0, ',', '.') }}</p>
                <span class="text-xs text-emerald-500 mt-1 block">Deposit + Pembayaran Parsial</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Sisa Saldo Terutang</span>
                <p class="text-2xl font-bold {{ $folio->outstanding_balance == 0 ? 'text-slate-400' : 'text-rose-600' }} mt-1">
                    IDR {{ number_format((float) $folio->outstanding_balance, 0, ',', '.') }}
                </p>
                <span class="text-xs {{ $folio->outstanding_balance == 0 ? 'text-emerald-600 font-semibold' : 'text-rose-500 font-medium' }} mt-1 block">
                    {{ $folio->outstanding_balance == 0 ? '✓ Saldo Nol (Siap Checkout)' : 'Wajib diselesaikan sebelum checkout' }}
                </span>
            </div>
        </div>

        <!-- Split Routing & Line Items -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Table Lines -->
            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Rincian Baris Transaksi Folio</h2>
                        <p class="text-xs text-slate-500">Klasifikasi otomatis pos biaya berdasarkan kontrak penagihan korporat (H6)</p>
                    </div>
                    <span class="text-xs font-medium px-2.5 py-1 bg-slate-100 text-slate-600 rounded-lg">
                        {{ count($lines) }} Transaksi
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600">
                        <thead class="bg-slate-50 text-xs uppercase font-semibold text-slate-500 border-b border-slate-100">
                            <tr>
                                <th class="px-6 py-3">Tanggal</th>
                                <th class="px-6 py-3">Kategori</th>
                                <th class="px-6 py-3">Deskripsi Transaksi</th>
                                <th class="px-6 py-3 text-right">Jumlah (IDR)</th>
                                <th class="px-6 py-3 text-center">Payer Target</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($lines as $item)
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="px-6 py-3.5 font-mono text-xs text-slate-500">{{ $item->business_date ?? date('Y-m-d') }}</td>
                                    <td class="px-6 py-3.5">
                                        <span class="px-2.5 py-1 text-xs font-semibold rounded-md {{ in_array($item->line_type, ['ROOM_CHARGE', 'HOTEL_TAX', 'SERVICE_CHARGE']) ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-purple-50 text-purple-700 border border-purple-200' }}">
                                            {{ $item->line_type }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3.5 font-medium text-slate-800">{{ $item->description }}</td>
                                    <td class="px-6 py-3.5 text-right font-mono font-semibold text-slate-900">
                                        IDR {{ number_format((float) $item->amount_idr, 0, ',', '.') }}
                                    </td>
                                    <td class="px-6 py-3.5 text-center">
                                        @if(in_array($item->line_type, ['ROOM_CHARGE', 'HOTEL_TAX', 'SERVICE_CHARGE']))
                                            <span class="inline-flex items-center gap-1 text-xs font-bold text-blue-600">
                                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2h-3a1 1 0 01-1-1v-2a1 1 0 00-1-1H9a1 1 0 00-1 1v2a1 1 0 01-1 1H4a1 1 0 110-2V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z"/></svg>
                                                Corporate
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-xs font-bold text-purple-600">
                                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>
                                                Personal
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-slate-400 italic">Belum ada baris transaksi yang dibukukan ke folio ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Settlement Control Panel -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Formulir Eksekusi Split Settlement</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Penetapan penanggung jawab pembayaran ganda & validasi batas kredit</p>
                </div>

                <form action="{{ route('hotel.folio.split.settle', ['folioId' => $folio->id]) }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">ID Perusahaan / Mitra Korporat</label>
                        <input type="text" name="corporate_id" value="CORP-ASTRA-01" required class="w-full px-3.5 py-2 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 font-mono">
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="corp_enabled" name="corporate_billing_enabled" value="1" checked class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <label for="corp_enabled" class="text-xs font-medium text-slate-700">Aktifkan Penagihan Langsung (City Ledger)</label>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Pagu Kredit Perusahaan (Credit Limit IDR)</label>
                        <input type="number" name="corporate_credit_limit_idr" value="10000000" min="0" required class="w-full px-3.5 py-2 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 font-mono">
                        <span class="text-[11px] text-slate-400 mt-1 block">Batas maksimal piutang korporat yang disetujui</span>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Nominal Pelunasan Kasir Tamu Personal (IDR)</label>
                        <input type="number" name="personal_payment_amount_idr" value="{{ $folio->outstanding_balance }}" min="0" required class="w-full px-3.5 py-2 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 font-mono">
                        <span class="text-[11px] text-slate-400 mt-1 block">Tunai / Kartu Kredit / QRIS tamu pribadi</span>
                    </div>

                    <input type="hidden" name="idempotency_key" value="folio-split-{{ $folio->id }}-{{ time() }}">

                    <div class="pt-4 border-t border-slate-100">
                        <button type="submit" class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-xl text-sm shadow-sm transition flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Eksekusi Split & Tutup Folio
                        </button>
                    </div>
                </form>

                <div class="bg-amber-50 rounded-xl p-4 border border-amber-200">
                    <div class="flex gap-2.5">
                        <svg class="w-5 h-5 text-amber-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                        <div class="text-xs text-amber-800 leading-relaxed">
                            <span class="font-bold">Prinsip Audit Saldo Nol:</span> Sistem akan menolak status penutupan folio jika saldo akhir tidak tepat IDR 0. Piutang perusahaan langsung dijurnal ke buku besar akun <code class="font-mono bg-amber-100 px-1 py-0.5 rounded">htl:city_ledger:IDR</code>.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dispute & Chargeback Management Modal Component -->
        @include('hotel::components.dispute_chargeback_modal')
    </div>
</div>
@endsection
