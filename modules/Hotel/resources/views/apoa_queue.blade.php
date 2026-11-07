@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="md:flex md:items-center md:justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Antrean Kepatuhan Laporan Orang Asing (APOA)</h1>
            <p class="text-sm text-slate-500">Kewajiban pelaporan tamu WNA sesuai UU 6/2011 Keimigrasian & Pungutan Wisman Bali.</p>
        </div>
        <div class="mt-4 md:mt-0">
            <form method="POST" action="{{ route('hotel.compliance.apoa.submit') }}">
                @csrf
                <button type="submit" class="rounded bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-500">
                    Kirim Batch ke Gateway APOA S1
                </button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-md bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <div class="bg-white shadow-sm rounded-lg border border-slate-200 overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-slate-700">
                <tr>
                    <th class="py-3 px-4 text-left font-semibold">Reservasi</th>
                    <th class="py-3 px-4 text-left font-semibold">Kebangsaan</th>
                    <th class="py-3 px-4 text-left font-semibold">Blind Index Paspor</th>
                    <th class="py-3 px-4 text-center font-semibold">Pungutan Bali (PWA)</th>
                    <th class="py-3 px-4 text-left font-semibold">Tenggat Waktu</th>
                    <th class="py-3 px-4 text-center font-semibold">Status SM</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @forelse($reports as $rep)
                    <tr>
                        <td class="py-3 px-4 font-mono font-semibold text-slate-900">{{ $rep->reservation->reservation_code ?? $rep->reservation_id }}</td>
                        <td class="py-3 px-4 font-bold text-slate-700">{{ $rep->nationality_country_code }}</td>
                        <td class="py-3 px-4 font-mono text-xs text-slate-400">{{ substr($rep->passport_blind_index, 0, 16) }}...</td>
                        <td class="py-3 px-4 text-center">
                            @if($rep->bali_pwa_levy_verified)
                                <span class="rounded bg-emerald-100 text-emerald-800 px-2 py-0.5 text-xs">LUNAS</span>
                            @else
                                <span class="rounded bg-amber-100 text-amber-800 px-2 py-0.5 text-xs">BELUM</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-slate-600 text-xs">{{ $rep->deadline_at->format('d M Y H:i') }}</td>
                        <td class="py-3 px-4 text-center">
                            <span class="rounded bg-slate-100 text-slate-800 px-2 py-0.5 text-xs font-semibold uppercase">{{ $rep->status }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-6 text-center text-sm text-slate-500">Tidak ada antrean tamu WNA yang pending.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4 border-t border-slate-200">
            {{ $reports->links() }}
        </div>
    </div>
</div>
@endsection
