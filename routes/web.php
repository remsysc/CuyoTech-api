<?php

use App\Http\Controllers\ReceiptController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('/receipts/{or_number}', [ReceiptController::class, 'show'])->where('or_number', '.*');
