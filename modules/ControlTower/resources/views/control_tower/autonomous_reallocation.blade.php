@extends('layouts.app')

@section('title', 'Autonomous Inventory Reallocation Playbook')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Autonomous Reallocation Execution</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Eksekusi penyeimbangan stok otomatis antar eselon pasokan (DC &rarr; Pabrik &rarr; Outlet) guna menyelesaikan potensi stockout secara otonom.
            </div>
            <form action="{{ route('control_tower.autonomous.reallocate') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="ar_item_code" class="form-label">Kode Item</label>
                        <input id="ar_item_code" type="text" name="item_code" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label for="ar_from_node" class="form-label">Eselon Asal (Surplus)</label>
                        <select id="ar_from_node" name="from_node" class="form-select">
                            <option value="DC">Distribution Center (DC)</option>
                            <option value="PLANT">Plant Warehouse</option>
                            <option value="PORT">Bonded Port</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="ar_to_node" class="form-label">Eselon Tujuan (Defisit)</label>
                        <select id="ar_to_node" name="to_node" class="form-select">
                            <option value="OUTLET">Regional Outlet</option>
                            <option value="PLANT">Manufacturing Plant</option>
                            <option value="DC">Central DC</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="ar_transfer_qty" class="form-label">Kuantitas Transfer</label>
                        <input id="ar_transfer_qty" type="number" name="transfer_qty" class="form-control" required min="1">
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-success">Eksekusi Rebalancing Playbook</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
