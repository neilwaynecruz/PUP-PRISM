<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCircle2,
    CircleHelp,
    Database,
    HardDrive,
    Layers,
    ListChecks,
    Radio,
    RefreshCw,
    Server,
    TerminalSquare,
    Timer,
    XCircle,
} from 'lucide-vue-next';
import type { Component } from 'vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { health as operationsHealth } from '@/routes/admin/operations';

type CheckStatus = 'healthy' | 'warning' | 'critical' | 'unknown';

type HealthCheck = {
    key: string;
    label: string;
    status: CheckStatus;
    summary: string;
    details: { label: string; value: string }[];
    hint: string | null;
};

type SchedulerRow = {
    command: string;
    label: string;
    schedule: string;
    last_run_at: string | null;
    status: CheckStatus;
    message: string;
};

type FailedJob = {
    uuid: string;
    connection: string;
    queue: string;
    job_name: string | null;
    exception_class: string | null;
    failed_at: string;
};

type HealthPayload = {
    status: 'ok' | 'degraded' | 'critical';
    summary: string;
    checked_at: string;
    counts: Record<CheckStatus, number>;
    checks: HealthCheck[];
    scheduler: SchedulerRow[];
    queue_operations: {
        connection: string;
        pending_jobs_count: number;
        pending_by_queue: Record<string, number>;
        reserved_jobs_count: number;
        possibly_stuck_jobs_count: number;
        failed_jobs_count: number;
        oldest_pending_job_age_seconds: number | null;
        recent_failed_jobs: FailedJob[];
        recommended_worker_command: string;
    };
};

const props = defineProps<{
    health: HealthPayload;
    tracked_commands: string[];
}>();

defineOptions({
    name: 'AdminOperationsHealthPage',
    inheritAttrs: false,
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: operationsHealth() },
            { title: 'Operations health', href: operationsHealth() },
        ],
    },
});

const AUTO_REFRESH_SECONDS = 60;
const isRefreshing = ref(false);
const autoRefresh = ref(true);
let refreshTimer: number | undefined;

function refresh(): void {
    if (isRefreshing.value) {
        return;
    }

    router.reload({
        only: ['health'],
        onStart: () => {
            isRefreshing.value = true;
        },
        onFinish: () => {
            isRefreshing.value = false;
        },
    });
}

function scheduleAutoRefresh(): void {
    window.clearInterval(refreshTimer);

    if (autoRefresh.value) {
        refreshTimer = window.setInterval(() => {
            if (document.visibilityState === 'visible') {
                refresh();
            }
        }, AUTO_REFRESH_SECONDS * 1000);
    }
}

watch(autoRefresh, scheduleAutoRefresh);
onMounted(scheduleAutoRefresh);
onBeforeUnmount(() => window.clearInterval(refreshTimer));

const statusMeta: Record<
    CheckStatus,
    { label: string; icon: Component; badge: string; accent: string }
> = {
    healthy: {
        label: 'Healthy',
        icon: CheckCircle2,
        badge: 'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        accent: 'text-emerald-600 dark:text-emerald-400',
    },
    warning: {
        label: 'Needs attention',
        icon: AlertTriangle,
        badge: 'border-amber-500/30 bg-amber-500/10 text-amber-800 dark:text-amber-300',
        accent: 'text-amber-600 dark:text-amber-400',
    },
    critical: {
        label: 'Failing',
        icon: XCircle,
        badge: 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300',
        accent: 'text-rose-600 dark:text-rose-400',
    },
    unknown: {
        label: 'Not verified',
        icon: CircleHelp,
        badge: 'border-border bg-muted/70 text-muted-foreground',
        accent: 'text-muted-foreground',
    },
};

const checkIcons: Record<string, Component> = {
    application: Server,
    database: Database,
    cache: Layers,
    queue: ListChecks,
    scheduler: Timer,
    realtime: Radio,
    storage: HardDrive,
};

const overall = computed(() => {
    switch (props.health.status) {
        case 'critical':
            return {
                title: 'Service disruption',
                classes: 'border-rose-500/30 bg-rose-500/6',
                icon: XCircle,
                iconClass: 'text-rose-600 dark:text-rose-400',
            };
        case 'degraded':
            return {
                title: 'Running with warnings',
                classes: 'border-amber-500/30 bg-amber-500/6',
                icon: AlertTriangle,
                iconClass: 'text-amber-600 dark:text-amber-400',
            };
        default:
            return {
                title: 'Operating normally',
                classes: 'border-emerald-500/30 bg-emerald-500/6',
                icon: CheckCircle2,
                iconClass: 'text-emerald-600 dark:text-emerald-400',
            };
    }
});

const queueRows = computed(() =>
    Object.entries(props.health.queue_operations.pending_by_queue).map(
        ([queue, count]) => ({ queue, count }),
    ),
);

