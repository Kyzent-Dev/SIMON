<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'placeholder.public');

// Stub: Task 2 of Fase 1 replaces this with the real login.
Route::view('/masuk', 'auth.login')->name('login')->middleware('guest');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn () => redirect()->route(auth()->user()->role->dashboardRoute()))->name('dashboard');

    Route::view('/admin', 'dashboard.admin')->name('admin.dashboard')->middleware('role:admin');
    Route::view('/pimpinan', 'dashboard.pimpinan')->name('pimpinan.dashboard')->middleware('role:pimpinan');
    Route::view('/irban', 'dashboard.irban')->name('irban.dashboard')->middleware('role:irban');
    Route::view('/bagian-umum', 'dashboard.bagian-umum')->name('bagian-umum.dashboard')->middleware('role:bagian_umum');
});
