<x-app-layout>
    <x-slot name="title">{{ __('Employees') }}</x-slot>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-brand-900">{{ __('Employees') }}</h2>
                <p class="text-sm text-slate-500 mt-0.5">{{ __('Staff profiles, documents and login access') }}</p>
            </div>
            @can('employees.create')
            <a href="{{ route('employees.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 focus:ring-2 focus:ring-accent-500 focus:ring-offset-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                {{ __('Add Employee') }}
            </a>
            @endcan
        </div>
    </x-slot>

    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 mb-6">
        <form method="GET" action="{{ route('employees.index') }}" class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[200px]">
                <x-input-label for="q" :value="__('Search')" />
                <input type="search" id="q" name="q" value="{{ request('q') }}" placeholder="{{ __('Name or phone…') }}"
                       class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500">
            </div>
            <div class="w-48">
                <x-input-label for="department_id" :value="__('Department')" />
                <select id="department_id" name="department_id"
                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected(request('department_id') == $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-48">
                <x-input-label for="designation_id" :value="__('Designation')" />
                <select id="designation_id" name="designation_id"
                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($designations as $designation)
                        <option value="{{ $designation->id }}" @selected(request('designation_id') == $designation->id)>{{ $designation->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-44">
                <x-input-label for="employment_status" :value="__('Status')" />
                <select id="employment_status" name="employment_status"
                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500">
                    <option value="">{{ __('All') }}</option>
                    @foreach (\App\Models\Employee::EMPLOYMENT_STATUSES as $status)
                        <option value="{{ $status }}" @selected(request('employment_status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="shrink-0 rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Filter') }}</button>
        </form>
    </div>

    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full min-w-[900px] text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-5 py-3 font-semibold">{{ __('Employee') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('Department / Designation') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('Site') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('Employment') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('Login') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($employees as $employee)
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <span class="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded-full bg-slate-100 ring-1 ring-slate-200">
                                @if ($employee->photo_url)
                                    <img src="{{ $employee->photo_url }}" alt="{{ $employee->name }}" class="h-full w-full object-cover">
                                @else
                                    <svg class="h-4 w-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                                @endif
                            </span>
                            <div>
                                <span class="block font-semibold text-slate-800">{{ $employee->name }}</span>
                                <span class="block text-xs text-slate-400">{{ $employee->phone }}</span>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-slate-600">
                        <span class="block">{{ $employee->department?->name ?? '—' }}</span>
                        <span class="block text-xs text-slate-400">{{ $employee->designation?->name ?? '—' }}</span>
                    </td>
                    <td class="px-5 py-3 text-slate-600">{{ $employee->site?->name ?? '—' }}</td>
                    <td class="px-5 py-3">
                        <span class="block text-slate-600 capitalize">{{ str_replace('_', ' ', $employee->employment_type) }}</span>
                        <span class="inline-flex mt-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1
                            {{ $employee->employment_status === 'active' ? 'bg-emerald-50 text-emerald-600 ring-emerald-200' : 'bg-slate-100 text-slate-500 ring-slate-200' }}">
                            {{ ucfirst(str_replace('_', ' ', $employee->employment_status)) }}
                        </span>
                    </td>
                    <td class="px-5 py-3">
                        @if ($employee->hasActiveLogin())
                            <span class="inline-flex rounded-full bg-accent-50 px-2.5 py-0.5 text-xs font-semibold text-accent-700 ring-1 ring-accent-200">{{ __('Enabled') }}</span>
                        @elseif ($employee->user_id)
                            <span class="inline-flex rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-600 ring-1 ring-rose-200">{{ __('Disabled') }}</span>
                        @else
                            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-500 ring-1 ring-slate-200">{{ __('No login') }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3">
                        <div class="flex items-center justify-end gap-1">
                            @can('employees.edit')
                            <a href="{{ route('employees.edit', $employee) }}" title="{{ __('Edit') }}"
                               class="rounded-lg p-2 text-slate-400 hover:bg-brand-50 hover:text-brand-700">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>
                            </a>
                            @endcan
                            @can('hrm.delete')
                            <form method="POST" action="{{ route('employees.destroy', $employee) }}"
                                  onsubmit="return confirm('{{ __('Delete employee :name? This cannot be undone.', ['name' => $employee->name]) }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="{{ __('Delete') }}"
                                        class="rounded-lg p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-600">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                                </button>
                            </form>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-5 py-10 text-center text-slate-400">{{ __('No employees yet — add your first employee.') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>

        @if ($employees->hasPages())
        <div class="border-t border-slate-100 px-5 py-4">
            {{ $employees->links() }}
        </div>
        @endif
    </div>
</x-app-layout>
