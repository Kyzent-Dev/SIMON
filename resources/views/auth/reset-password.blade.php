{{-- Reset via emailed link; same shell as design/auth/login.md §2. --}}
<x-layouts::auth title="Atur Ulang Kata Sandi">
    <div x-data="{ busy: false }">
        <h1 class="font-display text-2xl/8 font-bold text-neutral-900 lg:text-[1.75rem]/9">Atur Ulang Kata Sandi</h1>

        @include('auth.partials.error-summary', ['fields' => ['email' => 'Email', 'password' => 'Kata sandi baru']])

        <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-5" x-on:submit="busy = true" novalidate>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <x-ui.input label="Email" name="email" type="email" autocomplete="email" required :value="$email" :error="$errors->first('email')" />
            <x-ui.input label="Kata sandi baru" name="password" type="password" autocomplete="new-password" required
                helper="Minimal 8 karakter, huruf besar dan kecil, serta angka." :error="$errors->first('password')"
                x-init="matchMedia('(min-width: 1024px)').matches && ! document.querySelector('[data-error-focus]') && $el.focus()" />
            <x-ui.input label="Konfirmasi" name="password_confirmation" type="password" autocomplete="new-password" required />
            <x-ui.button size="lg" class="w-full" busy="busy">Simpan kata sandi</x-ui.button>
        </form>

        <p class="mt-6 text-center">
            <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center rounded-sm text-sm font-medium text-mint-700 underline-offset-2 hover:underline lg:min-h-6">Kembali ke halaman masuk</a>
        </p>
    </div>
</x-layouts::auth>
