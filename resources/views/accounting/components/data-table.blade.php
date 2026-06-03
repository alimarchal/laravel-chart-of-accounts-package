@props([
    'items' => [],
    'headers' => [],
    'emptyMessage' => 'No records found.',
    'emptyRoute' => null,
    'emptyLinkText' => 'Add a new record',
])

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
    <x-accounting::status-message />
    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">

        @if ($items->count() > 0)
            <div class="relative overflow-x-auto rounded-lg">
                <table class="min-w-max w-full table-auto text-sm">
                    <thead>
                        <tr class="bg-green-800 text-white uppercase text-sm">
                            @foreach($headers as $header)
                                <th class="py-2 px-2 {{ $header['align'] ?? 'text-left' }}">
                                    {!! $header['label'] !!}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="text-black text-md leading-normal font-extrabold">
                        {{ $slot }}
                    </tbody>
                    @if(isset($footer))
                        <tfoot class="bg-gray-100 font-bold uppercase text-sm">
                            {{ $footer }}
                        </tfoot>
                    @endif
                </table>
            </div>
            @if (method_exists($items, 'hasPages') && $items->hasPages())
                <div class="px-2 py-2">
                    {{ $items->links() }}
                </div>
            @endif
        @else
            <p class="text-gray-700 text-center py-4">
                {{ $emptyMessage }}
                @if($emptyRoute)
                    <a href="{{ $emptyRoute }}" class="text-blue-600 hover:underline">
                        {{ $emptyLinkText }}
                    </a>.
                @endif
            </p>
        @endif
    </div>
</div>