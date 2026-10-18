<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('customer.index');
});

Route::get('/dashboard', function () {
    return auth()->user()->role === 'admin'
        ? redirect()->route('admin.dashboard')
        : redirect()->route('staff.dashboard');
})->middleware('auth')->name('dashboard');

Route::middleware(['auth', 'role:staff'])->get('/staff', function () {
    return view('staff.dashboard');
})->name('staff.dashboard');

Route::middleware(['auth', 'role:admin'])->get('/admin', function () {
    return view('admin.dashboard');
})->name('admin.dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
