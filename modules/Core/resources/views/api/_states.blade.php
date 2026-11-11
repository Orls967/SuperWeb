{{-- Shared 5 UI States: kosong, memuat, galat, tanpa akses, sukses (toast aria-live) --}}
@if (session('success') || session('status'))
    <x-ds.toast type="success" title="Berhasil" :message="session('success') ?? session('status')" />
@endif

@if (session('error') || ($errors->any() ?? false))
    <x-ds.error
        title="Operasi Gagal"
        :message="session('error') ?? $errors->first()"
        code="422"
    />
@endif

@if (request()->boolean('loading'))
    <div class="my-4">
        <x-ds.skeleton type="table" :rows="4" :columns="4" />
    </div>
@endif

@if (request()->boolean('denied'))
    <x-ds.error
        title="Tanpa Akses"
        message="Peran Anda tidak memiliki izin untuk mengakses modul API Gateway."
        code="403"
    />
@endif

