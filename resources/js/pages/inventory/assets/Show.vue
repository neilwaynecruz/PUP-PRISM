<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Download,
    FileText,
    MapPin,
    ShieldCheck,
    Wrench,
} from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    approve as approveLifecycle,
    reject as rejectLifecycle,
    store as storeLifecycle,
} from '@/routes/inventory/asset-lifecycle';
import {
    approve as approveTransfer,
    cancel as cancelTransfer,
    reject as rejectTransfer,
    store as storeTransfer,
} from '@/routes/inventory/asset-transfers';
import { index } from '@/routes/inventory/assets';
import {
    destroy as destroyAttachment,
    download as downloadAttachment,
    store as storeAttachment,
} from '@/routes/inventory/attachments';
import {
    store as storeMaintenance,
    transition as transitionMaintenance,
} from '@/routes/inventory/maintenance';
import type { Auth } from '@/types';

type Person = { id: number; name: string };
type Location = {
    id: number;
    code: string;
    name: string;
    building?: string | null;
    room?: string | null;
};
type Position = {
    id: number;
    title: string;
    code: string;
    department: { name: string } | null;
};
type Maintenance = {
    id: number;
    maintenance_type: string;
    status: string;
    reported_issue: string;
    description: string | null;
    reported_date: string;
    scheduled_for: string | null;
    completed_at: string | null;
    service_provider: string | null;
    repair_cost: string | null;
    findings: string | null;
    reporter: Person;
    assignee: Person | null;
};
type Transfer = {
    id: number;
    status: string;
    reason: string;
    transfer_date: string;
    notes: string | null;
    from_location: Location | null;
    to_location: Location;
    initiator: Person;
    approver: Person | null;
};
type LifecycleEvent = {
    id: number;
    event_type: string;
    status: string;
    reason: string;
    method: string | null;
    supporting_reference: string | null;
    from_lifecycle_status: string | null;
    to_lifecycle_status: string | null;
    from_condition_status: string | null;
    to_condition_status: string | null;
    proposed_for: string | null;
    occurred_at: string | null;
    initiator: Person;
    approver: Person | null;
};
type Attachment = {
    id: number;
    title: string;
    category: string;
    description: string | null;
    original_name: string;
    mime_type: string;
    size: number;
    created_at: string;
    uploaded_by: number;
    uploader: Person;
};
type MaintenanceDetails = {
    assigned_to: string;
    service_provider: string;
    scheduled_for: string;
    repair_cost: string;
    findings: string;
    notes: string;
};
type Asset = {
    id: number;
    tag_code: string;
    status: string;
    lifecycle_status: string;
    condition_status: string;
    product: { name: string; sku: string };
    position: (Position & { department: { name: string } | null }) | null;
    current_location: Location | null;
    maintenances: Maintenance[];
    transfers: Transfer[];
    lifecycle_events: LifecycleEvent[];
    attachments: Attachment[];
};

const props = defineProps<{
    asset: Asset;
    locations: Location[];
    positions: Position[];
    users: Person[];
    can: {
        createMaintenance: boolean;
        createTransfer: boolean;
        createLifecycle: boolean;
    };
}>();
defineOptions({
    name: 'InventoryAssetShowPage',
    layout: {
        breadcrumbs: [
            { title: 'Assets', href: index() },
            { title: 'Asset details', href: index() },
        ],
    },
});

const page = usePage();
const auth = computed(() => page.props.auth as Auth);
const isAdmin = computed(() => auth.value.roles.includes('Admin'));
const pendingActions = ref<string[]>([]);
const maintenanceDetails = reactive<Record<number, MaintenanceDetails>>({});

watch(
    () => props.asset.maintenances,
    (records) => {
        for (const record of records) {
            maintenanceDetails[record.id] ??= {
                assigned_to: record.assignee?.id
                    ? String(record.assignee.id)
                    : '',
                service_provider: record.service_provider ?? '',
                scheduled_for: record.scheduled_for ?? '',
                repair_cost: record.repair_cost ?? '',
                findings: record.findings ?? '',
                notes: '',
            };
        }
    },
    { immediate: true },
);
const lifecycleOptions = computed(() => {
    if (props.asset.lifecycle_status === 'active') {
        return [
            { value: 'condition_change', label: 'Condition change' },
            { value: 'retirement', label: 'Retirement' },
        ];
    }

    if (props.asset.lifecycle_status === 'retired') {
        return [{ value: 'disposal', label: 'Disposal' }];
    }

    return [];
});

