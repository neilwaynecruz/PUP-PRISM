<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    Clock3,
    DatabaseZap,
    ListChecks,
    Radio,
    RefreshCw,
    ServerCog,
    TerminalSquare,
    XCircle,
} from 'lucide-vue-next';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { health as operationsHealth } from '@/routes/admin/operations';

type FailedJob = {
    uuid: string;
    connection: string;
    queue: string;
    failed_at: string;
};

type HealthPayload = {
    status: string;
    failed_jobs_count: number;
    queue_connection: string;
    scheduler_last_runs: Record<string, string | null>;
    queue_operations: {
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
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: operationsHealth() },
            { title: 'Operations health', href: operationsHealth() },
        ],
    },
});

const queueRows = computed(() =>
    Object.entries(props.health.queue_operations.pending_by_queue).map(
        ([queue, count]) => ({
            queue,
            count,
        }),
    ),
);

function formatCommand(command: string): string {
    return command.replace('app:', '');
}

function formatTimestamp(value: string | null): string {
    if (!value) {
        return 'No recent run recorded';
    }

    return new Date(value).toLocaleString();
}

function formatAge(seconds: number | null): string {
    if (seconds === null) {
        return '—';
    }

    if (seconds < 60) {
        return `${seconds}s`;
    }

    return `${Math.floor(seconds / 60)}m`;
}

const isHealthy = computed(() => props.health.status.toLowerCase() === 'ok');
const hasFailedJobs = computed(() => props.health.failed_jobs_count > 0);
const hasPendingJobs = computed(
    () => props.health.queue_operations.pending_jobs_count > 0,
);
const hasQueueWarnings = computed(
    () =>
        props.health.queue_operations.possibly_stuck_jobs_count > 0 ||
        props.health.queue_operations.failed_jobs_count > 0,
);

function metricCardClass(
    tone: 'healthy' | 'connection' | 'warning' | 'danger',
): string {
    const classes = {
        healthy:
            'border-emerald-400/25 bg-gradient-to-br from-emerald-500/15 via-card to-card text-emerald-700 dark:text-emerald-300',
        connection:
            'border-cyan-400/25 bg-gradient-to-br from-cyan-500/15 via-card to-card text-cyan-700 dark:text-cyan-300',
        warning:
            'border-amber-400/25 bg-gradient-to-br from-amber-500/15 via-card to-card text-amber-700 dark:text-amber-300',
        danger: 'border-rose-400/25 bg-gradient-to-br from-rose-500/15 via-card to-card text-rose-700 dark:text-rose-300',
    };

    return classes[tone];
}

function heartbeatClass(value: string | null): string {
    return value
        ? 'border-l-emerald-400 bg-emerald-500/5'
        : 'border-l-amber-400 bg-amber-500/5';
}

function heartbeatLabel(value: string | null): string {
    return value ? 'Healthy' : 'No run';
}

function heartbeatBadgeClass(value: string | null): string {
    return value
        ? 'border-emerald-400/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
        : 'border-amber-400/35 bg-amber-500/10 text-amber-700 dark:text-amber-300';
}

function queueCountClass(count: number): string {
    return count > 0
        ? 'border-amber-400/35 bg-amber-500/10 text-amber-700 dark:text-amber-300'
        : 'border-emerald-400/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
}
</script>

