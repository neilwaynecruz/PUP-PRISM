<?php

namespace App\Services\Attachments;

use App\Enums\ApprovalStatus;
use App\Enums\AssetTransferStatus;
use App\Enums\InventoryCountStatus;
use App\Models\Asset;
use App\Models\AssetLifecycleEvent;
use App\Models\AssetMaintenance;
use App\Models\AssetTransfer;
use App\Models\Attachment;
use App\Models\InventoryCountSession;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentService
{
    private const ATTACHABLE_TYPES = [
        'asset' => Asset::class,
        'maintenance' => AssetMaintenance::class,
        'transfer' => AssetTransfer::class,
        'lifecycle' => AssetLifecycleEvent::class,
        'count-session' => InventoryCountSession::class,
    ];

    public function resolveAttachable(string $type, int $id): Asset|AssetMaintenance|AssetTransfer|AssetLifecycleEvent|InventoryCountSession
    {
        $modelClass = self::ATTACHABLE_TYPES[$type] ?? null;
        if ($modelClass === null) {
            abort(404);
        }

        return $modelClass::query()->findOrFail($id);
    }

    /**
     * Determine whether an attachment belongs to a finalized record that preserves immutable evidence.
     */
    public static function deletionBlocker(Attachment $attachment): ?string
    {
        $attachable = $attachment->attachable;

        if (! $attachable instanceof Model) {
            return 'Attachments without an active parent record cannot be removed.';
        }

        if ($attachable instanceof AssetTransfer && $attachable->status === AssetTransferStatus::Approved) {
            return 'Attachments cannot be removed after an asset transfer has been approved.';
        }

        if ($attachable instanceof AssetLifecycleEvent && $attachable->status === ApprovalStatus::Approved) {
            return 'Attachments cannot be removed after a lifecycle event has been approved.';
        }

        if ($attachable instanceof InventoryCountSession
            && in_array($attachable->status, [InventoryCountStatus::Approved, InventoryCountStatus::Completed], true)) {
            return 'Attachments cannot be removed after a physical count session has been approved or completed.';
        }

        return null;
    }

    /** @param array<string, mixed> $payload */
    public function store(User $user, Asset|AssetMaintenance|AssetTransfer|AssetLifecycleEvent|InventoryCountSession $attachable, UploadedFile $file, array $payload): Attachment
    {
        $checksum = hash_file('sha256', $file->getRealPath());
        if ($checksum === false) {
            throw new \RuntimeException('The document could not be verified. Please choose the file again.');
        }

        return DB::transaction(function () use ($user, $attachable, $file, $payload, $checksum): Attachment {
            $attachable = $attachable->newQuery()->whereKey($attachable->getKey())->lockForUpdate()->firstOrFail();
            if ($attachable->attachments()->where('checksum', $checksum)->exists()) {
                throw new \RuntimeException('This document has already been uploaded to this record.');
            }

            $storedName = Str::uuid().'.'.$file->extension();
            $directory = 'attachments/'.Str::kebab(class_basename($attachable)).'/'.$attachable->getKey();
            $path = $file->storeAs($directory, $storedName, 'local');
            if ($path === false) {
                throw new \RuntimeException('The document could not be stored. Please try again.');
            }

            try {
                $attachment = $attachable->attachments()->create([
                    'uploaded_by' => $user->id,
                    'title' => $payload['title'],
                    'category' => $payload['category'],
                    'description' => $payload['description'] ?? null,
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => basename(str_replace('\\', '/', $file->getClientOriginalName())),
                    'stored_name' => $storedName,
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'checksum' => $checksum,
                    'size' => $file->getSize(),
                ]);
                AuditLogService::logCreated($attachment, "Supporting document uploaded for {$attachment->attachable_type} #{$attachment->attachable_id}.");
            } catch (QueryException $exception) {
                Storage::disk('local')->delete($path);

                $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
                $message = strtolower($exception->getMessage());
                if (in_array($sqlState, ['23505', '23000'], true)
                    || str_contains($message, 'unique constraint')
                    || str_contains($message, 'duplicate key')) {
                    throw new \RuntimeException('This document has already been uploaded to this record.', previous: $exception);
                }

                throw $exception;
            } catch (\Throwable $exception) {
                Storage::disk('local')->delete($path);
                throw $exception;
            }

            return $attachment;
        });
    }

    public function remove(Attachment $attachment): void
    {
        DB::transaction(function () use ($attachment): void {
            $attachment = Attachment::query()->whereKey($attachment->id)->lockForUpdate()->firstOrFail();

            $blocker = self::deletionBlocker($attachment);
            if ($blocker !== null) {
                throw new \RuntimeException($blocker);
            }

            $attachment->delete();
            AuditLogService::logDeleted($attachment, "Supporting document {$attachment->original_name} removed from active use.");
        });
    }
}
