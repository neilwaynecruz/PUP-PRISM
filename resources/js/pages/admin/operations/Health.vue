<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
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
    Object.entries(props.health.queue_operations.pending_by_queue).map(([queue, count]) => ({
        queue,
        count,
    })),
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
</script>

<template>
    <Head title="Operations health" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <Heading
            variant="small"
            title="Operations health"
            description="Queue depth, scheduler heartbeats, and failed job visibility."
        />

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-border/60 bg-card p-4">
                <p class="text-xs text-muted-foreground">Status</p>
                <p class="mt-1 text-xl font-semibold capitalize">{{ health.status }}</p>
            </div>
            <div class="rounded-xl border border-border/60 bg-card p-4">
                <p class="text-xs text-muted-foreground">Queue connection</p>
                <p class="mt-1 text-xl font-semibold">{{ health.queue_connection }}</p>
            </div>
            <div class="rounded-xl border border-border/60 bg-card p-4">
                <p class="text-xs text-muted-foreground">Pending jobs</p>
                <p class="mt-1 text-xl font-semibold">
                    {{ health.queue_operations.pending_jobs_count }}
                </p>
            </div>
            <div class="rounded-xl border border-border/60 bg-card p-4">
                <p class="text-xs text-muted-foreground">Failed jobs</p>
                <p class="mt-1 text-xl font-semibold">{{ health.failed_jobs_count }}</p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <h2 class="text-sm font-semibold">Queue operations</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Reserved jobs</dt>
                        <dd>{{ health.queue_operations.reserved_jobs_count }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Possibly stuck</dt>
                        <dd>{{ health.queue_operations.possibly_stuck_jobs_count }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Oldest pending age</dt>
                        <dd>
                            {{ formatAge(health.queue_operations.oldest_pending_job_age_seconds) }}
                        </dd>
                    </div>
                </dl>

                <div v-if="queueRows.length" class="mt-5">
                    <h3 class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Pending by queue
                    </h3>
                    <ul class="mt-2 space-y-2 text-sm">
                        <li
                            v-for="row in queueRows"
                            :key="row.queue"
                            class="flex justify-between rounded-lg bg-muted/40 px-3 py-2"
                        >
                            <span>{{ row.queue }}</span>
                            <span class="font-medium">{{ row.count }}</span>
                        </li>
                    </ul>
                </div>

                <p class="mt-5 rounded-lg bg-muted/40 px-3 py-2 font-mono text-xs">
                    {{ health.queue_operations.recommended_worker_command }}
                </p>
            </section>

            <section class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <h2 class="text-sm font-semibold">Scheduler heartbeats</h2>
                <ul class="mt-4 space-y-3 text-sm">
                    <li
                        v-for="command in tracked_commands"
                        :key="command"
                        class="rounded-lg border border-border/50 px-3 py-3"
                    >
                        <div class="font-medium">{{ formatCommand(command) }}</div>
                        <div class="mt-1 text-xs text-muted-foreground">
                            {{ formatTimestamp(health.scheduler_last_runs[command] ?? null) }}
                        </div>
                    </li>
                </ul>
            </section>
        </div>

        <section class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
            <h2 class="text-sm font-semibold">Recent failed jobs</h2>
            <div v-if="health.queue_operations.recent_failed_jobs.length === 0" class="mt-4 text-sm text-muted-foreground">
                No failed jobs recorded.
            </div>
            <div v-else class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr>
                            <th class="pb-2 pr-4">Queue</th>
                            <th class="pb-2 pr-4">Connection</th>
                            <th class="pb-2">Failed at</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="job in health.queue_operations.recent_failed_jobs"
                            :key="job.uuid"
                            class="border-t border-border/50"
                        >
                            <td class="py-2 pr-4">{{ job.queue }}</td>
                            <td class="py-2 pr-4">{{ job.connection }}</td>
                            <td class="py-2">{{ formatTimestamp(job.failed_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="flex justify-end">
            <Button variant="outline" size="sm" as-child class="rounded-lg">
                <Link :href="operationsHealth().url">Refresh</Link>
            </Button>
        </div>
    </div>
</template>
