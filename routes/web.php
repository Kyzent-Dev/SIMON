<?php

use App\Http\Controllers\Auth\ForcedPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Livewire\Admin\Irban;
use App\Livewire\Admin\IrbanTahunan;
use App\Livewire\Admin\Klasifikasi;
use App\Livewire\Admin\Pegawai;
use App\Livewire\Admin\PegawaiImpor;
use App\Livewire\Admin\Tim;
use App\Livewire\Admin\Pengaturan;
use Illuminate\Support\Facades\Route;

Route::view('/', 'placeholder.public');

Route::middleware('guest')->group(function () {
    Route::get('/masuk', [LoginController::class, 'create'])->name('login');
    Route::post('/masuk', [LoginController::class, 'store']);
    Route::get('/lupa-kata-sandi', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/lupa-kata-sandi', [PasswordResetLinkController::class, 'store'])->name('password.email')->middleware('throttle:6,1');
    Route::get('/atur-ulang-kata-sandi/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/atur-ulang-kata-sandi', [NewPasswordController::class, 'store'])->name('password.update')->middleware('throttle:6,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/keluar', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/perbarui-kata-sandi', [ForcedPasswordController::class, 'edit'])->name('password.force.edit');
    Route::put('/perbarui-kata-sandi', [ForcedPasswordController::class, 'update'])->name('password.force.update');

    Route::get('/dashboard', fn () => redirect()->route(auth()->user()->role->dashboardRoute()))->name('dashboard');

    Route::view('/admin', 'dashboard.admin')->name('admin.dashboard')->middleware('role:admin');
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/pengaturan', Pengaturan::class)->name('admin.pengaturan');
        Route::get('/klasifikasi', Klasifikasi::class)->name('admin.klasifikasi');
        Route::get('/pegawai', Pegawai::class)->name('admin.pegawai');
        Route::get('/pegawai/impor', PegawaiImpor::class)->name('admin.pegawai.impor');
        Route::get('/irban', Irban::class)->name('admin.irban');
        Route::get('/irban-tahunan', IrbanTahunan::class)->name('admin.irban-tahunan');
        Route::get('/tim', Tim::class)->name('admin.tim');
    });
    Route::view('/pimpinan', 'dashboard.pimpinan')->name('pimpinan.dashboard')->middleware('role:pimpinan');
    Route::view('/irban', 'dashboard.irban')->name('irban.dashboard')->middleware('role:irban');
    Route::view('/bagian-umum', 'dashboard.bagian-umum')->name('bagian-umum.dashboard')->middleware('role:bagian_umum');
});
