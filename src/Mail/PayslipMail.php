<?php

namespace Alimarchal\LaravelChartOfAccounts\Mail;

use Illuminate\Mail\Mailable;

/**
 * An employee's payslip as an attachment (PDF when dompdf is installed, otherwise HTML).
 */
class PayslipMail extends Mailable
{
    public function __construct(public readonly string $employeeName, public readonly string $month, public readonly string $companyName, private readonly string $content, private readonly string $mime, private readonly string $filename)
    {
        $this->subject(str_replace(':month', $month, (string) config('accounting.payroll.payslip_mail.subject', 'Payslip for :month')));
    }

    public function build(): static
    {
        return $this->view('accounting::mail.payslip')->attachData($this->content, $this->filename, ['mime' => $this->mime]);
    }
}
