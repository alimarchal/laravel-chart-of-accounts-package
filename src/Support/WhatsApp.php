<?php

namespace Alimarchal\LaravelChartOfAccounts\Support;

use Alimarchal\LaravelChartOfAccounts\Models\WhatsAppMessage;
use Alimarchal\LaravelChartOfAccounts\Services\WhatsAppService;
use Illuminate\Database\Eloquent\Model;

/**
 * The one-line way to send a WhatsApp message from anywhere in your application:
 *
 *   use Alimarchal\LaravelChartOfAccounts\Support\WhatsApp;
 *
 *   // a template you registered in config/accounting.php (accounting.whatsapp.templates.fee_received)
 *   WhatsApp::template('0300-1234567', 'fee_received', ['student' => 'Ali', 'amount' => '5,000', 'receipt' => 'RV-2026-00012', 'date' => '10 Oct 2026']);
 *
 *   // free text: only to a customer who wrote to you in the last 24 hours
 *   WhatsApp::text('+92 300 1234567', 'Your receipt is ready.');
 *
 * Both return the saved WhatsAppMessage (status "sent": Meta accepted it; delivered/read follow on the webhook) and throw an
 * AccountingException with a message you can show when WhatsApp refuses (a wrong number, the 24-hour window, a template mismatch).
 * queueTemplate() / queueText() return at once and send from a queue worker. Pass the record that caused the message as the
 * last-but-one argument ($related) to find it again later.
 */
final class WhatsApp
{
    /**
     * @param  array<int|string, mixed>  $parameters  a list ({{1}}, {{2}} …) or name => value as registered in config
     */
    public static function template(string $to, string $template, array $parameters = [], ?string $language = null, ?Model $related = null): WhatsAppMessage
    {
        return self::service()->template($to, $template, $parameters, $language, $related);
    }

    public static function text(string $to, string $message, ?Model $related = null): WhatsAppMessage
    {
        return self::service()->text($to, $message, $related);
    }

    /**
     * @param  array<int|string, mixed>  $parameters
     */
    public static function queueTemplate(string $to, string $template, array $parameters = [], ?string $language = null, ?Model $related = null): WhatsAppMessage
    {
        return self::service()->queueTemplate($to, $template, $parameters, $language, $related);
    }

    public static function queueText(string $to, string $message, ?Model $related = null): WhatsAppMessage
    {
        return self::service()->queueText($to, $message, $related);
    }

    /** True when WhatsApp is switched on (ACCOUNTING_WHATSAPP_ENABLED); use it to skip a message quietly where it is optional. */
    public static function enabled(): bool
    {
        return self::service()->enabled();
    }

    private static function service(): WhatsAppService
    {
        return app(WhatsAppService::class);
    }
}
