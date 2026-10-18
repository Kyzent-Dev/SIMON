<?php

namespace App\Livewire\Admin;

use App\Services\Settings;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Pengaturan Sistem')]
class Pengaturan extends Component
{
    /** Card => the settings keys it owns. Each card saves only these. */
    private const GROUPS = [
        'penomoran' => ['penomoran.format', 'penomoran.kode_arsip', 'penomoran.kode_sekretariat', 'penomoran.scope', 'penomoran.reset_tahunan', 'penomoran.klasifikasi_di_nomor'],
        'penugasan' => ['penugasan.hitungan_hari', 'penanda.batas_terlambat_hari'],
        'rekap' => ['rekap.dasar_triwulan'],
        'pkpt' => ['pkpt.draf_memblokir'],
        'publik' => ['publik.tampilkan_nomor_spt', 'publik.tampilkan_nama_objek'],
        'unggahan' => ['upload.maks_mb'],
        'kendala' => ['kendala.kategori'],
    ];

    /** Nested form state: v.penomoran.format, v.kendala.kategori, ... (dot keys become nested arrays). */
    public array $v = [];

    public function mount(Settings $settings): void
    {
        Gate::authorize('manage-master-data');
        foreach (array_keys(Settings::DEFAULTS) as $key) {
            Arr::set($this->v, $key, $settings->get($key));
        }
    }

    public function save(string $group, Settings $settings): void
    {
        Gate::authorize('manage-master-data');
        $keys = self::GROUPS[$group] ?? abort(404);

        $rules = [];
        foreach ($keys as $key) {
            $rules["v.$key"] = Settings::rules($key);
        }
        if ($group === 'kendala') {
            $rules['v.kendala.kategori.*'] = ['required', 'string', 'max:100', 'distinct'];
        }
        $this->validate($rules);

        foreach ($keys as $key) {
            $value = Arr::get($this->v, $key);
            $default = Settings::DEFAULTS[$key];
            $settings->set($key, match (true) {
                is_bool($default) => (bool) $value,
                is_int($default) => (int) $value,
                is_array($default) => array_values($value),
                default => $value,
            });
        }

        $this->dispatch('toast', type: 'success', message: 'Pengaturan disimpan.');
    }

    public function addKategori(): void
    {
        $this->v['kendala']['kategori'][] = '';
    }

    public function removeKategori(int $index): void
    {
        $list = $this->v['kendala']['kategori'];
        // "Lainnya" must stay and at least one category must remain.
        if (count($list) <= 1 || ($list[$index] ?? 'Lainnya') === 'Lainnya') {
            return;
        }
        unset($list[$index]);
        $this->v['kendala']['kategori'] = array_values($list);
    }

    protected function validationAttributes(): array
    {
        $labels = [
            'penomoran.format' => 'Format nomor', 'penomoran.kode_arsip' => 'Kode arsip', 'penomoran.kode_sekretariat' => 'Kode sekretariat',
            'penomoran.scope' => 'Cakupan penomoran', 'penugasan.hitungan_hari' => 'Hitungan hari',
            'penanda.batas_terlambat_hari' => 'Batas terlambat', 'rekap.dasar_triwulan' => 'Dasar triwulan',
            'upload.maks_mb' => 'Ukuran maksimum unggahan', 'kendala.kategori' => 'Kategori kendala',
        ];
        $attributes = ['v.kendala.kategori.*' => 'Nama kategori'];
        foreach ($labels as $key => $label) {
            $attributes["v.$key"] = $label;
        }

        return $attributes;
    }

    public function render()
    {
        return view('livewire.admin.pengaturan');
    }
}
