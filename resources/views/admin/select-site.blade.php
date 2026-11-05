<x-guest-layout>
    <div class="mb-5 text-center">
        <h2 class="text-xl font-bold text-brand-900">{{ __('Select your Duty Site') }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ __("You're assigned to more than one site. Pick where you're on duty — every site picker will start on it, and you can change it any time from the top bar.") }}</p>
    </div>

    <div class="space-y-2">
        @foreach ($sites as $site)
        <form method="POST" action="{{ route('sites.switch') }}">
            @csrf
            <input type="hidden" name="site_id" value="{{ $site->id }}">
            <button type="submit" class="w-full flex items-center justify-between rounded-xl px-4 py-3 ring-1 ring-slate-200 hover:bg-slate-50 hover:ring-accent-500 text-left">
                <span>
                    <span class="block text-sm font-semibold text-slate-800">{{ $site->name }}</span>
                    <span class="block text-[11px] text-slate-400">{{ $site->type }} &middot; {{ $site->code }}</span>
                </span>
                <svg class="h-4 w-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
            </button>
        </form>
        @endforeach

        @if (auth()->user()->seesAllSites())
        <form method="POST" action="{{ route('sites.switch') }}">
            @csrf
            <button type="submit" class="w-full rounded-xl px-4 py-3 ring-1 ring-dashed ring-slate-300 hover:bg-slate-50 text-center text-sm font-semibold text-slate-500">
                {{ __('View All Sites') }}
            </button>
        </form>
        @endif
    </div>

    <form method="POST" action="{{ route('logout') }}" class="mt-5 text-center">
        @csrf
        <button type="submit" class="text-xs text-slate-400 hover:text-slate-600 underline">{{ __('Log out') }}</button>
    </form>
</x-guest-layout>
