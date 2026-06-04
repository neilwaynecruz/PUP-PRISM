<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Product;
use App\Models\Requisition;
use Carbon\Carbon;
use Illuminate\Console\Command;

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
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $cutoffDate = Carbon::now()->subDays($days);

        $this->info("Cleaning up items deleted before: {$cutoffDate->format('Y-m-d H:i:s')}");
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No items will actually be deleted');
            $this->newLine();
        }

        // Cleanup Products
        $productsQuery = Product::onlyTrashed()->where('deleted_at', '<', $cutoffDate);
        $productsCount = $productsQuery->count();

        if ($productsCount > 0) {
            $this->info("Found {$productsCount} product(s) to permanently delete");
            if (! $dryRun) {
                $productsQuery->forceDelete();
                $this->info("✓ Deleted {$productsCount} product(s)");
            }
        } else {
            $this->info('No products to clean up');
        }

        // Cleanup Bookings
        $bookingsQuery = Booking::onlyTrashed()->where('deleted_at', '<', $cutoffDate);
        $bookingsCount = $bookingsQuery->count();

        if ($bookingsCount > 0) {
            $this->info("Found {$bookingsCount} booking(s) to permanently delete");
            if (! $dryRun) {
                $bookingsQuery->forceDelete();
                $this->info("✓ Deleted {$bookingsCount} booking(s)");
            }
        } else {
            $this->info('No bookings to clean up');
        }

        // Cleanup Requisitions
        $requisitionsQuery = Requisition::onlyTrashed()->where('deleted_at', '<', $cutoffDate);
        $requisitionsCount = $requisitionsQuery->count();

        if ($requisitionsCount > 0) {
            $this->info("Found {$requisitionsCount} requisition(s) to permanently delete");
            if (! $dryRun) {
                $requisitionsQuery->forceDelete();
                $this->info("✓ Deleted {$requisitionsCount} requisition(s)");
            }
        } else {
            $this->info('No requisitions to clean up');
        }

        $this->newLine();
        $total = $productsCount + $bookingsCount + $requisitionsCount;

        if ($dryRun) {
            $this->warn("Would have deleted {$total} item(s) in total");
        } else {
            $this->info("✓ Cleanup complete. Deleted {$total} item(s) in total");
        }

        return self::SUCCESS;
    }
}
