<?php

namespace App\Services\Procurement;

use App\Models\PurchaseOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PurchaseOrderNumberGenerator
{
    /**
     * Generate the next unique, concurrency-safe purchase order number.
     *
     * Format: PO-YYYYMMDD-XXXX (e.g. PO-20261018-0001)
     */
    public function nextNumber(?CarbonImmutable $date = null): string
    {
        $date ??= CarbonImmutable::now();
        $datePrefix = $date->format('Ymd');

        return DB::transaction(function () use ($datePrefix): string {
            $this->ensureSequenceRowExists($datePrefix);

            $sequence = DB::table('purchase_order_sequences')
                ->where('date_prefix', $datePrefix)
                ->lockForUpdate()
                ->first();

            $nextSequence = ((int) ($sequence->last_sequence ?? 0)) + 1;

            DB::table('purchase_order_sequences')
                ->where('date_prefix', $datePrefix)
                ->update([
                    'last_sequence' => $nextSequence,
                    'updated_at' => now(),
                ]);

            return sprintf('PO-%s-%04d', $datePrefix, $nextSequence);
        });
    }

    private function ensureSequenceRowExists(string $datePrefix): void
    {
        $exists = DB::table('purchase_order_sequences')
            ->where('date_prefix', $datePrefix)
            ->exists();

        if (! $exists) {
            $maxSeq = $this->resolveMaxExistingSequence($datePrefix);

            try {
                DB::table('purchase_order_sequences')->insert([
                    'date_prefix' => $datePrefix,
                    'last_sequence' => $maxSeq,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable) {
                // Another concurrent request may have inserted the row, proceed to lockForUpdate
            }
        }
    }

    private function resolveMaxExistingSequence(string $datePrefix): int
    {
        $existingPoNumbers = PurchaseOrder::withTrashed()
            ->where('po_number', 'like', "PO-{$datePrefix}-%")
            ->pluck('po_number');

        $maxSeq = 0;
        foreach ($existingPoNumbers as $poNum) {
            if (preg_match('/-(\d+)$/', (string) $poNum, $matches)) {
                $maxSeq = max($maxSeq, (int) $matches[1]);
            }
        }

        return $maxSeq;
    }
}
