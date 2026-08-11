@extends('layouts.guest')

@section('title', 'Login')

@section('content')
<div class="w-full max-w-md">
    {{-- Login Card --}}
    <x-card class="!p-8 md:!p-10">
        {{-- Header --}}
        <div class="text-center mb-8">
            <x-brand-logo
                class="mb-3 justify-center"
                logo-class="h-20 w-20"
                text-class="text-2xl font-extrabold text-[var(--color-gray-900)]"
            />
            <p class="text-sm text-[var(--color-gray-400)]">Sistem Pendukung Keputusan Pertanian</p>
        </div>

        {{-- Login Form --}}
        <form method="POST" action="{{ route('login.submit') }}" id="loginForm">
            @csrf

            {{-- Email --}}
            <x-form.input
                name="email"
                type="email"
                label="Email"
                placeholder="Masukkan email Anda"
                autocomplete="email"
                :required="true"
                :autofocus="true"
                :icon="'<svg class=&quot;w-5 h-5&quot; fill=&quot;none&quot; viewBox=&quot;0 0 24 24&quot; stroke=&quot;currentColor&quot; stroke-width=&quot;2&quot;><path stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; d=&quot;M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z&quot;/></svg>'"
            />

            {{-- Password --}}
            <x-form.input
                name="password"
                type="password"
                label="Password"
                placeholder="Masukkan password Anda"
                autocomplete="current-password"
                :required="true"
                :icon="'<svg class=&quot;w-5 h-5&quot; fill=&quot;none&quot; viewBox=&quot;0 0 24 24&quot; stroke=&quot;currentColor&quot; stroke-width=&quot;2&quot;><path stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; d=&quot;M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z&quot;/></svg>'"
            >
                <button type="button"
                        class="absolute right-3.5 top-1/2 -translate-y-1/2 text-[var(--color-gray-400)]
                               hover:text-[var(--color-gray-600)] cursor-pointer bg-transparent border-none p-0"
                        onclick="togglePassword()" aria-label="Toggle password visibility">
                    <svg id="eyeIcon" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </button>
            </x-form.input>

            {{-- Submit Button --}}
            <div class="mt-6">
                <x-button variant="primary" type="submit" :block="true" id="loginBtn">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                    </svg>
                    Masuk
                </x-button>
            </div>
        </form>

        <div class="mt-6 rounded-lg border border-slate-100 bg-slate-50 px-4 py-3 text-center text-sm text-slate-600">
            Belum punya akun?
            <a href="{{ route('register') }}" class="font-bold text-emerald-700 hover:text-emerald-800">Daftar sebagai owner atau supplier</a>
        </div>
    </x-card>

    {{-- Footer --}}
    <p class="text-center text-[var(--color-gray-400)] text-[13px] mt-6">
        &copy; {{ date('Y') }} Smart Farm SPK - Sistem Pendukung Keputusan
    </p>
</div>
@endsection

@push('scripts')
<script>
    function togglePassword() {
        const input = document.getElementById('password');
        const icon = document.getElementById('eyeIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"/>';
        } else {
            input.type = 'password';
            icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
        }
    }

    document.getElementById('loginForm').addEventListener('submit', function () {
        const btn = document.getElementById('loginBtn');
        btn.classList.add('btn-loading');
        btn.disabled = true;
        btn.innerHTML = 'Memproses...';
    });
</script>
@endpush
