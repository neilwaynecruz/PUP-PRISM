<script setup lang="ts">
import { Form, Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowRightLeft,
    CheckCircle2,
    CircleAlert,
    Clock3,
    MapPin,
    Search,
    Send,
    ShieldCheck,
    UserRound,
    X,
    XCircle,
} from 'lucide-vue-next';
import {
    computed,
    defineAsyncComponent,
    onBeforeUnmount,
    ref,
    watch,
} from 'vue';
import HandoverController from '@/actions/App/Http/Controllers/Inventory/HandoverController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import OcrAssistButton from '@/components/inventory/OcrAssistButton.vue';
import { Button } from '@/components/ui/button';

const QrScannerDialog = defineAsyncComponent(
    () => import('@/components/inventory/QrScannerDialog.vue'),
);
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    cancel as handoverCancel,
    index as handoverIndex,
    resend as handoverResend,
} from '@/routes/inventory/handover';

type AssetOption = {
    id: number;
    tag_code: string;
    name: string | null;
    status: string | null;
    position: { title: string; department: string | null } | null;
    location: string | null;
    unavailable_reason: string | null;
};

type SelectedAsset = {
    id: number;
    tag_code: string;
    name: string | null;
    category: string | null;
    status: string | null;
    condition: string | null;
    lifecycle: string | null;
    location: { code: string; name: string } | null;
    position: {
        id: number;
        title: string;
        code: string;
        department: string | null;
    } | null;
    accountable_person: string | null;
    accountable_since: string | null;
    position_holders: string[];
    pending_handover: {
        id: number;
        to: string | null;
        initiated_at: string | null;
    } | null;
    eligible: boolean;
    blockers: string[];
};

type UserOption = {
    id: number;
    name: string;
    email: string;
    position: {
        id: number;
        title: string;
        code: string;
        department: string | null;
    } | null;
    unavailable_reason?: string | null;
};

type HandoverStatus = 'pending' | 'verified' | 'cancelled';

type RecentRow = {
    id: number;
    status: HandoverStatus;
    tag_code: string | null;
    asset_name: string | null;
    to: UserOption | null;
    initiated_by: string | null;
    from_position: { title: string; department: string | null } | null;
    to_position: { title: string; department: string | null } | null;
    initiated_at: string | null;
    last_sent_at: string | null;
    verified_at: string | null;
    cancelled_at: string | null;
    cancelled_by: string | null;
    cancellation_reason: string | null;
    notes: string | null;
};

type PaginationLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    filters: {
        asset_search: string;
        asset: string;
        recipient_search: string;
        history_status: '' | HandoverStatus;
        history_search: string;
    };
    assetOptions: AssetOption[];
    selectedAsset: SelectedAsset | null;
    selectedAssetMissing: boolean;
    users: UserOption[];
    recent: { data: RecentRow[]; links: PaginationLink[]; total: number };
    counts: { pending: number; verified_this_month: number; cancelled: number };
}>();

defineOptions({
    name: 'HandoverInitiatePage',
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: handoverIndex() },
            { title: 'Handover', href: handoverIndex() },
        ],
    },
});

const assetSearch = ref(props.filters.asset_search ?? '');
const recipientSearch = ref(props.filters.recipient_search ?? '');
const historyStatus = ref<'' | HandoverStatus>(
    props.filters.history_status ?? '',
);
const historySearch = ref(props.filters.history_search ?? '');
const selectedRecipientId = ref<number | null>(null);
const selectedRecipientSnapshot = ref<UserOption | null>(null);
const scanFeedback = ref('');
const isLoadingAsset = ref(false);

const selectedTag = computed(() => props.selectedAsset?.tag_code ?? '');

const selectedRecipient = computed<UserOption | null>(() => {
    if (selectedRecipientId.value === null) {
        return null;
    }

    return (
        props.users.find((user) => user.id === selectedRecipientId.value) ??
        selectedRecipientSnapshot.value
    );
});

const canSubmit = computed(
    () =>
        Boolean(props.selectedAsset?.eligible) &&
        selectedRecipient.value !== null &&
        !selectedRecipient.value.unavailable_reason,
);

