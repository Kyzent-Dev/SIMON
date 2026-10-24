{{-- "Usulkan Perubahan" / "Usulkan item baru" modal (Fase 9.2 Task 9; irban.md other screens, _shared.md §5, login.md §5).
     Item summary (read-only, jadwal berlaku) → "Jenis usulan" radios (item: Geser jadwal / Hapus item / Lainnya; new item: Item baru only)
     → "Jenis penugasan" (item baru) → "Jadwal baru" (geser / item baru) → "Alasan". "Diinput oleh" in the footer. No field animation (§7.5). --}}
@use('App\Enums\AksiUsulan')
@use('App\Support\Tanggal')
@php
    $baru = $usulanItemId === null;
    $opsiAksi = $baru ? [AksiUsulan::Tambah] : [AksiUsulan::Geser, AksiUsulan::Hapus, AksiUsulan::Lainnya];
    $pakaiTanggal = in_array($aksi_usulan, [AksiUsulan::Geser->value, AksiUsulan::Tambah->value], true);
    $catatanAksi = [
        AksiUsulan::Hapus->value => 'Admin hanya dapat menghapus item yang belum dipakai SPT.',
        AksiUsulan::Lainnya->value => 'Tuliskan perubahan yang diusulkan pada Alasan, misalnya keterangan atau target SPT.',
    ][$aksi_usulan] ?? null;
@endphp
<x-ui.modal name="usulan-perubahan" :title="$baru ? 'Usulkan item baru' : 'Usulkan Perubahan'" size="md" wire:model="usulanOpen">
    @if ($usulanOpen && ($baru || $aktif))
        <form id="form-usulan" wire:submit="kirimUsulan" novalidate class="space-y-5">
            @include('auth.partials.error-summary', ['class' => '', 'fields' => [
                'aksi_usulan' => 'Jenis usulan', 'jenis_penugasan_id' => 'Jenis penugasan', 'tanggal_mulai' => 'Tanggal mulai',
                'tanggal_selesai' => 'Tanggal selesai', 'alasan' => 'Alasan', 'diinput_oleh' => 'Diinput oleh',
            ]])
            @if ($aktif)
                @php [$mulai, $selesai] = $rentang[$aktif->id]; @endphp
                <dl class="grid grid-cols-1 gap-x-4 gap-y-1 rounded-md border border-neutral-200 bg-neutral-25 p-3 text-sm sm:grid-cols-[auto_1fr]">
                    <dt class="text-neutral-600">Jenis penugasan</dt>
                    <dd class="max-sm:mb-2"><x-app.type-chip :color="$aktif->jenisPenugasan->warna" :label="$aktif->jenisPenugasan->nama" /></dd>
                    <dt class="text-neutral-600">Jadwal berlaku</dt>
                    <dd class="tabular-nums max-sm:mb-2">{{ Tanggal::rentang($mulai, $selesai) }} · {{ (int) $mulai->diffInDays($selesai) + 1 }} hari</dd>
                    @if ($aktif->keterangan)
                        <dt class="text-neutral-600">Keterangan</dt>
                        <dd>{{ $aktif->keterangan }}</dd>
                    @endif
                </dl>
            @endif

            <fieldset>
                <legend class="mb-1.5 text-sm font-medium text-neutral-900">Jenis usulan<span class="text-danger-700" aria-hidden="true"> *</span><span class="sr-only"> (wajib)</span></legend>
                <div class="flex flex-wrap gap-x-6">
                    @foreach ($opsiAksi as $a)
                        <x-ui.radio name="aksi_usulan" :value="$a->value" :label="$a->label()" wire:model.live="aksi_usulan" :checked="$aksi_usulan === $a->value" />
                    @endforeach
                </div>
                @error('aksi_usulan')<p id="aksi_usulan-error" class="mt-1.5 text-sm text-danger-700">{{ $message }}</p>@enderror
                @if ($catatanAksi)
                    <p class="mt-1.5 text-xs text-neutral-500">{{ $catatanAksi }}</p>
                @endif
            </fieldset>

            @if ($baru)
                <x-ui.select name="jenis_penugasan_id" label="Jenis penugasan" required wire:model="jenis_penugasan_id" :options="$jenisOptions" :value="$jenis_penugasan_id"
                    placeholder="Pilih jenis penugasan" :error="$errors->first('jenis_penugasan_id')" />
            @endif

            @if ($pakaiTanggal)
                <x-ui.date-range legend="Jadwal baru" required :min="$pkpt->tahun.'-01-01'" :max="$pkpt->tahun.'-12-31'"
                    :error-mulai="$errors->first('tanggal_mulai')" :error-selesai="$errors->first('tanggal_selesai')" />
            @endif

            <x-ui.textarea label="Alasan" name="alasan" wire:model="alasan" rows="3" maxlength="1000" required
                helper="Jelaskan mengapa perubahan ini perlu (maksimal 1000 karakter)." :error="$errors->first('alasan')" />
        </form>
    @endif
    <x-slot:footer>
        {{-- login.md §5: Diinput oleh lives in the modal's sticky footer, full width above the buttons. --}}
        @if ($usulanOpen)
            <x-app.diinput-oleh :pegawai="$penginput" :value="$diinput_oleh" :ganti="$ganti" :error="$errors->first('diinput_oleh')" drop-up
                wire:model.live="diinput_oleh" class="w-full text-left" />
        @endif
        <x-ui.button type="button" variant="ghost" x-on:click="$dispatch('close-modal', { name: 'usulan-perubahan' })">Batal</x-ui.button>
        @php $nama = $diinput_oleh ? $penginput->firstWhere('id', $diinput_oleh)?->nama_bergelar : null; @endphp
        <x-ui.button form="form-usulan" icon="paper-airplane" wire:loading.attr="disabled" wire:target="kirimUsulan" class="max-sm:w-full">
            {{ $nama ? 'Kirim sebagai '.$nama : 'Kirim usulan' }}
        </x-ui.button>
    </x-slot:footer>
</x-ui.modal>
