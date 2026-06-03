@foreach ($nodes as $node)
<div class="flex items-center py-1 hover:bg-gray-50 rounded" style="padding-left: {{ $depth * 24 + 8 }}px">
    @if ($depth > 0)<svg class="w-3 h-3 mr-1 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17l9.2-9.2M17 17V7H7"/></svg>@endif
    <span class="font-mono text-xs text-gray-500 mr-2">{{ $node->account_code }}</span>
    <a href="{{ route('accounting.chart-of-accounts.show', $node) }}" class="text-sm font-medium text-blue-700 hover:underline mr-2">{{ $node->account_name }}</a>
    <span class="text-xs text-gray-400">{{ optional($node->accountType)->name }}</span>
    @if ($node->is_group)<span class="ml-2 px-1 py-0.5 bg-blue-100 text-blue-700 rounded text-xs">Group</span>@endif
    @if (!$node->is_active)<span class="ml-2 px-1 py-0.5 bg-red-100 text-red-600 rounded text-xs">Inactive</span>@endif
</div>
@if ($node->children && $node->children->isNotEmpty())
@include('accounting::chart-of-accounts.partials.tree-node', ['nodes' => $node->children, 'depth' => $depth + 1])
@endif
@endforeach
