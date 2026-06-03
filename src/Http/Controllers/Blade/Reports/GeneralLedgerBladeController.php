<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\Reports;

use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GeneralLedgerBladeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $perPage = (int) $request->input('per_page', 100);
        if (! in_array($perPage, [10, 25, 50, 100, 250])) {
            $perPage = 100;
        }

        $periodId = $request->input('accounting_period_id');
        $entryDateFrom = $request->input('filter.entry_date_from');
        $entryDateTo = $request->input('filter.entry_date_to');

        if ($periodId) {
            $period = AccountingPeriod::find($periodId);
            if ($period) {
                $entryDateFrom = $period->start_date;
                $entryDateTo = $period->end_date;
            }
        }

        $query = DB::table('accounting_journal_entry_lines as jel')
            ->join('accounting_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('accounting_chart_of_accounts as coa', 'coa.id', '=', 'jel.chart_of_account_id')
            ->leftJoin('accounting_cost_centers as cc', 'cc.id', '=', 'jel.cost_center_id')
            ->select([
                'je.id as journal_entry_id',
                'je.entry_date',
                'je.reference',
                'je.description as journal_description',
                'je.status',
                'jel.id',
                'jel.line_no',
                'jel.debit',
                'jel.credit',
                'jel.description as line_description',
                'coa.account_code',
                'coa.account_name',
                'cc.code as cost_center_code',
                'cc.name as cost_center_name',
            ]);

        // Apply filters
        if ($request->filled('filter.account_code')) {
            $query->where('coa.account_code', 'like', '%'.$request->input('filter.account_code').'%');
        }

        if ($request->filled('filter.account_name')) {
            $query->where('coa.account_name', 'like', '%'.$request->input('filter.account_name').'%');
        }

        if ($entryDateFrom) {
            $query->where('je.entry_date', '>=', $entryDateFrom);
        }

        if ($entryDateTo) {
            $query->where('je.entry_date', '<=', $entryDateTo);
        }

        if ($request->filled('filter.entry_date_from') && ! $periodId) {
            // already applied above
        }

        if ($request->filled('filter.reference')) {
            $query->where('je.reference', 'like', '%'.$request->input('filter.reference').'%');
        }

        if ($request->filled('filter.status')) {
            $query->where('je.status', $request->input('filter.status'));
        }

        if ($request->filled('filter.cost_center_code')) {
            $query->where('cc.code', 'like', '%'.$request->input('filter.cost_center_code').'%');
        }

        if ($request->filled('filter.debit_min')) {
            $query->where('jel.debit', '>=', $request->input('filter.debit_min'));
        }

        if ($request->filled('filter.debit_max')) {
            $query->where('jel.debit', '<=', $request->input('filter.debit_max'));
        }

        if ($request->filled('filter.credit_min')) {
            $query->where('jel.credit', '>=', $request->input('filter.credit_min'));
        }

        if ($request->filled('filter.credit_max')) {
            $query->where('jel.credit', '<=', $request->input('filter.credit_max'));
        }

        // Apply sort
        $sort = $request->input('sort', 'entry_date');
        match ($sort) {
            '-entry_date' => $query->orderBy('je.entry_date', 'desc')->orderBy('je.id', 'desc')->orderBy('jel.line_no', 'asc'),
            'account_code' => $query->orderBy('coa.account_code', 'asc')->orderBy('je.entry_date', 'asc')->orderBy('jel.line_no', 'asc'),
            '-account_code' => $query->orderBy('coa.account_code', 'desc')->orderBy('je.entry_date', 'asc')->orderBy('jel.line_no', 'asc'),
            '-debit' => $query->orderBy('jel.debit', 'desc')->orderBy('je.entry_date', 'asc'),
            '-credit' => $query->orderBy('jel.credit', 'desc')->orderBy('je.entry_date', 'asc'),
            'status' => $query->orderBy('je.status', 'asc')->orderBy('je.entry_date', 'asc'),
            default => $query->orderBy('je.entry_date', 'asc')->orderBy('je.id', 'asc')->orderBy('jel.line_no', 'asc'),
        };

        $entries = $query->paginate($perPage)->withQueryString();

        $accounts = DB::table('accounting_chart_of_accounts')
            ->whereNotNull('account_code')
            ->orderBy('account_code')
            ->get(['account_code', 'account_name'])
            ->unique('account_code');

        $costCenters = DB::table('accounting_cost_centers')
            ->orderBy('code')
            ->get(['code', 'name']);

        $accountingPeriods = AccountingPeriod::orderBy('start_date', 'desc')->get();

        $statusOptions = ['draft' => 'Draft', 'posted' => 'Posted', 'void' => 'Void'];

        return view('accounting::reports.general-ledger', compact(
            'entries',
            'accounts',
            'costCenters',
            'accountingPeriods',
            'statusOptions',
            'periodId',
            'entryDateFrom',
            'entryDateTo',
        ));
    }
}
