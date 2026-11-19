<script setup lang="ts">
import { AlertOctagon } from 'lucide-vue-next';
import { formatDisplayDateTime } from '@/lib/dateFormatting';

export type DashboardAlertItem = {
    id: number;
    type: string;
    message: string;
    detected_at: string;
};

defineProps<{
    alerts: DashboardAlertItem[];
}>();

function alertBadgeClass(type: string): string {
    const value = type.toLowerCase();

    if (value.includes('expiry') || value.includes('expired')) {
        return 'border-rose-500/20 bg-rose-500/10 text-rose-700 dark:text-rose-300';
    }

    if (value.includes('stock') || value.includes('low')) {
        return 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    if (value.includes('forecast')) {
        return 'border-primary/20 bg-primary/10 text-primary';
    }

    return 'border-blue-500/20 bg-blue-500/10 text-blue-700 dark:text-blue-300';
}
</script>

<template>
    <div class="rounded-2xl border border-border/70 bg-card p-5 shadow-xs">
        <div class="mb-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div
                    class="flex h-8 w-8 items-center justify-center rounded-xl bg-rose-500/15 text-rose-600 ring-1 ring-rose-500/20 dark:text-rose-300"
                >
                    <AlertOctagon class="h-4 w-4" />
                </div>
                <div>
                    <div class="text-sm font-semibold tracking-tight">
                        System Alerts
                    </div>
                    <div class="text-xs text-muted-foreground">
                        Active operational warnings
                    </div>
                </div>
            </div>
        </div>
        <div
            v-if="alerts.length === 0"
            class="rounded-2xl border border-dashed border-emerald-500/20 bg-emerald-500/5 px-5 py-8 text-center text-sm text-muted-foreground"
        >
            No active alerts.
        </div>
        <ul v-else class="space-y-3 text-sm">
            <li
                v-for="a in alerts"
                :key="a.id"
                class="rounded-2xl border border-rose-500/15 bg-background/50 p-4 transition-colors hover:border-rose-500/30 hover:bg-rose-500/5"
            >
                <div
                    class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                >
                    <span
                        class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase"
                        :class="alertBadgeClass(a.type)"
                    >
                        {{ a.type }}
                    </span>
                    <span>{{ formatDisplayDateTime(a.detected_at) }}</span>
                </div>
                <div class="mt-1.5 leading-relaxed">{{ a.message }}</div>
            </li>
        </ul>
    </div>
</template>
