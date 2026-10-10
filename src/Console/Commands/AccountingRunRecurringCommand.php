<?php

namespace Alimarchal\LaravelChartOfAccounts\Console\Commands;

use Alimarchal\LaravelChartOfAccounts\Models\Company;
use Alimarchal\LaravelChartOfAccounts\Services\RecurringEntryService;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Support\FeatureManager;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AccountingRunRecurringCommand extends Command
{
    protected $signature = 'accounting:run-recurring
        {--date= : Generate what is due on or before this date (default: today)}
        {--company= : Company code (default: every company)}
        {--dry-run : List what is due without generating it}';

    protected $description = 'Generate the recurring entries that are due (scheduled daily).';

    public function handle(RecurringEntryService $recurring, CurrentCompany $companies): int
    {
        if (! app(FeatureManager::class)->enabled('recurring_entries')) {
            $this->info('The Recurring entries feature is turned off: nothing to do.');

            return self::SUCCESS;
        }

        $asOf = $this->option('date') ? Carbon::parse((string) $this->option('date')) : now();
        $query = Company::query()->orderBy('id');

        if ($this->option('company')) {
            $query->where('code', strtoupper((string) $this->option('company')));
        }

        $rows = [];
        $failed = 0;

        foreach ($query->get() as $company) {
            $companies->runAs($company, function () use ($recurring, $asOf, $company, &$rows, &$failed): void {
                foreach ($recurring->due($asOf) as $entry) {
                    if ($this->option('dry-run')) {
                        $rows[] = [$company->code, $entry->name, $entry->next_run_date?->toDateString(), 'due', ''];

                        continue;
                    }

                    foreach ($recurring->runDue($entry, $asOf) as $run) {
                        $failed += $run->status === 'failed' ? 1 : 0;
                        $rows[] = [$company->code, $entry->name, $run->run_date->toDateString(), $run->status, (string) $run->error];
                    }
                }
            });
        }

        if ($rows === []) {
            $this->info('No recurring entries are due.');

            return self::SUCCESS;
        }

        $this->table(['Company', 'Template', 'Date', 'Result', 'Note'], $rows);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
