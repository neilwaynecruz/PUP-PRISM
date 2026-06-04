<script setup lang="ts">
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import FullCalendar from '@fullcalendar/vue3';
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import BookingController from '@/actions/App/Http/Controllers/Inventory/BookingController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import QrScannerDialog from '@/components/inventory/QrScannerDialog.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    index as bookingsIndex,
    destroy as bookingsDestroy,
    bulkApprove as bookingsBulkApprove,
    bulkReject as bookingsBulkReject,
} from '@/routes/inventory/bookings';

type AssetOption = {
    id: number;
    tag_code: string;
    status: string;
    name: string | null;
    position: {
        title: string;
        department: string | null;
    } | null;
};

type BookingBase = {
    id: number;
    asset_id: number;
    asset_label?: string | null;
    title: string;
    start: string;
    end: string;
    status: 'Requested' | 'Approved' | 'Rejected' | 'Cancelled';
    requester: {
        name: string;
        email: string;
    } | null;
    requester_position: {
        title: string;
        department: string | null;
    } | null;
    approver: {
        name: string;
        email: string;
    } | null;
    can_delete: boolean;
};

type BookingEvent = BookingBase & {
    requester_id: number;
};

type PaginationLink = { url: string | null; label: string; active: boolean };
type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

const props = defineProps<{
    filters: { asset_search: string };
    assets: AssetOption[];
    calendar_events: Array<
        Pick<
            BookingEvent,
            'id' | 'asset_id' | 'title' | 'start' | 'end' | 'status'
        >
    >;
    approval_queue: Omit<BookingEvent, 'requester_id'>[];
    bookings: Paginated<BookingEvent>;
    exportUrls: { csv: string; pdf: string };
    can: { approve: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: bookingsIndex() },
            { title: 'Bookings', href: bookingsIndex() },
        ],
    },
});

const selectedAssetId = ref<string>('');
const startAt = ref<string>('');
const endAt = ref<string>('');
const purpose = ref<string>('');
const assetSearch = ref<string>(props.filters.asset_search ?? '');
const assetScanFeedback = ref<string>('');
const selectedRejectBooking = ref<BookingBase | null>(null);
const selectedBooking = ref<BookingBase | null>(null);
const deleteDialogOpen = ref(false);
const deleteReason = ref('');
const deleteReasonCustom = ref('');

const deletionReasons = [
    { value: 'No longer needed', label: 'No longer needed' },
    { value: 'Cancelled by requester', label: 'Cancelled by requester' },
    { value: 'Schedule conflict', label: 'Schedule conflict' },
    { value: 'Data entry error', label: 'Data entry error' },
    { value: 'Other', label: 'Other (please specify)' },
];

const isOtherReason = computed(() => deleteReason.value === 'Other');
const canConfirmDelete = computed(() => {
    if (!deleteReason.value) {
        return false;
    }

    if (deleteReason.value === 'Other' && !deleteReasonCustom.value.trim()) {
        return false;
    }

    return true;
});

function getDeletionReason(): string {
    if (deleteReason.value === 'Other') {
        return deleteReasonCustom.value.trim();
    }

    return deleteReason.value;
}

function formatDateTime(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const d = new Date(iso);

    return d.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}

function openDeleteDialog(booking: BookingBase): void {
    selectedBooking.value = booking;
    deleteReason.value = '';
    deleteReasonCustom.value = '';
    deleteDialogOpen.value = true;
}

function confirmDelete(): void {
    if (!selectedBooking.value || !canConfirmDelete.value) {
        return;
    }

    router.delete(bookingsDestroy(selectedBooking.value.id).url, {
        data: { deletion_reason: getDeletionReason() },
        onSuccess: () => {
            deleteDialogOpen.value = false;
            selectedBooking.value = null;
            deleteReason.value = '';
            deleteReasonCustom.value = '';
        },
    });
}

