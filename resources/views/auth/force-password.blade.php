{{-- design/auth/login.md §3: full-page card, no navigation (only "Keluar"). --}}
@php
    $differentFailed = $errors->first('password') === __('passwords.different');
@endphp

<x-layouts::auth title="Perbarui Kata Sandi">
    <div x-data="{
            busy: false,
            pw: '',
            get checks() {
                return [
                    this.pw.length >= 8,
                    /[a-z]/.test(this.pw) && /[A-Z]/.test(this.pw),
                    /[0-9]/.test(this.pw),
                ];
            },
            get score() {
                if (! this.pw) return 0;
                return Math.max(1, this.checks.filter(Boolean).length + (this.pw.length >= 12 || /[^A-Za-z0-9]/.test(this.pw) ? 1 : 0));
            },
            get strength() { return ['', 'Lemah', 'Sedang', 'Kuat', 'Sangat kuat'][this.score] },
        }">
        <h1 class="font-display text-2xl/8 font-bold text-neutral-900 lg:text-[1.75rem]/9">Perbarui Kata Sandi</h1>

        <x-ui.alert variant="info" class="mt-6">
            {{ __('Administrator mewajibkan pembaruan kata sandi untuk tahun :tahun.', ['tahun' => now('Asia/Jakarta')->year]) }}
        </x-ui.alert>

        @include('auth.partials.error-summary', ['fields' => ['password' => 'Kata sandi baru']])

        <form method="POST" action="{{ route('password.force.update') }}" class="mt-6 space-y-5" x-on:submit="busy = true" novalidate>
            @csrf
            @method('PUT')

            <x-ui.input label="Kata sandi baru" name="password" type="password" autocomplete="new-password" required
                aria-describedby="syarat-kata-sandi kekuatan-kata-sandi" x-model="pw" :error="$errors->first('password')"
                x-init="matchMedia('(min-width: 1024px)').matches && ! document.querySelector('[data-error-focus]') && $el.focus()" />

            {{-- Strength: 4 segments + text label (never color alone). --}}
            <div>
                <div class="grid grid-cols-4 gap-1" aria-hidden="true">
                    @foreach (range(1, 4) as $n)
                        <span class="h-1.5 rounded-full" x-bind:class="score >= {{ $n }} ? 'bg-mint-700' : 'bg-neutral-200'"></span>
                    @endforeach
                </div>
                <p id="kekuatan-kata-sandi" class="mt-1.5 min-h-4 text-xs text-neutral-600" aria-live="polite">
                    <span x-show="score > 0" x-cloak>Kekuatan: <span class="font-semibold text-neutral-900" x-text="strength"></span></span>
                </p>
            </div>

            <ul id="syarat-kata-sandi" class="space-y-1.5 text-sm text-neutral-900">
                @foreach (['Minimal 8 karakter', 'Huruf besar dan kecil', 'Angka'] as $i => $rule)
                    <li class="flex items-center gap-2">
                        <span x-show="checks[{{ $i }}]" x-cloak class="inline-flex text-success-700">{{ svg('heroicon-m-check-circle', 'size-5') }}</span>
                        <span x-show="! checks[{{ $i }}]" class="inline-flex text-neutral-500">{{ svg('heroicon-m-minus-circle', 'size-5') }}</span>
                        <span>{{ $rule }}<span class="sr-only" x-text="checks[{{ $i }}] ? ' (terpenuhi)' : ' (belum terpenuhi)'"></span></span>
                    </li>
                @endforeach
                {{-- Only the server knows the old password: neutral until a failed save says otherwise. --}}
                <li class="flex items-center gap-2">
                    @if ($differentFailed)
                        <span class="inline-flex text-danger-700">{{ svg('heroicon-m-x-circle', 'size-5') }}</span>
                        <span>Berbeda dari kata sandi lama<span class="sr-only"> (belum terpenuhi)</span></span>
                    @else
                        <span class="inline-flex text-neutral-500">{{ svg('heroicon-m-minus-circle', 'size-5') }}</span>
                        <span>Berbeda dari kata sandi lama<span class="sr-only"> (diperiksa saat disimpan)</span></span>
                    @endif
                </li>
            </ul>

            <x-ui.input label="Konfirmasi" name="password_confirmation" type="password" autocomplete="new-password" required />

            <x-ui.button size="lg" class="w-full" busy="busy">Simpan &amp; Lanjutkan</x-ui.button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
            @csrf
            <x-ui.button variant="ghost" icon="arrow-right-start-on-rectangle">Keluar</x-ui.button>
        </form>
    </div>
</x-layouts::auth>