function baseQuery(overrides: Record<string, string | undefined> = {}) {
    return {
        asset_search: assetSearch.value || undefined,
        asset: selectedTag.value || undefined,
        recipient_search: recipientSearch.value || undefined,
        history_status: historyStatus.value || undefined,
        history_search: historySearch.value || undefined,
        ...overrides,
    };
}

function visit(
    query: Record<string, string | undefined>,
    only: string[],
    extra: Record<string, unknown> = {},
): void {
    router.get(handoverIndex().url, query, {
        only: [...only, 'filters'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
        ...extra,
    });
}

const timers: Record<string, number | undefined> = {};
let isResettingSearches = false;

function debounce(key: string, callback: () => void): void {
    window.clearTimeout(timers[key]);
    timers[key] = window.setTimeout(callback, 250);
}

watch(
    assetSearch,
    () => {
        if (!isResettingSearches) {
            debounce('asset', () => visit(baseQuery(), ['assetOptions']));
        }
    },
    { flush: 'sync' },
);
watch(
    recipientSearch,
    () => {
        if (!isResettingSearches) {
            debounce('recipient', () => visit(baseQuery(), ['users']));
        }
    },
    { flush: 'sync' },
);
watch(historySearch, () =>
    debounce('history', () =>
        visit(baseQuery({ history_page: undefined }), ['recent']),
    ),
);
watch(historyStatus, () =>
    visit(baseQuery({ history_page: undefined }), ['recent']),
);

onBeforeUnmount(() => {
    Object.values(timers).forEach((timer) => window.clearTimeout(timer));
});

function selectAsset(tagCode: string): void {
    const tag = tagCode.trim();

    if (!tag) {
        return;
    }

    // A pending debounced search would otherwise cancel this visit.
    window.clearTimeout(timers.asset);

    visit(
        baseQuery({ asset: tag }),
        ['selectedAsset', 'selectedAssetMissing', 'users'],
        {
            onStart: () => (isLoadingAsset.value = true),
            onFinish: () => (isLoadingAsset.value = false),
        },
    );
}

function clearAsset(): void {
    scanFeedback.value = '';
    visit(baseQuery({ asset: undefined }), [
        'selectedAsset',
        'selectedAssetMissing',
        'users',
    ]);
}

/**
 * Enter in the asset box selects an exact tag match, so typed or
 * keyboard-wedge scanner input works without touching the list.
 */
function submitAssetSearch(): void {
    const typed = assetSearch.value.trim();

    if (!typed) {
        return;
    }

    const exact = props.assetOptions.find(
        (asset) => asset.tag_code.toLowerCase() === typed.toLowerCase(),
    );

    selectAsset(
        exact?.tag_code ??
            (props.assetOptions.length === 1
                ? props.assetOptions[0].tag_code
                : typed),
    );
}

function applyScannedTag(tagCode: string): void {
    scanFeedback.value = `Scanned ${tagCode.trim()}.`;
    selectAsset(tagCode);
}

function chooseRecipient(user: UserOption): void {
    if (user.unavailable_reason) {
        return;
    }

    selectedRecipientId.value = user.id;
    selectedRecipientSnapshot.value = user;
}

function clearRecipient(): void {
    selectedRecipientId.value = null;
    selectedRecipientSnapshot.value = null;
}

// A recipient who holds the newly selected asset's position is no longer valid.
watch(
    () => props.users,
    () => {
        const current = props.users.find(
            (user) => user.id === selectedRecipientId.value,
        );

        if (current?.unavailable_reason) {
            clearRecipient();
        }
    },
);

function onHandoverSent(): void {
    isResettingSearches = true;

    try {
        window.clearTimeout(timers.asset);
        window.clearTimeout(timers.recipient);
        clearRecipient();
        assetSearch.value = '';
        recipientSearch.value = '';
        scanFeedback.value = '';
    } finally {
        isResettingSearches = false;
    }
}

// ── Pending handover actions ──
const cancelTarget = ref<RecentRow | null>(null);
const cancelDialogOpen = ref(false);
const cancelForm = useForm({ cancellation_reason: '' });
const resendingId = ref<number | null>(null);

function openCancel(row: RecentRow): void {
    cancelTarget.value = row;
    cancelForm.reset();
    cancelForm.clearErrors();
    cancelDialogOpen.value = true;
}

function confirmCancel(): void {
    if (!cancelTarget.value) {
        return;
    }

    cancelForm.post(handoverCancel(cancelTarget.value.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            cancelDialogOpen.value = false;
            cancelTarget.value = null;
        },
    });
}

