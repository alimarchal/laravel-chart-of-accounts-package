<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Models\Company;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

/**
 * Typesets Blade templates into PDF with dompdf (when installed): company header and logo, "Page X of Y" on every
 * page. Remote resources and PHP in templates are disabled; the logo is embedded as a data URI.
 */
class PdfRenderer
{
    /**
     * Whether a document of this many rows is typeset with dompdf (otherwise the built-in renderer is used).
     */
    public function available(int $rows = 0): bool
    {
        $engine = (string) config('accounting.pdf.engine', 'auto');

        if ($engine === 'builtin' || ! class_exists(Dompdf::class)) {
            return false;
        }

        return $engine === 'dompdf' || $rows <= (int) config('accounting.pdf.dompdf_max_rows', 3000);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function render(string $view, array $data, string $orientation = 'portrait'): string
    {
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $options->setTempDir(sys_get_temp_dir());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view($view, [...$data, 'company' => $data['company'] ?? $this->company()])->render());
        $dompdf->setPaper((string) config('accounting.pdf.paper', 'a4'), $orientation);
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text($canvas->get_width() - 110, $canvas->get_height() - 28, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 8, [0.4, 0.4, 0.4]);

        return (string) $dompdf->output();
    }

    /**
     * The current company's letterhead: name, legal details and the logo as a data URI.
     *
     * @return array{name: string, legal_name: string|null, tax_number: string|null, registration_number: string|null, address: string|null, phone: string|null, email: string|null, logo: string|null}
     */
    public function company(): array
    {
        $company = app(CurrentCompany::class)->get();

        return [
            'name' => $company->name,
            'legal_name' => $company->legal_name,
            'tax_number' => $company->tax_number,
            'registration_number' => $company->registration_number,
            'address' => $company->address,
            'phone' => $company->phone,
            'email' => $company->email,
            'logo' => $this->logo($company),
        ];
    }

    private function logo(Company $company): ?string
    {
        $contents = null;
        $path = $company->logo_path;

        if ($path) {
            $disk = Storage::disk((string) config('accounting.pdf.logo_disk', 'public'));
            $contents = $disk->exists($path) ? $disk->get($path) : null;
        }

        $fallback = config('accounting.pdf.logo');

        if ($contents === null && is_string($fallback) && $fallback !== '' && is_file($fallback)) {
            $contents = file_get_contents($fallback) ?: null;
        }

        if ($contents === null) {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);

        return in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/svg+xml'], true)
            ? 'data:'.$mime.';base64,'.base64_encode($contents)
            : null;
    }
}
