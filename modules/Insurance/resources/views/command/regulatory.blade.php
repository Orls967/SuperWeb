@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Kepatuhan Regulasi & Manajemen Pengaduan Nasabah (SLA)</h1>
            <p class="text-sm text-gray-600">Pemantauan penyelesaian tiket keluhan klaim nasabah sesuai ketentuan perlindungan konsumen OJK</p>
        </div>
        <a href="{{ route('insurance.command.dashboard') }}" class="rounded bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300">Kembali ke Command Center</a>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nomor Tiket</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Referensi Polis</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kategori</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Uraian Pengaduan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Batas Waktu SLA</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($grievances as $g)
                <tr>
                    <td class="px-6 py-4 text-sm font-mono font-bold text-gray-900">{{ $g->ticket_number }}</td>
                    <td class="px-6 py-4 text-sm font-mono text-gray-700">{{ $g->policy_ref }}</td>
                    <td class="px-6 py-4 text-sm font-semibold text-rose-600">{{ $g->category }}</td>
                    <td class="px-6 py-4 text-sm text-gray-700 max-w-md truncate">{{ $g->grievance_text }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $g->sla_deadline?->format('d M Y') }}</td>
                    <td class="px-6 py-4 text-sm">
                        <span class="inline-flex rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800">{{ $g->status }}</span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">Belum ada tiket pengaduan regulasi tercatat.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $grievances->links() }}
        </div>
    </div>
</div>
@endsection

