<?php

namespace App\Livewire\Spt;

use App\Models\Spt;
use App\Services\KesesuaianPkpt;
use App\Services\PenandaSpt;
use App\Services\Settings;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Shared SPT detail page (_shared.md §7), any role allowed by SptPolicy::view. Read-only here; the Fase 5 action modals hang off this component. */
class Detail extends Component
{
    #[Locked]
    public int $sptId;

    public function mount(Spt $spt): void
    {
        Gate::authorize('view', $spt);
        $this->sptId = $spt->id;
    }

    private function spt(): Spt
    {
        $spt = Spt::with([
            'irban', 'klasifikasi', 'jenisPenugasan', 'pkptItem.pkpt', 'pkptItem.jenisPenugasan', 'ppj', 'objek.parent', 'personel.pegawai',
            'dokumen' => fn ($q) => $q->with(['diunggahOleh', 'diinputOleh'])->orderBy('created_at')->orderBy('id'),
            'riwayat' => fn ($q) => $q->with(['olehUser', 'diinputOleh'])->orderBy('created_at')->orderBy('id'),
            'kendala.dibuatOleh', 'kendala.diinputOleh',
        ])->findOrFail($this->sptId);
        Gate::authorize('view', $spt);

        return $spt;
    }

    public function render(PenandaSpt $penanda, KesesuaianPkpt $kesesuaian, Settings $settings)
    {
        $spt = $this->spt();
        $item = $spt->pkptItem;

        return view('livewire.spt.detail', [
            'spt' => $spt,
            'penanda' => $penanda->untuk($spt),
            'perluKendala' => $penanda->perluKendala($spt),
            'rentang' => $item ? $kesesuaian->rentang($item) : null,
            'sesuai' => $item && $spt->tanggal_mulai && $spt->tanggal_selesai ? $kesesuaian->sesuai($item, $spt->tanggal_mulai, $spt->tanggal_selesai) : null,
            'hariKerja' => $settings->get('penugasan.hitungan_hari') === 'kerja',
            'laksanakan' => auth()->user()->can('laksanakan', $spt),
            'bisaUbah' => auth()->user()->can('update', $spt),
        ])->title($spt->nomor_lengkap ?? 'Detail SPT');
    }
}
