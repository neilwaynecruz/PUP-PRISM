<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
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

    router.patch(assign(alertId).url, { assigned_to }, { preserveScroll: true });
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

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <Heading
            variant="small"
            title="Inventory alerts"
            description="Triage low-stock, expiring-lot, and forecast-driven alerts."
        />

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-border/60 bg-card p-4">
                <p class="text-xs text-muted-foreground">Active</p>
                <p class="mt-1 text-2xl font-semibold">{{ summary.active }}</p>
            </div>
            <div class="rounded-xl border border-border/60 bg-card p-4">
                <p class="text-xs text-muted-foreground">Unacknowledged</p>
                <p class="mt-1 text-2xl font-semibold">{{ summary.unacknowledged }}</p>
            </div>
            <div class="rounded-xl border border-border/60 bg-card p-4">
                <p class="text-xs text-muted-foreground">Assigned to me</p>
                <p class="mt-1 text-2xl font-semibold">{{ summary.assigned_to_me }}</p>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <Input
                v-model="search"
                placeholder="Search message or product..."
                class="h-10 rounded-lg lg:col-span-2"
            />
            <select
                v-model="type"
                class="h-10 rounded-lg border border-input bg-background px-3 text-sm"
            >
                <option value="">All types</option>
                <option v-for="alertType in types" :key="alertType" :value="alertType">
                    {{ typeLabel(alertType) }}
                </option>
            </select>
            <select
                v-model="status"
                class="h-10 rounded-lg border border-input bg-background px-3 text-sm"
            >
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="unacknowledged">Unacknowledged</option>
                <option value="acknowledged">Acknowledged</option>
                <option value="resolved">Resolved</option>
            </select>
            <select
                v-model="assignedTo"
                class="h-10 rounded-lg border border-input bg-background px-3 text-sm lg:col-span-2"
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
            class="rounded-xl border border-border/60 bg-card px-4 py-8 text-center text-sm text-muted-foreground shadow-sm"
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
                class="rounded-xl border border-border/60 bg-card p-4 shadow-sm"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="font-medium capitalize">
                            {{ typeLabel(alert.type) }}
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ alert.message }}
                        </p>
                    </div>
                    <span
                        class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium"
                        :class="
                            alert.is_active
                                ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
                                : 'bg-muted text-muted-foreground'
                        "
                    >
                        {{ alert.is_active ? 'Active' : 'Resolved' }}
                    </span>
                </div>

                <div class="mt-3 grid gap-2 text-xs text-muted-foreground">
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
                        Assigned: <span class="text-foreground/80">{{ alert.assigned_to.name }}</span>
                    </div>
                    <div v-if="alert.acknowledged_by">
                        Acknowledged:
                        <span class="text-foreground/80">{{ alert.acknowledged_by.name }}</span>
                    </div>
                    <div v-if="alert.resolved_by">
                        Resolved:
                        <span class="text-foreground/80">{{ alert.resolved_by.name }}</span>
                    </div>
                </div>

                <div
                    v-if="alert.is_active"
                    class="mt-4 grid gap-2"
                >
                    <Button
                        size="sm"
                        variant="outline"
                        class="w-full rounded-lg"
                        @click="acknowledgeAlert(alert.id)"
                    >
                        Acknowledge
                    </Button>
                    <div class="grid gap-2 sm:grid-cols-[1fr_auto]">
                        <select
                            v-model="assignSelections[alert.id]"
                            class="h-9 rounded-lg border border-input bg-background px-3 text-sm"
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
                            class="rounded-lg"
                            :disabled="!assignSelections[alert.id]"
                            @click="assignAlert(alert.id)"
                        >
                            Assign
                        </Button>
                    </div>
                    <Button
                        size="sm"
                        class="w-full rounded-lg"
                        @click="openResolveDialog(alert.id)"
                    >
                        Resolve
                    </Button>
                </div>
            </div>
        </div>

        <div class="hidden overflow-x-auto rounded-xl border border-border/60 bg-card shadow-sm md:block">
            <table class="min-w-full text-sm">
                <thead class="bg-muted/40 text-left">
                    <tr
                        class="text-xs font-semibold tracking-wider text-muted-foreground/80 uppercase [&>th]:px-4 [&>th]:py-3"
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
                        class="align-top [&>td]:px-4 [&>td]:py-3"
                    >
                        <td>
                            <div class="font-medium capitalize">{{ typeLabel(alert.type) }}</div>
                            <p class="mt-1 text-xs text-muted-foreground">{{ alert.message }}</p>
                            <span
                                class="mt-2 inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium"
                                :class="
                                    alert.is_active
                                        ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
                                        : 'bg-muted text-muted-foreground'
                                "
                            >
                                {{ alert.is_active ? 'Active' : 'Resolved' }}
                            </span>
                        </td>
                        <td>
                            <template v-if="alert.product">
                                <div class="font-medium">{{ alert.product.sku }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ alert.product.name }}
                                </div>
                            </template>
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                        <td class="text-xs text-muted-foreground">
                            {{ formatDate(alert.detected_at) }}
                        </td>
                        <td class="text-xs text-muted-foreground">
                            <div v-if="alert.assigned_to">
                                Assigned: {{ alert.assigned_to.name }}
                            </div>
                            <div v-if="alert.acknowledged_by">
                                Acknowledged: {{ alert.acknowledged_by.name }}
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
                                    class="rounded-lg"
                                    @click="acknowledgeAlert(alert.id)"
                                >
                                    Acknowledge
                                </Button>
                                <div class="flex items-center gap-2">
                                    <select
                                        v-model="assignSelections[alert.id]"
                                        class="h-8 rounded-lg border border-input bg-background px-2 text-xs"
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
                                        class="rounded-lg"
                                        :disabled="!assignSelections[alert.id]"
                                        @click="assignAlert(alert.id)"
                                    >
                                        Assign
                                    </Button>
                                </div>
                                <Button
                                    size="sm"
                                    class="rounded-lg"
                                    @click="openResolveDialog(alert.id)"
                                >
                                    Resolve
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="resolveDialogOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        >
            <div class="w-full max-w-md rounded-xl border border-border bg-card p-5 shadow-xl">
                <h3 class="text-sm font-semibold">Resolve alert</h3>
                <p class="mt-1 text-xs text-muted-foreground">
                    Optional notes help future operators understand the resolution.
                </p>
                <textarea
                    v-model="resolveForm.resolution_notes"
                    rows="4"
                    class="mt-4 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm"
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
