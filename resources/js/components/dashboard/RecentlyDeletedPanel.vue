<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { RotateCcw } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { trash as inventoryTrash } from '@/routes/inventory';

export type RecentlyDeletedItem = {
    id: number;
    type: string;
    name: string;
    deleted_at: string;
    deleted_by: string;
    restore_url: string;
};

defineProps<{
    items: RecentlyDeletedItem[];
}>();

function deletedBadgeClass(type: string): string {
    const value = type.toLowerCase();

    if (value === 'product') {
        return 'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-300';
    }

    if (value === 'booking') {
        return 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    if (value === 'requisition') {
        return 'border-primary/20 bg-primary/10 text-primary';
    }

    return 'border-slate-500/20 bg-slate-500/10 text-slate-700 dark:text-slate-300';
}

function restoreItem(url: string): void {
    router.put(url, {}, { preserveScroll: true });
}
</script>

<template>
    <div class="rounded-2xl border border-border/70 bg-card p-5 shadow-xs">
        <div class="mb-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div
                    class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-500/15 text-slate-600 ring-1 ring-slate-500/20 dark:text-slate-300"
                >
                    <RotateCcw class="h-4 w-4" />
                </div>
                <div>
                    <div class="text-sm font-semibold tracking-tight">
                        Recently Deleted
                    </div>
                    <div class="text-xs text-muted-foreground">
                        Recoverable inventory records
                    </div>
                </div>
            </div>
            <Button
                variant="ghost"
                size="sm"
                as-child
                class="rounded-xl border border-slate-500/20 bg-slate-500/10 text-slate-700 hover:bg-slate-500/15 dark:text-slate-200"
            >
                <Link :href="inventoryTrash()">View Trash</Link>
            </Button>
        </div>
        <ul class="space-y-2 text-sm">
            <li
                v-for="item in items"
                :key="`${item.type}-${item.id}`"
                class="flex items-center justify-between gap-3 rounded-2xl border border-border/40 bg-background/50 p-3 transition-colors hover:border-slate-500/25 hover:bg-slate-500/5"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold tracking-wide uppercase"
                        :class="deletedBadgeClass(item.type)"
                    >
                        {{ item.type }}
                    </span>
                    <div>
                        <div class="font-medium">{{ item.name }}</div>
                        <div class="text-xs text-muted-foreground">
                            <template
                                v-if="
                                    item.deleted_by &&
                                    item.deleted_by !== 'Unknown'
                                "
                            >
                                Deleted by {{ item.deleted_by }} ·
                            </template>
                            {{ item.deleted_at }}
                        </div>
                    </div>
                </div>
                <Button
                    variant="ghost"
                    size="sm"
                    class="h-8 rounded-xl border border-emerald-500/20 bg-emerald-500/10 text-xs font-semibold text-emerald-700 hover:bg-emerald-500/15 dark:text-emerald-300"
                    @click="restoreItem(item.restore_url)"
                >
                    Restore
                </Button>
            </li>
        </ul>
    </div>
</template>
