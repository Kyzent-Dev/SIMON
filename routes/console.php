<?php

use App\Support\CachePublik;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Date-dependent public values ("Hari ini", statuses) roll over at midnight.
Schedule::call(fn () => CachePublik::segarkan())->name('publik:segarkan')->dailyAt('00:00')->timezone('Asia/Jakarta');
Schedule::command('notifikasi:harian')->dailyAt('06:00')->timezone('Asia/Jakarta')->withoutOverlapping();
Schedule::command('ekspor:bersihkan')->dailyAt('05:00')->timezone('Asia/Jakarta')->withoutOverlapping();
Schedule::command('notifikasi:ringkasan-mingguan')->weeklyOn(1, '07:00')->timezone('Asia/Jakarta')->withoutOverlapping();
