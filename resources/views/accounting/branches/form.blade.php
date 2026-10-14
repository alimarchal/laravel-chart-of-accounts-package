<x-accounting::app-layout>
    <x-slot name="header"><x-accounting::page-header :title="($branch ? 'Edit Branch' : 'Create Branch')" :showSearch="false" :showRefresh="false" :showPrint="false" :backRoute="route('accounting.branches.index')"></x-accounting::page-header></x-slot>
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <x-accounting::status-message class="mb-4 mt-4 shadow-md" />
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            <x-accounting::validation-errors class="mb-4" />
            <form method="POST" action="{{ $branch ? route('accounting.branches.update', $branch) : route('accounting.branches.store') }}">
                @csrf
                @if($branch) @method('PUT') @endif
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div><x-accounting::label for="code" value="Code" /><x-accounting::input id="code" name="code" type="text" class="mt-1 block w-full" :value="old('code', $branch?->code)" required /></div>
                    <div><x-accounting::label for="name" value="Name" /><x-accounting::input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $branch?->name)" required /></div>
                    <div><x-accounting::label for="city" value="City" /><x-accounting::input id="city" name="city" type="text" class="mt-1 block w-full" :value="old('city', $branch?->city)" /></div>
                    <div><x-accounting::label for="phone" value="Phone" /><x-accounting::input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone', $branch?->phone)" /></div>
                    <div class="lg:col-span-2"><x-accounting::label for="address" value="Address" /><x-accounting::input id="address" name="address" type="text" class="mt-1 block w-full" :value="old('address', $branch?->address)" /></div>
                    <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="is_head_office" value="1" @checked(old('is_head_office', $branch?->is_head_office)) class="rounded border-gray-300"> Head office</label>
                    <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $branch?->is_active ?? true)) class="rounded border-gray-300"> Active</label>
                </div>
                <div class="flex justify-end mt-6"><x-accounting::button>{{ $branch ? 'Save' : 'Create' }}</x-accounting::button></div>
            </form>
        </div>

        @if($branch)
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            <h3 class="font-semibold text-gray-800 mb-1">People who work in this branch</h3>
            <p class="text-sm text-gray-500 mb-3">A person with no branch works in all branches. Once a person is added to one or more branches, they work in those only.</p>
            @forelse($members as $member)
                <div class="flex items-center justify-between border-b border-gray-100 py-2 text-sm">
                    <span>{{ $member->name }} <span class="text-gray-500">{{ $member->email }}</span></span>
                    <form method="POST" action="{{ route('accounting.branches.users.revoke', [$branch, $member->id]) }}" onsubmit="return confirm('Remove access?')">@csrf @method('DELETE')<button type="submit" class="text-red-600 hover:underline">Remove</button></form>
                </div>
            @empty
                <p class="text-sm text-gray-500">Nobody is limited to this branch yet.</p>
            @endforelse
            @can('branches.update')
            <form method="POST" action="{{ route('accounting.branches.users.grant', $branch) }}" class="flex flex-wrap items-end gap-3 mt-4">
                @csrf
                <div class="min-w-64">
                    <x-accounting::label for="user_id" value="Add a person" />
                    <select id="user_id" name="user_id" class="select2 border-gray-300 rounded-md shadow-sm block mt-1 w-full" required>
                        <option value=""></option>
                        @foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }} — {{ $user->email }}</option>@endforeach
                    </select>
                </div>
                <x-accounting::button>Give access</x-accounting::button>
            </form>
            @endcan
        </div>
        @endif
    </div></div>
</x-accounting::app-layout>
