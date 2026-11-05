<x-app-layout>
    <x-slot name="title">{{ __('Edit Employee') }}</x-slot>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-brand-900">{{ __('Edit Employee') }}</h2>
            <p class="text-sm text-slate-500 mt-0.5">{{ $employee->name }}</p>
        </div>
    </x-slot>

    @if ($employee->attachments->isNotEmpty())
    {{-- Deliberately outside the update form below — each delete posts to its
         own route/method, and HTML forms can't nest. --}}
    <div class="mb-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h3 class="font-bold text-brand-900">{{ __('Documents') }}</h3>
        <div class="mt-4 divide-y divide-slate-100 rounded-lg ring-1 ring-slate-200">
            @foreach ($employee->attachments as $attachment)
            <div class="flex items-center justify-between px-4 py-2.5 text-sm">
                <a href="{{ $attachment->url }}" target="_blank" class="flex items-center gap-2 text-brand-700 hover:underline">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                    <span>{{ $attachment->label }}</span>
                </a>
                <form method="POST" action="{{ route('employees.attachments.destroy', [$employee, $attachment]) }}"
                      onsubmit="return confirm('{{ __('Remove this document?') }}');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </form>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <form method="POST" action="{{ route('employees.update', $employee) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.employees._form')
    </form>

    {{-- Deliberately its own section, separate from the update form above —
         HTML forms can't nest, these post to different routes (toggle-login,
         salary) with their own methods, and account access/pay setup are
         distinct actions from editing the employee's profile fields. Laid
         out in the same full-width grid as the form above it (instead of a
         narrow floating box) so the page reads as one continuous layout. --}}
    <div class="mt-8 border-t border-slate-200 pt-6">
        <h3 class="text-sm font-bold uppercase tracking-wide text-slate-400">{{ __('Account & System Access') }}</h3>

        <div class="mt-3 grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-brand-700" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
                    <h4 class="font-bold text-brand-900">{{ __('Login Access') }}</h4>
                </div>
                <p class="mt-1 text-xs text-slate-400">
                    @if ($employee->hasActiveLogin())
                        {{ __('Login is enabled') }} — {{ $employee->user->email }}
                    @elseif ($employee->user_id)
                        {{ __('Login is disabled') }} — {{ $employee->user->email }}
                    @else
                        {{ __('This employee has no system login yet.') }}
                    @endif
                </p>

                <form method="POST" action="{{ route('employees.toggle-login', $employee) }}" class="mt-3">
                    @csrf
                    @method('PATCH')

                    @if (! $employee->user_id)
                    <div class="mb-3">
                        <x-input-label for="role" :value="__('Role to assign')" />
                        <select id="role" name="role" required
                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500">
                            <option value="">{{ __('Select role…') }}</option>
                            @foreach ($assignableRoles as $role)
                                <option value="{{ $role->name }}" @selected(old('role') === $role->name)>{{ $role->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('role')" />
                    </div>

                    <div class="mb-3">
                        <x-input-label for="login_email" :value="__('Login Email')" />
                        <x-text-input id="login_email" name="email" type="email" class="mt-1 block w-full"
                                      :value="old('email', $employee->email)" required />
                        <x-input-error class="mt-2" :messages="$errors->get('email')" />
                    </div>

                    <div class="mb-3">
                        <x-input-label for="login_password" :value="__('Password')" />
                        <x-text-input id="login_password" name="password" type="password" class="mt-1 block w-full" required />
                        <x-input-error class="mt-2" :messages="$errors->get('password')" />
                    </div>

                    <div class="mb-3">
                        <x-input-label for="login_password_confirmation" :value="__('Confirm Password')" />
                        <x-text-input id="login_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required />
                    </div>
                    @endif

                    <button type="submit"
                            class="w-full rounded-lg px-4 py-2 text-sm font-semibold ring-1
                            {{ $employee->hasActiveLogin() ? 'text-rose-600 ring-rose-200 hover:bg-rose-50' : 'text-emerald-700 ring-emerald-200 hover:bg-emerald-50' }}">
                        {{ $employee->hasActiveLogin() ? __('Disable Login') : ($employee->user_id ? __('Re-enable Login') : __('Enable Login')) }}
                    </button>
                </form>
            </div>

            @can('hrm.edit')
            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-brand-700" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                    <h4 class="font-bold text-brand-900">{{ __('Salary Structure') }}</h4>
                </div>
                <p class="mt-1 text-xs text-slate-400">
                    @if ($employee->salaryStructure?->mode === 'piece_rate')
                        {{ __('Piece rate — paid for the pieces done in production each month') }}
                    @elseif ($employee->salaryStructure)
                        {{ $employee->salaryStructure->mode === 'flat' ? __('Flat amount') : __('Component-based') }} —
                        {{ number_format($employee->salaryStructure->grossAmount(), 2) }} / {{ __('month') }}
                    @else
                        {{ __('Not set yet — required before this employee can be added to a payroll run.') }}
                    @endif
                </p>
                <button type="button" @click="$dispatch('open-modal', 'salary-structure')"
                        class="mt-3 block w-full rounded-lg px-4 py-2 text-center text-sm font-semibold text-brand-700 ring-1 ring-brand-200 hover:bg-brand-50">
                    {{ $employee->salaryStructure ? __('Edit Salary Structure') : __('Set Salary Structure') }}
                </button>
            </div>
            @endcan
        </div>
    </div>

    @can('hrm.edit')
    <x-modal name="salary-structure" max-width="lg" :show="$errors->any()">
        <div class="p-6" x-data="{ mode: @js(old('mode', $employee->salaryStructure->mode ?? 'flat')) }">
            <h2 class="text-lg font-bold text-brand-900">{{ __('Salary Structure') }}</h2>
            <p class="text-xs text-slate-400 mt-0.5">{{ $employee->name }}</p>

            <form method="POST" action="{{ route('employees.salary.update', $employee) }}" class="mt-4 space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <x-input-label :value="__('Mode')" />
                    <div class="mt-1 flex flex-wrap gap-x-4 gap-y-2">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" name="mode" value="flat" x-model="mode" class="border-slate-300 text-accent-600 focus:ring-accent-500">
                            {{ __('Flat monthly amount') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" name="mode" value="components" x-model="mode" class="border-slate-300 text-accent-600 focus:ring-accent-500">
                            {{ __('Basic + House Rent + Medical + Conveyance') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" name="mode" value="piece_rate" x-model="mode" class="border-slate-300 text-accent-600 focus:ring-accent-500">
                            {{ __('Piece rate (production)') }}
                        </label>
                    </div>
                    <x-input-error class="mt-2" :messages="$errors->get('mode')" />
                </div>

                <div x-show="mode === 'flat'">
                    <x-input-label for="flat_amount" :value="__('Flat Amount')" />
                    <x-text-input id="flat_amount" name="flat_amount" type="number" step="0.01" min="0" class="mt-1 block w-full"
                                  :value="old('flat_amount', $employee->salaryStructure->flat_amount ?? '0')" />
                    <x-input-error class="mt-2" :messages="$errors->get('flat_amount')" />
                </div>

                <p x-show="mode === 'piece_rate'" x-cloak class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600 ring-1 ring-slate-200">
                    {{ __('No fixed salary. The month\'s pay is the pieces this worker is given on production entries × the item\'s rate (HRM → Piece Rates).') }}
                </p>

                <div x-show="mode === 'components'" x-cloak class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="basic" :value="__('Basic')" />
                        <x-text-input id="basic" name="basic" type="number" step="0.01" min="0" class="mt-1 block w-full"
                                      :value="old('basic', $employee->salaryStructure->basic ?? '0')" />
                        <x-input-error class="mt-2" :messages="$errors->get('basic')" />
                    </div>
                    <div>
                        <x-input-label for="house_rent" :value="__('House Rent')" />
                        <x-text-input id="house_rent" name="house_rent" type="number" step="0.01" min="0" class="mt-1 block w-full"
                                      :value="old('house_rent', $employee->salaryStructure->house_rent ?? '0')" />
                        <x-input-error class="mt-2" :messages="$errors->get('house_rent')" />
                    </div>
                    <div>
                        <x-input-label for="medical" :value="__('Medical')" />
                        <x-text-input id="medical" name="medical" type="number" step="0.01" min="0" class="mt-1 block w-full"
                                      :value="old('medical', $employee->salaryStructure->medical ?? '0')" />
                        <x-input-error class="mt-2" :messages="$errors->get('medical')" />
                    </div>
                    <div>
                        <x-input-label for="conveyance" :value="__('Conveyance')" />
                        <x-text-input id="conveyance" name="conveyance" type="number" step="0.01" min="0" class="mt-1 block w-full"
                                      :value="old('conveyance', $employee->salaryStructure->conveyance ?? '0')" />
                        <x-input-error class="mt-2" :messages="$errors->get('conveyance')" />
                    </div>
                </div>

                <div>
                    <x-input-label for="effective_from" :value="__('Effective From')" />
                    <x-text-input id="effective_from" name="effective_from" type="date" class="mt-1 block w-full"
                                  :value="old('effective_from', $employee->salaryStructure?->effective_from?->format('Y-m-d') ?? now()->format('Y-m-d'))" required />
                    <x-input-error class="mt-2" :messages="$errors->get('effective_from')" />
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-primary-button>{{ __('Save Salary Structure') }}</x-primary-button>
                    <button type="button" x-on:click="$dispatch('close')" class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('Cancel') }}</button>
                </div>
            </form>
        </div>
    </x-modal>
    @endcan
</x-app-layout>
