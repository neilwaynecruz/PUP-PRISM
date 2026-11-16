<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AttachmentStoreRequest;
use App\Models\Attachment;
use App\Services\Attachments\AttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function store(
        AttachmentStoreRequest $request,
        string $attachableType,
        int $attachableId,
        AttachmentService $service,
    ): RedirectResponse {
        $attachable = $service->resolveAttachable($attachableType, $attachableId);
        $this->authorize('create', [Attachment::class, $attachable]);

        try {
            $service->store($request->user(), $attachable, $request->file('file'), $request->validated());
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => __('The supporting document has been uploaded.')]);

        return back();
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        $this->authorize('view', $attachment);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return response()->streamDownload(
            function () use ($attachment): void {
                $stream = Storage::disk($attachment->disk)->readStream($attachment->path);

                abort_unless(is_resource($stream), 404);
                fpassthru($stream);
                fclose($stream);
            },
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type],
        );
    }

    public function destroy(Attachment $attachment, AttachmentService $service): RedirectResponse
    {
        $this->authorize('delete', $attachment);

        try {
            $service->remove($attachment);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['attachment' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The document has been removed from active use.')]);

        return back();
    }
}
