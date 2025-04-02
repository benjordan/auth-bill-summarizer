<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BillController;

Route::get('/welcome', function () {
    return view('welcome');
});
Route::get('/', [BillController::class, 'index']);
Route::post('/analyze', [BillController::class, 'analyze'])->name('bill.analyze');
