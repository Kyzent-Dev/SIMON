<?php

namespace App\Livewire\Admin;

use App\Models\Pkpt;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/** Placeholder: Fase 3 Task 3 replaces this with the Excel import. */
class PkptImpor extends Component
{
    public Pkpt $pkpt;

    public function mount(Pkpt $pkpt): void
    {
        Gate::authorize('manage-master-data');
        $this->pkpt = $pkpt;
    }

    public function render()
    {
        return view('livewire.admin.pkpt-impor')->title("Impor PKPT {$this->pkpt->tahun}");
    }
}
