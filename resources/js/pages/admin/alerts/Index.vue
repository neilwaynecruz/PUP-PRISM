<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BellRing,
    CheckCircle2,
    Clock3,
    Flame,
    PackageSearch,
    Send,
    UserCheck,
} from 'lucide-vue-next';
import { onBeforeUnmount, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    acknowledge,
    assign,
    index as alertsIndex,
    resolve,
} from '@/routes/admin/alerts';

type AlertRow = {
    id: number;
    type: string;
    message: string;
    detected_at: string | null;
    acknowledged_at: string | null;
    resolved_at: string | null;
    resolution_notes: string | null;
    is_active: boolean;
    product: { id: number; sku: string; name: string } | null;
    acknowledged_by: { id: number; name: string } | null;
    assigned_to: { id: number; name: string } | null;
    resolved_by: { id: number; name: string } | null;
};

type Operator = { id: number; name: string; email: string };

type PaginationLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    filters: {
        search: string;
        type: string;
        status: string;
        assigned_to: number;
    };
    alerts: {
        data: AlertRow[];
        links: PaginationLink[];
    };
    types: string[];
    operators: Operator[];
    summary: {
        active: number;
        unacknowledged: number;
        assigned_to_me: number;
    };
}>();

defineOptions({
    name: 'AdminAlertsIndexPage',
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: alertsIndex() },
            { title: 'Alerts', href: alertsIndex() },
        ],
    },
});

const search = ref(props.filters.search ?? '');
const type = ref(props.filters.type ?? '');
const status = ref(props.filters.status ?? 'active');
const assignedTo = ref(
    props.filters.assigned_to > 0 ? String(props.filters.assigned_to) : '',
);

const resolveDialogOpen = ref(false);
const selectedAlertId = ref<number | null>(null);
const resolveForm = useForm({ resolution_notes: '' });
const assignSelections = ref<Record<number, string>>({});

let refreshTimer: number | undefined;