// ── Bulk actions on approval queue ──
const selectedIds = ref<Set<number>>(new Set());
const bulkActionDialogOpen = ref(false);
const pendingBulkAction = ref<'approve' | 'reject' | null>(null);

const allQueueSelected = computed(
    () =>
        props.approval_queue.length > 0 &&
        selectedIds.value.size === props.approval_queue.length,
);
const someQueueSelected = computed(
    () =>
        selectedIds.value.size > 0 &&
        selectedIds.value.size < props.approval_queue.length,
);
const hasQueueSelection = computed(() => selectedIds.value.size > 0);

function toggleSelectAllQueue(): void {
    if (allQueueSelected.value) {
        selectedIds.value.clear();
    } else {
        selectedIds.value = new Set(props.approval_queue.map((b) => b.id));
    }
}

function toggleSelectQueue(id: number): void {
    const next = new Set(selectedIds.value);

    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }

    selectedIds.value = next;
}

function runBulkApprove(): void {
    pendingBulkAction.value = 'approve';
    bulkActionDialogOpen.value = true;
}

function runBulkReject(): void {
    pendingBulkAction.value = 'reject';
    bulkActionDialogOpen.value = true;
}

function confirmBulkAction(): void {
    if (!pendingBulkAction.value) {
        return;
    }

    const endpoint =
        pendingBulkAction.value === 'approve'
            ? bookingsBulkApprove().url
            : bookingsBulkReject().url;

    router.post(
        endpoint,
        {
            ids: Array.from(selectedIds.value),
        },
        {
            onSuccess: () => {
                selectedIds.value.clear();
                bulkActionDialogOpen.value = false;
                pendingBulkAction.value = null;
            },
        },
    );
}

const statusColor = (status: BookingEvent['status']) => {
    switch (status) {
        case 'Approved':
            return {
                bg: 'bg-emerald-500',
                text: 'text-emerald-700 dark:text-emerald-300',
                pill: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
            };
        case 'Requested':
            return {
                bg: 'bg-amber-500',
                text: 'text-amber-700 dark:text-amber-300',
                pill: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
            };
        case 'Rejected':
            return {
                bg: 'bg-rose-500',
                text: 'text-rose-700 dark:text-rose-300',
                pill: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',
            };
        case 'Cancelled':
            return {
                bg: 'bg-slate-400',
                text: 'text-slate-600 dark:text-slate-400',
                pill: 'bg-slate-400/10 text-slate-600 dark:text-slate-400 border-slate-400/20',
            };
    }
};

const events = computed(() =>
    props.calendar_events.map((b) => ({
        id: String(b.id),
        title: b.title,
        start: b.start,
        end: b.end,
        backgroundColor:
            b.status === 'Approved'
                ? 'hsl(152 65% 45%)'
                : b.status === 'Requested'
                  ? 'hsl(38 95% 55%)'
                  : b.status === 'Rejected'
                    ? 'hsl(345 80% 58%)'
                    : 'hsl(220 8% 60%)',
        borderColor: 'transparent',
    })),
);

