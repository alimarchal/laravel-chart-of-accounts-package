<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\Attachment;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stores, lists, downloads and removes supporting documents of journal entries. Files go to a private disk
 * under accounting/{company}/{yyyy}/{mm}/; downloads go through the package's authorised route only.
 */
class AttachmentService
{
    /**
     * @return array<int, string>
     */
    public function rules(): array
    {
        return [
            'file',
            'max:'.(int) config('accounting.attachments.max_size_kb', 10240),
            'mimes:'.implode(',', (array) config('accounting.attachments.mimes', [])),
        ];
    }

    /**
     * Attach a file. Returns the attachment and the entries that already carry the very same file (a likely
     * duplicate bill or receipt), so the screen can warn.
     *
     * @return array{attachment: Attachment, duplicates: array<int, array{id: int, voucher_number: string|null}>}
     */
    public function attach(JournalEntry $entry, UploadedFile $file, ?string $description = null): array
    {
        $disk = (string) config('accounting.attachments.disk', 'local');
        $hash = (string) hash_file('sha256', $file->getRealPath());
        $extension = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?: 'bin'));
        $path = $file->storeAs(
            sprintf('accounting/%d/%s', $entry->company_id, now()->format('Y/m')),
            Str::uuid()->toString().'.'.$extension,
            ['disk' => $disk],
        );

        if ($path === false) {
            throw new AccountingException('The file could not be stored.');
        }

        try {
            $attachment = DB::transaction(function () use ($entry, $file, $description, $disk, $path, $hash): Attachment {
                $attachment = new Attachment(['description' => $description]);
                $attachment->forceFill([
                    'company_id' => $entry->company_id,
                    'attachable_type' => $entry->getMorphClass(),
                    'attachable_id' => $entry->getKey(),
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'sha256' => $hash,
                    'uploaded_by' => Auth::id(),
                ])->save();

                AccountingAuditLog::record($entry, 'ATTACHMENT_ADDED', null, null, [
                    'attachment_id' => $attachment->id, 'file' => $attachment->original_name, 'sha256' => $hash,
                ]);

                return $attachment;
            });
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }

        $duplicates = JournalEntry::query()
            ->whereKeyNot($entry->getKey())
            ->whereIn('id', Attachment::query()->where('attachable_type', $entry->getMorphClass())->where('sha256', $hash)->select('attachable_id'))
            ->get(['id', 'voucher_number'])
            ->map(fn (JournalEntry $other) => ['id' => $other->id, 'voucher_number' => $other->voucher_number])
            ->all();

        return ['attachment' => $attachment, 'duplicates' => $duplicates];
    }

    /**
     * Remove an attachment. Evidence of a posted entry stays: only drafts lose attachments.
     */
    public function remove(Attachment $attachment): void
    {
        $entry = $attachment->attachable;

        if ($entry instanceof JournalEntry && $entry->status !== 'draft') {
            throw new AccountingException('Attachments of posted or voided entries are kept as evidence and cannot be removed.');
        }

        DB::transaction(function () use ($attachment, $entry): void {
            $attachment->delete();

            if ($entry instanceof JournalEntry) {
                AccountingAuditLog::record($entry, 'ATTACHMENT_REMOVED', ['attachment_id' => $attachment->id, 'file' => $attachment->original_name], null);
            }
        });

        Storage::disk($attachment->disk)->delete($attachment->path);
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404, 'The file of this attachment is missing from storage.');
        }

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(JournalEntry $entry): array
    {
        return Attachment::query()
            ->where('attachable_type', $entry->getMorphClass())
            ->where('attachable_id', $entry->getKey())
            ->with('uploader:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn (Attachment $attachment) => $this->present($attachment))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Attachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
            'sha256' => $attachment->sha256,
            'description' => $attachment->description,
            'uploaded_by' => $attachment->uploader?->getAttribute('name'),
            'uploaded_at' => $attachment->created_at?->toISOString(),
        ];
    }

    /**
     * Entries at or above accounting.attachments.required_above (base currency) need supporting evidence.
     */
    public function assertEvidence(JournalEntry $entry): void
    {
        $threshold = config('accounting.attachments.required_above');

        if ($threshold === null || $threshold === '') {
            return;
        }

        $entry->loadMissing('lines');
        $total = $entry->lines->sum(fn ($line) => Money::toCents((string) $line->getRawOriginal('debit')));
        $baseTotal = (int) round($total * (float) $entry->getRawOriginal('fx_rate_to_base'));

        if ($baseTotal < Money::toCents((string) $threshold)) {
            return;
        }

        $has = Attachment::query()->where('attachable_type', $entry->getMorphClass())->where('attachable_id', $entry->getKey())->exists();

        if (! $has) {
            throw new AccountingException('Entries of '.Money::fromCents(Money::toCents((string) $threshold)).' or more need a supporting document: attach the bill, receipt or contract first.');
        }
    }
}
