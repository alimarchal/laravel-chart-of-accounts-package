<x-accounting::app-layout>
    <x-slot name="header">
        <x-accounting::page-header title="Journal Entries" subtitle="Every posting in the books: drafts, posted vouchers and reversals." :createRoute="route('accounting.journal-entries.create')" createLabel="New entry" createPermission="journal-entries.create" backRoute="accounting.dashboard" />
    </x-slot>
    <x-accounting::filter-section :action="route('accounting.journal-entries.index')">
        <div class="acc-grid">
            <div class="acc-field"><x-accounting::label for="filter_voucher_number" value="Voucher no." /><x-accounting::input id="filter_voucher_number" name="filter[voucher_number]" type="text" placeholder="JV-2026-00012" :value="request('filter.voucher_number')" /></div>
            <div class="acc-field"><x-accounting::label for="filter_source_document_number" value="Document no." /><x-accounting::input id="filter_source_document_number" name="filter[source_document_number]" type="text" placeholder="BILL-778" :value="request('filter.source_document_number')" /></div>
            <div class="acc-field"><x-accounting::label for="filter_reference" value="Reference" /><x-accounting::input id="filter_reference" name="filter[reference]" type="text" :value="request('filter.reference')" /></div>
            <div class="acc-field"><x-accounting::label for="filter_status" value="Status" />
                <select id="filter_status" name="filter[status]" class="acc-select">
                    <option value="">All statuses</option>
                    <option value="draft" @selected(request('filter.status') === 'draft')>Draft</option>
                    <option value="posted" @selected(request('filter.status') === 'posted')>Posted</option>
                    <option value="void" @selected(request('filter.status') === 'void')>Void</option>
                </select>
            </div>
            @if(!empty($branches))
            <div class="acc-field"><x-accounting::label for="filter_branch_id" value="Branch" />
                <select id="filter_branch_id" name="filter[branch_id]" class="acc-select">
                    <option value="">All branches</option>
                    @foreach($branches as $branch)<option value="{{ $branch['id'] }}" @selected((string) request('filter.branch_id') === (string) $branch['id'])>{{ $branch['code'] }} - {{ $branch['name'] }}</option>@endforeach
                </select>
            </div>
            @endif
            <div class="acc-field"><x-accounting::label for="filter_approval_status" value="Approval" />
                <select id="filter_approval_status" name="filter[approval_status]" class="acc-select">
                    <option value="">Any approval state</option>
                    <option value="pending" @selected(request('filter.approval_status') === 'pending')>Awaiting approval</option>
                    <option value="approved" @selected(request('filter.approval_status') === 'approved')>Approved</option>
                    <option value="rejected" @selected(request('filter.approval_status') === 'rejected')>Rejected</option>
                </select>
            </div>
            <div class="acc-field"><x-accounting::label for="filter_date_from" value="Date from" /><x-accounting::input id="filter_date_from" name="filter[entry_date_from]" type="date" :value="request('filter.entry_date_from')" /></div>
            <div class="acc-field"><x-accounting::label for="filter_date_to" value="Date to" /><x-accounting::input id="filter_date_to" name="filter[entry_date_to]" type="date" :value="request('filter.entry_date_to')" /></div>
        </div>
    </x-accounting::filter-section>
    @php($showBranch = \Alimarchal\LaravelChartOfAccounts\Support\CurrentBranch::enabled())
    <x-accounting::data-table :items="$journalEntries"
        :headers="array_values(array_filter([['label'=>'Voucher'],['label'=>'Date'],['label'=>'Reference / description'],$showBranch ? ['label'=>'Branch'] : null,['label'=>'Amount','align'=>'text-right'],['label'=>'Status'],['label'=>'','align'=>'text-right']]))"
        emptyMessage="No journal entries found." :emptyRoute="route('accounting.journal-entries.create')" emptyLinkText="Create the first one">
        @foreach ($journalEntries as $je)
        <tr>
            <td class="whitespace-nowrap">
                <a href="{{ route('accounting.journal-entries.show', $je) }}" style="text-decoration:none">
                @if ($je->voucher_number)<span class="acc-mono" style="font-weight:650;color:var(--acc-accent)">{{ $je->voucher_number }}</span>@else<span class="acc-mono acc-muted">{{ $je->voucherType?->code ?? 'JV' }} draft #{{ $je->id }}</span>@endif
                </a>
            </td>
            <td class="whitespace-nowrap">{{ $je->entry_date->format('d M Y') }}</td>
            <td style="max-width:26rem">
                <div style="font-weight:550;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $je->reference ?: Str::limit($je->description, 60) }}</div>
                <div class="acc-muted" style="font-size:.76rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                    @if ($je->reference && $je->description){{ Str::limit($je->description, 70) }}@endif
                    @if ($je->source_document_number)<span class="acc-mono">{{ $je->source_document_number }}</span>@endif
                    · {{ $je->lines_count }} {{ \Illuminate\Support\Str::plural('line', $je->lines_count) }}
                </div>
            </td>
            @if($showBranch)
            <td>@if($je->branch)<span class="acc-badge acc-badge--accent" title="{{ $je->branch->name }}">{{ $je->branch->code }}</span>@else<span class="acc-muted">—</span>@endif</td>
            @endif
            <td class="num" style="text-align:right;white-space:nowrap">{{ number_format((float) $je->total_debit, 2) }} <span class="acc-muted" style="font-size:.74rem">{{ $je->currency?->code }}</span></td>
            <td class="whitespace-nowrap">
                <span @class(['acc-badge', 'acc-badge--warn' => $je->status === 'draft', 'acc-badge--ok' => $je->status === 'posted', 'acc-badge--bad' => $je->status === 'void'])>{{ ucfirst($je->status) }}</span>
                @if ($je->approval_status)
                    <span @class(['acc-badge', 'acc-badge--info' => $je->approval_status === 'pending', 'acc-badge--ok' => $je->approval_status === 'approved', 'acc-badge--bad' => $je->approval_status === 'rejected'])>{{ $je->approval_status === 'pending' ? 'Awaiting approval' : ucfirst($je->approval_status) }}</span>
                @endif
            </td>
            <td style="text-align:right"><a href="{{ route('accounting.journal-entries.show', $je) }}" class="acc-btn acc-btn--sm">Open</a></td>
        </tr>
        @endforeach
    </x-accounting::data-table>
</x-accounting::app-layout>
