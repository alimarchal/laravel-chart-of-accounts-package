<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\Attachment;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Services\AttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Upload, download and remove supporting documents from the journal entry page (React and Blade).
 */
class AttachmentController extends Controller
{
    public function __construct(private readonly AttachmentService $attachments) {}

    public function store(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $data = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => $this->attachments->rules(),
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $duplicates = [];

        foreach ($data['files'] as $file) {
            $result = $this->attachments->attach($journalEntry, $file, $data['description'] ?? null);

            foreach ($result['duplicates'] as $duplicate) {
                $duplicates[$duplicate['id']] = $duplicate['voucher_number'] ?? '#'.$duplicate['id'];
            }
        }

        $response = back()->with('success', count($data['files']) === 1 ? 'File attached.' : count($data['files']).' files attached.');

        return $duplicates === []
            ? $response
            : $response->with('error', 'The same file is already attached to '.implode(', ', $duplicates).': check this is not a duplicate bill or receipt.');
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        return $this->attachments->download($attachment);
    }

    public function destroy(Attachment $attachment): RedirectResponse
    {
        try {
            $this->attachments->remove($attachment);
        } catch (AccountingException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Attachment removed.');
    }
}
