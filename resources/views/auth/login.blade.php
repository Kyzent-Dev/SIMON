{{-- design/auth/login.md §1, §4 --}}
@php $lockout = (int) session('lockout_seconds', 0); @endphp

<x-layouts::auth title="Masuk">
    <div x-data="{
            busy: false,
            show: false,
            s: {{ $lockout }},
            said: '',
            message() { return `Terlalu banyak percobaan masuk. Coba lagi dalam ${this.s} detik.` },
        }"
        x-init="if (s > 0) {
            said = message();
            const timer = setInterval(() => {
                s = Math.max(0, s - 1);
                if (s === 0) { clearInterval(timer); said = 'Silakan coba masuk kembali.' }
                else if (s % 15 === 0) { said = message() }
            }, 1000);
        }">
        <h1 class="font-display text-2xl/8 font-bold text-neutral-900 lg:text-[1.75rem]/9">Masuk</h1>

        <div>
            @if (session('expired'))
                <x-ui.alert variant="warning" role="status" class="mt-6">{{ session('expired') }}</x-ui.alert>
            @endif
            @if (session('status'))
                <x-ui.alert variant="success" role="status" class="mt-6">{{ session('status') }}</x-ui.alert>
            @endif

            @if ($lockout > 0)
                {{-- Visible countdown ticks every second but is hidden from AT; the sr-only live region speaks every 15s. --}}
                <x-ui.alert variant="warning" icon="clock" class="mt-6" tabindex="-1" data-error-focus x-init="$el.focus()">
                    <p aria-hidden="true">Terlalu banyak percobaan masuk. Coba lagi dalam <strong class="tabular-nums" x-text="s">{{ $lockout }}</strong> detik.</p>
                    <p class="sr-only" aria-live="polite" x-text="said">{{ __('auth.throttle', ['seconds' => $lockout]) }}</p>
                </x-ui.alert>
            @elseif ($errors->has('login'))
                <x-ui.alert variant="danger" role="alert" class="mt-6" tabindex="-1" data-error-focus x-init="$el.focus()">{{ $errors->first('login') }}</x-ui.alert>
            @else
                @include('auth.partials.error-summary', ['fields' => ['username' => 'Nama pengguna', 'password' => 'Kata sandi']])
            @endif
        </div>

        <form method="POST" action="{{ url('/masuk') }}" class="mt-6 space-y-5" x-on:submit="busy = true" novalidate>
            @csrf

            <x-ui.input label="Nama pengguna" name="username" autocomplete="username" autocapitalize="none" spellcheck="false" required
                :error="$errors->first('username')"
                x-init="matchMedia('(min-width: 1024px)').matches && ! document.querySelector('[data-error-focus]') && $el.focus()" />

            <x-ui.input label="Kata sandi" name="password" type="password" autocomplete="current-password" required
                :error="$errors->first('password')" x-bind:type="show ? 'text' : 'password'">
                <x-slot:suffix>
                    <button type="button" x-on:click="show = ! show" aria-pressed="false" x-bind:aria-pressed="show.toString()"
                        aria-controls="password" aria-label="Tampilkan kata sandi" title="Tampilkan kata sandi"
                        class="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-md text-neutral-600 hover:text-mint-700">
                        <span x-show="! show">{{ svg('heroicon-o-eye', 'size-5') }}</span>
                        <span x-show="show" x-cloak>{{ svg('heroicon-o-eye-slash', 'size-5') }}</span>
                    </button>
                </x-slot:suffix>
            </x-ui.input>

            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                <x-ui.checkbox label="Ingat saya" name="remember" />
                <a href="{{ route('password.request') }}" class="inline-flex min-h-11 items-center rounded-sm text-sm font-medium text-mint-700 underline-offset-2 hover:underline lg:min-h-6">Lupa kata sandi?</a>
            </div>

            <x-ui.button size="lg" class="w-full" busy="busy" disabled-when="busy || s > 0">Masuk</x-ui.button>
        </form>
    </div>
</x-layouts::auth>