let assetSearchTimer: number | undefined;
watch(assetSearch, () => {
    window.clearTimeout(assetSearchTimer);
    assetSearchTimer = window.setTimeout(() => {
        router.get(
            bookingsIndex().url,
            {
                asset_search: assetSearch.value || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }, 250);
});

watch(
    () => props.assets,
    (assets) => {
        if (assetSearch.value === '') {
            return;
        }

        const exactMatch = assets.find(
            (asset) => asset.tag_code === assetSearch.value.trim(),
        );

        if (!exactMatch) {
            return;
        }

        selectedAssetId.value = String(exactMatch.id);
        assetScanFeedback.value = `Matched ${exactMatch.tag_code} to ${exactMatch.name ?? 'Asset'}.`;
    },
    { deep: true },
);

function applyScannedAsset(tagCode: string): void {
    const match = props.assets.find(
        (asset) => asset.tag_code === tagCode.trim(),
    );

    if (!match) {
        assetSearch.value = tagCode.trim();
        assetScanFeedback.value = `Searching for ${tagCode} in the asset list.`;

        return;
    }

    assetSearch.value = tagCode.trim();
    selectedAssetId.value = String(match.id);
    assetScanFeedback.value = `Matched ${match.tag_code} to ${match.name ?? 'Asset'}.`;
}
</script>

<template>
    <Head title="Bookings" />

    <div class="flex flex-col gap-6 p-4 sm:p-6" data-testid="bookings-page">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                variant="small"
                title="Asset booking calendar"
                description="Requests only block availability after approval, and each request stays attributable to the requesting position."
            />

            <div class="flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                    class="rounded-lg border-dashed"
                >
                    <a :href="props.exportUrls.csv">Export CSV</a>
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                    class="rounded-lg border-dashed"
                >
                    <a :href="props.exportUrls.pdf">Export PDF</a>
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                    class="rounded-lg border-dashed"
                >
                    <Link href="/inventory/bookings/trash">Trash</Link>
                </Button>
            </div>
        </div>

        <div class="grid items-start gap-6 xl:grid-cols-[1fr_420px]">
            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <div
                    class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <div class="text-sm font-semibold tracking-tight">
                            Calendar overview
                        </div>
                        <div class="text-xs text-muted-foreground">
                            Green blocks approved use, amber blocks pending
                            requests, red blocks rejected requests.
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 font-medium text-emerald-600 dark:text-emerald-400"
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full bg-emerald-500"
                            />
                            Approved
                        </span>
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border border-amber-500/20 bg-amber-500/10 px-2.5 py-1 font-medium text-amber-600 dark:text-amber-400"
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full bg-amber-500"
                            />
                            Requested
                        </span>
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border border-rose-500/20 bg-rose-500/10 px-2.5 py-1 font-medium text-rose-600 dark:text-rose-400"
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full bg-rose-500"
                            />
                            Rejected
                        </span>
                    </div>
                </div>

                <div data-testid="booking-calendar" class="h-[520px]">
                    <FullCalendar
                        :plugins="[dayGridPlugin, interactionPlugin]"
                        initialView="dayGridMonth"
                        :events="events"
                        height="100%"
                    >
                        <template #eventContent="{ event }">
                            <div
                                data-testid="booking-calendar-event"
                                :data-event-id="event.id"
                            >
                                {{ event.title }}
                            </div>
                        </template>
                    </FullCalendar>
                </div>
            </div>

            <div
                class="self-start rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <Form
                    v-bind="BookingController.store.form()"
                    v-slot="{ errors, processing }"
                    class="grid gap-5"
                    data-shortcut="new"
                >
                    <Heading
                        variant="small"
                        title="New booking request"
                        description="Select an accountable asset, then choose the requested schedule."
                    />

                    <div class="grid gap-2">
                        <Label
                            for="asset_search"
                            class="text-xs font-medium tracking-wider text-muted-foreground/70 uppercase"
                            >Find asset</Label
                        >
                        <Input
                            id="asset_search"
                            v-model="assetSearch"
                            data-testid="booking-asset-search-input"
                            placeholder="Search by tag or product name"
                            class="rounded-lg"
                        />
                        <div class="text-xs text-muted-foreground">
                            The selector loads up to 25 matching available
                            assets at a time.
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <Label
                                for="asset_id"
                                class="text-xs font-medium tracking-wider text-muted-foreground/70 uppercase"
                                >Asset</Label
                            >
                            <QrScannerDialog
                                button-label="Scan asset QR"
                                title="Scan booking asset QR"
                                description="Scan an asset tag to auto-select the matching asset for this booking request."
                                @scanned="applyScannedAsset"
                            />
                        </div>
                        <select
                            id="asset_id"
                            name="asset_id"
                            v-model="selectedAssetId"
                            data-testid="booking-asset-select"
                            class="h-10 rounded-lg border border-input bg-background px-3 text-sm"
                            required
                        >
                            <option value="" disabled>Select asset</option>
                            <option
                                v-for="a in assets"
                                :key="a.id"
                                :value="String(a.id)"
                            >
                                {{ a.tag_code }} — {{ a.name ?? 'Asset' }}
                                <template v-if="a.position">
                                    | {{ a.position.title
                                    }}{{
                                        a.position.department
                                            ? `, ${a.position.department}`
                                            : ''
                                    }}
                                </template>
                                ({{ a.status }})
                            </option>
                        </select>
                        <div
                            v-if="assetScanFeedback"
                            class="text-xs text-muted-foreground"
                        >
                            {{ assetScanFeedback }}
                        </div>
                        <InputError :message="errors.asset_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label
                            for="start_at"
                            class="text-xs font-medium tracking-wider text-muted-foreground/70 uppercase"
                            >Start</Label
                        >
                        <Input
                            id="start_at"
                            name="start_at"
                            v-model="startAt"
                            data-testid="booking-start-input"
                            type="datetime-local"
                            required
                            class="rounded-lg"
                        />
                        <InputError :message="errors.start_at" />
                    </div>

                    <div class="grid gap-2">
                        <Label
                            for="end_at"
                            class="text-xs font-medium tracking-wider text-muted-foreground/70 uppercase"
                            >End</Label
                        >
                        <Input
                            id="end_at"
                            name="end_at"
                            v-model="endAt"
                            data-testid="booking-end-input"
                            type="datetime-local"
                            required
                            class="rounded-lg"
                        />
                        <InputError :message="errors.end_at" />
                    </div>

                    <div class="grid gap-2">
                        <Label
                            for="purpose"
                            class="text-xs font-medium tracking-wider text-muted-foreground/70 uppercase"
                            >Purpose (optional)</Label
                        >
                        <Input
                            id="purpose"
                            name="purpose"
                            v-model="purpose"
                            data-testid="booking-purpose-input"
                            placeholder="e.g. Classroom demo"
                            class="rounded-lg"
                        />
                        <InputError :message="errors.purpose" />
                    </div>

                    <div class="flex items-center justify-end gap-2">
                        <Button
                            type="submit"
                            :disabled="processing"
                            data-test="request-booking-button"
                            data-testid="request-booking-button"
                            class="rounded-lg shadow-sm"
                            >Request booking</Button
                        >
                    </div>
                </Form>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold tracking-tight">
                            Approval queue
                        </div>
                        <div class="text-xs text-muted-foreground">
                            Property custodians can approve or reject pending
                            requests directly from this screen.
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-primary" />
                            {{ approval_queue.length }} pending
                        </span>
                        <Checkbox
                            v-if="approval_queue.length > 0"
                            :checked="allQueueSelected"
                            :indeterminate="someQueueSelected"
                            @update:checked="toggleSelectAllQueue"
                            aria-label="Select all pending bookings"
                        />
                    </div>
                </div>

                <!-- Bulk action bar -->
                <div
                    v-if="can.approve && hasQueueSelection"
                    class="mb-3 flex flex-wrap items-center gap-2 rounded-lg border border-primary/20 bg-primary/5 px-3 py-2 text-sm"
                >
                    <span class="font-medium text-primary"
                        >{{ selectedIds.size }} selected</span
                    >
                    <div class="ml-auto flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-7 rounded-lg text-xs"
                            @click="runBulkApprove"
                            >Approve</Button
                        >
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-7 rounded-lg text-xs"
                            @click="runBulkReject"
                            >Reject</Button
                        >
                    </div>
                </div>

                <div v-if="approval_queue.length" class="grid gap-3">
                    <div
                        v-for="booking in approval_queue"
                        :key="booking.id"
                        class="rounded-xl border border-border/40 p-4 shadow-sm"
                    >
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div class="flex items-start gap-2">
                                <Checkbox
                                    :checked="selectedIds.has(booking.id)"
                                    @update:checked="
                                        () => toggleSelectQueue(booking.id)
                                    "
                                    aria-label="Select booking"
                                    class="mt-0.5"
                                />
                                <div class="space-y-1 text-sm">
                                    <div class="font-medium">
                                        {{ booking.title }}
                                    </div>
                                    <div class="text-muted-foreground">
                                        Requested by
                                        {{
                                            booking.requester?.name ??
                                            booking.requester?.email ??
                                            'Unknown requester'
                                        }}
                                    </div>
                                    <div
                                        v-if="booking.requester_position"
                                        class="text-muted-foreground"
                                    >
                                        {{ booking.requester_position.title
                                        }}{{
                                            booking.requester_position
                                                .department
                                                ? `, ${booking.requester_position.department}`
                                                : ''
                                        }}
                                    </div>
                                    <div
                                        class="text-xs text-muted-foreground/80"
                                    >
                                        {{ formatDateTime(booking.start) }} to
                                        {{ formatDateTime(booking.end) }}
                                    </div>
                                </div>
                            </div>

                            <span
                                :class="[
                                    'inline-flex shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium',
                                    statusColor(booking.status).pill,
                                ]"
                            >
                                <span
                                    :class="[
                                        'h-1.5 w-1.5 rounded-full',
                                        statusColor(booking.status).bg,
                                    ]"
                                />
                                {{ booking.status }}
                            </span>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <Button
                                variant="ghost"
                                size="sm"
                                as-child
                                class="h-8 rounded-lg text-xs"
                            >
                                <Link
                                    :href="`/inventory/bookings/${booking.id}`"
                                    >View</Link
                                >
                            </Button>
                            <template v-if="can.approve">
                                <Form
                                    v-bind="
                                        BookingController.update.form(
                                            booking.id,
                                        )
                                    "
                                >
                                    <Button
                                        type="submit"
                                        name="action"
                                        value="approve"
                                        data-test="approve-booking-button"
                                        data-testid="approve-booking-button"
                                        class="rounded-lg"
                                        >Approve</Button
                                    >
                                </Form>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="rounded-lg border-dashed"
                                    @click="selectedRejectBooking = booking"
                                >
                                    Reject
                                </Button>
                            </template>
                            <Button
                                v-if="booking.can_delete"
                                variant="ghost"
                                size="sm"
                                class="h-8 rounded-lg text-xs text-rose-600 hover:text-rose-700"
                                @click="openDeleteDialog(booking)"
                            >
                                Delete
                            </Button>
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="rounded-xl border border-dashed border-border/60 p-8 text-center text-sm text-muted-foreground"
                >
                    No pending requests. The walkthrough queue is clear.
                </div>
            </div>

            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <div class="mb-4">
                    <div class="text-sm font-semibold tracking-tight">
                        Booking records
                    </div>
                    <div class="text-xs text-muted-foreground">
                        Paginated records keep the page responsive while
                        preserving access to the full booking history.
                    </div>
                </div>

                <div class="grid gap-3">
                    <div
                        v-for="booking in bookings.data"
                        :key="booking.id"
                        class="rounded-xl border border-border/40 p-4 text-sm transition-colors hover:bg-muted/30"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div class="font-medium">{{ booking.title }}</div>
                            <span
                                :class="[
                                    'inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-semibold tracking-wider uppercase',
                                    statusColor(booking.status).pill,
                                ]"
                            >
                                <span
                                    :class="[
                                        'h-1 w-1 rounded-full',
                                        statusColor(booking.status).bg,
                                    ]"
                                />
                                {{ booking.status }}
                            </span>
                        </div>
                        <div
                            class="mt-2 space-y-1 text-xs text-muted-foreground"
                        >
                            <div v-if="booking.asset_label">
                                Tag: {{ booking.asset_label }}
                            </div>
                            <div>
                                {{ formatDateTime(booking.start) }} to
                                {{ formatDateTime(booking.end) }}
                            </div>
                            <div>
                                {{
                                    booking.requester?.name ??
                                    booking.requester?.email ??
                                    'Unknown requester'
                                }}
                            </div>
                            <div v-if="booking.approver">
                                Processed by {{ booking.approver.name }}
                            </div>
                        </div>
                        <div class="mt-2 flex items-center gap-2">
                            <Button
                                variant="ghost"
                                size="sm"
                                as-child
                                class="h-7 rounded-lg text-xs"
                            >
                                <Link
                                    :href="`/inventory/bookings/${booking.id}`"
                                    >View</Link
                                >
                            </Button>
                            <Button
                                v-if="booking.can_delete"
                                variant="ghost"
                                size="sm"
                                class="h-7 rounded-lg text-xs text-rose-600 hover:text-rose-700"
                                @click="openDeleteDialog(booking)"
                            >
                                Delete
                            </Button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="bookings.links.length"
                    class="mt-4 flex flex-wrap items-center justify-center gap-2"
                >
                    <Button
                        v-for="(link, index) in bookings.links"
                        :key="index"
                        variant="ghost"
                        size="sm"
                        :disabled="!link.url"
                        as-child
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
        </div>

        <Dialog
            :open="selectedRejectBooking !== null"
            @update:open="
                (open) => {
                    if (!open) selectedRejectBooking = null;
                }
            "
        >
            <DialogContent v-if="selectedRejectBooking">
                <DialogHeader class="space-y-3">
                    <DialogTitle>Reject booking request?</DialogTitle>
                    <DialogDescription>
                        This will permanently reject the booking request for
                        <strong>{{ selectedRejectBooking.title }}</strong
                        >. The requester will be notified.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" type="button"
                            >Cancel</Button
                        >
                    </DialogClose>
                    <Form
                        v-bind="
                            BookingController.update.form(
                                selectedRejectBooking.id,
                            )
                        "
                        class="inline"
                        @success="selectedRejectBooking = null"
                    >
                        <Button
                            type="submit"
                            name="action"
                            value="reject"
                            variant="destructive"
                            >Confirm reject</Button
                        >
                    </Form>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="bulkActionDialogOpen">
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle>
                        {{
                            pendingBulkAction === 'approve'
                                ? 'Approve selected bookings?'
                                : 'Reject selected bookings?'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        This will process
                        <strong
                            >{{ selectedIds.size }} selected booking
                            request(s)</strong
                        >
                        in the approval queue.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" type="button"
                            >Cancel</Button
                        >
                    </DialogClose>
                    <Button
                        :variant="
                            pendingBulkAction === 'reject'
                                ? 'destructive'
                                : 'default'
                        "
                        @click="confirmBulkAction"
                    >
                        {{
                            pendingBulkAction === 'approve'
                                ? 'Confirm approve'
                                : 'Confirm reject'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="deleteDialogOpen">
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle>Delete booking?</DialogTitle>
                    <DialogDescription>
                        This will move the booking for
                        <strong>{{ selectedBooking?.title }}</strong> to the
                        trash. You can restore it later if needed.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-4 py-4">
                    <div class="grid gap-2">
                        <Label for="delete-reason"
                            >Reason for deletion
                            <span class="text-rose-500">*</span></Label
                        >
                        <Select v-model="deleteReason">
                            <SelectTrigger id="delete-reason">
                                <SelectValue placeholder="Select a reason..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="reason in deletionReasons"
                                    :key="reason.value"
                                    :value="reason.value"
                                >
                                    {{ reason.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div v-if="isOtherReason" class="grid gap-2">
                        <Label for="delete-reason-custom"
                            >Please specify
                            <span class="text-rose-500">*</span></Label
                        >
                        <textarea
                            id="delete-reason-custom"
                            v-model="deleteReasonCustom"
                            placeholder="Enter your reason..."
                            rows="3"
                            class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        ></textarea>
                    </div>
                </div>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        :disabled="!canConfirmDelete"
                        @click="confirmDelete"
                        >Delete</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