function resendLink(row: RecentRow): void {
    router.post(
        handoverResend(row.id).url,
        {},
        {
            preserveScroll: true,
            onStart: () => (resendingId.value = row.id),
            onFinish: () => (resendingId.value = null),
        },
    );
}

// ── Formatting ──
function formatDateTime(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}

function positionLabel(
    position: { title: string; department: string | null } | null,
): string {
    if (!position) {
        return 'No position';
    }

    return position.department
        ? `${position.title}, ${position.department}`
        : position.title;
}

function formatStatus(value: string | null): string {
    return value ? value.replace(/_/g, ' ') : '—';
}

const statusMeta: Record<
    HandoverStatus,
    { label: string; classes: string; stripe: string }
> = {
    pending: {
        label: 'Awaiting verification',
        classes:
            'border-amber-500/30 bg-amber-500/10 text-amber-800 dark:text-amber-300',
        stripe: 'bg-amber-500',
    },
    verified: {
        label: 'Verified',
        classes:
            'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        stripe: 'bg-emerald-500',
    },
    cancelled: {
        label: 'Cancelled',
        classes: 'border-border bg-muted/70 text-muted-foreground',
        stripe: 'bg-muted-foreground/40',
    },
};

const historyTabs: { value: '' | HandoverStatus; label: string }[] = [
    { value: '', label: 'All' },
    { value: 'pending', label: 'Awaiting' },
    { value: 'verified', label: 'Verified' },
    { value: 'cancelled', label: 'Cancelled' },
];
</script>

