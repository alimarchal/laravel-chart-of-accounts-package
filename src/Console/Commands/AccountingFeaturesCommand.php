<?php

namespace Alimarchal\LaravelChartOfAccounts\Console\Commands;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Support\FeatureManager;
use Illuminate\Console\Command;

/**
 * List the feature switches, turn features on or off, or put one back to its config default.
 */
class AccountingFeaturesCommand extends Command
{
    protected $signature = 'accounting:features
        {action=list : list, enable, disable or reset}
        {features?* : feature keys (see the list), or "all" for every optional feature}';

    protected $description = 'List, enable, disable or reset the accounting feature switches';

    public function handle(FeatureManager $features): int
    {
        $action = (string) $this->argument('action');

        if ($action === 'list') {
            $this->table(['Feature', 'State', 'Source', 'Depends on', 'What it is'], collect($features->all())->map(fn (array $row): array => [
                $row['key'], $row['enabled'] ? 'on' : ($row['own'] ? 'off (parent off)' : 'off'), $row['source'], $row['parent'] ?? '', $row['label'],
            ])->all());

            return self::SUCCESS;
        }

        if (! in_array($action, ['enable', 'disable', 'reset'], true)) {
            $this->error("Unknown action '{$action}': use list, enable, disable or reset.");

            return self::INVALID;
        }

        $keys = (array) $this->argument('features');

        if ($keys === []) {
            $this->error('Name the features, or "all".');

            return self::INVALID;
        }

        if (in_array('all', $keys, true)) {
            $keys = array_keys(FeatureManager::catalog());
        }

        try {
            foreach ($keys as $key) {
                match ($action) {
                    'enable' => $features->set($key, true),
                    'disable' => $features->set($key, false),
                    default => $features->known($key) ? $features->reset($key) : throw new AccountingException("'{$key}' is not a feature that can be switched."),
                };
                $this->line("{$key}: {$action} done");
            }
        } catch (AccountingException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
