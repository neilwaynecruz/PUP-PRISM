<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronDown,
    Download,
    History,
    RotateCcw,
    ShieldAlert,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
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
import { useAppNavigation } from '@/composables/useAppNavigation';
import { shouldApplyDebouncedVisit } from '@/lib/inertiaNavigation';
import {
    exportMethod as auditLogsExport,
    index as auditLogsIndex,
} from '@/routes/inventory/audit-logs';

interface LogChange {
    field: string;
    label: string;
    old_value: string;
    new_value: string;
}

type ActionTone = 'danger' | 'success' | 'info' | 'movement' | 'neutral';

interface LogEntry {
    id: number;
    action: string;
    action_label: string;
    action_tone: ActionTone;
    model_type: string;
    model_label: string;
    model_id: number | null;
    description: string;
    user: { id: number; name: string; email: string } | null;
    created_at: string;
    ip_address: string | null;
    changes: LogChange[];
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

type Option = { value: string; label: string };

const props = defineProps<{
    logs: {
        data: LogEntry[];
        links: PaginationLink[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    filters: {
        search: string;
        action: string;
        model_type: string;
        user_id: number;
        date_from: string;
        date_to: string;
        sort: 'newest' | 'oldest';
    };
    filterOptions: {
        actions: Option[];
        modelTypes: Option[];
        users: { id: number; name: string; email: string }[];
    };
    alerts: {
        title: string;
        description: string;
        severity: 'high' | 'medium' | 'low';
    }[];
}>();

const search = ref(props.filters.search);
const action = ref(props.filters.action || 'all');
const modelType = ref(props.filters.model_type || 'all');
const userId = ref(
    props.filters.user_id > 0 ? String(props.filters.user_id) : 'all',
);
const dateFrom = ref(props.filters.date_from);
const dateTo = ref(props.filters.date_to);
const sort = ref<'newest' | 'oldest'>(props.filters.sort ?? 'newest');
const isRefreshing = ref(false);
const lastUpdatedAt = ref<Date | null>(null);
const expandedLogIds = ref<Set<number>>(new Set());
const { isNavigating } = useAppNavigation();

let pollTimer: number | null = null;

const hasActiveFilters = computed(() =>
    Boolean(
        search.value ||
        action.value !== 'all' ||
        modelType.value !== 'all' ||
        userId.value !== 'all' ||
        dateFrom.value ||
        dateTo.value,
    ),
);

const dateRangeInvalid = computed(() =>
    Boolean(dateFrom.value && dateTo.value && dateTo.value < dateFrom.value),
);

const resultSummary = computed(() => {
    if (props.logs.total === 0) {
        return 'No matching activity';
    }

    return `Showing ${props.logs.from ?? 0}–${props.logs.to ?? 0} of ${props.logs.total.toLocaleString()} entries`;
});

function currentQuery() {
    return {
        search: search.value || undefined,
        action: action.value === 'all' ? undefined : action.value,
        model_type: modelType.value === 'all' ? undefined : modelType.value,
        user_id: userId.value === 'all' ? undefined : userId.value,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
        sort: sort.value === 'newest' ? undefined : sort.value,
    };
}

function applyFilters(): void {
    if (dateRangeInvalid.value) {
        return;
    }

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
    action.value = 'all';
    modelType.value = 'all';
    userId.value = 'all';
    dateFrom.value = '';
    dateTo.value = '';
    applyFilters();
}

// Selects apply immediately; free-text search and dates apply on Enter or "Apply".
watch([action, modelType, userId, sort], applyFilters);

function setQuickRange(days: number): void {
    const today = new Date();
    const from = new Date();
    from.setDate(today.getDate() - (days - 1));
    dateFrom.value = toDateInput(from);
    dateTo.value = toDateInput(today);
    applyFilters();
}

function toDateInput(date: Date): string {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

function toggleChanges(logId: number): void {
    const next = new Set(expandedLogIds.value);

    if (next.has(logId)) {
        next.delete(logId);
    } else {
        next.add(logId);
    }

    expandedLogIds.value = next;
}

function pollLogs(): void {
    if (
        document.visibilityState !== 'visible' ||
        isNavigating.value ||
        !shouldApplyDebouncedVisit('/inventory/audit-logs')
    ) {
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
        return 'Refreshes every 15 seconds while this page is open.';
    }

    return `Last refreshed ${lastUpdatedAt.value.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
    })}`;
}

const toneClasses: Record<ActionTone, string> = {
    danger: 'border-rose-500/25 bg-rose-500/10 text-rose-700 dark:text-rose-300',
    success:
        'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    info: 'border-sky-500/25 bg-sky-500/10 text-sky-700 dark:text-sky-300',
    movement:
        'border-amber-500/30 bg-amber-500/10 text-amber-800 dark:text-amber-300',
    neutral: 'border-border bg-muted/60 text-muted-foreground',
};

function actionClass(tone: ActionTone): string {
    return toneClasses[tone] ?? toneClasses.neutral;
}

onMounted(() => {
    startPolling();
    document.addEventListener('visibilitychange', handleVisibilityChange);
});

onUnmounted(() => {
    stopPolling();
    document.removeEventListener('visibilitychange', handleVisibilityChange);
});

watch(isNavigating, (navigating) => {
    if (navigating) {
        stopPolling();

        return;
    }

    if (!shouldApplyDebouncedVisit('/inventory/audit-logs')) {
        return;
    }

    if (document.visibilityState === 'visible') {
        startPolling();
    }
});
</script>

<template>
    <Head title="Audit Log" />

    <div class="flex flex-col gap-6 p-4 sm:p-6" data-testid="audit-logs-page">
        <div
            class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"
        >
            <Heading
                variant="small"
                title="Audit log"
                description="See who did what, to which record, and when. Entries are read-only and cannot be edited."
            />
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs text-muted-foreground">
                    {{ formatRelativeUpdate() }}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    class="rounded-lg"
                    data-testid="audit-export-button"
                    @click="exportCsv"
                >
                    <Download class="mr-1.5 h-3.5 w-3.5" />
                    Export CSV
                </Button>
            </div>
        </div>

        <div
            v-if="alerts.length"
            class="grid gap-2 md:grid-cols-2 xl:grid-cols-3"
        >
            <div
                v-for="(alert, i) in alerts"
                :key="i"
                class="flex items-start gap-3 rounded-xl border p-4"
                :class="{
                    'border-rose-500/25 bg-rose-500/5 text-rose-800 dark:text-rose-300':
                        alert.severity === 'high',
                    'border-amber-500/30 bg-amber-500/5 text-amber-800 dark:text-amber-300':
                        alert.severity === 'medium',
                    'border-sky-500/25 bg-sky-500/5 text-sky-800 dark:text-sky-300':
                        alert.severity === 'low',
                }"
            >
                <ShieldAlert class="mt-0.5 h-4 w-4 shrink-0" />
                <div>
                    <div class="text-sm font-semibold">{{ alert.title }}</div>
                    <div class="text-xs opacity-85">
                        {{ alert.description }}
                    </div>
                </div>
            </div>
        </div>

        <form
            class="rounded-xl border border-border/60 bg-card p-4 shadow-sm"
            @submit.prevent="applyFilters"
        >
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="grid gap-1.5 sm:col-span-2">
                    <Label for="audit-search">Search</Label>
                    <Input
                        id="audit-search"
                        v-model="search"
                        type="search"
                        data-shortcut="search"
                        placeholder="Description, user, email, record ID, or IP address"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="audit-user">Performed by</Label>
                    <Select v-model="userId">
                        <SelectTrigger id="audit-user">
                            <SelectValue placeholder="Anyone" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Anyone</SelectItem>
                            <SelectItem
                                v-for="user in filterOptions.users"
                                :key="user.id"
                                :value="String(user.id)"
                            >
                                {{ user.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="audit-action">Action</Label>
                    <Select v-model="action">
                        <SelectTrigger id="audit-action">
                            <SelectValue placeholder="All actions" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All actions</SelectItem>
                            <SelectItem
                                v-for="item in filterOptions.actions"
                                :key="item.value"
                                :value="item.value"
                            >
                                {{ item.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="audit-model">Record type</Label>
                    <Select v-model="modelType">
                        <SelectTrigger id="audit-model">
                            <SelectValue placeholder="All record types" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all"
                                >All record types</SelectItem
                            >
                            <SelectItem
                                v-for="item in filterOptions.modelTypes"
                                :key="item.value"
                                :value="item.value"
                            >
                                {{ item.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="audit-date-from">From</Label>
                    <Input
                        id="audit-date-from"
                        v-model="dateFrom"
                        type="date"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="audit-date-to">To</Label>
                    <Input
                        id="audit-date-to"
                        v-model="dateTo"
                        type="date"
                        :min="dateFrom || undefined"
                        :aria-invalid="dateRangeInvalid"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="audit-sort">Order</Label>
                    <Select v-model="sort">
                        <SelectTrigger id="audit-sort">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="newest">Newest first</SelectItem>
                            <SelectItem value="oldest">Oldest first</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
            <p
                v-if="dateRangeInvalid"
                class="mt-2 text-xs font-medium text-rose-600 dark:text-rose-400"
            >
                The end date must be on or after the start date.
            </p>
            <div
                class="mt-4 flex flex-col gap-3 border-t border-border/50 pt-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="mr-1 text-xs text-muted-foreground"
                        >Quick range:</span
                    >
                    <Button
                        v-for="range in [
                            { days: 1, label: 'Today' },
                            { days: 7, label: 'Last 7 days' },
                            { days: 30, label: 'Last 30 days' },
                        ]"
                        :key="range.days"
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-7 rounded-full px-3 text-xs"
                        @click="setQuickRange(range.days)"
                    >
                        {{ range.label }}
                    </Button>
                </div>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <Button
                        v-if="hasActiveFilters"
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="resetFilters"
                    >
                        <RotateCcw class="mr-1.5 h-3.5 w-3.5" />
                        Clear filters
                    </Button>
                    <Button
                        type="submit"
                        size="sm"
                        :disabled="isRefreshing || dateRangeInvalid"
                    >
                        Apply filters
                    </Button>
                </div>
            </div>
        </form>

        <div class="rounded-xl border border-border/60 bg-card shadow-sm">
            <div
                class="flex items-center justify-between gap-3 border-b border-border/50 px-4 py-3"
            >
                <div class="flex items-center gap-2 text-sm font-semibold">
                    <History class="h-4 w-4 text-primary" />
                    Activity
                </div>
                <div
                    class="text-xs text-muted-foreground tabular-nums"
                    data-testid="audit-result-summary"
                >
                    {{ resultSummary }}
                </div>
            </div>

            <div v-if="isRefreshing" class="p-4">
                <TableSkeleton :rows="6" :columns="5" />
            </div>

            <div
                v-else-if="logs.data.length === 0"
                class="flex flex-col items-center gap-2 px-4 py-14 text-center"
            >
                <History class="h-8 w-8 text-muted-foreground/50" />
                <p class="text-sm font-medium">No activity found</p>
                <p class="max-w-sm text-xs text-muted-foreground">
                    {{
                        hasActiveFilters
                            ? 'No entries match these filters. Try widening the date range or clearing filters.'
                            : 'Activity will appear here as people use PRISM.'
                    }}
                </p>
            </div>

            <ul v-else class="divide-y divide-border/60">
                <li
                    v-for="log in logs.data"
                    :key="log.id"
                    class="px-4 py-3.5"
                    data-testid="audit-log-entry"
                >
                    <div
                        class="grid gap-3 md:grid-cols-[10.5rem_minmax(0,1fr)_auto] md:items-start"
                    >
                        <div class="text-xs text-muted-foreground tabular-nums">
                            <div class="font-medium text-foreground/80">
                                {{ formatDate(log.created_at) }}
                            </div>
                            <div v-if="log.ip_address" class="mt-0.5 font-mono">
                                {{ log.ip_address }}
                            </div>
                        </div>

                        <div class="min-w-0 space-y-1.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-semibold"
                                    :class="actionClass(log.action_tone)"
                                    :title="log.action"
                                >
                                    {{ log.action_label }}
                                </span>
                                <span
                                    class="text-xs font-medium text-muted-foreground"
                                >
                                    {{ log.model_label
                                    }}<template v-if="log.model_id">
                                        #{{ log.model_id }}</template
                                    >
                                </span>
                            </div>
                            <p class="text-sm wrap-break-word text-foreground">
                                {{ log.description }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                by
                                <span class="font-medium text-foreground/80">{{
                                    log.user?.name ?? 'System'
                                }}</span>
                                <template v-if="log.user?.email">
                                    · {{ log.user.email }}</template
                                >
                            </p>
                        </div>

                        <div class="md:text-right">
                            <Button
                                v-if="log.changes.length > 0"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-7 rounded-lg px-2 text-xs"
                                :aria-expanded="expandedLogIds.has(log.id)"
                                :aria-controls="`audit-changes-${log.id}`"
                                @click="toggleChanges(log.id)"
                            >
                                {{ log.changes.length }}
                                {{
                                    log.changes.length === 1
                                        ? 'change'
                                        : 'changes'
                                }}
                                <ChevronDown
                                    class="ml-1 h-3.5 w-3.5 transition-transform"
                                    :class="{
                                        'rotate-180': expandedLogIds.has(
                                            log.id,
                                        ),
                                    }"
                                />
                            </Button>
                        </div>
                    </div>

                    <div
                        v-if="
                            log.changes.length > 0 && expandedLogIds.has(log.id)
                        "
                        :id="`audit-changes-${log.id}`"
                        class="mt-3 overflow-x-auto rounded-lg border border-border/60 md:ml-45"
                    >
                        <table class="w-full min-w-md text-xs">
                            <thead
                                class="bg-muted/50 text-left text-muted-foreground"
                            >
                                <tr
                                    class="[&>th]:px-3 [&>th]:py-2 [&>th]:font-semibold"
                                >
                                    <th class="w-1/4">Field</th>
                                    <th class="w-[37.5%]">Before</th>
                                    <th class="w-[37.5%]">After</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/50">
                                <tr
                                    v-for="change in log.changes"
                                    :key="`${log.id}-${change.field}`"
                                    class="align-top [&>td]:px-3 [&>td]:py-2"
                                >
                                    <td class="font-medium">
                                        {{ change.label }}
                                    </td>
                                    <td
                                        class="wrap-break-word text-rose-700/90 dark:text-rose-300/90"
                                    >
                                        {{ change.old_value }}
                                    </td>
                                    <td
                                        class="wrap-break-word text-emerald-700 dark:text-emerald-300"
                                    >
                                        {{ change.new_value }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </li>
            </ul>
        </div>

        <nav
            v-if="logs.links.length > 3"
            aria-label="Audit log pages"
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
        </nav>
    </div>
</template>
