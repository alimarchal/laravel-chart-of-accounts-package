<?php

namespace Alimarchal\LaravelChartOfAccounts\Console\Commands;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\Company;
use Alimarchal\LaravelChartOfAccounts\Services\WhatsAppService;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Illuminate\Console\Command;

/**
 * Check the WhatsApp set-up, send a test message, or list the templates of your WhatsApp Business Account.
 */
class AccountingWhatsAppCommand extends Command
{
    protected $signature = 'accounting:whatsapp
        {action=status : status, test or templates}
        {to? : test: the number to send to, with the country code (a test number only sends to the numbers you added in WhatsApp Manager)}
        {--template=hello_world : test: the template key or name}
        {--param=* : test: a template parameter, name=value or just the value (repeatable)}
        {--text= : test: send this free text instead of a template (only within 24 hours of the customer\'s last message)}
        {--company= : Company code (default: the default company)}';

    protected $description = 'Check the WhatsApp set-up, send a test message, or list the approved templates';

    public function handle(WhatsAppService $whatsapp, CurrentCompany $companies): int
    {
        $company = $this->option('company') ? Company::query()->where('code', strtoupper((string) $this->option('company')))->first() : null;

        if ($this->option('company') && $company === null) {
            $this->error("No company with code {$this->option('company')}.");

            return self::FAILURE;
        }

        $run = fn (): int => match ((string) $this->argument('action')) {
            'status' => $this->status($whatsapp),
            'test' => $this->test($whatsapp),
            'templates' => $this->templates($whatsapp),
            default => $this->unknown(),
        };

        return $company ? $companies->runAs($company, $run) : $run();
    }

    private function status(WhatsAppService $whatsapp): int
    {
        $this->info('WhatsApp: '.($whatsapp->enabled() ? 'on' : 'OFF (set ACCOUNTING_WHATSAPP_ENABLED=true)').' · mode '.$whatsapp->mode().' · Graph API '.config('accounting.whatsapp.api_version'));
        $this->table(['Setting (.env)', 'Set', 'Needed', 'What it is'], array_map(fn (array $check): array => [$check['env'], $check['set'] ? 'yes' : 'no', $check['needed'] ? 'yes' : 'no', $check['what']], $whatsapp->checks()));
        $this->line('Webhook URL for Meta: '.url((string) config('accounting.api_prefix', 'api/v1/accounting').'/whatsapp/webhook'));
        $this->table(['Template key', 'Name', 'Language', 'Parameters'], array_map(fn (array $template): array => [$template['key'], $template['name'], $template['language'], implode(', ', $template['parameters'])], $whatsapp->templates()));

        return self::SUCCESS;
    }

    private function test(WhatsAppService $whatsapp): int
    {
        $to = (string) $this->argument('to');

        if ($to === '') {
            $this->error('Give the number to send to: php artisan accounting:whatsapp test +923001234567');

            return self::INVALID;
        }

        try {
            if ($this->option('text')) {
                $message = $whatsapp->text($to, (string) $this->option('text'));
            } else {
                $parameters = [];

                foreach ((array) $this->option('param') as $param) {
                    str_contains((string) $param, '=') ? $parameters[strstr((string) $param, '=', true)] = substr((string) strstr((string) $param, '='), 1) : $parameters[] = (string) $param;
                }

                $message = $whatsapp->template($to, (string) $this->option('template'), $parameters);
            }
        } catch (AccountingException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Handed to WhatsApp ({$whatsapp->mode()} mode): message #{$message->id}, id {$message->wamid}. Delivery and read arrive on the webhook.");

        return self::SUCCESS;
    }

    private function templates(WhatsAppService $whatsapp): int
    {
        if ($whatsapp->mode() !== 'live') {
            $this->line('Sandbox (mode fake): these are the templates registered in config/accounting.php; live mode lists the ones Meta approved.');
            $this->table(['Name', 'Language', 'Status', 'Category'], array_map(fn (array $template): array => [$template['name'], $template['language'], 'local', '-'], $whatsapp->templates()));

            return self::SUCCESS;
        }

        try {
            $rows = $whatsapp->remoteTemplates();
        } catch (AccountingException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Name', 'Language', 'Status', 'Category'], array_map(fn (array $row): array => array_values($row), $rows));

        return self::SUCCESS;
    }

    private function unknown(): int
    {
        $this->error('Unknown action: use status, test or templates.');

        return self::INVALID;
    }
}