<template>
    <Head title="Operations health" />

    <div
        class="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 sm:p-6 lg:p-8"
    >
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
        >
            <Heading
                variant="small"
                title="Operations health"
                description="Queue depth, scheduler heartbeats, and failed job visibility."
            />
            <Button
                variant="outline"
                size="sm"
                as-child
                class="rounded-lg border-cyan-400/30 bg-cyan-500/10 text-cyan-700 hover:bg-cyan-500/15 dark:text-cyan-200"
            >
                <Link :href="operationsHealth().url">
                    <RefreshCw class="mr-1.5 h-3.5 w-3.5" />
                    Refresh
                </Link>
            </Button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div
                class="rounded-xl border p-5 shadow-sm"
                :class="metricCardClass(isHealthy ? 'healthy' : 'danger')"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-wide uppercase"
                        >
                            Status
                        </p>
                        <p
                            class="mt-2 text-2xl font-semibold tracking-tight capitalize"
                        >
                            {{ health.status }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg bg-current/10 p-2 ring-1 ring-current/20"
                    >
                        <CheckCircle2 v-if="isHealthy" class="h-5 w-5" />
                        <XCircle v-else class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-4 text-xs text-muted-foreground">
                    Application services and scheduled monitors.
                </p>
            </div>
            <div
                class="rounded-xl border p-5 shadow-sm"
                :class="metricCardClass('connection')"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-wide uppercase"
                        >
                            Queue connection
                        </p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight">
                            {{ health.queue_connection }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg bg-current/10 p-2 ring-1 ring-current/20"
                    >
                        <DatabaseZap class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-4 text-xs text-muted-foreground">
                    Worker backend currently configured for jobs.
                </p>
            </div>
            <div
                class="rounded-xl border p-5 shadow-sm"
                :class="metricCardClass(hasPendingJobs ? 'warning' : 'healthy')"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-wide uppercase"
                        >
                            Pending jobs
                        </p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight">
                            {{ health.queue_operations.pending_jobs_count }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg bg-current/10 p-2 ring-1 ring-current/20"
                    >
                        <Clock3 class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-4 text-xs text-muted-foreground">
                    Queued work waiting for a worker slot.
                </p>
            </div>
            <div
                class="rounded-xl border p-5 shadow-sm"
                :class="metricCardClass(hasFailedJobs ? 'danger' : 'healthy')"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-wide uppercase"
                        >
                            Failed jobs
                        </p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight">
                            {{ health.failed_jobs_count }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg bg-current/10 p-2 ring-1 ring-current/20"
                    >
                        <AlertTriangle v-if="hasFailedJobs" class="h-5 w-5" />
                        <CheckCircle2 v-else class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-4 text-xs text-muted-foreground">
                    Recent execution failures requiring review.
                </p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section
                class="rounded-xl border border-cyan-400/20 bg-card/90 p-5 shadow-sm"
            >
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2
                            class="flex items-center gap-2 text-sm font-semibold"
                        >
                            <ServerCog class="h-4 w-4 text-cyan-500" />
                            Queue operations
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Worker throughput, aging, and queue pressure.
                        </p>
                    </div>
                    <span
                        class="rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                        :class="
                            hasQueueWarnings
                                ? 'border-amber-400/35 bg-amber-500/10 text-amber-700 dark:text-amber-300'
                                : 'border-emerald-400/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                        "
                    >
                        {{ hasQueueWarnings ? 'Watch' : 'Stable' }}
                    </span>
                </div>
                <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-3">
                    <div
                        class="rounded-lg border border-border/50 bg-muted/30 p-3"
                    >
                        <dt class="text-xs text-muted-foreground">
                            Reserved jobs
                        </dt>
                        <dd class="mt-1 text-lg font-semibold">
                            {{ health.queue_operations.reserved_jobs_count }}
                        </dd>
                    </div>
                    <div
                        class="rounded-lg border border-border/50 bg-muted/30 p-3"
                    >
                        <dt class="text-xs text-muted-foreground">
                            Possibly stuck
                        </dt>
                        <dd class="mt-1 text-lg font-semibold">
                            {{
                                health.queue_operations
                                    .possibly_stuck_jobs_count
                            }}
                        </dd>
                    </div>
                    <div
                        class="rounded-lg border border-border/50 bg-muted/30 p-3"
                    >
                        <dt class="text-xs text-muted-foreground">
                            Oldest pending
                        </dt>
                        <dd class="mt-1 text-lg font-semibold">
                            {{
                                formatAge(
                                    health.queue_operations
                                        .oldest_pending_job_age_seconds,
                                )
                            }}
                        </dd>
                    </div>
                </dl>

                <div v-if="queueRows.length" class="mt-5">
                    <h3
                        class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        <ListChecks class="h-3.5 w-3.5" />
                        Pending by queue
                    </h3>
                    <ul class="mt-2 space-y-2 text-sm">
                        <li
                            v-for="row in queueRows"
                            :key="row.queue"
                            class="flex items-center justify-between rounded-lg border border-border/50 bg-muted/35 px-3 py-2"
                        >
                            <span>{{ row.queue }}</span>
                            <span
                                class="rounded-full border px-2 py-0.5 text-xs font-semibold"
                                :class="queueCountClass(row.count)"
                            >
                                {{ row.count }}
                            </span>
                        </li>
                    </ul>
                </div>

                <div
                    class="mt-5 rounded-lg border border-cyan-400/20 bg-slate-950 px-3 py-3 text-slate-100 shadow-inner dark:bg-black/35"
                >
                    <div
                        class="mb-2 flex items-center gap-2 text-[11px] font-semibold tracking-wide text-cyan-300 uppercase"
                    >
                        <TerminalSquare class="h-3.5 w-3.5" />
                        Recommended worker command
                    </div>
                    <code class="font-mono text-xs break-all">
                        {{ health.queue_operations.recommended_worker_command }}
                    </code>
                </div>
            </section>

            <section
                class="rounded-xl border border-emerald-400/20 bg-card/90 p-5 shadow-sm"
            >
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2
                            class="flex items-center gap-2 text-sm font-semibold"
                        >
                            <Radio class="h-4 w-4 text-emerald-500" />
                            Scheduler heartbeats
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Tracked commands and their last successful run.
                        </p>
                    </div>
                </div>
                <ul class="mt-4 space-y-3 text-sm">
                    <li
                        v-for="command in tracked_commands"
                        :key="command"
                        class="rounded-lg border border-l-4 border-border/50 px-3 py-3"
                        :class="
                            heartbeatClass(
                                health.scheduler_last_runs[command] ?? null,
                            )
                        "
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div class="font-medium">
                                {{ formatCommand(command) }}
                            </div>
                            <span
                                class="rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                                :class="
                                    heartbeatBadgeClass(
                                        health.scheduler_last_runs[command] ??
                                            null,
                                    )
                                "
                            >
                                {{
                                    heartbeatLabel(
                                        health.scheduler_last_runs[command] ??
                                            null,
                                    )
                                }}
                            </span>
                        </div>
                        <div class="mt-1 text-xs text-muted-foreground">
                            {{
                                formatTimestamp(
                                    health.scheduler_last_runs[command] ?? null,
                                )
                            }}
                        </div>
                    </li>
                </ul>
            </section>
        </div>

        <section
            class="rounded-xl border border-rose-400/20 bg-card/90 p-5 shadow-sm"
        >
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="flex items-center gap-2 text-sm font-semibold">
                        <Activity class="h-4 w-4 text-rose-500" />
                        Recent failed jobs
                    </h2>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Latest queue failures with connection and queue context.
                    </p>
                </div>
                <span
                    class="rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                    :class="
                        hasFailedJobs
                            ? 'border-rose-400/35 bg-rose-500/10 text-rose-700 dark:text-rose-300'
                            : 'border-emerald-400/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                    "
                >
                    {{ hasFailedJobs ? 'Needs review' : 'Clear' }}
                </span>
            </div>
            <div
                v-if="health.queue_operations.recent_failed_jobs.length === 0"
                class="mt-4 rounded-lg border border-emerald-400/20 bg-emerald-500/5 px-4 py-6 text-sm text-emerald-700 dark:text-emerald-300"
            >
                <CheckCircle2 class="mr-2 inline h-4 w-4" />
                No failed jobs recorded.
            </div>
            <div v-else class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead
                        class="text-left text-xs text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="border-b border-border/60 pr-4 pb-3">
                                Queue
                            </th>
                            <th class="border-b border-border/60 pr-4 pb-3">
                                Connection
                            </th>
                            <th class="border-b border-border/60 pb-3">
                                Failed at
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="job in health.queue_operations
                                .recent_failed_jobs"
                            :key="job.uuid"
                            class="border-t border-border/50"
                        >
                            <td class="py-3 pr-4 font-medium">
                                {{ job.queue }}
                            </td>
                            <td class="py-3 pr-4">
                                <span
                                    class="rounded-md border border-cyan-400/25 bg-cyan-500/10 px-2 py-1 font-mono text-xs text-cyan-700 dark:text-cyan-300"
                                >
                                    {{ job.connection }}
                                </span>
                            </td>
                            <td class="py-3 text-muted-foreground">
                                {{ formatTimestamp(job.failed_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
