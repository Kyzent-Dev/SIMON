{{-- admin.md "Latar Halaman Masuk": live preview of the login brand panel (partials/latar-masuk: photo, mint-950/80 scrim, Delapan Sungai, same text as /masuk),
     one upload field (checked on choose; saved with "Simpan foto"), "Hapus foto" behind a confirm modal. No animation (DESIGN §7.5).
     After a delete the trigger is gone, so focus moves to the preview heading (tabindex -1). --}}
<div>
    <x-ui.page-header title="Latar Halaman Masuk" description="Foto di panel kiri halaman Masuk. Tanpa foto, panel memakai warna hijau tua bawaan." />

    <div class="grid gap-6 lg:grid-cols-2">
        <section aria-labelledby="latar-pratinjau" class="min-w-0">
            <h2 id="latar-pratinjau" tabindex="-1" class="mb-1 text-lg font-semibold text-neutral-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mint-600">Pratinjau panel</h2>
            <p class="mb-3 text-sm text-neutral-600">{{ $pratinjau ? 'Foto baru, belum disimpan.' : ($base !== '' ? 'Foto yang tampil sekarang di halaman Masuk.' : 'Latar bawaan, belum ada foto.') }}</p>
            <div aria-hidden="true" class="relative flex aspect-[4/3] items-center overflow-hidden rounded-xl bg-mint-950 px-6 sm:px-10">
                @include('partials.latar-masuk', $pratinjau ? ['src' => $pratinjau, 'pratinjau' => true] : ['base' => $base])
                <div class="relative max-w-sm">
                    <x-app.logo class="h-12" />
                    <p class="mt-4 font-display text-3xl/9 font-bold text-white">SINTAS</p>
                    <p class="mt-2 text-xs text-mint-200 sm:text-sm">Sistem Informasi Surat Tugas Pengawasan — Inspektorat Daerah Kabupaten Muara Enim</p>
                    <div class="mt-4 h-1 w-16 rounded-full bg-accent-400"></div>
                </div>
            </div>
        </section>

        <x-ui.card class="min-w-0 self-start">
            <form novalidate wire:submit="save" class="space-y-4">
                <h2 class="text-lg font-semibold text-neutral-900">Foto latar</h2>
                @include('auth.partials.error-summary', ['class' => '', 'fields' => ['foto_latar' => 'Foto latar']])
                <x-ui.file-upload name="foto_latar" label="Foto" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" wire:model="foto_latar"
                    :helper="'JPG, PNG, atau WebP, maks. 5 MB, lebar minimal 960 piksel. Foto dipotong 16:9 lalu menyesuaikan panel.'" :error="$errors->first('foto_latar')"
                    :filename="$foto_latar && ! $errors->has('foto_latar') ? $foto_latar->getClientOriginalName() : null"
                    :size="$foto_latar && ! $errors->has('foto_latar') ? number_format(max(1, round($foto_latar->getSize() / 1024)), 0, ',', '.').' KB' : null" />
                <div class="flex flex-wrap justify-end gap-2">
                    @if ($foto_latar)
                        <x-ui.button type="button" variant="ghost" wire:click="batal">Batal</x-ui.button>
                        <x-ui.button wire:loading.attr="disabled" wire:target="save,foto_latar">Simpan foto</x-ui.button>
                    @elseif ($base !== '')
                        <x-ui.button type="button" variant="secondary" icon="trash" wire:click="confirmHapus">Hapus foto</x-ui.button>
                    @endif
                </div>
            </form>
        </x-ui.card>
    </div>

    <x-ui.modal name="latar-hapus" title="Hapus foto latar?" size="sm" wire:model="hapusOpen">
        <p>Foto akan dihapus permanen. Halaman Masuk kembali memakai latar hijau tua bawaan.</p>
        <x-slot:footer>
            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', { name: 'latar-hapus' })">Batal</x-ui.button>
            <x-ui.button type="button" variant="danger" wire:loading.attr="disabled" wire:target="hapus"
                x-on:click="const h = document.getElementById('latar-pratinjau'); $wire.hapus().then(() => $nextTick(() => h.focus()))">Hapus</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
