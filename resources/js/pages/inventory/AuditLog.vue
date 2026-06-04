<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import TableSkeleton from '@/components/TableSkeleton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { exportMethod as auditLogsExport, index as auditLogsIndex } from '@/routes/inventory/audit-logs';

interface LogChange {
    field: string;
    label: string;
    old_value: string;
    new_value: string;
}

interface LogEntry {
    id: number;
    action: string;
    model_type: string;
    model_label: string;
    model_id: number | null;
    description: string;
    user: { id: number; name: string; email: string } | null;
    created_at: string;
    ip_address: string | null;
    changes: LogChange[];
    raw_old_values: Record<string, unknown> | null;
    raw_new_values: Record<string, unknown> | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    logs: {
        data: LogEntry[];
        links: PaginationLink[];
        current_page: number;
        last_page: number;
    };
    filters: {
        search: string;
        action: string;
        model_type: string;
        date_from: string;
        date_to: string;
    };
    filterOptions: {
        actions: string[];
        modelTypes: string[];
    };
    alerts: {
        title: string;
        description: string;
        severity: 'high' | 'medium' | 'low';
    }[];
}>();

const search = ref(props.filters.search);
const action = ref(props.filters.action);
const modelType = ref(props.filters.model_type);
const dateFrom = ref(props.filters.date_from);
const dateTo = ref(props.filters.date_to);
const isRefreshing = ref(false);
const lastUpdatedAt = ref<Date | null>(null);

let pollTimer: number | null = null;

const hasActiveFilters = computed(() => {
    return Boolean(
        search.value ||
            action.value ||
            modelType.value ||
            dateFrom.value ||
            dateTo.value,
    );
});

function currentQuery() {
    return {
        search: search.value || undefined,
        action: action.value || undefined,
        model_type: modelType.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
    };
}

function applyFilters(): void {
    router.get(auditLogsIndex().url, currentQuery(), {
        preserveState: true,
        preserveScroll: true,
        onStart: () => {
            isRefreshing.value = true;
        },
        onFinish: () => {
            isRefreshing.value = false;
            lastUpdatedAt.value = new Date();
        },
    });
}

function resetFilters(): void {
    search.value = '';
    action.value = '';
    modelType.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    applyFilters();
}

function pollLogs(): void {
    if (document.visibilityState !== 'visible') {
        return;
    }

    router.reload({
        only: ['logs', 'alerts'],
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            lastUpdatedAt.value = new Date();
        },
    } as Record<string, unknown>);
}

function startPolling(): void {
    stopPolling();
    pollTimer = window.setInterval(pollLogs, 15000);
}

function stopPolling(): void {
    if (pollTimer !== null) {
        window.clearInterval(pollTimer);
        pollTimer = null;
    }
}

function handleVisibilityChange(): void {
    if (document.visibilityState === 'visible') {
        pollLogs();
        startPolling();

        return;
    }

    stopPolling();
}

function exportCsv(): void {
    window.location.href = auditLogsExport.url({ query: currentQuery() });
}

function formatDate(date: string): string {
    return new Date(date).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}

function formatRelativeUpdate(): string {
    if (!lastUpdatedAt.value) {
        return 'Live updates every 15 seconds while this page is visible.';
    }

    return `Updated ${lastUpdatedAt.value.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
    })}`;
}

function formatJson(payload: Record<string, unknown> | null): string {
    return payload ? JSON.stringify(payload, null, 2) : '—';
}

function getActionColor(actionValue: string): string {
    const colors: Record<string, string> = {
        create: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        update: 'bg-blue-500/10 text-blue-700 dark:text-blue-400',
        delete: 'bg-rose-500/10 text-rose-700 dark:text-rose-400',
        restore: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
        force_delete:
            'bg-red-500/10 text-red-700 dark:text-red-400',
        approve: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        reject: 'bg-rose-500/10 text-rose-700 dark:text-rose-400',
        issue: 'bg-sky-500/10 text-sky-700 dark:text-sky-400',
        receive: 'bg-teal-500/10 text-teal-700 dark:text-teal-400',
        transfer:
            'bg-violet-500/10 text-violet-700 dark:text-violet-400',
    };

    return (
        colors[actionValue] ||
        'bg-slate-500/10 text-slate-700 dark:text-slate-400'
    );
}

function getModelTypeColor(type: string): string {
    const colors: Record<string, string> = {
        Product: 'bg-sky-500/10 text-sky-700 dark:text-sky-400',
        Booking: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
        Requisition: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        Asset: 'bg-violet-500/10 text-violet-700 dark:text-violet-400',
        HandoverLog: 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-400',
    };

    return colors[type] || 'bg-slate-500/10 text-slate-700 dark:text-slate-400';
}

onMounted(() => {
    startPolling();
    document.addEventListener('visibilitychange', handleVisibilityChange);
});

