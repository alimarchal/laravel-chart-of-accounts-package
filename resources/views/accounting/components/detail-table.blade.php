@props([
    'headers' => [],
    'title' => null,
    'customWidth' => null,
])

<style>
    .detail-table-scroll::-webkit-scrollbar {
        height: 10px;
    }

    .detail-table-scroll::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 10px;
    }

    .detail-table-scroll::-webkit-scrollbar-thumb {
        background: linear-gradient(to right, #10b981, #059669);
        border-radius: 10px;
        transition: background 0.3s ease;
    }

    .detail-table-scroll::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(to right, #059669, #047857);
    }

    .detail-table-scroll {
        scrollbar-width: thin;
        scrollbar-color: #10b981 #f1f5f9;
    }
</style>

<div>
    @if($title)
        <h3 class="text-lg font-semibold text-gray-900 mb-2 text-center">{{ $title }}</h3>
    @endif

    <div class="detail-table-scroll relative overflow-x-auto border border-gray-300 rounded-lg">
        <table class="{{ $customWidth ?? 'min-w-max w-full' }} table-auto text-sm border-collapse">
            <thead>
                <tr class="bg-green-800 text-white text-sm">
                    @foreach($headers as $header)
                        <th class="py-1 px-1 border-b border-green-700 {{ $header['align'] ?? 'text-left' }}">
                            {{ $header['label'] }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="text-black text-sm leading-normal font-extrabold">
                {{ $slot }}
            </tbody>
            @if(isset($footer))
                <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                    {{ $footer }}
                </tfoot>
            @endif
        </table>
    </div>
</div>
