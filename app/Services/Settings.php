<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** System settings (PRD §15): key + json value in `settings`, falling back to DEFAULTS. Registered as a singleton. */
class Settings
{
    public const DEFAULTS = [
        'penomoran.format' => '{kode_arsip}/{nomor_urut}/{kode_irban}/SPT/{tahun}',
        'penomoran.kode_arsip' => '700',
        'penomoran.scope' => 'global',
        'penomoran.reset_tahunan' => true,
        'penomoran.kode_sekretariat' => 'INSPEKTORAT',
        'penomoran.klasifikasi_di_nomor' => false,
        'penugasan.hitungan_hari' => 'kalender',
        'penanda.batas_terlambat_hari' => 14,
        'rekap.dasar_triwulan' => 'tanggal_selesai',
        'pkpt.draf_memblokir' => false,
        'publik.tampilkan_nomor_spt' => false,
        'publik.tampilkan_nama_objek' => false,
        'upload.maks_mb' => 10,
        'kendala.kategori' => ['Jadwal PKPT belum disahkan', 'Bentrok penugasan lain', 'Personel tidak tersedia', 'Objek tidak siap', 'Lainnya'],
    ];

    private const CACHE_KEY = 'settings.all';

    public function get(string $key): mixed
    {
        $this->assertKnown($key);

        return $this->stored()[$key] ?? self::DEFAULTS[$key];
    }

    public function set(string $key, mixed $value): void
    {
        $this->assertKnown($key);
        DB::table('settings')->upsert(
            [['key' => $key, 'value' => json_encode($value, JSON_UNESCAPED_UNICODE), 'created_at' => now(), 'updated_at' => now()]],
            ['key'],
            ['value', 'updated_at'],
        );
        Cache::forget(self::CACHE_KEY);
    }

    /** Validation rules for one key; the page prefixes the attribute path with its own property. */
    public static function rules(string $key): array
    {
        return match ($key) {
            'penomoran.format' => ['required', 'string', 'max:150', self::contains('{nomor_urut}', '{tahun}')],
            'penomoran.kode_arsip' => ['required', 'string', 'max:20'],
            'penomoran.kode_sekretariat' => ['required', 'string', 'max:50'],
            'penomoran.scope' => ['required', 'in:global,per_irban'],
            'penugasan.hitungan_hari' => ['required', 'in:kalender,kerja'],
            'rekap.dasar_triwulan' => ['required', 'in:tanggal_selesai,tanggal_mulai,tanggal_lhp'],
            'penanda.batas_terlambat_hari' => ['required', 'integer', 'min:1', 'max:365'],
            'upload.maks_mb' => ['required', 'integer', 'min:1', 'max:100'],
            'kendala.kategori' => ['required', 'array', 'min:1', function ($attribute, $value, $fail) {
                if (! in_array('Lainnya', (array) $value, true)) {
                    $fail('Kategori "Lainnya" wajib ada.');
                }
            }],
            default => ['boolean'],
        };
    }

    private static function contains(string ...$tokens): Closure
    {
        return function ($attribute, $value, $fail) use ($tokens) {
            foreach ($tokens as $token) {
                if (! str_contains((string) $value, $token)) {
                    $fail('Format harus memuat '.implode(' dan ', $tokens).'.');

                    return;
                }
            }
        };
    }

    private function stored(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => DB::table('settings')->pluck('value', 'key')
            ->map(fn ($v) => json_decode($v, true))->all());
    }

    private function assertKnown(string $key): void
    {
        array_key_exists($key, self::DEFAULTS) || throw new InvalidArgumentException("Pengaturan tidak dikenal: {$key}");
    }
}