watch([search, type, status, assignedTo], () => {
    window.clearTimeout(refreshTimer);
    refreshTimer = window.setTimeout(() => {
        router.get(
            alertsIndex().url,
            {
                search: search.value || undefined,
                type: type.value || undefined,
                status: status.value || undefined,
                assigned_to: assignedTo.value || undefined,
            },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    }, 250);
});

onBeforeUnmount(() => {
    window.clearTimeout(refreshTimer);
});

function typeLabel(value: string): string {
    return value.replace(/_/g, ' ');
}

function isUnacknowledged(alert: AlertRow): boolean {
    return alert.is_active && alert.acknowledged_at === null;
}

function alertTypeAccent(value: string): string {
    if (value.includes('expir')) {
        return 'border-orange-400/40 bg-orange-500/10 text-orange-700 dark:text-orange-300';
    }

    if (value.includes('forecast')) {
        return 'border-sky-400/40 bg-sky-500/10 text-sky-700 dark:text-sky-300';
    }

    if (value.includes('stock')) {
        return 'border-amber-400/40 bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    return 'border-rose-400/40 bg-rose-500/10 text-rose-700 dark:text-rose-300';
}

function alertRowAccent(alert: AlertRow): string {
    if (!alert.is_active) {
        return 'border-l-emerald-400/60';
    }

    if (isUnacknowledged(alert)) {
        return 'border-l-amber-400';
    }

    return 'border-l-sky-400/80';
}

function statusBadgeClass(alert: AlertRow): string {
    if (!alert.is_active) {
        return 'border-emerald-400/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    if (isUnacknowledged(alert)) {
        return 'border-amber-400/40 bg-amber-500/15 text-amber-800 dark:text-amber-200';
    }

    return 'border-blue-400/35 bg-blue-500/10 text-blue-700 dark:text-blue-300';
}

function statusLabel(alert: AlertRow): string {
    if (!alert.is_active) {
        return 'Resolved';
    }

    return isUnacknowledged(alert) ? 'Unacknowledged' : 'Active';
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
}

function acknowledgeAlert(alertId: number): void {
    router.patch(acknowledge(alertId).url, {}, { preserveScroll: true });
}

function assignAlert(alertId: number): void {
    const assigned_to = assignSelections.value[alertId];

    if (!assigned_to) {
        return;
    }

    router.patch(
        assign(alertId).url,
        { assigned_to },
        { preserveScroll: true },
    );
}

function openResolveDialog(alertId: number): void {
    selectedAlertId.value = alertId;
    resolveForm.reset();
    resolveDialogOpen.value = true;
}

function submitResolve(): void {
    if (selectedAlertId.value === null) {
        return;
    }

    resolveForm.patch(resolve(selectedAlertId.value).url, {
        preserveScroll: true,
        onSuccess: () => {
            resolveDialogOpen.value = false;
            selectedAlertId.value = null;
        },
    });
}
</script>

<template>
    <Head title="Alerts" />

    <div
        class="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 sm:p-6 lg:p-8"
    >
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
        >
            <Heading
                variant="small"
                title="Inventory alerts"
                description="Triage low-stock, expiring-lot, and forecast-driven alerts."
            />
            <div
                class="inline-flex w-fit items-center gap-2 rounded-full border border-amber-400/25 bg-amber-500/10 px-3 py-1.5 text-xs font-semibold text-amber-700 shadow-xs dark:text-amber-200"
            >
                <BellRing class="h-3.5 w-3.5" />
                Live operational watchlist
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div
                class="group relative overflow-hidden rounded-xl border border-amber-400/25 bg-gradient-to-br from-amber-500/15 via-card to-card p-5 shadow-sm transition-colors hover:border-amber-400/45"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-wide text-amber-700 uppercase dark:text-amber-300"
                        >
                            Active
                        </p>
                        <p class="mt-2 text-3xl font-semibold tracking-tight">
                            {{ summary.active }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg bg-amber-500/15 p-2 text-amber-700 ring-1 ring-amber-400/25 dark:text-amber-200"
                    >
                        <Flame class="h-5 w-5" />
                    </div>
                </div>
                <div
                    class="mt-4 h-1.5 overflow-hidden rounded-full bg-amber-950/10 dark:bg-white/10"
                >
                    <div class="h-full w-2/3 rounded-full bg-amber-400/80" />
                </div>
            </div>
            <div
                class="group relative overflow-hidden rounded-xl border border-rose-400/25 bg-gradient-to-br from-rose-500/15 via-card to-card p-5 shadow-sm transition-colors hover:border-rose-400/45"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-wide text-rose-700 uppercase dark:text-rose-300"
                        >
                            Unacknowledged
                        </p>
                        <p class="mt-2 text-3xl font-semibold tracking-tight">
                            {{ summary.unacknowledged }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg bg-rose-500/15 p-2 text-rose-700 ring-1 ring-rose-400/25 dark:text-rose-200"
                    >
                        <AlertTriangle class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-4 text-xs text-muted-foreground">
                    Needs operator review before it can be resolved.
                </p>
            </div>
            <div
                class="group relative overflow-hidden rounded-xl border border-cyan-400/25 bg-gradient-to-br from-cyan-500/15 via-card to-card p-5 shadow-sm transition-colors hover:border-cyan-400/45 sm:col-span-2 xl:col-span-1"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-wide text-cyan-700 uppercase dark:text-cyan-300"
                        >
                            Assigned to me
                        </p>
                        <p class="mt-2 text-3xl font-semibold tracking-tight">
                            {{ summary.assigned_to_me }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg bg-cyan-500/15 p-2 text-cyan-700 ring-1 ring-cyan-400/25 dark:text-cyan-200"
                    >
                        <UserCheck class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-4 text-xs text-muted-foreground">
                    Personal queue for ownership and follow-through.
                </p>
            </div>
        </div>

        <div
            class="grid gap-3 rounded-xl border border-border/60 bg-card/75 p-3 shadow-sm sm:grid-cols-2 sm:p-4 lg:grid-cols-4"
        >
            <Input
                v-model="search"
                placeholder="Search message or product..."
                class="h-10 rounded-lg border-border/70 bg-background/80 shadow-xs lg:col-span-2"
            />
            <select
                v-model="type"
                class="h-10 rounded-lg border border-input bg-background/80 px-3 text-sm text-foreground shadow-xs focus:border-ring focus:ring-2 focus:ring-ring/20 focus:outline-none"
            >
                <option value="">All types</option>
                <option
                    v-for="alertType in types"
                    :key="alertType"
                    :value="alertType"
                >
                    {{ typeLabel(alertType) }}
                </option>
            </select>
            <select
                v-model="status"
                class="h-10 rounded-lg border border-input bg-background/80 px-3 text-sm text-foreground shadow-xs focus:border-ring focus:ring-2 focus:ring-ring/20 focus:outline-none"
            >
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="unacknowledged">Unacknowledged</option>
                <option value="acknowledged">Acknowledged</option>
                <option value="resolved">Resolved</option>
            </select>
            <select
                v-model="assignedTo"
                class="h-10 rounded-lg border border-input bg-background/80 px-3 text-sm text-foreground shadow-xs focus:border-ring focus:ring-2 focus:ring-ring/20 focus:outline-none lg:col-span-2"
            >
                <option value="">All assignees</option>
                <option
                    v-for="operator in operators"
                    :key="operator.id"
                    :value="String(operator.id)"
                >
                    {{ operator.name }}
                </option>
            </select>
        </div>

        <div
            v-if="alerts.data.length === 0"
            class="rounded-xl border border-dashed border-border/70 bg-card/70 px-4 py-10 text-center text-sm text-muted-foreground shadow-sm"
        >
            No alerts match the current filters.
        </div>

        <div
            v-else
            class="grid gap-3 md:hidden"
            data-testid="alerts-mobile-cards"
        >
            <div
                v-for="alert in alerts.data"
                :key="`mobile-${alert.id}`"
                class="rounded-xl border border-l-4 border-border/60 bg-card/90 p-4 shadow-sm"
                :class="alertRowAccent(alert)"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold capitalize"
                                :class="alertTypeAccent(alert.type)"
                            >
                                <PackageSearch class="h-3 w-3" />
                                {{ typeLabel(alert.type) }}
                            </span>
                            <span
                                class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                                :class="statusBadgeClass(alert)"
                            >
                                {{ statusLabel(alert) }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ alert.message }}
                        </p>
                    </div>
                </div>

                <div
                    class="mt-4 grid gap-2 rounded-lg bg-muted/35 p-3 text-xs text-muted-foreground"
                >
                    <div>
                        Detected:
                        <span class="text-foreground/80">{{
                            formatDate(alert.detected_at)
                        }}</span>
                    </div>
                    <div v-if="alert.product">
                        Product:
                        <span class="text-foreground/80">
                            {{ alert.product.sku }} — {{ alert.product.name }}
                        </span>
                    </div>
                    <div v-if="alert.assigned_to">
                        Assigned:
                        <span class="text-foreground/80">{{
                            alert.assigned_to.name
                        }}</span>
                    </div>
                    <div v-if="alert.acknowledged_by">
                        Acknowledged:
                        <span class="text-foreground/80">{{
                            alert.acknowledged_by.name
                        }}</span>
                    </div>
                    <div v-if="alert.resolved_by">
                        Resolved:
                        <span class="text-foreground/80">{{
                            alert.resolved_by.name
                        }}</span>
                    </div>
                </div>

                <div v-if="alert.is_active" class="mt-4 grid gap-2">
                    <Button
                        size="sm"
                        variant="outline"
                        class="w-full rounded-lg border-amber-400/35 bg-amber-500/10 text-amber-700 hover:bg-amber-500/15 dark:text-amber-200"
                        @click="acknowledgeAlert(alert.id)"
                    >
                        <CheckCircle2 class="mr-1.5 h-3.5 w-3.5" />
                        Acknowledge
                    </Button>
                    <div class="grid gap-2 sm:grid-cols-[1fr_auto]">
                        <select
                            v-model="assignSelections[alert.id]"
                            class="h-9 rounded-lg border border-input bg-background/80 px-3 text-sm"
                        >
                            <option value="">Assign to...</option>
                            <option
                                v-for="operator in operators"
                                :key="operator.id"
                                :value="String(operator.id)"
                            >
                                {{ operator.name }}
                            </option>
                        </select>
                        <Button
                            size="sm"
                            variant="secondary"
                            class="rounded-lg bg-cyan-500/12 text-cyan-700 hover:bg-cyan-500/20 dark:text-cyan-200"
                            :disabled="!assignSelections[alert.id]"
                            @click="assignAlert(alert.id)"
                        >
                            <Send class="mr-1.5 h-3.5 w-3.5" />
                            Assign
                        </Button>
                    </div>
                    <Button
                        size="sm"
                        class="w-full rounded-lg bg-emerald-600 text-white hover:bg-emerald-500"
                        @click="openResolveDialog(alert.id)"
                    >
                        <CheckCircle2 class="mr-1.5 h-3.5 w-3.5" />
                        Resolve
                    </Button>
                </div>
            </div>
        </div>

        <div
            class="hidden overflow-hidden rounded-xl border border-border/60 bg-card/90 shadow-sm md:block"
        >
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr
                            class="text-xs font-semibold tracking-wider text-muted-foreground/90 uppercase [&>th]:px-5 [&>th]:py-4"
                        >
                            <th>Alert</th>
                            <th>Product</th>
                            <th>Detected</th>
                            <th>Ownership</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr
                            v-for="alert in alerts.data"
                            :key="alert.id"
                            class="border-l-4 align-top transition-colors hover:bg-primary/[0.035] dark:hover:bg-primary/[0.06] [&>td]:px-5 [&>td]:py-4"
                            :class="alertRowAccent(alert)"
                        >
                            <td>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold capitalize"
                                        :class="alertTypeAccent(alert.type)"
                                    >
                                        <PackageSearch class="h-3 w-3" />
                                        {{ typeLabel(alert.type) }}
                                    </span>
                                    <span
                                        class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                                        :class="statusBadgeClass(alert)"
                                    >
                                        {{ statusLabel(alert) }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ alert.message }}
                                </p>
                            </td>
                            <td>
                                <template v-if="alert.product">
                                    <div class="font-medium">
                                        {{ alert.product.sku }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ alert.product.name }}
                                    </div>
                                </template>
                                <span v-else class="text-muted-foreground"
                                    >—</span
                                >
                            </td>
                            <td class="text-xs text-muted-foreground">
                                <div class="inline-flex items-center gap-1.5">
                                    <Clock3
                                        class="h-3.5 w-3.5 text-amber-500"
                                    />
                                    {{ formatDate(alert.detected_at) }}
                                </div>
                            </td>
                            <td class="text-xs text-muted-foreground">
                                <div v-if="alert.assigned_to">
                                    Assigned: {{ alert.assigned_to.name }}
                                </div>
                                <div v-if="alert.acknowledged_by">
                                    Acknowledged:
                                    {{ alert.acknowledged_by.name }}
                                </div>
                                <div v-if="alert.resolved_by">
                                    Resolved: {{ alert.resolved_by.name }}
                                </div>
                            </td>
                            <td class="text-right">
                                <div
                                    v-if="alert.is_active"
                                    class="flex flex-col items-end gap-2"
                                >
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="rounded-lg border-amber-400/35 bg-amber-500/10 text-amber-700 hover:bg-amber-500/15 dark:text-amber-200"
                                        @click="acknowledgeAlert(alert.id)"
                                    >
                                        <CheckCircle2
                                            class="mr-1.5 h-3.5 w-3.5"
                                        />
                                        Acknowledge
                                    </Button>
                                    <div class="flex items-center gap-2">
                                        <select
                                            v-model="assignSelections[alert.id]"
                                            class="h-8 rounded-lg border border-input bg-background/80 px-2 text-xs"
                                        >
                                            <option value="">
                                                Assign to...
                                            </option>
                                            <option
                                                v-for="operator in operators"
                                                :key="operator.id"
                                                :value="String(operator.id)"
                                            >
                                                {{ operator.name }}
                                            </option>
                                        </select>
                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            class="rounded-lg bg-cyan-500/12 text-cyan-700 hover:bg-cyan-500/20 dark:text-cyan-200"
                                            :disabled="
                                                !assignSelections[alert.id]
                                            "
                                            @click="assignAlert(alert.id)"
                                        >
                                            <Send class="mr-1.5 h-3.5 w-3.5" />
                                            Assign
                                        </Button>
                                    </div>
                                    <Button
                                        size="sm"
                                        class="rounded-lg bg-emerald-600 text-white hover:bg-emerald-500"
                                        @click="openResolveDialog(alert.id)"
                                    >
                                        <CheckCircle2
                                            class="mr-1.5 h-3.5 w-3.5"
                                        />
                                        Resolve
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div
            v-if="resolveDialogOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 p-4 backdrop-blur-sm"
        >
            <div
                class="w-full max-w-md rounded-xl border border-border/70 bg-card p-5 shadow-xl"
            >
                <h3 class="text-sm font-semibold">Resolve alert</h3>
                <p class="mt-1 text-xs text-muted-foreground">
                    Optional notes help future operators understand the
                    resolution.
                </p>
                <textarea
                    v-model="resolveForm.resolution_notes"
                    rows="4"
                    class="mt-4 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:border-ring focus:ring-2 focus:ring-ring/20 focus:outline-none"
                    placeholder="Resolution notes (optional)"
                />
                <div class="mt-4 flex justify-end gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="resolveDialogOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        size="sm"
                        :disabled="resolveForm.processing"
                        @click="submitResolve"
                    >
                        Resolve alert
                    </Button>
                </div>
            </div>
        </div>

        <div
            v-if="alerts.links.length"
            class="flex flex-wrap items-center justify-center gap-1"
        >
            <Button
                v-for="(link, index) in alerts.links"
                :key="index"
                variant="ghost"
                size="sm"
                :disabled="!link.url"
                as-child
                class="h-8 rounded-lg text-xs"
                :class="link.active ? 'bg-primary/10 text-primary' : ''"
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
    </div>
</template>
