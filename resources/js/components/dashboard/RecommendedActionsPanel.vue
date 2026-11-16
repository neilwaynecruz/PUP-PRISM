<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    Calendar,
    ClipboardList,
    Clock3,
    Sparkles,
    TrendingUp,
} from 'lucide-vue-next';
import { index as handoverIndex } from '@/routes/inventory/handover';
import { index as productsIndex } from '@/routes/inventory/products';
import { index as requisitionsIndex } from '@/routes/inventory/requisitions';

defineProps<{
    lowStockCount: number;
    firstLowStockName: string | null;
    nearExpiryBatchCount: number;
    pendingRequisitionsCount: number;
    pendingBookingsCount: number;
}>();
</script>

<template>
    <div class="rounded-2xl border border-border/70 bg-card p-5 shadow-xs">
        <div
            class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary/15 text-primary ring-1 ring-primary/20"
                >
                    <Sparkles class="h-5 w-5" />
                </div>
                <div>
                    <h2 class="text-base font-semibold text-foreground">
                        Recommended actions
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Review items that may need action based on stock levels
                        and pending work.
                    </p>
                </div>
            </div>
            <div
                class="rounded-2xl border border-primary/20 bg-primary/10 px-4 py-2 text-xs font-medium text-primary"
            >
                Based on current system records
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <!-- Observation 1: Low Stock -->
            <div
                class="rounded-2xl border border-l-2 border-border/70 border-l-amber-500/70 bg-muted/20 p-4 transition-[border-color,background-color,box-shadow] hover:border-amber-500/35 hover:bg-amber-500/5 hover:shadow-xs"
            >
                <div class="flex gap-3">
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-500/15 text-amber-600 ring-1 ring-amber-500/20 dark:text-amber-300"
                    >
                        <AlertTriangle class="h-4.5 w-4.5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-semibold text-foreground">
                            Reorder Consumables
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            <span class="font-medium text-foreground">{{
                                lowStockCount > 0 && firstLowStockName
                                    ? firstLowStockName
                                    : 'No low-stock items'
                            }}</span>
                            {{
                                lowStockCount > 0
                                    ? ' is running below reorder threshold.'
                                    : ' detected right now.'
                            }}
                        </p>
                        <Link
                            :href="productsIndex()"
                            class="mt-3 inline-flex items-center gap-1 rounded-full bg-amber-500/15 px-3 py-1 text-xs font-semibold text-amber-700 transition-colors hover:bg-amber-500/25 dark:text-amber-200"
                        >
                            Adjust Stock
                            <TrendingUp class="h-3.5 w-3.5" />
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Observation 2: Near Expiry -->
            <div
                class="rounded-2xl border border-l-2 border-border/70 border-l-rose-500/70 bg-muted/20 p-4 transition-[border-color,background-color,box-shadow] hover:border-rose-500/35 hover:bg-rose-500/5 hover:shadow-xs"
            >
                <div class="flex gap-3">
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-rose-500/15 text-rose-600 ring-1 ring-rose-500/20 dark:text-rose-300"
                    >
                        <Calendar class="h-4.5 w-4.5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-semibold text-foreground">
                            Near-Expiry Warning
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            <span class="font-medium text-foreground"
                                >{{ nearExpiryBatchCount }} batch(es)</span
                            >
                            {{
                                nearExpiryBatchCount > 0
                                    ? ' expire within 30 days.'
                                    : ' are expiring within 30 days.'
                            }}
                        </p>
                        <Link
                            :href="handoverIndex()"
                            class="mt-3 inline-flex items-center gap-1 rounded-full bg-rose-500/15 px-3 py-1 text-xs font-semibold text-rose-700 transition-colors hover:bg-rose-500/25 dark:text-rose-200"
                        >
                            Initiate Handover
                            <Clock3 class="h-3.5 w-3.5" />
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Observation 3: Pending Approvals -->
            <div
                class="rounded-2xl border border-l-2 border-border/70 border-l-primary/70 bg-muted/20 p-4 transition-[border-color,background-color,box-shadow] hover:border-primary/35 hover:bg-primary/5 hover:shadow-xs"
            >
                <div class="flex gap-3">
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-500/15 text-blue-600 ring-1 ring-blue-500/20 dark:text-blue-300"
                    >
                        <Activity class="h-4.5 w-4.5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-semibold text-foreground">
                            Pending Action Items
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            You have
                            <span class="font-medium text-foreground"
                                >{{
                                    pendingRequisitionsCount
                                }}
                                requisitions</span
                            >
                            and
                            <span class="font-medium text-foreground"
                                >{{ pendingBookingsCount }} bookings</span
                            >
                            awaiting review.
                        </p>
                        <Link
                            :href="requisitionsIndex()"
                            class="mt-3 inline-flex items-center gap-1 rounded-full bg-blue-500/15 px-3 py-1 text-xs font-semibold text-blue-700 transition-colors hover:bg-blue-500/25 dark:text-blue-200"
                        >
                            Approve Requisitions
                            <ClipboardList class="h-3.5 w-3.5" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