onUnmounted(() => {
    stopPolling();
    document.removeEventListener('visibilitychange', handleVisibilityChange);
});
</script>

<template>
    <Head title="Audit Log" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <Heading
                variant="small"
                title="Audit Log"
                description="Track operational changes with readable field-by-field diffs."
            />
            <div class="text-sm text-muted-foreground">
                {{ formatRelativeUpdate() }}
            </div>
        </div>

        <div v-if="alerts.length" class="flex flex-col gap-2">
            <div
                v-for="(alert, i) in alerts"
                :key="i"
                class="flex items-start gap-3 rounded-lg border p-4"
                :class="{
                    'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300':
                        alert.severity === 'high',
                    'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300':
                        alert.severity === 'medium',
                    'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300':
                        alert.severity === 'low',
                }"
            >
                <div>
                    <div class="text-sm font-semibold">{{ alert.title }}</div>
                    <div class="text-sm opacity-80">
                        {{ alert.description }}
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-border/60 bg-card p-4 shadow-sm">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div class="grid gap-2">
                    <Label for="search">Search</Label>
                    <Input
                        id="search"
                        v-model="search"
                        data-shortcut="search"
                        placeholder="Search activity or user..."
                        @keyup.enter="applyFilters"
                    />
                </div>
                <div class="grid gap-2">
                    <Label>Action</Label>
                    <Select v-model="action">
                        <SelectTrigger>
                            <SelectValue placeholder="All actions" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">All actions</SelectItem>
                            <SelectItem
                                v-for="item in filterOptions.actions"
                                :key="item"
                                :value="item"
                            >
                                {{ item }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2">
                    <Label>Model Type</Label>
                    <Select v-model="modelType">
                        <SelectTrigger>
                            <SelectValue placeholder="All types" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">All types</SelectItem>
                            <SelectItem
                                v-for="item in filterOptions.modelTypes"
                                :key="item"
                                :value="item"
                            >
                                {{ item }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2">
                    <Label for="date_from">Date From</Label>
                    <Input id="date_from" v-model="dateFrom" type="date" />
                </div>
                <div class="grid gap-2">
                    <Label for="date_to">Date To</Label>
                    <Input id="date_to" v-model="dateTo" type="date" />
                </div>
            </div>
            <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
                <Button
                    v-if="hasActiveFilters"
                    variant="ghost"
                    @click="resetFilters"
                >
                    Reset
                </Button>
                <Button variant="outline" @click="exportCsv">Export CSV</Button>
                <Button :disabled="isRefreshing" @click="applyFilters">
                    Apply Filters
                </Button>
            </div>
        </div>

        <div
            class="rounded-xl border border-border/60 bg-card shadow-sm"
        >
            <div v-if="isRefreshing" class="p-4">
                <TableSkeleton :rows="6" :columns="6" />
            </div>

            <template v-else>
                <div class="grid gap-3 p-4 md:hidden">
                    <div
                        v-if="logs.data.length === 0"
                        class="rounded-xl border border-dashed border-border/60 p-8 text-center text-sm text-muted-foreground"
                    >
                        No audit log entries found.
                    </div>

                    <div
                        v-for="log in logs.data"
                        :key="log.id"
                        class="rounded-xl border border-border/40 p-4 text-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-medium">
                                    {{ log.user?.name ?? 'System' }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ formatDate(log.created_at) }}
                                </div>
                            </div>
                            <span
                                class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-medium uppercase"
                                :class="getActionColor(log.action)"
                            >
                                {{ log.action }}
                            </span>
                        </div>

                        <div class="mt-3 flex items-center gap-2">
                            <span
                                class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-medium"
                                :class="getModelTypeColor(log.model_type)"
                            >
                                {{ log.model_label }}
                            </span>
                            <span
                                v-if="log.model_id"
                                class="text-xs text-muted-foreground"
                            >
                                #{{ log.model_id }}
                            </span>
                        </div>

                        <div class="mt-3 text-sm">{{ log.description }}</div>

                        <div
                            v-if="log.changes.length > 0"
                            class="mt-3 space-y-2 rounded-lg bg-muted/40 p-3"
                        >
                            <div
                                v-for="change in log.changes"
                                :key="`${log.id}-${change.field}`"
                                class="text-sm"
                            >
                                <span class="font-medium">{{ change.label }}:</span>
                                <span class="text-muted-foreground">
                                    "{{ change.old_value }}" → "{{ change.new_value }}"
                                </span>
                            </div>
                        </div>

                        <details
                            v-if="log.raw_old_values || log.raw_new_values"
                            class="mt-3 rounded-lg border border-border/50 p-3"
                        >
                            <summary class="cursor-pointer text-sm font-medium">
                                Advanced details
                            </summary>
                            <div class="mt-3 grid gap-3">
                                <div>
                                    <div class="mb-1 text-xs font-semibold uppercase text-muted-foreground">
                                        Previous
                                    </div>
                                    <pre class="overflow-x-auto rounded bg-muted/60 p-3 text-xs">{{ formatJson(log.raw_old_values) }}</pre>
                                </div>
                                <div>
                                    <div class="mb-1 text-xs font-semibold uppercase text-muted-foreground">
                                        New
                                    </div>
                                    <pre class="overflow-x-auto rounded bg-muted/60 p-3 text-xs">{{ formatJson(log.raw_new_values) }}</pre>
                                </div>
                            </div>
                        </details>

                        <div class="mt-3 text-xs text-muted-foreground">
                            IP: {{ log.ip_address ?? '—' }}
                        </div>
                    </div>
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full text-sm">
                        <thead class="bg-muted/40 text-left">
                            <tr
                                class="text-xs font-semibold tracking-wider text-muted-foreground/80 uppercase [&>th]:px-4 [&>th]:py-3"
                            >
                                <th>Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Model</th>
                                <th>Activity</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            <tr v-if="logs.data.length === 0">
                                <td
                                    colspan="6"
                                    class="px-4 py-8 text-center text-sm text-muted-foreground"
                                >
                                    No audit log entries found.
                                </td>
                            </tr>
                            <tr
                                v-for="log in logs.data"
                                :key="log.id"
                                class="align-top [&>td]:px-4 [&>td]:py-3"
                            >
                                <td
                                    class="text-xs whitespace-nowrap text-muted-foreground"
                                >
                                    {{ formatDate(log.created_at) }}
                                </td>
                                <td>
                                    <div class="font-medium">
                                        {{ log.user?.name ?? 'System' }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ log.user?.email }}
                                    </div>
                                </td>
                                <td>
                                    <span
                                        class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-medium tracking-wide uppercase"
                                        :class="getActionColor(log.action)"
                                    >
                                        {{ log.action }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-medium"
                                            :class="getModelTypeColor(log.model_type)"
                                        >
                                            {{ log.model_label }}
                                        </span>
                                        <span
                                            v-if="log.model_id"
                                            class="text-xs text-muted-foreground"
                                        >
                                            #{{ log.model_id }}
                                        </span>
                                    </div>
                                </td>
                                <td class="max-w-xl">
                                    <div class="space-y-2">
                                        <div class="font-medium">
                                            {{ log.description }}
                                        </div>
                                        <div
                                            v-if="log.changes.length > 0"
                                            class="space-y-1 rounded-lg bg-muted/40 p-3"
                                        >
                                            <div
                                                v-for="change in log.changes"
                                                :key="`${log.id}-${change.field}`"
                                                class="text-sm"
                                            >
                                                <span class="font-medium">{{ change.label }}:</span>
                                                <span class="text-muted-foreground">
                                                    "{{ change.old_value }}" → "{{ change.new_value }}"
                                                </span>
                                            </div>
                                        </div>
                                        <details
                                            v-if="log.raw_old_values || log.raw_new_values"
                                            class="rounded-lg border border-border/50 p-3"
                                        >
                                            <summary class="cursor-pointer text-sm font-medium">
                                                Advanced details
                                            </summary>
                                            <div class="mt-3 grid gap-3 xl:grid-cols-2">
                                                <div>
                                                    <div class="mb-1 text-xs font-semibold uppercase text-muted-foreground">
                                                        Previous
                                                    </div>
                                                    <pre class="overflow-x-auto rounded bg-muted/60 p-3 text-xs">{{ formatJson(log.raw_old_values) }}</pre>
                                                </div>
                                                <div>
                                                    <div class="mb-1 text-xs font-semibold uppercase text-muted-foreground">
                                                        New
                                                    </div>
                                                    <pre class="overflow-x-auto rounded bg-muted/60 p-3 text-xs">{{ formatJson(log.raw_new_values) }}</pre>
                                                </div>
                                            </div>
                                        </details>
                                    </div>
                                </td>
                                <td class="text-xs text-muted-foreground">
                                    {{ log.ip_address ?? '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>

        <div
            v-if="logs.links.length > 3"
            class="flex flex-wrap items-center justify-center gap-1"
        >
            <Button
                v-for="(link, i) in logs.links"
                :key="i"
                variant="ghost"
                size="sm"
                :disabled="!link.url"
                as-child
                class="h-8 rounded-lg text-xs"
                :class="
                    link.active ? 'bg-primary/10 font-medium text-primary' : ''
                "
            >
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    preserve-state
                >
                    <span v-html="link.label" />
                </Link>
                <span v-else v-html="link.label" />
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Showing page {{ logs.current_page }} of {{ logs.last_page }}
        </div>
    </div>
</template>
