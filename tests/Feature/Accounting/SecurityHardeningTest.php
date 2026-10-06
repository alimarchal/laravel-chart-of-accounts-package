<?php

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Services\AccountingReportExporter;
use Alimarchal\LaravelChartOfAccounts\Services\AttachmentService;
use Alimarchal\LaravelChartOfAccounts\Support\Fbr\HttpFbrGateway;
use Alimarchal\LaravelChartOfAccounts\Support\SpreadsheetReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

function workbook(string $sheet, ?string $doctype = null): string
{
    $path = tempnam(sys_get_temp_dir(), 'sec').'.xlsx';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<Types/>');
    $zip->addFromString('xl/worksheets/sheet1.xml', ($doctype ?? '').$sheet);
    $zip->close();

    return $path;
}

$sheet = '<worksheet><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>code</t></is></c></row><row r="2"><c r="A2" t="inlineStr"><is><t>1101</t></is></c></row></sheetData></worksheet>';

it('reads a normal workbook', function () use ($sheet): void {
    expect(SpreadsheetReader::read(workbook($sheet), 'xlsx'))->toBe([['code' => '1101']]);
});

it('refuses a workbook that declares a DOCTYPE or entities', function () use ($sheet): void {
    $evil = '<!DOCTYPE x [<!ENTITY a "aaaaaaaaaa"><!ENTITY b "&a;&a;&a;&a;&a;&a;&a;&a;">]>';

    expect(fn () => SpreadsheetReader::read(workbook($sheet, $evil), 'xlsx'))->toThrow(AccountingException::class);
});

it('stops formulas in exported CSV cells', function (): void {
    expect(AccountingReportExporter::safeCell('=HYPERLINK("http://evil","x")'))->toBe("'=HYPERLINK(\"http://evil\",\"x\")")
        ->and(AccountingReportExporter::safeCell('@SUM(A1)'))->toBe("'@SUM(A1)")
        ->and(AccountingReportExporter::safeCell('+cmd|calc'))->toBe("'+cmd|calc")
        ->and(AccountingReportExporter::safeCell('-5'))->toBe('-5')
        ->and(AccountingReportExporter::safeCell('1200.50'))->toBe('1200.50')
        ->and(AccountingReportExporter::safeCell('Rent'))->toBe('Rent')
        ->and(AccountingReportExporter::safeCell(null))->toBe('');
});

it('never sends the FBR token over plain http', function (): void {
    config(['accounting.fbr.url' => 'http://fbr.test/invoices', 'accounting.fbr.token' => 'secret']);
    Http::fake();

    $result = (new HttpFbrGateway)->submit([]);

    expect($result['accepted'])->toBeFalse()->and($result['error'])->toContain('https://');
    Http::assertNothingSent();
});

it('accepts only the whitelisted document types as attachments', function (): void {
    $rules = app(AttachmentService::class)->rules();
    $check = fn (UploadedFile $file): bool => Validator::make(['file' => $file], ['file' => $rules])->passes();

    expect($check(UploadedFile::fake()->create('bill.pdf', 10, 'application/pdf')))->toBeTrue()
        ->and($check(UploadedFile::fake()->create('shell.php', 1)))->toBeFalse()
        ->and($check(UploadedFile::fake()->create('page.html', 1)))->toBeFalse()
        ->and($check(UploadedFile::fake()->create('logo.svg', 1)))->toBeFalse()
        ->and($check(UploadedFile::fake()->create('run.exe', 1)))->toBeFalse();
});