function formatTimestamp(value: string | null): string {
    if (!value) {
        return 'Never';
    }

    return new Date(value).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function formatAge(seconds: number | null): string {
    if (seconds === null) {
        return 'None waiting';
    }

    if (seconds < 60) {
        return `${seconds} sec`;
    }

    if (seconds < 3600) {
        return `${Math.floor(seconds / 60)} min`;
    }

    return `${Math.floor(seconds / 3600)} hr ${Math.floor((seconds % 3600) / 60)} min`;
}
</script>

<template>
    <Head title="Operations health" />

    <div
        class="mx-auto flex w-full max-w-400 flex-col gap-6 p-4 sm:p-6 lg:p-8"
        data-testid="operations-health-page"
    >
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
        >
            <Heading
                variant="small"
                title="Operations health"
                description="Live checks of the database, cache, queue, scheduled tasks, realtime server, and storage."
            />
            <div class="flex flex-wrap items-center gap-3">
                <label
                    class="flex items-center gap-2 text-xs text-muted-foreground"
                >
                    <Checkbox
                        :model-value="autoRefresh"
                        @update:model-value="autoRefresh = $event === true"
                    />
                    Refresh every {{ AUTO_REFRESH_SECONDS }} seconds
                </label>
                <Button
                    variant="outline"
                    size="sm"
                    class="rounded-lg"
                    :disabled="isRefreshing"
                    data-testid="operations-health-refresh"
                    @click="refresh"
                >
                    <RefreshCw
                        class="mr-1.5 h-3.5 w-3.5"
                        :class="{ 'animate-spin': isRefreshing }"
                    />
                    {{ isRefreshing ? 'Checking…' : 'Run checks now' }}
                </Button>
            </div>
        </div>

        <section
            class="flex flex-col gap-4 rounded-xl border p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between"
            :class="overall.classes"
            data-testid="operations-health-overall"
            aria-live="polite"
        >
            <div class="flex items-start gap-3">
                <component
                    :is="overall.icon"
                    class="mt-0.5 h-6 w-6 shrink-0"
                    :class="overall.iconClass"
                />
                <div>
                    <h2 class="text-base font-semibold">{{ overall.title }}</h2>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        {{ health.summary }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground/80">
                        Last checked {{ formatTimestamp(health.checked_at) }}
                    </p>
                </div>
            </div>
            <dl class="grid grid-cols-4 gap-2 text-center sm:min-w-88">
                <div
                    v-for="status in [
                        'healthy',
                        'warning',
                        'critical',
                        'unknown',
                    ] as CheckStatus[]"
                    :key="status"
                    class="rounded-lg border border-border/50 bg-background/70 px-2 py-2"
                >
                    <dt
                        class="text-[10px] font-semibold tracking-wide uppercase"
                        :class="statusMeta[status].accent"
                    >
                        {{ statusMeta[status].label }}
                    </dt>
                    <dd class="mt-0.5 text-lg font-semibold tabular-nums">
                        {{ health.counts[status] }}
                    </dd>
                </div>
            </dl>
        </section>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="check in health.checks"
                :key="check.key"
                class="flex flex-col rounded-xl border border-border/60 bg-card p-5 shadow-sm"
                :data-testid="`health-check-${check.key}`"
                :data-status="check.status"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <div
                            class="rounded-lg border border-border/60 bg-muted/40 p-2"
                        >
                            <component
                                :is="checkIcons[check.key] ?? Server"
                                class="h-4 w-4 text-primary"
                            />
                        </div>
                        <h3 class="text-sm font-semibold">{{ check.label }}</h3>
                    </div>
                    <span
                        class="inline-flex shrink-0 items-center gap-1 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold"
                        :class="statusMeta[check.status].badge"
                    >
                        <component
                            :is="statusMeta[check.status].icon"
                            class="h-3 w-3"
                        />
                        {{ statusMeta[check.status].label }}
                    </span>
                </div>
                <p class="mt-3 text-sm text-foreground/90">
                    {{ check.summary }}
                </p>
                <dl
                    v-if="check.details.length"
                    class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs"
                >
                    <template
                        v-for="detail in check.details"
                        :key="detail.label"
                    >
                        <dt class="text-muted-foreground">
                            {{ detail.label }}
                        </dt>
                        <dd
                            class="truncate text-right font-medium"
                            :title="detail.value"
                        >
                            {{ detail.value }}
                        </dd>
                    </template>
                </dl>
                <p
                    v-if="check.hint && check.status !== 'healthy'"
                    class="mt-auto pt-3 text-xs text-muted-foreground"
                >
                    <span class="font-semibold text-foreground/80"
                        >What to do:</span
                    >
                    {{ check.hint }}
                </p>
            </article>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <h2 class="flex items-center gap-2 text-sm font-semibold">
                    <Timer class="h-4 w-4 text-primary" />
                    Scheduled tasks
                </h2>
                <p class="mt-1 text-xs text-muted-foreground">
                    Each task records a heartbeat when it finishes. Tasks are
                    overdue when they miss their expected window.
                </p>
                <ul class="mt-4 divide-y divide-border/60">
                    <li
                        v-for="row in health.scheduler"
                        :key="row.command"
                        class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <div class="text-sm font-medium">
                                {{ row.label }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ row.schedule }} ·
                                <span class="font-mono">{{ row.command }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 sm:text-right">
                            <div class="text-xs text-muted-foreground">
                                <div>
                                    Last run:
                                    {{ formatTimestamp(row.last_run_at) }}
                                </div>
                                <div v-if="row.status === 'warning'">
                                    {{ row.message }}
                                </div>
                            </div>
                            <span
                                class="inline-flex shrink-0 items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                                :class="statusMeta[row.status].badge"
                            >
                                {{
                                    row.status === 'healthy'
                                        ? 'On schedule'
                                        : row.status === 'warning'
                                          ? 'Overdue'
                                          : 'No run yet'
                                }}
                            </span>
                        </div>
                    </li>
                </ul>
            </section>

            <section
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <h2 class="flex items-center gap-2 text-sm font-semibold">
                    <ListChecks class="h-4 w-4 text-primary" />
                    Queue workload
                </h2>
                <p class="mt-1 text-xs text-muted-foreground">
                    Background jobs send notifications and broadcast live
                    updates.
                </p>
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                    <div
                        v-for="metric in [
                            {
                                label: 'Waiting',
                                value: health.queue_operations
                                    .pending_jobs_count,
                            },
                            {
                                label: 'In progress',
                                value: health.queue_operations
                                    .reserved_jobs_count,
                            },
                            {
                                label: 'Possibly stuck',
                                value: health.queue_operations
                                    .possibly_stuck_jobs_count,
                            },
                            {
                                label: 'Failed',
                                value: health.queue_operations
                                    .failed_jobs_count,
                            },
                        ]"
                        :key="metric.label"
                        class="rounded-lg border border-border/50 bg-muted/30 p-3"
                    >
                        <dt class="text-xs text-muted-foreground">
                            {{ metric.label }}
                        </dt>
                        <dd class="mt-1 text-lg font-semibold tabular-nums">
                            {{ metric.value }}
                        </dd>
                    </div>
                </dl>
                <p class="mt-3 text-xs text-muted-foreground">
                    Oldest waiting job:
                    <span class="font-medium text-foreground/80">{{
                        formatAge(
                            health.queue_operations
                                .oldest_pending_job_age_seconds,
                        )
                    }}</span>
                    · Connection:
                    <span class="font-mono">{{
                        health.queue_operations.connection
                    }}</span>
                </p>

                <ul v-if="queueRows.length" class="mt-3 flex flex-wrap gap-2">
                    <li
                        v-for="row in queueRows"
                        :key="row.queue"
                        class="rounded-full border border-border/60 bg-muted/40 px-2.5 py-1 text-xs"
                    >
                        {{ row.queue }}:
                        <span class="font-semibold tabular-nums">{{
                            row.count
                        }}</span>
                    </li>
                </ul>

                <div
                    class="mt-4 rounded-lg border border-border/60 bg-muted/40 px-3 py-3"
                >
                    <div
                        class="mb-1.5 flex items-center gap-2 text-[11px] font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        <TerminalSquare class="h-3.5 w-3.5" />
                        Recommended worker command
                    </div>
                    <code class="font-mono text-xs break-all">
                        {{ health.queue_operations.recommended_worker_command }}
                    </code>
                </div>
            </section>
        </div>

        <section
            class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
        >
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="flex items-center gap-2 text-sm font-semibold">
                        <XCircle class="h-4 w-4 text-primary" />
                        Recent failed jobs
                    </h2>
                    <p class="mt-1 text-xs text-muted-foreground">
                        The five most recent failures. Error messages are hidden
                        here; read them with "php artisan queue:failed".
                    </p>
                </div>
            </div>
            <div
                v-if="health.queue_operations.recent_failed_jobs.length === 0"
                class="mt-4 flex items-center gap-2 rounded-lg border border-emerald-500/20 bg-emerald-500/5 px-4 py-4 text-sm text-emerald-700 dark:text-emerald-300"
            >
                <CheckCircle2 class="h-4 w-4" />
                No failed jobs recorded.
            </div>
            <div v-else class="mt-4 overflow-x-auto">
                <table class="w-full min-w-xl text-sm">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr
                            class="border-b border-border/60 [&>th]:pb-2 [&>th]:font-semibold"
                        >
                            <th class="pr-4">Job</th>
                            <th class="pr-4">Error type</th>
                            <th class="pr-4">Queue</th>
                            <th>Failed at</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/50">
                        <tr
                            v-for="job in health.queue_operations
                                .recent_failed_jobs"
                            :key="job.uuid"
                            class="[&>td]:py-2.5"
                        >
                            <td class="pr-4 font-medium">
                                {{ job.job_name ?? 'Unknown job' }}
                            </td>
                            <td class="pr-4 font-mono text-xs">
                                {{ job.exception_class ?? '—' }}
                            </td>
                            <td class="pr-4 text-muted-foreground">
                                {{ job.queue }}
                                <span class="text-xs"
                                    >({{ job.connection }})</span
                                >
                            </td>
                            <td class="text-muted-foreground">
                                {{ formatTimestamp(job.failed_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
