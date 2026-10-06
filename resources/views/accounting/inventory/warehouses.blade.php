<x-accounting::app-layout title="Warehouses">
    <x-slot name="header">
        <div class="flex items-center justify-between"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Warehouses</h2><a href="{{ route('accounting.inventory.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Inventory</a></div>
    </x-slot>
    <div class="py-6"><div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session("success"))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session("success") }}</div>@endif
        @if(session("error"))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session("error") }}</div>@endif
        <x-accounting::validation-errors />
        <div class="bg-white shadow rounded-lg p-5"><table class="w-full text-sm"><tbody>
            @forelse ($warehouses as $warehouse)
                <tr class="border-t first:border-0"><td class="py-2 font-medium">{{ $warehouse['code'] }}</td><td>{{ $warehouse['name'] }}</td><td class="text-gray-500">{{ $warehouse['address'] }}</td><td>{{ $warehouse['is_active'] ? '' : 'inactive' }}</td>
                    <td class="text-right">@can('inventory.manage')
                        <form method="POST" action="{{ route('accounting.inventory.warehouses.update', $warehouse['id']) }}" class="inline">@csrf @method('PUT')<input type="hidden" name="code" value="{{ $warehouse['code'] }}"><input type="hidden" name="name" value="{{ $warehouse['name'] }}"><input type="hidden" name="address" value="{{ $warehouse['address'] }}"><input type="hidden" name="is_active" value="{{ $warehouse['is_active'] ? 0 : 1 }}"><button class="text-xs text-indigo-700 hover:underline">{{ $warehouse['is_active'] ? 'Deactivate' : 'Activate' }}</button></form>
                        <form method="POST" action="{{ route('accounting.inventory.warehouses.destroy', $warehouse['id']) }}" class="inline" onsubmit="return confirm('Delete this warehouse?')">@csrf @method('DELETE')<button class="ml-2 text-xs text-red-700 hover:underline">Delete</button></form>
                    @endcan</td></tr>
            @empty
                <tr><td class="py-4 text-center text-gray-500">No warehouses yet.</td></tr>
            @endforelse
        </tbody></table></div>
        @can('inventory.manage')
            <form method="POST" action="{{ route('accounting.inventory.warehouses.store') }}" class="bg-white shadow rounded-lg p-5 grid gap-4 md:grid-cols-4">
                @csrf <h3 class="font-semibold text-gray-800 md:col-span-4">Add a warehouse</h3>
                <label class="text-sm"><span class="text-gray-700">Code</span><input name="code" value="{{ old('code') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                <label class="text-sm"><span class="text-gray-700">Name</span><input name="name" value="{{ old('name') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                <label class="text-sm"><span class="text-gray-700">Address</span><input name="address" value="{{ old('address') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                <div class="pt-6"><button class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Add</button></div>
            </form>
        @endcan
    </div></div>
</x-accounting::app-layout>
