<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\Attachment;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Services\AttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Supporting documents of journal entries.
 */
class AttachmentApiController extends Controller
{
    public function __construct(private readonly AttachmentService $attachments) {}

    public function index(JournalEntry $journalEntry): JsonResponse
    {
        return response()->json(['data' => $this->attachments->list($journalEntry)]);
    }

    /**
     * Upload a file (multipart: file, description?). The response lists other entries that carry the same file.
     */
    public function store(Request $request, JournalEntry $journalEntry): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', ...$this->attachments->rules()],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $this->attachments->attach($journalEntry, $data['file'], $data['description'] ?? null);

        return response()->json([
            'data' => $this->attachments->present($result['attachment']),
            'duplicates' => $result['duplicates'],
        ], 201);
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        return $this->attachments->download($attachment);
    }

    public function destroy(Attachment $attachment): JsonResponse
    {
        try {
            $this->attachments->remove($attachment);
        } catch (AccountingException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(null, 204);
    }
}
