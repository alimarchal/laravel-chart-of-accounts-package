<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Jobs\GenerateReportExport;
use Alimarchal\LaravelChartOfAccounts\Models\Company;
use Alimarchal\LaravelChartOfAccounts\Models\ReportExport;
use Alimarchal\LaravelChartOfAccounts\Reports\ReportCatalog;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Report exports too large for a request: queued, generated into private storage, downloaded by their owner,
 * pruned after accounting.exports.keep_days.
 */
class ReportExportService
{
    public function __construct(
        private readonly ReportCatalog $catalog,
        private readonly AccountingReportExporter $exporter,
    ) {}

    /**
     * Whether an XLSX/PDF export of these rows is above the in-request limit and should run in the background.
     *
     * @param  Builder|iterable<int, mixed>  $rows
     */
    public function shouldQueue(Builder|iterable $rows, string $format): bool
    {
        if (! config('accounting.exports.queue_large', true)) {
            return false;
        }

        $max = (int) config("accounting.export_max_rows.{$format}", $format === 'pdf' ? 2000 : 50000);

        if (! $rows instanceof Builder) {
            return collect($rows)->count() > $max;
        }

        return DB::query()->fromSub((clone $rows)->reorder()->limit($max + 1), 'rows')->count() > $max;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function queue(Authenticatable $user, string $report, string $format, array $filters): ReportExport
    {
        $export = ReportExport::query()->create([
            'user_id' => $user->getAuthIdentifier(),
            'report' => $report,
            'format' => $format,
            'filters' => array_filter($filters, fn ($value) => is_scalar($value) && $value !== ''),
            'status' => 'queued',
        ]);

        GenerateReportExport::dispatch($export->id)->afterCommit();

        return $export;
    }

    /**
     * Build the file. Runs as the requesting user in the export's company and re-checks their permission.
     */
    public function generate(int $exportId): void
    {
        $export = ReportExport::query()->withoutGlobalScopes()->find($exportId);

        if (! $export || $export->status !== 'queued') {
            return;
        }

        $company = Company::query()->find($export->company_id);
        $userModel = (string) config('auth.providers.users.model');
        $user = $userModel::query()->find($export->user_id);

        if (! $company || ! $user instanceof Authenticatable) {
            $export->forceFill(['status' => 'failed', 'error' => 'The company or user no longer exists.', 'finished_at' => now()])->save();

            return;
        }

        $export->forceFill(['status' => 'running', 'started_at' => now()])->save();
        $previous = Auth::user();
        Auth::setUser($user);

        try {
            app(CurrentCompany::class)->runAs($company, function () use ($export, $user): void {
                $definition = $this->catalog->resolve($export->report, (array) $export->filters);

                if ($definition === null || ! $user->can($definition['permission'])) {
                    throw new \RuntimeException('You are no longer allowed to run this report.');
                }

                $source = ($definition['rows'])();
                $max = (int) config("accounting.exports.max_rows.{$export->format}", 500000);
                $rows = ($source instanceof Builder ? $source->limit($max + 1)->get() : collect($source))
                    ->map(fn ($row): array => (array) $row)->values();
                /** @var Collection<int, array<string, mixed>> $rows */
                if ($rows->count() > $max) {
                    throw new \RuntimeException("The report has more than {$max} rows, the limit for background ".strtoupper($export->format).' exports. Narrow the filters or export as CSV.');
                }

                $disk = (string) config('accounting.exports.disk', 'local');
                $path = sprintf('accounting/%d/exports/%s', $export->company_id, $export->id.'-'.$export->filename());
                $contents = $this->exporter->contents($rows, $export->report, $export->format, ['title' => $definition['title'], 'filters' => $definition['filters']]);
                Storage::disk($disk)->put($path, $contents);

                $export->forceFill([
                    'status' => 'ready', 'disk' => $disk, 'path' => $path, 'rows' => $rows->count(),
                    'size' => strlen($contents), 'finished_at' => now(),
                ])->save();
            });
        } catch (\Throwable $exception) {
            $export->forceFill(['status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 1000), 'finished_at' => now()])->save();
            report($exception);
        } finally {
            $previous ? Auth::setUser($previous) : Auth::forgetUser();
        }
    }

    public function download(ReportExport $export): StreamedResponse
    {
        abort_unless($export->status === 'ready' && $export->disk && $export->path && Storage::disk($export->disk)->exists($export->path), 404);

        return Storage::disk($export->disk)->download($export->path, $export->filename());
    }

    public function delete(ReportExport $export): void
    {
        if ($export->disk && $export->path) {
            Storage::disk($export->disk)->delete($export->path);
        }

        $export->delete();
    }

    /**
     * Delete exports (and their files) older than accounting.exports.keep_days.
     */
    public function prune(): int
    {
        $count = 0;

        ReportExport::query()->withoutGlobalScopes()
            ->where('created_at', '<', now()->subDays((int) config('accounting.exports.keep_days', 7)))
            ->each(function (ReportExport $export) use (&$count): void {
                $this->delete($export);
                $count++;
            });

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    public function present(ReportExport $export): array
    {
        return [
            'id' => $export->id,
            'report' => $export->report,
            'title' => (string) str($export->report)->headline(),
            'format' => $export->format,
            'filters' => $export->filters ?? [],
            'status' => $export->status,
            'rows' => $export->rows,
            'size' => $export->size,
            'error' => $export->error,
            'created_at' => $export->created_at?->toISOString(),
            'finished_at' => $export->finished_at?->toISOString(),
        ];
    }

    public function listUrl(): string
    {
        return route(config('accounting.route_name_prefix', 'accounting').'.exports.index');
    }
}