function todayInManila(): string {
    const parts = new Intl.DateTimeFormat('en-US', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        timeZone: 'Asia/Manila',
    }).formatToParts();
    const values = Object.fromEntries(
        parts.map((part) => [part.type, part.value]),
    );

    return `${values.year}-${values.month}-${values.day}`;
}

const maintenanceForm = useForm({
    asset_id: props.asset.id,
    maintenance_type: 'inspection',
    reported_issue: '',
    description: '',
    reported_date: todayInManila(),
    assigned_to: '',
    service_provider: '',
    scheduled_for: '',
    repair_cost: '',
    notes: '',
});
const transferForm = useForm({
    asset_id: props.asset.id,
    to_location_id: '',
    to_position_id: '',
    reason: '',
    transfer_date: todayInManila(),
    notes: '',
});
const lifecycleForm = useForm({
    asset_id: props.asset.id,
    event_type:
        props.asset.lifecycle_status === 'retired'
            ? 'disposal'
            : 'condition_change',
    condition_status: '',
    reason: '',
    method: '',
    supporting_reference: '',
    proposed_for: '',
    notes: '',
});
const attachmentForm = useForm<{
    title: string;
    category: string;
    description: string;
    file: File | null;
}>({ title: '', category: 'other', description: '', file: null });

function submitMaintenance(): void {
    maintenanceForm.post(storeMaintenance().url, {
        preserveScroll: true,
        onSuccess: () =>
            maintenanceForm.reset('reported_issue', 'description', 'notes'),
    });
}
function submitTransfer(): void {
    transferForm.post(storeTransfer().url, {
        preserveScroll: true,
        onSuccess: () =>
            transferForm.reset(
                'to_location_id',
                'to_position_id',
                'reason',
                'notes',
            ),
    });
}
function submitLifecycle(): void {
    lifecycleForm.post(storeLifecycle().url, {
        preserveScroll: true,
        onSuccess: () =>
            lifecycleForm.reset(
                'condition_status',
                'reason',
                'method',
                'supporting_reference',
                'proposed_for',
                'notes',
            ),
    });
}
function submitAttachment(): void {
    attachmentForm.post(storeAttachment(['asset', props.asset.id]).url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => attachmentForm.reset(),
    });
}

