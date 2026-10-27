<?php

use App\Http\Controllers\ParcelController;
use App\Http\Controllers\ShipmentStatusLogController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [ParcelController::class, 'index'])->name('dashboard');
    Route::resource('parcels', ParcelController::class)->only(['index', 'create', 'store', 'update']);
    Route::resource('shipment-status-logs', ShipmentStatusLogController::class)->only(['index', 'create', 'store', 'update']);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
