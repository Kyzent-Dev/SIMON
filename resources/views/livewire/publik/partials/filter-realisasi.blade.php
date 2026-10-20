{{-- The six Realisasi filters, rendered inline (≥768, $sfx = '') and in the bottom Filter drawer (<768, $sfx = '-m'). Every field applies live. --}}
<x-ui.select label="Tahun" :name="'tahun'.$sfx" :options="$tahunOpsi" :value="$tahun" wire:model.live="tahun" />
<x-ui.select label="Triwulan" :name="'tw'.$sfx" :options="$twOpsi" :value="$tw" placeholder="Semua Triwulan" wire:model.live="tw" />
<x-ui.select label="Bulan" :name="'bulan'.$sfx" :options="$bulanOpsi" :value="$bulan" placeholder="Semua Bulan" wire:model.live="bulan" />
<x-ui.select label="Irban" :name="'irban'.$sfx" :options="$irbanOpsi" :value="$irban" placeholder="Semua Irban" wire:model.live="irban" />
<x-ui.combobox label="Wilayah" :name="'wilayah'.$sfx" :options="$wilayahOpsi" placeholder="Semua Wilayah" wire:model.live="wilayah" />
<x-ui.select label="Jenis Penugasan" :name="'jenis'.$sfx" :options="$jenisOpsi" :value="$jenis" placeholder="Semua Jenis" wire:model.live="jenis" />
