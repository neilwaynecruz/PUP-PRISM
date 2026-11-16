<script setup lang="ts">
import {
    Head,
    Link,
    router,
    setLayoutProps,
    useForm,
    usePage,
} from '@inertiajs/vue3';
import {
    Activity,
    ArrowLeft,
    CheckCircle2,
    Download,
    FileText,
    MapPin,
    RotateCcw,
    ShieldCheck,
    UserCheck,
    Wrench,
} from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { index, returnMethod, show } from '@/routes/inventory/assets';
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
    serial_number: string | null;
    status: string;
    lifecycle_status: string;
    condition_status: string;
    acquisition_date: string | null;
    acquisition_cost: number | string | null;
    warranty_expires_at: string | null;
    installed_at: string | null;
    maintenance_interval_days: number | null;
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
        returnAsset?: boolean;
    };
}>();
defineOptions({
    name: 'InventoryAssetShowPage',
});

watch(
    () => [props.asset.id, props.asset.tag_code] as const,
    ([id, tagCode]) => {
        setLayoutProps({
            breadcrumbs: [
                { title: 'Assets', href: index() },
                { title: tagCode || 'Asset details', href: show(id) },
            ],
        });
    },
    { immediate: true },
);

const page = usePage();
const auth = computed(() => page.props.auth as Auth);
const isAdmin = computed(() => auth.value.roles.includes('Admin'));
const pendingActions = ref<string[]>([]);
const maintenanceDetails = reactive<Record<number, MaintenanceDetails>>({});

