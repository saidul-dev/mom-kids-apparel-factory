<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Sign In') }} — {{ config('app.name', 'Mom & Kids Apparel') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased text-slate-900 bg-gradient-to-br from-pink-50 via-white to-sky-50">
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-12">
        <a href="/" class="mb-6 flex flex-col items-center text-center">
            <x-application-logo class="h-12 w-12" />
            <span class="mt-3 text-xl font-bold text-brand-900">{{ config('app.name', 'Mom & Kids Apparel') }}</span>
            <span class="mt-0.5 text-sm text-slate-500">{{ __('Clothing for mothers & kids — factory to outlet') }}</span>
        </a>

        <div class="w-full max-w-sm rounded-2xl bg-white p-8 shadow-sm ring-1 ring-pink-100">
            <h1 class="text-xl font-bold text-slate-800">{{ __('Sign in') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Enter your email and password to continue.') }}</p>

            @if (session('status'))
            <div class="mt-4 rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700 ring-1 ring-emerald-200">
                {{ session('status') }}
            </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
                @csrf

                <div>
                    <label for="email" class="mb-1 block text-sm font-medium text-slate-700">{{ __('Email') }}</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                        autocomplete="username"
                        class="w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    @error('email') <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm font-medium text-slate-700">{{ __('Password') }}</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password"
                        class="w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    @error('password') <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center justify-between">
                    <label for="remember_me" class="inline-flex items-center gap-2 text-sm text-slate-600">
                        <input id="remember_me" type="checkbox" name="remember"
                            class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        {{ __('Remember me') }}
                    </label>

                    @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-sm font-medium text-brand-600 hover:text-brand-800">
                        {{ __('Forgot password?') }}
                    </a>
                    @endif
                </div>

                <button type="submit"
                    class="w-full rounded-lg bg-brand-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-accent-500 focus:ring-offset-2">
                    {{ __('Sign in') }}
                </button>
            </form>

            @if (app()->environment('local'))
            @php
            $demoAccounts = [
                ['role' => 'Super Admin', 'email' => 'admin@businesserp.test'],
                ['role' => 'Admin', 'email' => 'owner@businesserp.test'],
                ['role' => 'Manager', 'email' => 'manager@businesserp.test'],
                ['role' => 'HR', 'email' => 'hr@businesserp.test'],
                ['role' => 'Accountant', 'email' => 'accountant@businesserp.test'],
            ];
            @endphp
            <div class="mt-6 border-t border-slate-100 pt-4">
                <p class="text-xs text-slate-400">{{ __('Quick fill (dev only)') }}</p>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @foreach ($demoAccounts as $account)
                    <button type="button"
                        onclick="document.getElementById('email').value = '{{ $account['email'] }}'; document.getElementById('password').value = 'password';"
                        class="rounded-full border border-slate-200 px-2.5 py-1 text-xs text-slate-600 hover:bg-slate-50">
                        {{ $account['role'] }}
                    </button>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        <p class="mt-6 text-xs text-slate-400">
            &copy; {{ date('Y') }} {{ config('app.name', 'Mom & Kids Apparel') }} &middot; {{ __('Powered by') }}
            <a href="https://vexasoft.net" target="_blank" rel="noopener" class="font-semibold text-slate-500 hover:text-brand-700">Vexasoft</a>
        </p>
    </div>
</body>

</html>
