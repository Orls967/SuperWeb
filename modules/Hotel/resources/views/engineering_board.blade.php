@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="border-b border-slate-200 pb-4">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Papan Engineering, Work Orders & SLA Kompensasi</h1>
        <p class="text-sm text-slate-500">Integrasi modul Asset G7-K9 untuk penanganan gangguan fasilitas dan kompensasi H5.</p>
    </div>

    @if(session('success'))
        <div class="rounded-md bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Form Terbitkan Work Order Asset -->
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900 mb-3">Terbitkan Work Order Kerusakan (G7-K9)</h2>
            <form method="POST" action="{{ route('hotel.engineering.work_orders') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-700">Pilih Kamar / Aset</label>
                    <select name="room_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                        @foreach($rooms as $rm)
                            <option value="{{ $rm->id }}">Kamar {{ $rm->room_number }} ({{ $rm->property->name ?? 'Hotel' }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700">Deskripsi Masalah</label>
                    <textarea name="issue_description" rows="2" class="mt-1 block w-full rounded-md border-slate-300 text-sm" placeholder="Contoh: AC bocor air atau air panas mati"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700">SLA Perbaikan (Jam)</label>
                    <input type="number" name="sla_hours" value="4" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                </div>
                <button type="submit" class="w-full rounded bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-500">
                    Kirim Work Order ke Modul Asset
                </button>
            </form>
        </div>

        <!-- Form Hitung Kompensasi Gangguan H5 -->
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900 mb-3">Penyelesaian Kompensasi Gangguan Fasilitas (H5)</h2>
            <form method="POST" action="{{ route('hotel.engineering.compensate') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="idempotency_key" value="RECOV-{{ time() }}-{{ bin2hex(random_bytes(3)) }}">
                <div>
                    <label class="block text-xs font-medium text-slate-700">ID Reservasi Tamu</label>
                    <input type="number" name="reservation_id" required class="mt-1 block w-full rounded-md border-slate-300 text-sm" placeholder="Contoh: 1">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700">Kelebihan Waktu di Atas SLA (Jam)</label>
                    <input type="number" step="0.5" name="excess_hours" value="4.0" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <p class="text-xs text-slate-400 mt-1">&le;2 jam: 10% | 2-6 jam: 25% | &gt;6 jam: 50% + Free Night Certificate</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700">Catatan Masalah</label>
                    <input type="text" name="issue_description" value="AC tidak dingin selama menginap" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                </div>
                <button type="submit" class="w-full rounded bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-500">
                    Kreditkan Kompensasi ke Folio (Contra-Revenue)
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
