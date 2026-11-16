<?php

namespace App\Console\Commands;

use App\Enums\RequisitionStatus;
use App\Models\Attachment;
use App\Models\Booking;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Requisition;
use App\Support\SchedulerHeartbeat;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CleanupTrash extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trash:cleanup
                            {--days=30 : Number of days after which soft-deleted items are permanently removed}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Permanently delete soft-deleted items older than specified days';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = max(0, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoffDate = Carbon::now()->subDays($days);

        $this->info("Cleaning up items deleted before: {$cutoffDate->format('Y-m-d H:i:s')}");
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No items will actually be deleted');
            $this->newLine();
        }

        // Cleanup Products (preserve products referenced by inventory/transaction history)
        $productStats = $this->cleanupProducts($cutoffDate, $dryRun);
        // Cleanup Bookings
        $bookingStats = $this->cleanupBookings($cutoffDate, $dryRun);
        // Cleanup Requisitions (preserve requisitions with issuance/movement history)
        $requisitionStats = $this->cleanupRequisitions($cutoffDate, $dryRun);
        // Cleanup Expired Soft-Deleted Attachments & Storage Files
        $attachmentStats = $this->cleanupAttachments($cutoffDate, $dryRun);

        $this->newLine();
        $total = $productStats['deleted']
            + $bookingStats['deleted']
            + $requisitionStats['deleted']
            + $attachmentStats['deleted'];

        if ($dryRun) {
            $this->warn("Would have deleted {$total} item(s) in total");
        } else {
            $this->info("✓ Cleanup complete. Deleted {$total} item(s) in total");
            SchedulerHeartbeat::record(SchedulerHeartbeat::COMMAND_TRASH_CLEANUP);
        }

        return self::SUCCESS;
    }

    /**
     * @return array{deleted: int, skipped: int}
     */
    private function cleanupProducts(Carbon $cutoffDate, bool $dryRun): array
    {
        $deleted = 0;
        $skipped = 0;
        $candidateCount = 0;

        Product::onlyTrashed()
            ->where('deleted_at', '<', $cutoffDate)
            ->orderBy('id')
            ->chunkById(100, function ($products) use (&$deleted, &$skipped, &$candidateCount, $dryRun): void {
                /** @var Collection<int, Product> $products */
                foreach ($products as $product) {
                    if ($this->productHasHistory($product->id)) {
                        $skipped++;

                        continue;
                    }

                    $candidateCount++;
                    if ($dryRun) {
                        $deleted++;

                        continue;
                    }

                    try {
                        $filesToDelete = DB::transaction(function () use ($product): array {
                            ProductStock::query()->where('product_id', $product->id)->delete();
                            $files = $this->detachAndDeleteAttachments(Product::class, $product->id);
                            $product->forceDelete();

                            return $files;
                        });

                        $this->deleteUnreferencedStorageFiles($filesToDelete);
                        $deleted++;
                    } catch (QueryException $e) {
                        report($e);
                        $skipped++;
                    }
                }
            });

        if ($candidateCount > 0) {
            $this->info("Found {$candidateCount} product(s) to permanently delete");
            if (! $dryRun) {
                $this->info("✓ Deleted {$deleted} product(s)");
            }
        } else {
            $this->info('No products to clean up');
        }

        if ($skipped > 0) {
            $this->warn("Preserved {$skipped} product(s) with historical or inventory references");
        }

        return ['deleted' => $deleted, 'skipped' => $skipped];
    }

    /**
     * @return array{deleted: int, skipped: int}
     */
    private function cleanupBookings(Carbon $cutoffDate, bool $dryRun): array
    {
        $deleted = 0;
        $skipped = 0;
        $candidateCount = 0;

        Booking::onlyTrashed()
            ->where('deleted_at', '<', $cutoffDate)
            ->orderBy('id')
            ->chunkById(100, function ($bookings) use (&$deleted, &$skipped, &$candidateCount, $dryRun): void {
                /** @var Collection<int, Booking> $bookings */
                foreach ($bookings as $booking) {
                    $candidateCount++;
                    if ($dryRun) {
                        $deleted++;

                        continue;
                    }

                    try {
                        $filesToDelete = DB::transaction(function () use ($booking): array {
                            $files = $this->detachAndDeleteAttachments(Booking::class, $booking->id);
                            $booking->forceDelete();

                            return $files;
                        });

                        $this->deleteUnreferencedStorageFiles($filesToDelete);
                        $deleted++;
                    } catch (QueryException $e) {
                        report($e);
                        $skipped++;
                    }
                }
            });

        if ($candidateCount > 0) {
            $this->info("Found {$candidateCount} booking(s) to permanently delete");
            if (! $dryRun) {
                $this->info("✓ Deleted {$deleted} booking(s)");
            }
        } else {
            $this->info('No bookings to clean up');
        }

        if ($skipped > 0) {
            $this->warn("Preserved {$skipped} booking(s) with restricted references");
        }

        return ['deleted' => $deleted, 'skipped' => $skipped];
    }

    /**
     * @return array{deleted: int, skipped: int}
     */
    private function cleanupRequisitions(Carbon $cutoffDate, bool $dryRun): array
    {
        $deleted = 0;
        $skipped = 0;
        $candidateCount = 0;

        Requisition::onlyTrashed()
            ->where('deleted_at', '<', $cutoffDate)
            ->orderBy('id')
            ->chunkById(100, function ($requisitions) use (&$deleted, &$skipped, &$candidateCount, $dryRun): void {
                /** @var Collection<int, Requisition> $requisitions */
                foreach ($requisitions as $requisition) {
                    if ($this->requisitionHasHistory($requisition)) {
                        $skipped++;

                        continue;
                    }

                    $candidateCount++;
                    if ($dryRun) {
                        $deleted++;

                        continue;
                    }

                    try {
                        $filesToDelete = DB::transaction(function () use ($requisition): array {
                            $files = $this->detachAndDeleteAttachments(Requisition::class, $requisition->id);
                            $requisition->forceDelete();

                            return $files;
                        });

                        $this->deleteUnreferencedStorageFiles($filesToDelete);
                        $deleted++;
                    } catch (QueryException $e) {
                        report($e);
                        $skipped++;
                    }
                }
            });

        if ($candidateCount > 0) {
            $this->info("Found {$candidateCount} requisition(s) to permanently delete");
            if (! $dryRun) {
                $this->info("✓ Deleted {$deleted} requisition(s)");
            }
        } else {
            $this->info('No requisitions to clean up');
        }

        if ($skipped > 0) {
            $this->warn("Preserved {$skipped} requisition(s) with issuance or movement history");
        }

        return ['deleted' => $deleted, 'skipped' => $skipped];
    }

    /**
     * @return array{deleted: int, skipped: int}
     */
    private function cleanupAttachments(Carbon $cutoffDate, bool $dryRun): array
    {
        $deleted = 0;
        $skipped = 0;
        $candidateCount = 0;

        Attachment::onlyTrashed()
            ->where('deleted_at', '<', $cutoffDate)
            ->orderBy('id')
            ->chunkById(100, function ($attachments) use (&$deleted, &$skipped, &$candidateCount, $dryRun): void {
                /** @var Collection<int, Attachment> $attachments */
                foreach ($attachments as $attachment) {
                    $candidateCount++;
                    if ($dryRun) {
                        $deleted++;

                        continue;
                    }

                    try {
                        $fileRef = ['disk' => $attachment->disk ?: 'local', 'path' => $attachment->path];
                        DB::transaction(function () use ($attachment): void {
                            $attachment->forceDelete();
                        });

                        $this->deleteUnreferencedStorageFiles([$fileRef]);
                        $deleted++;
                    } catch (QueryException $e) {
                        report($e);
                        $skipped++;
                    }
                }
            });

        if ($candidateCount > 0) {
            $this->info("Found {$candidateCount} attachment(s) to permanently delete");
            if (! $dryRun) {
                $this->info("✓ Deleted {$deleted} attachment(s)");
            }
        } else {
            $this->info('No attachments to clean up');
        }

        return ['deleted' => $deleted, 'skipped' => $skipped];
    }

    private function productHasHistory(int $productId): bool
    {
        return DB::table('stock_lots')->where('product_id', $productId)->exists()
            || DB::table('assets')->where('product_id', $productId)->exists()
            || DB::table('stock_movements')->where('product_id', $productId)->exists()
            || DB::table('requisition_lines')->where('product_id', $productId)->exists()
            || DB::table('purchase_order_lines')->where('product_id', $productId)->exists()
            || DB::table('inventory_count_lines')->where('product_id', $productId)->exists()
            || DB::table('product_stocks')->where('product_id', $productId)->where('on_hand_qty', '>', 0)->exists();
    }

    private function requisitionHasHistory(Requisition $requisition): bool
    {
        return DB::table('stock_movements')->where('requisition_id', $requisition->id)->exists()
            || DB::table('requisition_lines')->where('requisition_id', $requisition->id)->where('qty_issued', '>', 0)->exists()
            || in_array($requisition->status, [
                RequisitionStatus::Issued,
                RequisitionStatus::PartiallyIssued,
                RequisitionStatus::Backordered,
            ], true);
    }

    /**
     * @return array<int, array{disk: string, path: string}>
     */
    private function detachAndDeleteAttachments(string $attachableType, int $attachableId): array
    {
        /** @var Collection<int, Attachment> $attachments */
        $attachments = Attachment::query()
            ->withTrashed()
            ->where('attachable_type', $attachableType)
            ->where('attachable_id', $attachableId)
            ->get();

        $files = [];
        foreach ($attachments as $attachment) {
            $files[] = [
                'disk' => $attachment->disk ?: 'local',
                'path' => $attachment->path,
            ];
            $attachment->forceDelete();
        }

        return $files;
    }

    /**
     * @param  array<int, array{disk: string, path: string}>  $files
     */
    private function deleteUnreferencedStorageFiles(array $files): void
    {
        foreach ($files as $file) {
            if ($file['path'] === '') {
                continue;
            }

            $stillReferenced = Attachment::query()
                ->withTrashed()
                ->where('disk', $file['disk'])
                ->where('path', $file['path'])
                ->exists();

            if (! $stillReferenced && Storage::disk($file['disk'])->exists($file['path'])) {
                Storage::disk($file['disk'])->delete($file['path']);
            }
        }
    }
}