watch(
    () => props.asset.maintenances,
    (records) => {
        const activeIds = new Set(records.map((record) => record.id));

        for (const existingId of Object.keys(maintenanceDetails)) {
            if (!activeIds.has(Number(existingId))) {
                delete maintenanceDetails[Number(existingId)];
            }
        }

        for (const record of records) {
            maintenanceDetails[record.id] = {
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

const returnDialogOpen = ref(false);
const returnForm = useForm({
    location_id: props.asset.current_location?.id
        ? String(props.asset.current_location.id)
        : '',
    condition_status: props.asset.condition_status || 'serviceable',
    notes: '',
});

function openReturnDialog(): void {
    returnForm.location_id = props.asset.current_location?.id
        ? String(props.asset.current_location.id)
        : '';
    returnForm.condition_status = props.asset.condition_status || 'serviceable';
    returnForm.notes = '';
    returnForm.clearErrors();
    returnDialogOpen.value = true;
}

function submitReturnToStock(): void {
    returnForm
        .transform((data) => ({
            location_id: data.location_id ? Number(data.location_id) : null,
            condition_status: data.condition_status || null,
            notes: data.notes.trim() || null,
        }))
        .post(returnMethod(props.asset.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                returnDialogOpen.value = false;
                returnForm.reset('notes');
            },
        });
}

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

function formatCurrency(value: number | string | null): string {
    if (value === null || value === '') {
        return 'Not recorded';
    }

    const numeric = Number(value);

    if (!Number.isFinite(numeric)) {
        return 'Not recorded';
    }

    return `₱${numeric.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}
type AssetConfirmAction = {
    actionKey: string;
    title: string;
    description: string;
    confirmLabel: string;
    destructive: boolean;
    onConfirm: () => void;
};

const confirmActionDialog = ref<{
    open: boolean;
    actionKey: string;
    title: string;
    description: string;
    confirmLabel: string;
    destructive: boolean;
    onConfirm: (() => void) | null;
}>({
    open: false,
    actionKey: '',
    title: '',
    description: '',
    confirmLabel: 'Confirm',
    destructive: false,
    onConfirm: null,
});

function openAssetConfirmDialog(config: AssetConfirmAction): void {
    if (
        config.actionKey !== '' &&
        pendingActions.value.includes(config.actionKey)
    ) {
        return;
    }

    confirmActionDialog.value = {
        open: true,
        ...config,
    };
}

function executeAssetConfirmedAction(): void {
    const callback = confirmActionDialog.value.onConfirm;
    confirmActionDialog.value.open = false;
    confirmActionDialog.value.onConfirm = null;
    callback?.();
}

function transitionMaintenanceStatus(
    record: Maintenance,
    status: string,
): void {
    const actionKey = `maintenance-${record.id}`;

    openAssetConfirmDialog({
        actionKey,
        title: `Mark Maintenance as ${label(status)}`,
        description: `Change this maintenance record to ${label(status)}?`,
        confirmLabel: `Confirm ${label(status)}`,
        destructive: status === 'cancelled',
        onConfirm: () => {
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
        },
    });
}
function reviewTransfer(record: Transfer, action: 'approve' | 'reject'): void {
    const actionKey = `transfer-${record.id}`;

    openAssetConfirmDialog({
        actionKey,
        title:
            action === 'approve'
                ? 'Approve Asset Transfer'
                : 'Reject Asset Transfer',
        description:
            action === 'approve'
                ? 'Approve this asset location transfer?'
                : 'Reject this asset location transfer?',
        confirmLabel:
            action === 'approve' ? 'Confirm Approval' : 'Confirm Rejection',
        destructive: action === 'reject',
        onConfirm: () => {
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
        },
    });
}
function cancelPendingTransfer(record: Transfer): void {
    const actionKey = `transfer-${record.id}`;

    openAssetConfirmDialog({
        actionKey,
        title: 'Cancel Pending Transfer',
        description:
            'Cancel this pending transfer? The asset location will not change.',
        confirmLabel: 'Confirm Cancellation',
        destructive: true,
        onConfirm: () => {
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
        },
    });
}
function reviewLifecycle(
    record: LifecycleEvent,
    action: 'approve' | 'reject',
): void {
    const actionKey = `lifecycle-${record.id}`;

    openAssetConfirmDialog({
        actionKey,
        title:
            action === 'approve'
                ? 'Approve Lifecycle Change'
                : 'Reject Lifecycle Change',
        description:
            action === 'approve'
                ? 'Approve this lifecycle change?'
                : 'Reject this lifecycle change?',
        confirmLabel:
            action === 'approve' ? 'Confirm Approval' : 'Confirm Rejection',
        destructive: action === 'reject',
        onConfirm: () => {
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
        },
    });
}
function removeAttachment(record: Attachment): void {
    openAssetConfirmDialog({
        actionKey: `attachment-${record.id}`,
        title: 'Remove Supporting Document',
        description: `Remove “${record.title}” from active use? The retained file will remain protected.`,
        confirmLabel: 'Confirm Removal',
        destructive: true,
        onConfirm: () => {
            router.delete(destroyAttachment(record.id).url, {
                preserveScroll: true,
            });
        },
    });
}
</script>

<template>
    <Head :title="`Asset ${asset.tag_code}`" />
    <div
        class="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 sm:p-6 lg:p-8"
    >
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
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

            <Button
                v-if="can.returnAsset && asset.status === 'Checked_Out'"
                type="button"
                variant="outline"
                data-testid="return-asset-button"
                class="min-h-10 gap-2 self-start sm:self-auto"
                @click="openReturnDialog"
            >
                <RotateCcw aria-hidden="true" class="h-4 w-4" />
                Return to stock
            </Button>
        </div>

        <Dialog v-model:open="returnDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Return asset to stock</DialogTitle>
                    <DialogDescription>
                        Release custodian accountability for
                        <strong>{{ asset.tag_code }}</strong> and return it to
                        available stock.
                    </DialogDescription>
                </DialogHeader>

                <form
                    id="return-asset-form"
                    class="grid gap-4 py-2"
                    @submit.prevent="submitReturnToStock"
                >
                    <label class="grid gap-1.5 text-xs font-medium">
                        <span>Destination storage location</span>
                        <select
                            v-model="returnForm.location_id"
                            data-testid="return-asset-location"
                            class="h-10 w-full rounded-lg border border-input bg-background px-3 text-sm text-foreground"
                        >
                            <option value="">Keep current location</option>
                            <option
                                v-for="location in locations"
                                :key="location.id"
                                :value="String(location.id)"
                            >
                                {{ location.name }} ({{ location.code }})
                            </option>
                        </select>
                        <InputError :message="returnForm.errors.location_id" />
                    </label>

                    <label class="grid gap-1.5 text-xs font-medium">
                        <span>Condition upon return</span>
                        <select
                            v-model="returnForm.condition_status"
                            data-testid="return-asset-condition"
                            class="h-10 w-full rounded-lg border border-input bg-background px-3 text-sm text-foreground"
                        >
                            <option value="serviceable">Serviceable</option>
                            <option value="unserviceable">Unserviceable</option>
                            <option value="damaged">Damaged</option>
                        </select>
                        <InputError
                            :message="returnForm.errors.condition_status"
                        />
                    </label>

                    <label class="grid gap-1.5 text-xs font-medium">
                        <span>Return notes (optional)</span>
                        <textarea
                            v-model="returnForm.notes"
                            rows="3"
                            placeholder="Condition check notes or custodian turnover remarks"
                            data-testid="return-asset-notes"
                            class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground"
                        />
                        <InputError :message="returnForm.errors.notes" />
                        <InputError
                            :message="
                                (
                                    returnForm.errors as Record<
                                        string,
                                        string | undefined
                                    >
                                ).return
                            "
                        />
                    </label>
                </form>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        form="return-asset-form"
                        data-testid="confirm-return-asset-button"
                        :disabled="returnForm.processing"
                    >
                        {{
                            returnForm.processing
                                ? 'Returning…'
                                : 'Confirm return'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <section
            aria-label="Asset summary"
            class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5"
        >
            <div
                class="rounded-xl border border-border/70 bg-card p-4 shadow-xs"
            >
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-medium text-muted-foreground">
                        Availability
                    </p>
                    <Activity
                        aria-hidden="true"
                        class="h-4 w-4 text-muted-foreground/60"
                    />
                </div>
                <p class="mt-2 text-base font-semibold text-foreground">
                    {{ label(asset.status) }}
                </p>
            </div>
            <div
                class="rounded-xl border border-border/70 bg-card p-4 shadow-xs"
            >
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-medium text-muted-foreground">
                        Condition
                    </p>
                    <CheckCircle2
                        aria-hidden="true"
                        class="h-4 w-4 text-muted-foreground/60"
                    />
                </div>
                <p class="mt-2 text-base font-semibold text-foreground">
                    {{ label(asset.condition_status) }}
                </p>
            </div>
            <div
                class="rounded-xl border border-border/70 bg-card p-4 shadow-xs"
            >
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-medium text-muted-foreground">
                        Lifecycle
                    </p>
                    <ShieldCheck
                        aria-hidden="true"
                        class="h-4 w-4 text-muted-foreground/60"
                    />
                </div>
                <p class="mt-2 text-base font-semibold text-foreground">
                    {{ label(asset.lifecycle_status) }}
                </p>
            </div>
            <div
                class="rounded-xl border border-border/70 bg-card p-4 shadow-xs"
            >
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-medium text-muted-foreground">
                        Location
                    </p>
                    <MapPin
                        aria-hidden="true"
                        class="h-4 w-4 text-muted-foreground/60"
                    />
                </div>
                <p
                    class="mt-2 truncate text-base font-semibold text-foreground"
                    :title="asset.current_location?.name ?? 'Not recorded'"
                >
                    {{ asset.current_location?.name ?? 'Not recorded' }}
                </p>
            </div>
            <div
                class="rounded-xl border border-border/70 bg-card p-4 shadow-xs"
            >
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-medium text-muted-foreground">
                        Accountability
                    </p>
                    <UserCheck
                        aria-hidden="true"
                        class="h-4 w-4 text-muted-foreground/60"
                    />
                </div>
                <p
                    class="mt-2 truncate text-base font-semibold text-foreground"
                    :title="asset.position?.title ?? 'In stock'"
                >
                    {{ asset.position?.title ?? 'In stock' }}
                </p>
            </div>
        </section>

        <section
            aria-label="Asset acquisition and service details"
            class="rounded-xl border border-border/70 bg-card p-4 shadow-xs"
        >
            <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <dt class="text-xs text-muted-foreground">Serial number</dt>
                    <dd class="mt-1 font-medium text-foreground">
                        {{ asset.serial_number ?? 'Not recorded' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Acquired</dt>
                    <dd class="mt-1 font-medium text-foreground">
                        {{ asset.acquisition_date ?? 'Not recorded' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        Acquisition cost
                    </dt>
                    <dd class="mt-1 font-medium text-foreground">
                        {{ formatCurrency(asset.acquisition_cost) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        Warranty through
                    </dt>
                    <dd class="mt-1 font-medium text-foreground">
                        {{ asset.warranty_expires_at ?? 'Not recorded' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        Service interval
                    </dt>
                    <dd class="mt-1 font-medium text-foreground">
                        {{
                            asset.maintenance_interval_days
                                ? `${asset.maintenance_interval_days} days`
                                : 'As needed'
                        }}
                    </dd>
                </div>
            </dl>
        </section>

        <div class="grid gap-6 xl:grid-cols-3">
            <section
                class="min-w-0 space-y-4 xl:col-span-2"
                aria-labelledby="maintenance-title"
            >
                <div>
                    <h2
                        id="maintenance-title"
                        class="flex items-center gap-2 text-lg font-semibold"
                    >
                        <Wrench
                            aria-hidden="true"
                            class="h-5 w-5 text-primary"
                        />Maintenance and repairs
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        Record service work and outcomes. Existing bookings are
                        protected and must be resolved before work begins.
                    </p>
                </div>
                <details
                    v-if="
                        can.createMaintenance &&
                        asset.lifecycle_status === 'active'
                    "
                    class="overflow-hidden rounded-xl border border-border/70 bg-card shadow-xs"
                    open
                >
                    <summary
                        class="cursor-pointer px-4 py-3 text-sm font-medium transition hover:bg-muted/40"
                    >
                        Create maintenance request
                    </summary>
                    <form
                        class="grid w-full min-w-0 gap-3.5 border-t border-border/60 p-4 sm:grid-cols-2"
                        @submit.prevent="submitMaintenance"
                    >
                        <label
                            class="grid w-full min-w-0 gap-1.5 text-xs font-medium text-foreground"
                        >
                            <span>Maintenance type</span>
                            <select
                                v-model="maintenanceForm.maintenance_type"
                                class="h-10 w-full max-w-full min-w-0 rounded-lg border border-input bg-background px-3 text-sm text-foreground transition focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
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
                            </select>
                        </label>
                        <label
                            class="grid w-full min-w-0 gap-1.5 text-xs font-medium text-foreground"
                        >
                            <span>Reported date</span>
                            <input
                                v-model="maintenanceForm.reported_date"
                                required
                                type="date"
                                class="h-10 w-full max-w-full min-w-0 rounded-lg border border-input bg-background px-3 text-sm text-foreground transition focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                            />
                        </label>
                        <label
                            class="grid w-full min-w-0 gap-1.5 text-xs font-medium text-foreground sm:col-span-2"
                        >
                            <span>Reported issue</span>
                            <textarea
                                v-model="maintenanceForm.reported_issue"
                                required
                                rows="3"
                                placeholder="Describe the issue in clear, practical terms"
                                class="w-full max-w-full min-w-0 rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground transition placeholder:text-muted-foreground focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                            />
                            <InputError
                                :message="
                                    maintenanceForm.errors.reported_issue ||
                                    maintenanceForm.errors.asset_id
                                "
                            />
                        </label>
                        <label
                            class="grid w-full min-w-0 gap-1.5 text-xs font-medium text-foreground"
                        >
                            <span>Assigned personnel</span>
                            <select
                                v-model="maintenanceForm.assigned_to"
                                class="h-10 w-full max-w-full min-w-0 rounded-lg border border-input bg-background px-3 text-sm text-foreground transition focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                            >
                                <option value="">Assign later</option>
                                <option
                                    v-for="user in users"
                                    :key="user.id"
                                    :value="String(user.id)"
                                >
                                    {{ user.name }}
                                </option>
                            </select>
                        </label>
                        <label
                            class="grid w-full min-w-0 gap-1.5 text-xs font-medium text-foreground"
                        >
                            <span>Scheduled service date</span>
                            <input
                                v-model="maintenanceForm.scheduled_for"
                                type="date"
                                class="h-10 w-full max-w-full min-w-0 rounded-lg border border-input bg-background px-3 text-sm text-foreground transition focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                            />
                        </label>
                        <div class="flex justify-end sm:col-span-2">
                            <Button
                                type="submit"
                                class="h-9 rounded-lg px-4 text-xs font-semibold shadow-xs"
                                :disabled="maintenanceForm.processing"
                            >
                                {{
                                    maintenanceForm.processing
                                        ? 'Creating request…'
                                        : 'Create Maintenance Request'
                                }}
                            </Button>
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
                        class="mt-3 overflow-hidden rounded-lg border border-border/60 bg-muted/20"
                    >
                        <summary
                            class="cursor-pointer px-3 py-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase transition hover:bg-muted/40"
                        >
                            Service details
                        </summary>
                        <div
                            class="grid w-full min-w-0 gap-3 border-t border-border/60 p-3 sm:grid-cols-2"
                        >
                            <label
                                class="grid w-full min-w-0 gap-1 text-xs font-medium text-foreground"
                            >
                                <span>Assigned personnel</span>
                                <select
                                    v-model="
                                        maintenanceDetails[record.id]
                                            .assigned_to
                                    "
                                    class="h-9 w-full max-w-full min-w-0 rounded-md border border-input bg-background px-2.5 text-xs text-foreground transition focus-visible:border-primary/50 focus-visible:ring-1 focus-visible:ring-primary/30 focus-visible:outline-none"
                                >
                                    <option value="">Not assigned</option>
                                    <option
                                        v-for="user in users"
                                        :key="user.id"
                                        :value="String(user.id)"
                                    >
                                        {{ user.name }}
                                    </option>
                                </select>
                            </label>
                            <label
                                class="grid w-full min-w-0 gap-1 text-xs font-medium text-foreground"
                            >
                                <span>Scheduled service date</span>
                                <input
                                    v-model="
                                        maintenanceDetails[record.id]
                                            .scheduled_for
                                    "
                                    type="date"
                                    class="h-9 w-full max-w-full min-w-0 rounded-md border border-input bg-background px-2.5 text-xs text-foreground transition focus-visible:border-primary/50 focus-visible:ring-1 focus-visible:ring-primary/30 focus-visible:outline-none"
                                />
                            </label>
                            <label
                                class="grid w-full min-w-0 gap-1 text-xs font-medium text-foreground"
                            >
                                <span>Service provider</span>
                                <input
                                    v-model="
                                        maintenanceDetails[record.id]
                                            .service_provider
                                    "
                                    placeholder="Internal team or service provider"
                                    class="h-9 w-full max-w-full min-w-0 rounded-md border border-input bg-background px-2.5 text-xs text-foreground transition focus-visible:border-primary/50 focus-visible:ring-1 focus-visible:ring-primary/30 focus-visible:outline-none"
                                />
                            </label>
                            <label
                                class="grid w-full min-w-0 gap-1 text-xs font-medium text-foreground"
                            >
                                <span>Repair cost</span>
                                <input
                                    v-model="
                                        maintenanceDetails[record.id]
                                            .repair_cost
                                    "
                                    min="0"
                                    step="0.01"
                                    type="number"
                                    placeholder="0.00"
                                    class="h-9 w-full max-w-full min-w-0 rounded-md border border-input bg-background px-2.5 text-xs text-foreground transition focus-visible:border-primary/50 focus-visible:ring-1 focus-visible:ring-primary/30 focus-visible:outline-none"
                                />
                            </label>
                            <label
                                class="grid w-full min-w-0 gap-1 text-xs font-medium text-foreground sm:col-span-2"
                            >
                                <span>Findings</span>
                                <textarea
                                    v-model="
                                        maintenanceDetails[record.id].findings
                                    "
                                    rows="2"
                                    placeholder="Record the inspection or repair findings"
                                    class="w-full max-w-full min-w-0 rounded-md border border-input bg-background px-2.5 py-2 text-xs text-foreground transition focus-visible:border-primary/50 focus-visible:ring-1 focus-visible:ring-primary/30 focus-visible:outline-none"
                                />
                            </label>
                            <label
                                class="grid w-full min-w-0 gap-1 text-xs font-medium text-foreground sm:col-span-2"
                            >
                                <span>Internal notes</span>
                                <textarea
                                    v-model="
                                        maintenanceDetails[record.id].notes
                                    "
                                    rows="2"
                                    placeholder="Add details for the maintenance history"
                                    class="w-full max-w-full min-w-0 rounded-md border border-input bg-background px-2.5 py-2 text-xs text-foreground transition focus-visible:border-primary/50 focus-visible:ring-1 focus-visible:ring-primary/30 focus-visible:outline-none"
                                />
                            </label>
                            <p
                                class="text-[11px] text-muted-foreground sm:col-span-2"
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

            <aside class="min-w-0 space-y-6">
                <section
                    class="min-w-0 space-y-3"
                    aria-labelledby="transfer-title"
                >
                    <div class="min-w-0">
                        <h2
                            id="transfer-title"
                            class="flex items-center gap-2 text-lg font-semibold"
                        >
                            <MapPin
                                aria-hidden="true"
                                class="h-5 w-5 text-primary"
                            />Location transfers
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            A transfer changes physical location only.
                            Accountability still requires the handover workflow.
                        </p>
                    </div>
                    <form
                        v-if="
                            can.createTransfer &&
                            asset.lifecycle_status === 'active'
                        "
                        class="w-full min-w-0 overflow-hidden rounded-xl border border-border/70 bg-card p-4 shadow-xs"
                        @submit.prevent="submitTransfer"
                    >
                        <div class="grid w-full min-w-0 gap-3.5">
                            <label
                                class="grid w-full min-w-0 gap-1.5 text-xs font-medium text-foreground"
                            >
                                <span>Destination</span>
                                <select
                                    v-model="transferForm.to_location_id"
                                    required
                                    class="h-10 w-full max-w-full min-w-0 rounded-lg border border-input bg-background px-3 text-sm text-foreground transition focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                                >
                                    <option value="" disabled>
                                        Select a location
                                    </option>
                                    <option
                                        v-for="location in locations"
                                        :key="location.id"
                                        :value="String(location.id)"
                                    >
                                        {{ location.code }} —
                                        {{ location.name }}
                                    </option>
                                </select>
                                <InputError
                                    :message="
                                        transferForm.errors.to_location_id
                                    "
                                />
                            </label>
                            <label
                                class="grid w-full min-w-0 gap-1.5 text-xs font-medium text-foreground"
                            >
                                <span>Reason</span>
                                <textarea
                                    v-model="transferForm.reason"
                                    required
                                    rows="2"
                                    placeholder="Why is this asset moving?"
                                    class="w-full max-w-full min-w-0 rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground transition placeholder:text-muted-foreground focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                                />
                            </label>
                            <label
                                class="grid w-full min-w-0 gap-1.5 text-xs font-medium text-foreground"
                            >
                                <span>Transfer date</span>
                                <input
                                    v-model="transferForm.transfer_date"
                                    type="date"
                                    required
                                    class="h-10 w-full max-w-full min-w-0 rounded-lg border border-input bg-background px-3 text-sm text-foreground transition focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                                />
                            </label>
                            <Button
                                type="submit"
                                class="h-9 w-full rounded-lg text-xs font-semibold shadow-xs"
                                :disabled="transferForm.processing"
                            >
                                {{
                                    transferForm.processing
                                        ? 'Submitting…'
                                        : 'Submit Transfer Request'
                                }}
                            </Button>
                        </div>
                    </form>
                    <article
                        v-for="record in asset.transfers"
                        :key="record.id"
                        class="w-full min-w-0 overflow-hidden rounded-xl border border-border/70 bg-card p-4 text-sm shadow-xs"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <strong
                                class="min-w-0 leading-tight font-semibold break-words text-foreground"
                            >
                                {{ record.from_location?.name ?? 'Unrecorded' }}
                                → {{ record.to_location.name }}
                            </strong>
                            <span
                                class="shrink-0 rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground"
                            >
                                {{ label(record.status) }}
                            </span>
                        </div>
                        <p
                            class="mt-2 text-xs leading-relaxed break-words text-muted-foreground"
                        >
                            {{ record.reason }}
                        </p>
                        <p class="mt-2 text-[11px] text-muted-foreground">
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

        <section class="min-w-0 space-y-4" aria-labelledby="lifecycle-title">
            <div>
                <h2
                    id="lifecycle-title"
                    class="flex items-center gap-2 text-lg font-semibold"
                >
                    <ShieldCheck
                        aria-hidden="true"
                        class="h-5 w-5 text-primary"
                    />Lifecycle and condition history
                </h2>
                <p class="text-xs text-muted-foreground">
                    Condition changes are separate from retirement and disposal.
                </p>
            </div>
            <details
                v-if="can.createLifecycle && lifecycleOptions.length > 0"
                class="overflow-hidden rounded-xl border border-border/70 bg-card shadow-xs"
            >
                <summary
                    class="cursor-pointer px-4 py-3 text-sm font-medium transition hover:bg-muted/40"
                >
                    Propose a lifecycle or condition change
                </summary>
                <form
                    class="grid w-full min-w-0 gap-3.5 border-t border-border/60 p-4 md:grid-cols-3"
                    @submit.prevent="submitLifecycle"
                >
                    <label
                        class="grid w-full min-w-0 gap-1.5 text-xs font-medium text-foreground"
                    >
                        <span>Change type</span>
                        <select
                            v-model="lifecycleForm.event_type"
                            class="h-10 w-full max-w-full min-w-0 rounded-lg border border-input bg-background px-3 text-sm text-foreground transition focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                        >
                            <option
                                v-for="option in lifecycleOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </label>
                    <label
                        v-if="lifecycleForm.event_type === 'condition_change'"
                        class="grid w-full min-w-0 gap-1.5 text-xs font-medium text-foreground"
                    >
                        <span>New condition</span>
                        <select
                            v-model="lifecycleForm.condition_status"
                            required
                            class="h-10 w-full max-w-full min-w-0 rounded-lg border border-input bg-background px-3 text-sm text-foreground transition focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                        >
                            <option value="" disabled>Select condition</option>
                            <option value="serviceable">Serviceable</option>
                            <option value="unserviceable">Unserviceable</option>
                            <option value="damaged">Damaged</option>
                        </select>
                    </label>
                    <label
                        class="grid w-full min-w-0 gap-1.5 text-xs font-medium text-foreground"
                    >
                        <span>Proposed date</span>
                        <input
                            v-model="lifecycleForm.proposed_for"
                            type="date"
                            class="h-10 w-full max-w-full min-w-0 rounded-lg border border-input bg-background px-3 text-sm text-foreground transition focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                        />
                    </label>
                    <label
                        v-if="
                            ['retirement', 'disposal'].includes(
                                lifecycleForm.event_type,
                            )
                        "
                        class="grid w-full min-w-0 gap-1.5 text-xs font-medium text-foreground"
                    >
                        <span>Method</span>
                        <input
                            v-model="lifecycleForm.method"
                            placeholder="Example: Transfer to disposal committee"
                            class="h-10 w-full max-w-full min-w-0 rounded-lg border border-input bg-background px-3 text-sm text-foreground transition focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                        />
                    </label>
                    <label
                        v-if="
                            ['retirement', 'disposal'].includes(
                                lifecycleForm.event_type,
                            )
                        "
                        class="grid w-full min-w-0 gap-1.5 text-xs font-medium text-foreground md:col-span-2"
                    >
                        <span>Supporting reference</span>
                        <input
                            v-model="lifecycleForm.supporting_reference"
                            placeholder="Document, resolution, or inspection reference"
                            class="h-10 w-full max-w-full min-w-0 rounded-lg border border-input bg-background px-3 text-sm text-foreground transition focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                        />
                    </label>
                    <label
                        class="grid w-full min-w-0 gap-1.5 text-xs font-medium text-foreground md:col-span-3"
                    >
                        <span>Reason</span>
                        <textarea
                            v-model="lifecycleForm.reason"
                            required
                            rows="3"
                            placeholder="Explain why this change is needed"
                            class="w-full max-w-full min-w-0 rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground transition placeholder:text-muted-foreground focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                        />
                        <InputError
                            :message="
                                lifecycleForm.errors.event_type ||
                                lifecycleForm.errors.reason
                            "
                        />
                    </label>
                    <label
                        class="grid w-full min-w-0 gap-1.5 text-xs font-medium text-foreground md:col-span-3"
                    >
                        <span>Review notes</span>
                        <textarea
                            v-model="lifecycleForm.notes"
                            rows="2"
                            placeholder="Add any details reviewers should consider"
                            class="w-full max-w-full min-w-0 rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground transition placeholder:text-muted-foreground focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                        />
                    </label>
                    <div class="flex justify-end md:col-span-3">
                        <Button
                            type="submit"
                            class="h-9 rounded-lg px-4 text-xs font-semibold shadow-xs"
                            :disabled="lifecycleForm.processing"
                        >
                            {{
                                lifecycleForm.processing
                                    ? 'Submitting…'
                                    : 'Submit for Approval'
                            }}
                        </Button>
                    </div>
                </form>
            </details>
            <div class="grid gap-3 lg:grid-cols-2">
                <article
                    v-for="record in asset.lifecycle_events"
                    :key="record.id"
                    class="rounded-xl border border-border/70 bg-card p-4 shadow-xs"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="font-semibold text-foreground">
                                {{ label(record.event_type) }}
                            </h3>
                            <p
                                class="mt-1 text-sm break-words text-muted-foreground"
                            >
                                {{ record.reason }}
                            </p>
                        </div>
                        <span
                            class="shrink-0 rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground"
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

        <section class="min-w-0 space-y-4" aria-labelledby="documents-title">
            <div>
                <h2
                    id="documents-title"
                    class="flex items-center gap-2 text-lg font-semibold"
                >
                    <FileText
                        aria-hidden="true"
                        class="h-5 w-5 text-primary"
                    />Supporting documents
                </h2>
                <p class="text-xs text-muted-foreground">
                    Files are stored privately and can only be downloaded
                    through an authorized PRISM session.
                </p>
            </div>
            <form
                class="grid w-full min-w-0 gap-3.5 rounded-xl border border-border/70 bg-card p-4 shadow-xs md:grid-cols-4 md:items-end"
                enctype="multipart/form-data"
                @submit.prevent="submitAttachment"
            >
                <label
                    class="grid w-full min-w-0 gap-1.5 text-xs font-medium text-foreground"
                >
                    <span>Document title</span>
                    <input
                        v-model="attachmentForm.title"
                        required
                        class="h-10 w-full max-w-full min-w-0 rounded-lg border border-input bg-background px-3 text-sm text-foreground transition focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                    />
                </label>
                <label
                    class="grid w-full min-w-0 gap-1.5 text-xs font-medium text-foreground"
                >
                    <span>Category</span>
                    <select
                        v-model="attachmentForm.category"
                        class="h-10 w-full max-w-full min-w-0 rounded-lg border border-input bg-background px-3 text-sm text-foreground transition focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                    >
                        <option value="receipt">Receipt</option>
                        <option value="acknowledgment">Acknowledgment</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="inspection">Inspection</option>
                        <option value="transfer">Transfer</option>
                        <option value="disposal">Disposal</option>
                        <option value="other">Other</option>
                    </select>
                </label>
                <label
                    class="grid w-full min-w-0 gap-1.5 text-xs font-medium text-foreground"
                >
                    <span>File (up to 10 MB)</span>
                    <input
                        required
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                        class="h-10 w-full max-w-full min-w-0 rounded-lg border border-input bg-background px-3 py-1.5 text-xs text-foreground transition file:mr-2 file:rounded-md file:border-0 file:bg-muted file:px-2.5 file:py-1 file:text-xs file:font-medium file:text-foreground hover:file:bg-muted/80"
                        @change="setFile"
                    />
                    <InputError :message="attachmentForm.errors.file" />
                </label>
                <Button
                    type="submit"
                    class="h-10 w-full rounded-lg text-xs font-semibold shadow-xs"
                    :disabled="attachmentForm.processing"
                >
                    {{
                        attachmentForm.processing
                            ? 'Uploading…'
                            : 'Upload Document'
                    }}
                </Button>
            </form>
            <div class="grid gap-3 md:grid-cols-2">
                <article
                    v-for="record in asset.attachments"
                    :key="record.id"
                    class="flex items-start justify-between gap-3 rounded-xl border border-border/70 bg-card p-4 shadow-xs"
                >
                    <div class="min-w-0">
                        <h3 class="truncate font-semibold text-foreground">
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
                    <div class="flex shrink-0 gap-1.5">
                        <Button size="sm" variant="outline" as-child
                            ><a :href="downloadAttachment(record.id).url"
                                ><Download
                                    aria-hidden="true"
                                    class="mr-1 h-3.5 w-3.5"
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

        <Dialog v-model:open="confirmActionDialog.open">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ confirmActionDialog.title }}</DialogTitle>
                    <DialogDescription>{{
                        confirmActionDialog.description
                    }}</DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="confirmActionDialog.open = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        data-testid="confirm-asset-action-button"
                        :variant="
                            confirmActionDialog.destructive
                                ? 'destructive'
                                : 'default'
                        "
                        @click="executeAssetConfirmedAction"
                    >
                        {{ confirmActionDialog.confirmLabel }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
