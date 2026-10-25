{{-- Log Aktivitas filter fields, inline (≥768) and in the "Filter" drawer (<768); $p keeps ids unique. --}}
<div class="md:w-40"><x-ui.input label="Dari" name="dari" type="date" :id="$p.'-dari'" wire:model.live="dari" :value="$this->dari" /></div>
<div class="md:w-40"><x-ui.input label="Sampai" name="sampai" type="date" :id="$p.'-sampai'" wire:model.live="sampai" :value="$this->sampai" /></div>
<div class="md:w-48"><x-ui.select label="Pengguna" name="pengguna" :id="$p.'-pengguna'" wire:model.live="pengguna" :options="$penggunaOpsi" placeholder="Semua pengguna" :value="$this->pengguna" /></div>
<div class="md:w-48"><x-ui.select label="Objek" name="objek" :id="$p.'-objek'" wire:model.live="objek" :options="\App\Support\LabelAktivitas::opsiObjek()" placeholder="Semua objek" :value="$this->objek" /></div>
<div class="md:w-36"><x-ui.select label="Aksi" name="aksi" :id="$p.'-aksi'" wire:model.live="aksi" :options="\App\Livewire\LogAktivitas::AKSI" placeholder="Semua aksi" :value="$this->aksi" /></div>
