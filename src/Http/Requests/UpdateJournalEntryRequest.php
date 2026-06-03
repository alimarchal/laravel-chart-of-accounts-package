<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Requests;

class UpdateJournalEntryRequest extends StoreJournalEntryRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('journal-entries.update') ?? false;
    }
}
