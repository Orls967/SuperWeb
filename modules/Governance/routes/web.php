<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'role:admin,governance_admin,voter'])->prefix('governance')->name('governance.')->group(function () {
    Route::get('/', function () {
        return view('governance::index');
    })->name('index');
});