<template>
    <Head title="Asset handover" />

    <div
        class="flex flex-col gap-6 p-4 sm:p-6"
        data-testid="handover-initiate-page"
    >
        <div
            class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between"
        >
            <Heading
                variant="small"
                title="Digital asset handover"
                description="Transfer accountability for an asset to another person. The recipient confirms with a signature before any records change."
            />
            <div class="flex flex-wrap gap-2 text-xs">
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1.5 font-medium text-amber-800 transition hover:bg-amber-500/15 dark:text-amber-300"
                    @click="historyStatus = 'pending'"
                >
                    <Clock3 class="h-3.5 w-3.5" />
                    {{ counts.pending }} awaiting verification
                </button>
                <span
                    class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/25 bg-emerald-500/10 px-3 py-1.5 font-medium text-emerald-700 dark:text-emerald-300"
                >
                    <CheckCircle2 class="h-3.5 w-3.5" />
                    {{ counts.verified_this_month }} verified this month
                </span>
            </div>
        </div>

        <div
            class="grid items-start gap-6 xl:grid-cols-[minmax(0,7fr)_minmax(0,5fr)]"
        >
            <Form
                v-bind="HandoverController.store.form()"
                v-slot="{ errors, processing }"
                class="grid gap-4"
                @success="onHandoverSent"
            >
                <input
                    type="hidden"
                    name="asset_tag_code"
                    :value="selectedTag"
                />
                <input
                    type="hidden"
                    name="to_user_id"
                    :value="selectedRecipientId ?? ''"
                />

                <!-- Step 1: Asset -->
                <section
                    class="rounded-xl border border-border/60 bg-card shadow-sm"
                    aria-labelledby="handover-step-asset"
                >
                    <header
                        class="flex items-center gap-3 border-b border-border/50 px-5 py-3.5"
                    >
                        <span
                            class="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground"
                            >1</span
                        >
                        <div>
                            <h2
                                id="handover-step-asset"
                                class="text-sm font-semibold"
                            >
                                Choose the asset
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                Scan its label, type the tag code, or search by
                                name, holder, or location.
                            </p>
                        </div>
                    </header>

                    <div class="grid gap-3 px-5 py-4">
                        <div
                            v-if="selectedAsset"
                            class="rounded-xl border p-4"
                            :class="
                                selectedAsset.eligible
                                    ? 'border-emerald-500/30 bg-emerald-500/4'
                                    : 'border-rose-500/30 bg-rose-500/4'
                            "
                            data-testid="handover-selected-asset"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-base font-semibold">
                                        {{
                                            selectedAsset.name ??
                                            'Unnamed asset'
                                        }}
                                    </div>
                                    <div
                                        class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground"
                                    >
                                        <span
                                            class="font-mono font-medium text-foreground/80"
                                            >{{ selectedAsset.tag_code }}</span
                                        >
                                        <span v-if="selectedAsset.category"
                                            >·
                                            {{ selectedAsset.category }}</span
                                        >
                                        <span class="capitalize"
                                            >·
                                            {{
                                                formatStatus(
                                                    selectedAsset.status,
                                                )
                                            }}</span
                                        >
                                        <span
                                            v-if="selectedAsset.condition"
                                            class="capitalize"
                                            >·
                                            {{
                                                formatStatus(
                                                    selectedAsset.condition,
                                                )
                                            }}
                                            condition</span
                                        >
                                    </div>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="h-8 shrink-0 rounded-lg"
                                    @click="clearAsset"
                                >
                                    <X class="mr-1 h-3.5 w-3.5" />
                                    Change
                                </Button>
                            </div>

                            <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Currently accountable
                                    </dt>
                                    <dd class="font-medium">
                                        {{
                                            positionLabel(
                                                selectedAsset.position,
                                            )
                                        }}
                                    </dd>
                                    <dd class="text-xs text-muted-foreground">
                                        <template
                                            v-if="
                                                selectedAsset.accountable_person
                                            "
                                        >
                                            {{
                                                selectedAsset.accountable_person
                                            }}
                                            since
                                            {{
                                                formatDateTime(
                                                    selectedAsset.accountable_since,
                                                )
                                            }}
                                        </template>
                                        <template
                                            v-else-if="
                                                selectedAsset.position_holders
                                                    .length
                                            "
                                        >
                                            Held by
                                            {{
                                                selectedAsset.position_holders.join(
                                                    ', ',
                                                )
                                            }}
                                        </template>
                                        <template v-else>
                                            No verified holder on record
                                        </template>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Location
                                    </dt>
                                    <dd
                                        class="flex items-center gap-1 font-medium"
                                    >
                                        <MapPin
                                            class="h-3.5 w-3.5 text-muted-foreground"
                                        />
                                        {{
                                            selectedAsset.location?.name ??
                                            'Not recorded'
                                        }}
                                    </dd>
                                </div>
                            </dl>

                            <div
                                v-if="selectedAsset.eligible"
                                class="mt-3 flex items-center gap-2 text-sm font-medium text-emerald-700 dark:text-emerald-300"
                            >
                                <CheckCircle2 class="h-4 w-4" />
                                Ready for handover
                            </div>
                            <div
                                v-else
                                class="mt-3 rounded-lg border border-rose-500/25 bg-rose-500/5 px-3 py-2 text-sm text-rose-700 dark:text-rose-300"
                                role="alert"
                            >
                                <div
                                    class="flex items-center gap-2 font-medium"
                                >
                                    <XCircle class="h-4 w-4" />
                                    This asset cannot be handed over yet
                                </div>
                                <ul class="mt-1 list-disc pl-6 text-xs">
                                    <li
                                        v-for="blocker in selectedAsset.blockers"
                                        :key="blocker"
                                    >
                                        {{ blocker }}
                                    </li>
                                </ul>
                                <p
                                    v-if="selectedAsset.pending_handover"
                                    class="mt-1 text-xs"
                                >
                                    Sent to
                                    {{
                                        selectedAsset.pending_handover.to ??
                                        'a recipient'
                                    }}
                                    on
                                    {{
                                        formatDateTime(
                                            selectedAsset.pending_handover
                                                .initiated_at,
                                        )
                                    }}. Cancel it in the history panel to start
                                    over.
                                </p>
                            </div>
                        </div>

                        <template v-else>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <div class="relative flex-1">
                                    <Search
                                        class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                                    />
                                    <Input
                                        id="asset_search"
                                        v-model="assetSearch"
                                        type="search"
                                        autocomplete="off"
                                        aria-label="Search assets or enter a tag code"
                                        data-testid="handover-asset-tag-input"
                                        placeholder="Tag code, asset name, holder, or location"
                                        class="h-10 rounded-lg pl-9"
                                        @keydown.enter.prevent="
                                            submitAssetSearch
                                        "
                                    />
                                </div>
                                <QrScannerDialog
                                    button-label="Scan label"
                                    title="Scan asset label"
                                    description="Point the camera at the asset's QR label, or type the tag code manually."
                                    trigger-test-id="handover-scan-button"
                                    @scanned="applyScannedTag"
                                />
                                <OcrAssistButton
                                    label="Find asset from label"
                                    title="Find handover asset from label"
                                    description="Review the printed tag or serial number, then search eligible handover candidates. Select the correct asset yourself."
                                    @reviewed="assetSearch = $event"
                                />
                            </div>

                            <p
                                v-if="scanFeedback"
                                class="text-xs text-muted-foreground"
                                aria-live="polite"
                            >
                                {{ scanFeedback }}
                            </p>
                            <p
                                v-if="selectedAssetMissing"
                                class="flex items-center gap-1.5 text-xs font-medium text-rose-600 dark:text-rose-400"
                                role="alert"
                            >
                                <CircleAlert class="h-3.5 w-3.5" />
                                No asset has the tag “{{ filters.asset }}”.
                                Check the label or search by name.
                            </p>

                            <ul
                                v-if="assetOptions.length"
                                class="max-h-80 divide-y divide-border/50 overflow-y-auto rounded-lg border border-border/60"
                                aria-label="Matching assets"
                                data-testid="handover-asset-options"
                            >
                                <li
                                    v-for="asset in assetOptions"
                                    :key="asset.id"
                                >
                                    <button
                                        type="button"
                                        class="flex w-full items-start justify-between gap-3 px-3 py-2.5 text-left transition hover:bg-muted/50 focus-visible:bg-muted/60 focus-visible:outline-none disabled:cursor-wait"
                                        :disabled="isLoadingAsset"
                                        :data-tag="asset.tag_code"
                                        @click="selectAsset(asset.tag_code)"
                                    >
                                        <div class="min-w-0">
                                            <div
                                                class="truncate text-sm font-medium"
                                            >
                                                {{
                                                    asset.name ??
                                                    'Unnamed asset'
                                                }}
                                            </div>
                                            <div
                                                class="truncate text-xs text-muted-foreground"
                                            >
                                                <span class="font-mono">{{
                                                    asset.tag_code
                                                }}</span>
                                                ·
                                                {{
                                                    positionLabel(
                                                        asset.position,
                                                    )
                                                }}
                                                <template v-if="asset.location">
                                                    ·
                                                    {{
                                                        asset.location
                                                    }}</template
                                                >
                                            </div>
                                        </div>
                                        <span
                                            class="shrink-0 rounded-full border px-2 py-0.5 text-[11px] font-medium"
                                            :class="
                                                asset.unavailable_reason
                                                    ? 'border-border bg-muted/60 text-muted-foreground'
                                                    : 'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                            "
                                        >
                                            {{
                                                asset.unavailable_reason ??
                                                'Available'
                                            }}
                                        </span>
                                    </button>
                                </li>
                            </ul>
                            <p
                                v-else
                                class="rounded-lg border border-dashed border-border/60 px-3 py-6 text-center text-sm text-muted-foreground"
                            >
                                No assets match “{{ assetSearch }}”.
                            </p>
                            <p
                                v-if="assetOptions.length === 25"
                                class="text-[11px] text-muted-foreground"
                            >
                                Showing the first 25 matches. Type more to
                                narrow the list.
                            </p>
                        </template>
                        <InputError :message="errors.asset_tag_code" />
                    </div>
                </section>

                <!-- Step 2: Recipient -->
                <section
                    class="rounded-xl border border-border/60 bg-card shadow-sm"
                    aria-labelledby="handover-step-recipient"
                >
                    <header
                        class="flex items-center gap-3 border-b border-border/50 px-5 py-3.5"
                    >
                        <span
                            class="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground"
                            >2</span
                        >
                        <div>
                            <h2
                                id="handover-step-recipient"
                                class="text-sm font-semibold"
                            >
                                Choose the recipient
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                Only active personnel with an assigned position
                                can accept accountability.
                            </p>
                        </div>
                    </header>

                    <div class="grid gap-3 px-5 py-4">
                        <div
                            v-if="selectedRecipient"
                            class="flex items-start justify-between gap-3 rounded-xl border border-primary/30 bg-primary/4 p-3"
                            data-testid="handover-selected-recipient"
                        >
                            <div class="flex items-start gap-3">
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                                >
                                    <UserRound class="h-4 w-4" />
                                </div>
                                <div class="min-w-0">
                                    <div class="font-medium">
                                        {{ selectedRecipient.name }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{
                                            positionLabel(
                                                selectedRecipient.position,
                                            )
                                        }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ selectedRecipient.email }}
                                    </div>
                                </div>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-8 shrink-0 rounded-lg"
                                @click="clearRecipient"
                            >
                                <X class="mr-1 h-3.5 w-3.5" />
                                Change
                            </Button>
                        </div>

                        <template v-else>
                            <div class="relative">
                                <Search
                                    class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    id="recipient_search"
                                    v-model="recipientSearch"
                                    type="search"
                                    autocomplete="off"
                                    aria-label="Search recipients"
                                    data-testid="handover-recipient-search-input"
                                    placeholder="Name, email, position, or department"
                                    class="h-10 rounded-lg pl-9"
                                />
                            </div>
                            <ul
                                v-if="users.length"
                                class="max-h-72 divide-y divide-border/50 overflow-y-auto rounded-lg border border-border/60"
                                aria-label="Matching recipients"
                                data-testid="handover-recipient-options"
                            >
                                <li v-for="user in users" :key="user.id">
                                    <button
                                        type="button"
                                        class="flex w-full items-start justify-between gap-3 px-3 py-2.5 text-left transition hover:bg-muted/50 focus-visible:bg-muted/60 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:bg-transparent"
                                        :disabled="
                                            Boolean(user.unavailable_reason)
                                        "
                                        :data-user-id="user.id"
                                        @click="chooseRecipient(user)"
                                    >
                                        <div class="min-w-0">
                                            <div
                                                class="truncate text-sm font-medium"
                                            >
                                                {{ user.name }}
                                            </div>
                                            <div
                                                class="truncate text-xs text-muted-foreground"
                                            >
                                                {{
                                                    positionLabel(user.position)
                                                }}
                                                · {{ user.email }}
                                            </div>
                                        </div>
                                        <span
                                            v-if="user.unavailable_reason"
                                            class="shrink-0 rounded-full border border-border bg-muted/60 px-2 py-0.5 text-[11px] text-muted-foreground"
                                        >
                                            {{ user.unavailable_reason }}
                                        </span>
                                    </button>
                                </li>
                            </ul>
                            <p
                                v-else
                                class="rounded-lg border border-dashed border-border/60 px-3 py-6 text-center text-sm text-muted-foreground"
                            >
                                No active personnel match “{{
                                    recipientSearch
                                }}”.
                            </p>
                        </template>
                        <InputError :message="errors.to_user_id" />
                    </div>
                </section>

                <!-- Step 3: Confirm -->
                <section
                    class="rounded-xl border border-border/60 bg-card shadow-sm"
                    aria-labelledby="handover-step-confirm"
                >
                    <header
                        class="flex items-center gap-3 border-b border-border/50 px-5 py-3.5"
                    >
                        <span
                            class="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground"
                            >3</span
                        >
                        <h2
                            id="handover-step-confirm"
                            class="text-sm font-semibold"
                        >
                            Add notes and send
                        </h2>
                    </header>
                    <div class="grid gap-4 px-5 py-4">
                        <div class="grid gap-1.5">
                            <Label for="notes"
                                >Notes for the record (optional)</Label
                            >
                            <textarea
                                id="notes"
                                name="notes"
                                rows="2"
                                maxlength="2000"
                                placeholder="e.g. Includes charger and carrying case."
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/20 focus-visible:outline-none"
                            />
                            <InputError :message="errors.notes" />
                        </div>

                        <div
                            class="flex items-start gap-2 rounded-lg bg-muted/40 px-3 py-2.5 text-xs text-muted-foreground"
                        >
                            <ShieldCheck
                                class="mt-0.5 h-4 w-4 shrink-0 text-primary"
                            />
                            <p>
                                The recipient gets a secure verification link.
                                Accountability moves to their position only
                                after they sign. You can resend or cancel the
                                request while it is waiting.
                            </p>
                        </div>

                        <div
                            class="sticky bottom-4 z-10 rounded-xl border border-border/60 bg-background/95 p-3 shadow-lg backdrop-blur md:static md:border-0 md:bg-transparent md:p-0 md:shadow-none"
                        >
                            <Button
                                type="submit"
                                :disabled="processing || !canSubmit"
                                data-test="send-verification-button"
                                data-testid="send-verification-button"
                                class="w-full rounded-lg font-semibold"
                            >
                                <Send class="mr-1.5 h-4 w-4" />
                                {{
                                    processing
                                        ? 'Sending…'
                                        : selectedRecipient
                                          ? `Send verification to ${selectedRecipient.name}`
                                          : 'Send verification link'
                                }}
                            </Button>
                            <p
                                v-if="!canSubmit && !processing"
                                class="mt-2 text-center text-[11px] text-muted-foreground"
                            >
                                {{
                                    !selectedAsset
                                        ? 'Choose an asset to continue.'
                                        : !selectedAsset.eligible
                                          ? 'Choose an asset that is ready for handover.'
                                          : 'Choose a recipient to continue.'
                                }}
                            </p>
                        </div>
                    </div>
                </section>
            </Form>

            <!-- History -->
            <section
                class="rounded-xl border border-border/60 bg-card shadow-sm xl:sticky xl:top-4"
                aria-labelledby="handover-history"
                data-testid="handover-history"
            >
                <header class="border-b border-border/50 px-5 pt-4">
                    <div class="flex items-center justify-between gap-3">
                        <h2 id="handover-history" class="text-sm font-semibold">
                            Handover history
                        </h2>
                        <span class="text-xs text-muted-foreground tabular-nums"
                            >{{ recent.total }} total</span
                        >
                    </div>
                    <div class="relative mt-3">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="historySearch"
                            type="search"
                            aria-label="Search handover history"
                            placeholder="Asset, tag, or recipient"
                            class="h-9 rounded-lg pl-9"
                        />
                    </div>
                    <div class="mt-2 flex gap-1 overflow-x-auto" role="tablist">
                        <button
                            v-for="tab in historyTabs"
                            :key="tab.value || 'all'"
                            type="button"
                            role="tab"
                            :aria-selected="historyStatus === tab.value"
                            class="-mb-px shrink-0 border-b-2 px-3 py-2 text-xs font-medium transition-colors"
                            :class="
                                historyStatus === tab.value
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground'
                            "
                            @click="historyStatus = tab.value"
                        >
                            {{ tab.label }}
                        </button>
                    </div>
                </header>

                <ul v-if="recent.data.length" class="divide-y divide-border/50">
                    <li
                        v-for="row in recent.data"
                        :key="row.id"
                        class="relative px-5 py-3.5"
                        data-testid="handover-history-row"
                    >
                        <span
                            class="absolute inset-y-0 left-0 w-1"
                            :class="statusMeta[row.status].stripe"
                        />
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-medium">
                                    {{ row.asset_name ?? 'Unknown asset' }}
                                    <span
                                        class="font-mono text-xs text-muted-foreground"
                                        >{{ row.tag_code }}</span
                                    >
                                </div>
                                <div
                                    class="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground"
                                >
                                    <ArrowRightLeft class="h-3 w-3 shrink-0" />
                                    <span class="truncate">
                                        {{
                                            row.from_position?.title ??
                                            'Unassigned'
                                        }}
                                        →
                                        <span
                                            class="font-medium text-foreground/85"
                                            >{{ row.to?.name ?? '—' }}</span
                                        >
                                        <template v-if="row.to_position">
                                            ({{
                                                positionLabel(row.to_position)
                                            }})</template
                                        >
                                    </span>
                                </div>
                            </div>
                            <span
                                class="shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                                :class="statusMeta[row.status].classes"
                            >
                                {{ statusMeta[row.status].label }}
                            </span>
                        </div>

                        <div
                            class="mt-1.5 text-[11px] text-muted-foreground tabular-nums"
                        >
                            Sent {{ formatDateTime(row.initiated_at) }}
                            <template v-if="row.initiated_by">
                                by {{ row.initiated_by }}</template
                            >
                            <template
                                v-if="
                                    row.status === 'pending' &&
                                    row.last_sent_at &&
                                    row.last_sent_at !== row.initiated_at
                                "
                            >
                                · resent
                                {{ formatDateTime(row.last_sent_at) }}</template
                            >
                            <div
                                v-if="row.status === 'verified'"
                                class="text-emerald-700 dark:text-emerald-400"
                            >
                                Verified {{ formatDateTime(row.verified_at) }}
                            </div>
                            <div v-if="row.status === 'cancelled'">
                                Cancelled {{ formatDateTime(row.cancelled_at) }}
                                <template v-if="row.cancelled_by">
                                    by {{ row.cancelled_by }}</template
                                >
                                <template v-if="row.cancellation_reason">
                                    — “{{ row.cancellation_reason }}”</template
                                >
                            </div>
                        </div>

                        <div
                            v-if="row.status === 'pending'"
                            class="mt-2 flex flex-wrap gap-2"
                        >
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-7 rounded-lg text-xs"
                                :disabled="resendingId === row.id"
                                data-testid="handover-resend-button"
                                @click="resendLink(row)"
                            >
                                <Send class="mr-1 h-3 w-3" />
                                Resend link
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-7 rounded-lg text-xs text-rose-600 hover:bg-rose-500/10 hover:text-rose-700 dark:text-rose-400"
                                data-testid="handover-cancel-button"
                                @click="openCancel(row)"
                            >
                                Cancel request
                            </Button>
                        </div>
                    </li>
                </ul>
                <div
                    v-else
                    class="flex flex-col items-center gap-2 px-5 py-12 text-center"
                >
                    <ArrowRightLeft class="h-7 w-7 text-muted-foreground/50" />
                    <p class="text-sm font-medium">No handovers found</p>
                    <p class="max-w-xs text-xs text-muted-foreground">
                        {{
                            historyStatus || historySearch
                                ? 'Try another status or search term.'
                                : 'Handovers you send will appear here with their verification progress.'
                        }}
                    </p>
                </div>

                <nav
                    v-if="recent.links.length > 3"
                    class="flex flex-wrap justify-center gap-1 border-t border-border/50 px-3 py-3"
                    aria-label="Handover history pages"
                >
                    <template v-for="(link, i) in recent.links" :key="i">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            :only="['recent', 'filters']"
                            preserve-scroll
                            preserve-state
                            class="inline-flex h-7 min-w-7 items-center justify-center rounded-md px-2 text-xs"
                            :class="
                                link.active
                                    ? 'bg-primary/10 font-semibold text-primary'
                                    : 'text-muted-foreground hover:bg-muted'
                            "
                        >
                            <span v-html="link.label" />
                        </Link>
                        <span
                            v-else
                            class="inline-flex h-7 min-w-7 items-center justify-center px-2 text-xs text-muted-foreground/50"
                            v-html="link.label"
                        />
                    </template>
                </nav>
            </section>
        </div>

        <Dialog v-model:open="cancelDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Cancel this handover?</DialogTitle>
                    <DialogDescription>
                        {{ cancelTarget?.asset_name }} ({{
                            cancelTarget?.tag_code
                        }}) will stay with its current position, and the link
                        sent to
                        {{ cancelTarget?.to?.name ?? 'the recipient' }} will
                        stop working. This is recorded in the audit log.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-1.5">
                    <Label for="cancellation-reason">Reason</Label>
                    <textarea
                        id="cancellation-reason"
                        v-model="cancelForm.cancellation_reason"
                        rows="3"
                        maxlength="500"
                        placeholder="e.g. Sent to the wrong person."
                        class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/20 focus-visible:outline-none"
                    />
                    <InputError
                        :message="cancelForm.errors.cancellation_reason"
                    />
                </div>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="ghost"
                            >Keep request</Button
                        >
                    </DialogClose>
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="
                            cancelForm.processing ||
                            cancelForm.cancellation_reason.trim().length < 3
                        "
                        data-testid="handover-cancel-confirm"
                        @click="confirmCancel"
                    >
                        {{
                            cancelForm.processing
                                ? 'Cancelling…'
                                : 'Cancel handover'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
