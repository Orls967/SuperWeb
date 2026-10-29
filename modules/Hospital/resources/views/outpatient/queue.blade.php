@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow sm:rounded-lg">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-xl font-semibold text-white">Papan Antrian Rawat Jalan</h2>
                    <p class="mt-1 text-sm text-gray-400">Daftar antrian aktif per poliklinik hari ini ({{ now()->format('d M Y') }}).</p>
                </div>
                <a href="{{ route('hospital.outpatient.registration.index') }}" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md">
                    + Pendaftaran Baru
                </a>
            </div>

            @if(session('success'))
                <div class="mb-6 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-700 text-sm text-left text-gray-300">
                    <thead class="bg-gray-700/50 text-xs uppercase text-gray-400">
                        <tr>
                            <th class="px-6 py-3">No. Antrian</th>
                            <th class="px-6 py-3">Poli</th>
                            <th class="px-6 py-3">Dokter</th>
                            <th class="px-6 py-3">Pasien</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        @forelse($queues as $q)
                            <tr class="hover:bg-gray-750">
                                <td class="px-6 py-4 font-mono font-bold text-white text-base">
                                    {{ $q->queue_code }} (#{{ $q->queue_number }})
                                </td>
                                <td class="px-6 py-4">{{ $q->polyclinic?->name ?? '-' }}</td>
                                <td class="px-6 py-4">{{ $q->doctor?->user?->name ?? '-' }}</td>
                                <td class="px-6 py-4">{{ $q->encounter?->patient?->name ?? 'Pasien Umum' }}</td>
                                <td class="px-6 py-4">
                                    @if($q->status === 'WAITING')
                                        <span class="px-2.5 py-1 text-xs rounded-full bg-amber-900/60 text-amber-300 border border-amber-600">Menunggu</span>
                                    @elseif($q->status === 'CALLED')
                                        <span class="px-2.5 py-1 text-xs rounded-full bg-blue-900/60 text-blue-300 border border-blue-600">Dipanggil</span>
                                    @elseif($q->status === 'SERVED')
                                        <span class="px-2.5 py-1 text-xs rounded-full bg-emerald-900/60 text-emerald-300 border border-emerald-600">Selesai</span>
                                    @elseif($q->status === 'CANCELLED')
                                        <span class="px-2.5 py-1 text-xs rounded-full bg-rose-900/60 text-rose-300 border border-rose-600">Batal</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    @if($q->status === 'WAITING')
                                        <form action="{{ route('hospital.outpatient.queue.call', $q) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="px-3 py-1 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded">
                                                Panggil
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                    Belum ada antrian terdaftar hari ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