function setFile(event: Event): void {
    attachmentForm.file = (event.target as HTMLInputElement).files?.[0] ?? null;
}
function label(value: string | null): string {
    return value
        ? value
              .replaceAll('_', ' ')
              .replace(/\b\w/g, (letter) => letter.toUpperCase())
        : 'Not specified';
}
function formatDate(value: string): string {
    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeZone: 'Asia/Manila',
    }).format(new Date(value));
}
function transitionMaintenanceStatus(
    record: Maintenance,
    status: string,
): void {
    const actionKey = `maintenance-${record.id}`;

    if (
        pendingActions.value.includes(actionKey) ||
        !window.confirm(`Change this maintenance record to ${label(status)}?`)
    ) {
        return;
    }

    pendingActions.value.push(actionKey);
    router.put(
        transitionMaintenance(record.id).url,
        { status, ...maintenanceDetails[record.id] },
        {
            preserveScroll: true,
            onFinish: () => {
                pendingActions.value = pendingActions.value.filter(
                    (key) => key !== actionKey,
                );
            },
        },
    );
}
function reviewTransfer(record: Transfer, action: 'approve' | 'reject'): void {
    const actionKey = `transfer-${record.id}`;

    if (
        pendingActions.value.includes(actionKey) ||
        !window.confirm(
            action === 'approve'
                ? 'Approve this asset location transfer?'
                : 'Reject this asset location transfer?',
        )
    ) {
        return;
    }

    pendingActions.value.push(actionKey);
    router.post(
        (action === 'approve'
            ? approveTransfer(record.id)
            : rejectTransfer(record.id)
        ).url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                pendingActions.value = pendingActions.value.filter(
                    (key) => key !== actionKey,
                );
            },
        },
    );
}
function cancelPendingTransfer(record: Transfer): void {
    const actionKey = `transfer-${record.id}`;

    if (
        pendingActions.value.includes(actionKey) ||
        !window.confirm(
            'Cancel this pending transfer? The asset location will not change.',
        )
    ) {
        return;
    }

    pendingActions.value.push(actionKey);
    router.post(
        cancelTransfer(record.id).url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                pendingActions.value = pendingActions.value.filter(
                    (key) => key !== actionKey,
                );
            },
        },
    );
}
function reviewLifecycle(
    record: LifecycleEvent,
    action: 'approve' | 'reject',
): void {
    const actionKey = `lifecycle-${record.id}`;

    if (
        pendingActions.value.includes(actionKey) ||
        !window.confirm(
            action === 'approve'
                ? 'Approve this lifecycle change?'
                : 'Reject this lifecycle change?',
        )
    ) {
        return;
    }

    pendingActions.value.push(actionKey);
    router.post(
        (action === 'approve'
            ? approveLifecycle(record.id)
            : rejectLifecycle(record.id)
        ).url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                pendingActions.value = pendingActions.value.filter(
                    (key) => key !== actionKey,
                );
            },
        },
    );
}
function removeAttachment(record: Attachment): void {
    if (
        window.confirm(
            `Remove “${record.title}” from active use? The retained file will remain protected.`,
        )
    ) {
        router.delete(destroyAttachment(record.id).url, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head :title="`Asset ${asset.tag_code}`" />
    <div
        class="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 sm:p-6 lg:p-8"
    >
        <div>
            <Link
                :href="index()"
                class="mb-3 inline-flex min-h-10 items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                ><ArrowLeft aria-hidden="true" class="h-4 w-4" />Asset
                registry</Link
            ><Heading
                variant="small"
                :title="asset.tag_code"
                :description="`${asset.product.name} · ${asset.product.sku}`"
            />
        </div>

        <section
            aria-label="Asset summary"
            class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5"
        >
            <div class="rounded-xl border border-border/70 bg-card p-4">
                <p class="text-xs text-muted-foreground">Availability</p>
                <p class="mt-1 font-semibold">{{ label(asset.status) }}</p>
            </div>
            <div class="rounded-xl border border-border/70 bg-card p-4">
                <p class="text-xs text-muted-foreground">Condition</p>
                <p class="mt-1 font-semibold">
                    {{ label(asset.condition_status) }}
                </p>
            </div>
            <div class="rounded-xl border border-border/70 bg-card p-4">
                <p class="text-xs text-muted-foreground">Lifecycle</p>
                <p class="mt-1 font-semibold">
                    {{ label(asset.lifecycle_status) }}
                </p>
            </div>
            <div class="rounded-xl border border-border/70 bg-card p-4">
                <p class="text-xs text-muted-foreground">Location</p>
                <p class="mt-1 font-semibold">
                    {{ asset.current_location?.name ?? 'Not recorded' }}
                </p>
            </div>
            <div class="rounded-xl border border-border/70 bg-card p-4">
                <p class="text-xs text-muted-foreground">Accountability</p>
                <p class="mt-1 font-semibold">
                    {{ asset.position?.title ?? 'In stock' }}
                </p>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-3">
            <section
                class="space-y-4 xl:col-span-2"
                aria-labelledby="maintenance-title"
            >
                <div>
                    <h2
                        id="maintenance-title"
                        class="flex items-center gap-2 text-lg font-semibold"
                    >
                        <Wrench aria-hidden="true" class="h-5 w-5" />Maintenance
                        and repairs
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Record service work and outcomes. Existing bookings are
                        protected and must be resolved before work begins.
                    </p>
                </div>
                <details
                    v-if="
                        can.createMaintenance &&
                        asset.lifecycle_status === 'active'
                    "
                    class="rounded-xl border border-border/70 bg-card"
                    open
                >
                    <summary class="cursor-pointer px-4 py-3 font-medium">
                        Create maintenance request
                    </summary>
                    <form
                        class="grid gap-3 border-t border-border/60 p-4 sm:grid-cols-2"
                        @submit.prevent="submitMaintenance"
                    >
                        <label class="grid gap-1 text-sm font-medium"
                            >Maintenance type<select
                                v-model="maintenanceForm.maintenance_type"
                                class="min-h-11 rounded-md border border-input bg-background px-3 sm:min-h-9"
                            >
                                <option value="preventive">
                                    Preventive maintenance
                                </option>
                                <option value="corrective">
                                    Corrective maintenance
                                </option>
                                <option value="inspection">Inspection</option>
                                <option value="repair">Repair</option>
                                <option value="other">Other</option>
                            </select></label
                        >
                        <label class="grid gap-1 text-sm font-medium"
                            >Reported date<input
                                v-model="maintenanceForm.reported_date"
                                required
                                type="date"
                                class="min-h-11 rounded-md border border-input bg-background px-3 sm:min-h-9"
                        /></label>
                        <label
                            class="grid gap-1 text-sm font-medium sm:col-span-2"
                            >Reported issue<textarea
                                v-model="maintenanceForm.reported_issue"
                                required
                                rows="3"
                                placeholder="Describe the issue in clear, practical terms"
                                class="rounded-md border border-input bg-background px-3 py-2" /><InputError
                                :message="
                                    maintenanceForm.errors.reported_issue ||
                                    maintenanceForm.errors.asset_id
                                "
                        /></label>
                        <label class="grid gap-1 text-sm font-medium"
                            >Assigned personnel<select
                                v-model="maintenanceForm.assigned_to"
                                class="min-h-11 rounded-md border border-input bg-background px-3 sm:min-h-9"
                            >
                                <option value="">Assign later</option>
                                <option
                                    v-for="user in users"
                                    :key="user.id"
                                    :value="String(user.id)"
                                >
                                    {{ user.name }}
                                </option>
                            </select></label
                        >
                        <label class="grid gap-1 text-sm font-medium"
                            >Scheduled service date<input
                                v-model="maintenanceForm.scheduled_for"
                                type="date"
                                class="min-h-11 rounded-md border border-input bg-background px-3 sm:min-h-9"
                        /></label>
                        <div class="flex justify-end sm:col-span-2">
                            <Button
                                type="submit"
                                :disabled="maintenanceForm.processing"
                                >{{
                                    maintenanceForm.processing
                                        ? 'Creating request…'
                                        : 'Create Maintenance Request'
                                }}</Button
                            >
                        </div>
                    </form>
                </details>
                <article
                    v-for="record in asset.maintenances"
                    :key="record.id"
                    class="rounded-xl border border-border/70 bg-card p-4"
                >
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div>
                            <h3 class="font-semibold">
                                {{ label(record.maintenance_type) }}
                            </h3>
                            <p class="mt-1 text-sm">
                                {{ record.reported_issue }}
                            </p>
                            <p class="mt-2 text-xs text-muted-foreground">
                                Reported
                                {{ formatDate(record.reported_date) }} by
                                {{ record.reporter.name
                                }}<template v-if="record.scheduled_for">
                                    · Scheduled
                                    {{
                                        formatDate(record.scheduled_for)
                                    }}</template
                                >
                            </p>
                        </div>
                        <span
                            class="rounded-full bg-muted px-2.5 py-1 text-xs font-medium"
                            >{{ label(record.status) }}</span
                        >
                    </div>
                    <p
                        v-if="record.findings"
                        class="mt-3 rounded-lg bg-muted/50 p-3 text-sm"
                    >
                        <strong>Findings:</strong> {{ record.findings }}
                    </p>
                    <details
                        v-if="
                            ['reported', 'scheduled', 'in_progress'].includes(
                                record.status,
                            ) &&
                            (isAdmin ||
                                auth.roles.includes('Property Custodian'))
                        "
                        class="mt-3 rounded-lg border border-border/60"
                    >
                        <summary
                            class="cursor-pointer px-3 py-2 text-sm font-medium"
                        >
                            Service details
                        </summary>
                        <div
                            class="grid gap-3 border-t border-border/60 p-3 sm:grid-cols-2"
                        >
                            <label class="grid gap-1 text-sm font-medium"
                                >Assigned personnel<select
                                    v-model="
                                        maintenanceDetails[record.id]
                                            .assigned_to
                                    "
                                    class="min-h-11 rounded-md border border-input bg-background px-3 sm:min-h-9"
                                >
                                    <option value="">Not assigned</option>
                                    <option
                                        v-for="user in users"
                                        :key="user.id"
                                        :value="String(user.id)"
                                    >
                                        {{ user.name }}
                                    </option>
                                </select></label
                            >
                            <label class="grid gap-1 text-sm font-medium"
                                >Scheduled service date<input
                                    v-model="
                                        maintenanceDetails[record.id]
                                            .scheduled_for
                                    "
                                    type="date"
                                    class="min-h-11 rounded-md border border-input bg-background px-3 sm:min-h-9"
                            /></label>
                            <label class="grid gap-1 text-sm font-medium"
                                >Service provider<input
                                    v-model="
                                        maintenanceDetails[record.id]
                                            .service_provider
                                    "
                                    placeholder="Internal team or service provider"
                                    class="min-h-11 rounded-md border border-input bg-background px-3 sm:min-h-9"
                            /></label>
                            <label class="grid gap-1 text-sm font-medium"
                                >Repair cost<input
                                    v-model="
                                        maintenanceDetails[record.id]
                                            .repair_cost
                                    "
                                    min="0"
                                    step="0.01"
                                    type="number"
                                    placeholder="0.00"
                                    class="min-h-11 rounded-md border border-input bg-background px-3 sm:min-h-9"
                            /></label>
                            <label
                                class="grid gap-1 text-sm font-medium sm:col-span-2"
                                >Findings<textarea
                                    v-model="
                                        maintenanceDetails[record.id].findings
                                    "
                                    rows="2"
                                    placeholder="Record the inspection or repair findings"
                                    class="rounded-md border border-input bg-background px-3 py-2"
                                />
                            </label>
                            <label
                                class="grid gap-1 text-sm font-medium sm:col-span-2"
                                >Internal notes<textarea
                                    v-model="
                                        maintenanceDetails[record.id].notes
                                    "
                                    rows="2"
                                    placeholder="Add details for the maintenance history"
                                    class="rounded-md border border-input bg-background px-3 py-2"
                                />
                            </label>
                            <p
                                class="text-xs text-muted-foreground sm:col-span-2"
                            >
                                These details are saved with the next status
                                change.
                            </p>
                        </div>
                    </details>
                    <div
                        v-if="
                            ['reported', 'scheduled', 'in_progress'].includes(
                                record.status,
                            ) &&
                            (isAdmin ||
                                auth.roles.includes('Property Custodian'))
                        "
                        class="mt-3 flex flex-wrap gap-2"
                    >
                        <Button
                            v-if="record.status === 'reported'"
                            size="sm"
                            variant="outline"
                            :disabled="
                                pendingActions.includes(
                                    `maintenance-${record.id}`,
                                )
                            "
                            @click="
                                transitionMaintenanceStatus(record, 'scheduled')
                            "
                            >Schedule</Button
                        ><Button
                            v-if="
                                ['reported', 'scheduled'].includes(
                                    record.status,
                                )
                            "
                            size="sm"
                            :disabled="
                                pendingActions.includes(
                                    `maintenance-${record.id}`,
                                )
                            "
                            @click="
                                transitionMaintenanceStatus(
                                    record,
                                    'in_progress',
                                )
                            "
                            >Start Work</Button
                        ><Button
                            v-if="record.status === 'in_progress'"
                            size="sm"
                            :disabled="
                                pendingActions.includes(
                                    `maintenance-${record.id}`,
                                )
                            "
                            @click="
                                transitionMaintenanceStatus(record, 'completed')
                            "
                            >Mark Completed</Button
                        ><Button
                            size="sm"
                            variant="destructive"
                            :disabled="
                                pendingActions.includes(
                                    `maintenance-${record.id}`,
                                )
                            "
                            @click="
                                transitionMaintenanceStatus(record, 'cancelled')
                            "
                            >Cancel</Button
                        >
                    </div>
                </article>
                <p
                    v-if="asset.maintenances.length === 0"
                    class="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground"
                >
                    No maintenance records have been created for this asset.
                </p>
            </section>

            <aside class="space-y-6">
                <section class="space-y-3" aria-labelledby="transfer-title">
                    <div>
                        <h2
                            id="transfer-title"
                            class="flex items-center gap-2 text-lg font-semibold"
                        >
                            <MapPin
                                aria-hidden="true"
                                class="h-5 w-5"
                            />Location transfers
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            A transfer changes physical location only.
                            Accountability still requires the handover workflow.
                        </p>
                    </div>
                    <form
                        v-if="
                            can.createTransfer &&
                            asset.lifecycle_status === 'active'
                        "
                        class="grid gap-3 rounded-xl border border-border/70 bg-card p-4"
                        @submit.prevent="submitTransfer"
                    >
                        <label class="grid gap-1 text-sm font-medium"
                            >Destination<select
                                v-model="transferForm.to_location_id"
                                required
                                class="min-h-11 rounded-md border border-input bg-background px-3 sm:min-h-9"
                            >
                                <option value="" disabled>
                                    Select a location
                                </option>
                                <option
                                    v-for="location in locations"
                                    :key="location.id"
                                    :value="String(location.id)"
                                >
                                    {{ location.code }} — {{ location.name }}
                                </option></select
                            ><InputError
                                :message="
                                    transferForm.errors.to_location_id
                                " /></label
                        ><label class="grid gap-1 text-sm font-medium"
                            >Reason<textarea
                                v-model="transferForm.reason"
                                required
                                rows="2"
                                placeholder="Why is this asset moving?"
                                class="rounded-md border border-input bg-background px-3 py-2"
                            /></label
                        ><label class="grid gap-1 text-sm font-medium"
                            >Transfer date<input
                                v-model="transferForm.transfer_date"
                                type="date"
                                required
                                class="min-h-11 rounded-md border border-input bg-background px-3 sm:min-h-9" /></label
                        ><Button
                            type="submit"
                            :disabled="transferForm.processing"
                            >Submit Transfer Request</Button
                        >
                    </form>
                    <article
                        v-for="record in asset.transfers"
                        :key="record.id"
                        class="rounded-xl border border-border/70 bg-card p-4 text-sm"
                    >
                        <div class="flex justify-between gap-2">
                            <strong
                                >{{
                                    record.from_location?.name ?? 'Unrecorded'
                                }}
                                → {{ record.to_location.name }}</strong
                            ><span class="text-xs text-muted-foreground">{{
                                label(record.status)
                            }}</span>
                        </div>
                        <p class="mt-2">{{ record.reason }}</p>
                        <p class="mt-2 text-xs text-muted-foreground">
                            Requested by {{ record.initiator.name }} for
                            {{ formatDate(record.transfer_date) }}
                        </p>
                        <div
                            v-if="
                                record.status === 'pending' &&
                                (isAdmin ||
                                    record.initiator.id === auth.user.id)
                            "
                            class="mt-3 flex flex-wrap gap-2"
                        >
                            <Button
                                v-if="
                                    isAdmin &&
                                    record.initiator.id !== auth.user.id
                                "
                                size="sm"
                                :disabled="
                                    pendingActions.includes(
                                        `transfer-${record.id}`,
                                    )
                                "
                                @click="reviewTransfer(record, 'approve')"
                                >Approve</Button
                            ><Button
                                v-if="
                                    isAdmin &&
                                    record.initiator.id !== auth.user.id
                                "
                                size="sm"
                                variant="destructive"
                                :disabled="
                                    pendingActions.includes(
                                        `transfer-${record.id}`,
                                    )
                                "
                                @click="reviewTransfer(record, 'reject')"
                                >Reject</Button
                            ><Button
                                size="sm"
                                variant="outline"
                                :disabled="
                                    pendingActions.includes(
                                        `transfer-${record.id}`,
                                    )
                                "
                                @click="cancelPendingTransfer(record)"
                                >Cancel Request</Button
                            >
                        </div>
                    </article>
                </section>
            </aside>
        </div>

        <section class="space-y-4" aria-labelledby="lifecycle-title">
            <div>
                <h2
                    id="lifecycle-title"
                    class="flex items-center gap-2 text-lg font-semibold"
                >
                    <ShieldCheck aria-hidden="true" class="h-5 w-5" />Lifecycle
                    and condition history
                </h2>
                <p class="text-sm text-muted-foreground">
                    Condition changes are separate from retirement and disposal.
                </p>
            </div>
            <details
                v-if="can.createLifecycle && lifecycleOptions.length > 0"
                class="rounded-xl border border-border/70 bg-card"
            >
                <summary class="cursor-pointer px-4 py-3 font-medium">
                    Propose a lifecycle or condition change
                </summary>
                <form
                    class="grid gap-3 border-t border-border/60 p-4 md:grid-cols-3"
                    @submit.prevent="submitLifecycle"
                >
                    <label class="grid gap-1 text-sm font-medium"
                        >Change type<select
                            v-model="lifecycleForm.event_type"
                            class="min-h-11 rounded-md border border-input bg-background px-3 sm:min-h-9"
                        >
                            <option
                                v-for="option in lifecycleOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select></label
                    ><label
                        v-if="lifecycleForm.event_type === 'condition_change'"
                        class="grid gap-1 text-sm font-medium"
                        >New condition<select
                            v-model="lifecycleForm.condition_status"
                            required
                            class="min-h-11 rounded-md border border-input bg-background px-3 sm:min-h-9"
                        >
                            <option value="" disabled>Select condition</option>
                            <option value="serviceable">Serviceable</option>
                            <option value="unserviceable">Unserviceable</option>
                            <option value="damaged">Damaged</option>
                        </select></label
                    ><label class="grid gap-1 text-sm font-medium"
                        >Proposed date<input
                            v-model="lifecycleForm.proposed_for"
                            type="date"
                            class="min-h-11 rounded-md border border-input bg-background px-3 sm:min-h-9" /></label
                    ><label
                        v-if="
                            ['retirement', 'disposal'].includes(
                                lifecycleForm.event_type,
                            )
                        "
                        class="grid gap-1 text-sm font-medium"
                        >Method<input
                            v-model="lifecycleForm.method"
                            placeholder="Example: Transfer to disposal committee"
                            class="min-h-11 rounded-md border border-input bg-background px-3 sm:min-h-9" /></label
                    ><label
                        v-if="
                            ['retirement', 'disposal'].includes(
                                lifecycleForm.event_type,
                            )
                        "
                        class="grid gap-1 text-sm font-medium md:col-span-2"
                        >Supporting reference<input
                            v-model="lifecycleForm.supporting_reference"
                            placeholder="Document, resolution, or inspection reference"
                            class="min-h-11 rounded-md border border-input bg-background px-3 sm:min-h-9" /></label
                    ><label class="grid gap-1 text-sm font-medium md:col-span-3"
                        >Reason<textarea
                            v-model="lifecycleForm.reason"
                            required
                            rows="3"
                            placeholder="Explain why this change is needed"
                            class="rounded-md border border-input bg-background px-3 py-2" /><InputError
                            :message="
                                lifecycleForm.errors.event_type ||
                                lifecycleForm.errors.reason
                            "
                    /></label>
                    <label class="grid gap-1 text-sm font-medium md:col-span-3"
                        >Review notes<textarea
                            v-model="lifecycleForm.notes"
                            rows="2"
                            placeholder="Add any details reviewers should consider"
                            class="rounded-md border border-input bg-background px-3 py-2"
                        />
                    </label>
                    <div class="flex justify-end md:col-span-3">
                        <Button
                            type="submit"
                            :disabled="lifecycleForm.processing"
                            >Submit for Approval</Button
                        >
                    </div>
                </form>
            </details>
            <div class="grid gap-3 lg:grid-cols-2">
                <article
                    v-for="record in asset.lifecycle_events"
                    :key="record.id"
                    class="rounded-xl border border-border/70 bg-card p-4"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-semibold">
                                {{ label(record.event_type) }}
                            </h3>
                            <p class="mt-1 text-sm">{{ record.reason }}</p>
                        </div>
                        <span
                            class="rounded-full bg-muted px-2.5 py-1 text-xs"
                            >{{ label(record.status) }}</span
                        >
                    </div>
                    <p class="mt-3 text-xs text-muted-foreground">
                        Requested by {{ record.initiator.name
                        }}<template v-if="record.approver">
                            · Reviewed by {{ record.approver.name }}</template
                        >
                    </p>
                    <div
                        v-if="
                            isAdmin &&
                            record.status === 'pending' &&
                            record.initiator.id !== auth.user.id
                        "
                        class="mt-3 flex gap-2"
                    >
                        <Button
                            size="sm"
                            :disabled="
                                pendingActions.includes(
                                    `lifecycle-${record.id}`,
                                )
                            "
                            @click="reviewLifecycle(record, 'approve')"
                            >Approve</Button
                        ><Button
                            size="sm"
                            variant="destructive"
                            :disabled="
                                pendingActions.includes(
                                    `lifecycle-${record.id}`,
                                )
                            "
                            @click="reviewLifecycle(record, 'reject')"
                            >Reject</Button
                        >
                    </div>
                </article>
            </div>
        </section>

        <section class="space-y-4" aria-labelledby="documents-title">
            <div>
                <h2
                    id="documents-title"
                    class="flex items-center gap-2 text-lg font-semibold"
                >
                    <FileText aria-hidden="true" class="h-5 w-5" />Supporting
                    documents
                </h2>
                <p class="text-sm text-muted-foreground">
                    Files are stored privately and can only be downloaded
                    through an authorized PRISM session.
                </p>
            </div>
            <form
                class="grid gap-3 rounded-xl border border-border/70 bg-card p-4 md:grid-cols-4 md:items-end"
                enctype="multipart/form-data"
                @submit.prevent="submitAttachment"
            >
                <label class="grid min-w-0 gap-1 text-sm font-medium"
                    >Document title<input
                        v-model="attachmentForm.title"
                        required
                        class="min-h-11 w-full min-w-0 rounded-md border border-input bg-background px-3 sm:min-h-9" /></label
                ><label class="grid min-w-0 gap-1 text-sm font-medium"
                    >Category<select
                        v-model="attachmentForm.category"
                        class="min-h-11 w-full min-w-0 rounded-md border border-input bg-background px-3 sm:min-h-9"
                    >
                        <option value="receipt">Receipt</option>
                        <option value="acknowledgment">Acknowledgment</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="inspection">Inspection</option>
                        <option value="transfer">Transfer</option>
                        <option value="disposal">Disposal</option>
                        <option value="other">Other</option>
                    </select></label
                ><label class="grid min-w-0 gap-1 text-sm font-medium"
                    >File (up to 10 MB)<input
                        required
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                        class="min-h-11 w-full max-w-full min-w-0 rounded-md border border-input bg-background px-3 py-2 text-sm"
                        @change="setFile" /><InputError
                        :message="attachmentForm.errors.file" /></label
                ><Button
                    type="submit"
                    class="w-full"
                    :disabled="attachmentForm.processing"
                    >{{
                        attachmentForm.processing
                            ? 'Uploading…'
                            : 'Upload Document'
                    }}</Button
                >
            </form>
            <div class="grid gap-3 md:grid-cols-2">
                <article
                    v-for="record in asset.attachments"
                    :key="record.id"
                    class="flex items-start justify-between gap-3 rounded-xl border border-border/70 bg-card p-4"
                >
                    <div class="min-w-0">
                        <h3 class="truncate font-semibold">
                            {{ record.title }}
                        </h3>
                        <p class="truncate text-sm text-muted-foreground">
                            {{ record.original_name }} ·
                            {{ Math.ceil(record.size / 1024) }} KB
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Uploaded by {{ record.uploader.name }}
                        </p>
                    </div>
                    <div class="flex shrink-0 gap-1">
                        <Button size="sm" variant="outline" as-child
                            ><a :href="downloadAttachment(record.id).url"
                                ><Download
                                    aria-hidden="true"
                                    class="mr-1 h-4 w-4"
                                />Download</a
                            ></Button
                        ><Button
                            v-if="
                                isAdmin || record.uploaded_by === auth.user.id
                            "
                            size="sm"
                            variant="destructive"
                            @click="removeAttachment(record)"
                            >Remove</Button
                        >
                    </div>
                </article>
            </div>
            <p
                v-if="asset.attachments.length === 0"
                class="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground"
            >
                No supporting documents have been uploaded for this asset.
            </p>
        </section>
    </div>
</template>
