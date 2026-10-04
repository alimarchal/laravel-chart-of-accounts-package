<?php

namespace Alimarchal\LaravelChartOfAccounts\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation rules for company-owned tables. Plain exists/unique rules query the table directly
 * (no Eloquent scope), so they would accept another company's ids and reject codes another company
 * already uses.
 */
final class CompanyRule
{
    public static function exists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where('company_id', CurrentCompany::currentId());
    }

    public static function unique(string $table, string $column): Unique
    {
        return Rule::unique($table, $column)->where('company_id', CurrentCompany::currentId());
    }

    /**
     * A journal line of the current company (lines carry the company of their entry).
     */
    public static function existsLine(): Exists
    {
        return Rule::exists('accounting_journal_entry_lines', 'id')->where(fn ($query) => $query->whereIn(
            'journal_entry_id',
            DB::table('accounting_journal_entries')->where('company_id', CurrentCompany::currentId())->select('id'),
        ));
    }
}
