<?php

namespace App\Livewire\Admin;

use App\Services\Settings;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Kontak & Footer (admin.md): Settings group `kontak` in one card, with a preview rendered by the public footer partial. */
#[Title('Kontak & Footer')]
class Kontak extends Component
{
    /** Nested form state as on Pengaturan: v.kontak.telepon, ... */
    public array $v = [];

    public function mount(Settings $settings): void
    {
        Gate::authorize('manage-master-data');
        $this->v = ['kontak' => $settings->all()['kontak']];
    }

    public function save(Settings $settings): void
    {
        Gate::authorize('manage-master-data');
        $this->v = Settings::sanitize('kontak', $this->v);
        $this->validate(Settings::groupRules('kontak'));
        $settings->saveGroup('kontak', $this->v);
        $this->dispatch('toast', type: 'success', message: 'Kontak disimpan.');
    }

    protected function validationAttributes(): array
    {
        return ['v.kontak.telepon' => 'Telepon', 'v.kontak.email' => 'Email', 'v.kontak.instagram' => 'Instagram',
            'v.kontak.alamat' => 'Alamat kantor', 'v.kontak.peta_url' => 'Tautan sematan peta'];
    }

    public function render()
    {
        return view('livewire.admin.kontak');
    }
}
