<?php

use App\Support\CachePublik;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Date-dependent public values ("Hari ini", statuses) roll over at midnight; an office-hours server needs no catch-up (TTL 3600 s).
// Everything else runs in office hours (08.00–16.00 WIB): the scheduler never catches up a run the machine was off for.
Schedule::call(fn () => CachePublik::segarkan())->name('publik:segarkan')->dailyAt('00:00')->timezone('Asia/Jakarta');
Schedule::call(fn () => CachePublik::pangkasKedaluwarsa())->name('cache:pangkas')->dailyAt('08:35')->timezone('Asia/Jakarta');
Schedule::command('notifikasi:harian')->dailyAt('08:40')->timezone('Asia/Jakarta')->withoutOverlapping();
Schedule::command('ekspor:bersihkan')->dailyAt('08:30')->timezone('Asia/Jakarta')->withoutOverlapping();
Schedule::command('notifikasi:ringkasan-mingguan')->weeklyOn(1, '08:45')->timezone('Asia/Jakarta')->withoutOverlapping();
Schedule::command('backup:clean')->dailyAt('12:00')->timezone('Asia/Jakarta')->withoutOverlapping();
Schedule::command('backup:run')->dailyAt('12:10')->timezone('Asia/Jakarta')->withoutOverlapping();
Schedule::command('backup:monitor')->dailyAt('12:40')->timezone('Asia/Jakarta')->withoutOverlapping();
