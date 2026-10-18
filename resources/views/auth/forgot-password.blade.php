{{-- design/auth/login.md §2 --}}
<x-layouts::auth title="Lupa Kata Sandi">
    <div x-data="{ busy: false }">
        <h1 class="font-display text-2xl/8 font-bold text-neutral-900 lg:text-[1.75rem]/9">Lupa Kata Sandi</h1>

        @if (session('status'))
            <x-ui.alert variant="success" role="status" class="mt-6" tabindex="-1" x-init="$el.focus()">{{ session('status') }}</x-ui.alert>
        @endif
        @include('auth.partials.error-summary', ['fields' => ['email' => 'Email']])

        <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-5" x-on:submit="busy = true" novalidate>
            @csrf
            <x-ui.input label="Email" name="email" type="email" autocomplete="email" required :error="$errors->first('email')"
                x-init="matchMedia('(min-width: 1024px)').matches && ! document.querySelector('[data-error-focus]') && $el.focus()" />
            <x-ui.button size="lg" class="w-full" busy="busy">Kirim tautan reset</x-ui.button>
        </form>

        <p class="mt-6 text-center">
            <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center rounded-sm text-sm font-medium text-mint-700 underline-offset-2 hover:underline lg:min-h-6">Kembali ke halaman masuk</a>
        </p>
    </div>
</x-layouts::auth>
