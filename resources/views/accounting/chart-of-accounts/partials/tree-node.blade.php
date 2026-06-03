@php
$hasChildren = $node->childrenRecursive->isNotEmpty();
@endphp

<li x-data="{
        open: {{ $hasChildren ? 'true' : 'false' }},
        toggle() { this.open = !this.open },
        expand() { this.open = true },
        collapse() { this.open = false },
        init() {
            window.addEventListener('coa-expand-all', () => this.expand());
            window.addEventListener('coa-collapse-all', () => this.collapse());
        }
    }" class="relative">
    <div class="flex items-center gap-3 py-2 px-3 rounded-md transition hover:bg-gray-300/50">
        @if ($hasChildren)
        <button type="button" @click.stop="toggle"
            class="size-6 flex items-center justify-center rounded-md border border-gray-300 text-gray-600 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-400">
            <svg x-show="open" x-cloak xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24"
                stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z" />
            </svg>
            <svg x-show="!open" x-cloak xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none"
                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
            </svg>
        </button>
        @else
        <span class="size-6 flex items-center justify-center rounded-md bg-indigo-500/10 text-indigo-500">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
            </svg>
        </span>
        @endif

        <div class="flex flex-wrap items-center gap-2 text-sm cursor-pointer" @click="toggle">
            <span class="font-mono font-semibold text-gray-700">{{ $node->account_code }}</span>
            <span class="font-medium text-gray-900">{{ $node->account_name }}</span>

            @if ($node->accountType)
            <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">
                {{ $node->accountType->name }}
            </span>
            @endif

            <span class="text-xs px-2 py-0.5 rounded-full {{ $node->normal_balance === 'debit' ? 'bg-emerald-100 text-emerald-700' : 'bg-purple-100 text-purple-700' }}">
                {{ ucfirst($node->normal_balance) }}
            </span>

            <span class="text-xs px-2 py-0.5 rounded-full {{ $node->is_group ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-700' }}">
                {{ $node->is_group ? 'Group' : 'Posting' }}
            </span>

            <span class="text-xs px-2 py-0.5 rounded-full {{ $node->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                {{ $node->is_active ? 'Active' : 'Inactive' }}
            </span>
        </div>
    </div>

    @if ($hasChildren)
    <ul x-show="open" x-transition x-cloak
        class="space-y-1 ms-5 ps-5 border-s border-dashed border-gray-300">
        @foreach ($node->childrenRecursive as $child)
        @include('accounting::chart-of-accounts.partials.tree-node', ['node' => $child])
        @endforeach
    </ul>
    @endif
</li>
