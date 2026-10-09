@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Human Capital Management (HCM) & Payroll</h2>
            <p class="mt-1 text-sm text-gray-400">Direktori Karyawan, Penggajian Bulanan & Alokasi Tenaga Kerja Manufaktur</p>

            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Daftar Karyawan Terdaftar</h3>
                    <div class="space-y-2">
                        @forelse($employees as $emp)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between">
                                <div>
                                    <div class="text-white font-medium">{{ $emp->name }} ({{ $emp->employee_number }})</div>
                                    <div class="text-xs text-gray-400">{{ $emp->position }} • {{ $emp->employment_type }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-emerald-400 text-sm">Rp {{ number_format($emp->basic_salary_idr, 0, ',', '.') }}</div>
                                    <div class="text-xs text-gray-400">{{ $emp->bank_name }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada karyawan.</div>
                        @endforelse
                    </div>
                </div>

                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Riwayat Penggajian (Payroll)</h3>
                    <div class="space-y-2">
                        @forelse($payrolls as $pay)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between">
                                <div>
                                    <div class="text-white font-medium">{{ $pay->employee?->name }} ({{ $pay->period }})</div>
                                    <div class="text-xs text-gray-400">Potongan: Rp {{ number_format($pay->deductions_idr, 0, ',', '.') }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-emerald-400 text-sm">Net: Rp {{ number_format($pay->net_salary_idr, 0, ',', '.') }}</div>
                                    <span class="inline-flex px-2 text-xs font-semibold rounded-full bg-blue-900 text-blue-200">{{ $pay->status }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada slip gaji.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
