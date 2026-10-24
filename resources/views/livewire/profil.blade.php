{{-- Profil (spec 7.2): latar banner + avatar (this page only), then single-column forms (_shared.md §4): "Foto & latar", "Data akun", "Kata sandi".
     Irban accounts: data akun read-only; every write carries "Diinput oleh" in its footer (login.md §5); the password change asks for confirmation first. No animation (DESIGN §7.5). --}}
@php
    $nama = $diinput_oleh ? $penginput->firstWhere('id', $diinput_oleh)?->nama_bergelar : null;
    $terima = '.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp';
    $ukuran = fn ($f) => $f ? number_format(max(1, round($f->getSize() / 1024)), 0, ',', '.').' KB' : null;
    $footer = 'flex flex-col gap-3 border-t border-neutral-200 pt-4 sm:flex-row sm:items-end sm:justify-between';
@endphp
<div>
    <x-ui.page-header title="Profil" title-id="profil-judul" description="Foto, data akun, dan kata sandi akun Anda." />

    <div class="max-w-[720px] space-y-6">
        <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-xs">
            <div class="aspect-[4/1] bg-mint-100">
                @if ($user->latarUrl())
                    <img src="{{ $user->latarUrl() }}" alt="" width="1600" height="400" class="size-full object-cover" data-latar>
                @endif
            </div>
            <div class="flex items-end gap-4 px-4 pb-4 lg:px-5">
                <x-app.avatar :user="$user" class="-mt-10 size-20 text-2xl ring-4 ring-white sm:-mt-12 sm:size-24" />
                <div class="min-w-0 pb-1">
                    <p class="truncate font-display text-[22px] leading-[30px] font-bold text-neutral-900">{{ $user->name }}</p>
                    <p class="truncate text-sm text-neutral-600">{{ $user->username }} · {{ $user->role->label() }}@if ($irban && $user->irban) · {{ $user->irban->nama }}@endif</p>
                </div>
            </div>
        </div>

        <x-ui.card>
            <form novalidate wire:submit="simpanGambar" class="space-y-4">
                <div>
                    <h2 class="text-lg font-semibold text-neutral-900">Foto &amp; latar</h2>
                    <p class="mt-1 text-sm text-neutral-600">Foto tampil di menu pengguna dan halaman ini. Latar hanya tampil di halaman ini.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <x-ui.file-upload name="foto" label="Foto profil" :accept="$terima" wire:model="foto" :error="$errors->first('foto')"
                            helper="JPG, PNG, atau WebP, maks. {{ $maksMb }} MB, minimal 256 × 256 piksel. Dipotong persegi."
                            :filename="$foto?->getClientOriginalName()" :size="$ukuran($foto)" />
                        @if ($user->foto_profil)
                            <x-ui.button type="button" variant="secondary" icon="trash" wire:click="hapusGambar('foto')" wire:loading.attr="disabled" wire:target="hapusGambar">Hapus foto</x-ui.button>
                        @endif
                    </div>
                    <div class="space-y-2">
                        <x-ui.file-upload name="latar" label="Latar profil" :accept="$terima" wire:model="latar" :error="$errors->first('latar')"
                            helper="JPG, PNG, atau WebP, maks. {{ $maksMb }} MB, minimal 800 × 200 piksel. Dipotong 4:1."
                            :filename="$latar?->getClientOriginalName()" :size="$ukuran($latar)" />
                        @if ($user->latar_profil)
                            <x-ui.button type="button" variant="secondary" icon="trash" wire:click="hapusGambar('latar')" wire:loading.attr="disabled" wire:target="hapusGambar">Hapus latar</x-ui.button>
                        @endif
                    </div>
                </div>
                <div class="{{ $footer }}">
                    @if ($irban && ! $sandiOpen)
                        <x-app.diinput-oleh :pegawai="$penginput" :value="$diinput_oleh" :ganti="$ganti" :error="$errors->first('diinput_oleh')" drop-up
                            wire:model.live="diinput_oleh" class="min-w-0 sm:max-w-sm sm:flex-1" />
                    @endif
                    <x-ui.button icon="arrow-up-tray" wire:loading.attr="disabled" wire:target="simpanGambar,foto,latar" class="max-sm:w-full sm:ml-auto">Simpan gambar</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <x-ui.card>
            <div class="space-y-4">
                <div>
                    <h2 class="text-lg font-semibold text-neutral-900">Data akun</h2>
                    @if ($irban)
                        <p class="mt-1 text-sm text-neutral-600">Akun Irban dipakai bersama anggotanya. Hubungi Admin untuk mengubah</p>
                    @endif
                </div>
                @if ($irban)
                    <dl class="divide-y divide-neutral-200 text-sm">
                        @foreach (['Nama tampilan' => $user->name, 'Username' => $user->username, 'Email' => $user->email] as $label => $nilai)
                            <div class="grid gap-1 py-3 sm:grid-cols-3 sm:gap-4">
                                <dt class="font-medium text-neutral-600">{{ $label }}</dt>
                                <dd class="break-all text-neutral-900 sm:col-span-2">{{ $nilai }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @else
                    <form novalidate wire:submit="simpanAkun" class="space-y-4">
                        <x-ui.input label="Nama tampilan" name="name" wire:model="name" required maxlength="255" autocomplete="name" :error="$errors->first('name')" />
                        <x-ui.input label="Username" name="username" wire:model="username" required maxlength="30" autocomplete="username" autocapitalize="none"
                            helper="3–30 karakter: huruf kecil, angka, titik, atau garis bawah." :error="$errors->first('username')" />
                        <x-ui.input label="Email" name="email" type="email" wire:model="email" required maxlength="255" autocomplete="email"
                            helper="Email baru berlaku setelah tautan verifikasi yang dikirim ke alamat itu dibuka." :error="$errors->first('email')" />
                        @if ($user->email_baru)
                            <x-ui.alert variant="info" icon="envelope">Menunggu verifikasi: {{ $user->email_baru }}</x-ui.alert>
                        @endif
                        <div class="{{ $footer }}">
                            <x-ui.button wire:loading.attr="disabled" wire:target="simpanAkun" class="max-sm:w-full sm:ml-auto">Simpan data akun</x-ui.button>
                        </div>
                    </form>
                @endif
            </div>
        </x-ui.card>

        <x-ui.card id="kata-sandi">
            <form novalidate wire:submit="kirimSandi" class="space-y-4">
                <div>
                    <h2 class="text-lg font-semibold text-neutral-900">Kata sandi</h2>
                    <p class="mt-1 text-sm text-neutral-600">Setelah diubah, semua sesi lain dengan akun ini akan keluar.</p>
                </div>
                <x-ui.input label="Kata sandi lama" name="password_lama" type="password" wire:model="password_lama" required autocomplete="current-password" :error="$errors->first('password_lama')" />
                <x-ui.input label="Kata sandi baru" name="password" type="password" wire:model="password" required autocomplete="new-password"
                    helper="Minimal 8 karakter, huruf besar dan kecil, serta angka. Berbeda dari kata sandi lama." :error="$errors->first('password')" />
                <x-ui.input label="Konfirmasi kata sandi baru" name="password_confirmation" type="password" wire:model="password_confirmation" required autocomplete="new-password" />
                <div class="{{ $footer }}">
                    <x-ui.button icon="key" wire:loading.attr="disabled" wire:target="kirimSandi" class="max-sm:w-full sm:ml-auto">Ubah kata sandi</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>

    @if ($irban)
        <x-ui.modal name="konfirmasi-sandi" title="Ubah kata sandi akun Irban?" wire:model="sandiOpen">
            <p>Semua anggota yang sedang masuk dengan akun ini akan keluar.</p>
            <x-slot:footer>
                @if ($sandiOpen)
                    <x-app.diinput-oleh :pegawai="$penginput" :value="$diinput_oleh" :ganti="$ganti" :error="$errors->first('diinput_oleh')" drop-up
                        wire:model.live="diinput_oleh" class="w-full text-left" />
                @endif
                <x-ui.button type="button" variant="ghost" x-on:click="$dispatch('close-modal', { name: 'konfirmasi-sandi' })">Batal</x-ui.button>
                <x-ui.button type="button" icon="key" wire:click="kirimSandi" wire:loading.attr="disabled" wire:target="kirimSandi" class="max-sm:w-full">
                    {{ $nama ? 'Ubah sebagai '.$nama : 'Ubah kata sandi' }}
                </x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
