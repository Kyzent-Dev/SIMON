<?php

namespace App\Services;

use App\Support\CachePublik;
use Closure;
use Illuminate\Support\Arr;
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
        'kontak.telepon' => '',
        'kontak.email' => '',
        'kontak.instagram' => '',
        'kontak.alamat' => '',
        'kontak.peta_url' => '',
    ];

    /** Settings page card => the keys it owns. */
    public const GROUPS = [
        'penomoran' => ['penomoran.format', 'penomoran.kode_arsip', 'penomoran.kode_sekretariat', 'penomoran.scope', 'penomoran.reset_tahunan', 'penomoran.klasifikasi_di_nomor'],
        'penugasan' => ['penugasan.hitungan_hari', 'penanda.batas_terlambat_hari'],
        'rekap' => ['rekap.dasar_triwulan'],
        'pkpt' => ['pkpt.draf_memblokir'],
        'publik' => ['publik.tampilkan_nomor_spt', 'publik.tampilkan_nama_objek'],
        'unggahan' => ['upload.maks_mb'],
        'kendala' => ['kendala.kategori'],
        // Edited only on admin.kontak (Kontak & Footer); the Pengaturan page has no card for it.
        'kontak' => ['kontak.telepon', 'kontak.email', 'kontak.instagram', 'kontak.alamat', 'kontak.peta_url'],
    ];

    public const PETA_PREFIX = 'https://www.google.com/maps/embed?';

    private const CACHE_KEY = 'settings.all';

    public function get(string $key): mixed
    {
        $this->assertKnown($key);

        return $this->stored()[$key] ?? self::DEFAULTS[$key];
    }

    public function set(string $key, mixed $value): void
    {
        $this->assertKnown($key);
        $lama = $this->get($key);
        DB::table('settings')->upsert(
            [['key' => $key, 'value' => json_encode($value, JSON_UNESCAPED_UNICODE), 'created_at' => now(), 'updated_at' => now()]],
            ['key'],
            ['value', 'updated_at'],
        );
        Cache::forget(self::CACHE_KEY);
        if (str_starts_with($key, 'publik.') || $key === 'rekap.dasar_triwulan') {
            DB::afterCommit(fn () => CachePublik::segarkan());
        }
        if ($lama !== $value) {
            activity('pengaturan')->event('updated')
                ->withProperties(['old' => [$key => $lama], 'attributes' => [$key => $value]])->log('updated');
        }
    }

    /** Current values as a nested array (dot keys become nested keys), e.g. ['penomoran' => ['format' => ...]]. */
    public function all(): array
    {
        $nested = [];
        foreach (array_keys(self::DEFAULTS) as $key) {
            Arr::set($nested, $key, $this->get($key));
        }

        return $nested;
    }

    /** Trim text input of one card before validation, so "Lainnya " still counts as "Lainnya"; Instagram drops a leading @. */
    public static function sanitize(string $group, array $nested): array
    {
        foreach (self::groupKeys($group) as $key) {
            $value = Arr::get($nested, $key);
            if (is_string($value)) {
                Arr::set($nested, $key, trim($value));
            } elseif (is_array($value)) {
                Arr::set($nested, $key, array_map(fn ($v) => is_string($v) ? trim($v) : $v, $value));
            }
        }
        if ($group === 'kontak') {
            Arr::set($nested, 'kontak.instagram', ltrim((string) Arr::get($nested, 'kontak.instagram'), '@'));
        }

        return $nested;
    }

    /** Validation rules for one card, keyed under $prefix (the page's state property). */
    public static function groupRules(string $group, string $prefix = 'v'): array
    {
        $rules = [];
        foreach (self::groupKeys($group) as $key) {
            $rules["$prefix.$key"] = self::rules($key);
        }
        if ($group === 'kendala') {
            $rules["$prefix.kendala.kategori.*"] = ['required', 'string', 'max:100', 'distinct'];
        }

        return $rules;
    }

    /** Persist one card from validated nested input, coercing each value to its default's type. Other cards' keys are untouched. */
    public function saveGroup(string $group, array $nested): void
    {
        foreach (self::groupKeys($group) as $key) {
            $value = Arr::get($nested, $key);
            $default = self::DEFAULTS[$key];
            $this->set($key, match (true) {
                is_bool($default) => (bool) $value,
                is_int($default) => (int) $value,
                is_array($default) => array_values(array_map('trim', (array) $value)),
                is_string($default) => (string) $value,
                default => $value,
            });
        }
    }

    private static function groupKeys(string $group): array
    {
        return self::GROUPS[$group] ?? throw new InvalidArgumentException("Kartu pengaturan tidak dikenal: {$group}");
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
            // 12 = Livewire's temporary upload limit; a higher value could never be honoured.
            'upload.maks_mb' => ['required', 'integer', 'min:1', 'max:12'],
            'kendala.kategori' => ['required', 'array', 'min:1', function ($attribute, $value, $fail) {
                if (! in_array('Lainnya', (array) $value, true)) {
                    $fail('Kategori "Lainnya" wajib ada.');
                }
            }],
            'kontak.telepon' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\s-]*$/'],
            'kontak.email' => ['nullable', 'email', 'max:100'],
            'kontak.instagram' => ['nullable', 'string', 'regex:/^[A-Za-z0-9._]{0,30}$/'],
            'kontak.alamat' => ['nullable', 'string', 'max:300'],
            'kontak.peta_url' => ['nullable', 'string', 'max:2000', function ($attribute, $value, $fail) {
                if (! str_starts_with((string) $value, self::PETA_PREFIX) || filter_var($value, FILTER_VALIDATE_URL) === false) {
                    $fail('Gunakan tautan sematan Google Maps (https://www.google.com/maps/embed?...).');
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
