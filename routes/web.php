<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BillController;

Route::get('/welcome', function () {
    return view('welcome');
});
Route::get('/', [BillController::class, 'index']);
Route::post('/analyze', [BillController::class, 'analyze'])->name('bill.analyze');
Route::get('/api/search-bills', [BillController::class, 'search'])->name('bills.search');
Route::get('/bill/{bill}', [BillController::class, 'show'])->name('bill.show');
