@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Terminal Penggantian & Revokasi Kunci Kamar</h1>
            <p class="text-sm text-slate-500">Penerbitan kartu kunci fisik RFID pengganti atau re-issue token QR kunci digital ponsel tamu.</p>
        </div>
    </div>

    <!-- Form Penggantian Kunci -->
    <div class="bg-white rounded-lg border border-slate-200 p-6 shadow-sm max-w-2xl">
        <form class="space-y-4">
            <div>
                <label class="block text-xs font-medium text-slate-700">Nomor Kamar Tamu</label>
                <input type="text" placeholder="Contoh: 304" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700">Alasan Penggantian Kunci</label>
                <select class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="LOST_CARD">Kartu Fisik Hilang (Auto Revoke Old Token)</option>
                    <option value="DAMAGED_CARD">Kartu Fisik Rusak / Demagnetized</option>
                    <option value="NEW_GUEST_MOBILE">Ponsel Baru Tamu (Kirim Token QR Baru)</option>
                    <option value="ADDITIONAL_KEY">Permintaan Kunci Tambahan (Pasangan/Keluarga)</option>
                </select>
            </div>
            <div class="pt-4 flex justify-end space-x-3">
                <button type="button" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50">
                    Batal
                </button>
                <button type="submit" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    Encode & Terbitkan Kunci Pengganti
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
