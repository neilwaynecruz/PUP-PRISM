<script setup lang="ts">
import dayGridPlugin from '@fullcalendar/daygrid';
import type { CalendarOptions } from '@fullcalendar/core';
import interactionPlugin from '@fullcalendar/interaction';
import FullCalendar from '@fullcalendar/vue3';
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
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
import { useBulkSelection } from '@/composables/useBulkSelection';
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
const bulkActionDialogOpen = ref(false);
const pendingBulkAction = ref<'approve' | 'reject' | null>(null);

const {
    selectedIds,
    allSelected: allQueueSelected,
    someSelected: someQueueSelected,
    hasSelection: hasQueueSelection,
    toggleSelectAll: toggleSelectAllQueue,
    toggleSelect: toggleSelectQueue,
    clearSelection,
} = useBulkSelection(() => props.approval_queue);

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
                clearSelection();
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

const calendarOptions = computed<CalendarOptions>(() => ({
    plugins: [dayGridPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,dayGridWeek,dayGridDay',
    },
    events: events.value,
    height: 520,
    eventDisplay: 'block',
}));

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

onBeforeUnmount(() => {
    window.clearTimeout(assetSearchTimer);
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
                description="Reserve accountable assets with full traceability. Approved bookings block availability; pending requests await custodian review."
            />

            <div class="flex flex-wrap items-center gap-2">
                <Button variant="outline" size="sm" as-child class="rounded-lg">
                    <a
                        :href="props.exportUrls.csv"
                        class="inline-flex items-center gap-1.5"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="14"
                            height="14"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path
                                d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"
                            />
                            <polyline points="7 10 12 15 17 10" />
                            <line x1="12" x2="12" y1="15" y2="3" />
                        </svg>
                        Export CSV
                    </a>
                </Button>
                <Button variant="outline" size="sm" as-child class="rounded-lg">
                    <a
                        :href="props.exportUrls.pdf"
                        class="inline-flex items-center gap-1.5"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="14"
                            height="14"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path
                                d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"
                            />
                            <polyline points="7 10 12 15 17 10" />
                            <line x1="12" x2="12" y1="15" y2="3" />
                        </svg>
                        Export PDF
                    </a>
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    as-child
                    class="rounded-lg text-muted-foreground hover:text-foreground"
                >
                    <Link href="/inventory/bookings/trash">Trash</Link>
                </Button>
            </div>
        </div>

        <div class="grid items-start gap-6 xl:grid-cols-[1fr_400px]">
            <div class="rounded-xl border border-border/50 bg-card shadow-sm">
                <div
                    class="flex flex-col gap-3 border-b border-border/40 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <div class="text-sm font-semibold tracking-tight">
                            Calendar overview
                        </div>
                        <div class="text-xs text-muted-foreground/80">
                            Green = approved &middot; Amber = requested &middot;
                            Red = rejected
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 text-[11px] font-semibold tracking-wider text-emerald-600 uppercase dark:text-emerald-400"
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full bg-emerald-500"
                            />
                            Approved
                        </span>
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border border-amber-500/20 bg-amber-500/10 px-2.5 py-1 text-[11px] font-semibold tracking-wider text-amber-600 uppercase dark:text-amber-400"
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full bg-amber-500"
                            />
                            Requested
                        </span>
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border border-rose-500/20 bg-rose-500/10 px-2.5 py-1 text-[11px] font-semibold tracking-wider text-rose-600 uppercase dark:text-rose-400"
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full bg-rose-500"
                            />
                            Rejected
                        </span>
                    </div>
                </div>
                <div data-testid="booking-calendar" class="p-3">
                    <FullCalendar :options="calendarOptions">
                        <template #eventContent="{ event }">
                            <div
                                data-testid="booking-calendar-event"
                                :data-event-id="event.id"
                                class="truncate px-1.5 py-0.5 text-[11px] leading-tight font-medium text-white"
                                :title="event.title"
                            >
                                {{ event.title }}
                            </div>
                        </template>
                    </FullCalendar>
                    <div
                        v-if="props.calendar_events.length === 0"
                        class="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border/50 py-12 text-center"
                    >
                        <div
                            class="rounded-full border border-border/40 bg-muted/40 p-3"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="20"
                                height="20"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="text-muted-foreground"
                            >
                                <rect
                                    width="18"
                                    height="18"
                                    x="3"
                                    y="4"
                                    rx="2"
                                    ry="2"
                                />
                                <line x1="16" x2="16" y1="2" y2="6" />
                                <line x1="8" x2="8" y1="2" y2="6" />
                                <line x1="3" x2="21" y1="10" y2="10" />
                            </svg>
                        </div>
                        <div class="text-sm font-medium text-muted-foreground">
                            No bookings for this period
                        </div>
                        <div class="max-w-xs text-xs text-muted-foreground/70">
                            Use the form on the right to submit a new booking
                            request. Approved bookings will appear here.
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="self-start rounded-xl border border-border/50 bg-card shadow-sm"
            >
                <div class="border-b border-border/40 px-5 py-4">
                    <div class="text-sm font-semibold tracking-tight">
                        New booking request
                    </div>
                    <div class="text-xs text-muted-foreground/80">
                        Select an accountable asset and choose your schedule.
                    </div>
                </div>
                <Form
                    v-bind="BookingController.store.form()"
                    v-slot="{ errors, processing }"
                    class="grid gap-4 px-5 py-4"
                    data-shortcut="new"
                >
                    <div class="grid gap-1.5">
                        <Label
                            for="asset_search"
                            class="text-xs font-medium tracking-wider text-muted-foreground/70 uppercase"
                            >Search asset</Label
                        >
                        <Input
                            id="asset_search"
                            v-model="assetSearch"
                            data-testid="booking-asset-search-input"
                            placeholder="Type tag code or product name"
                            class="h-9 rounded-lg text-sm"
                        />
                        <div class="text-[11px] text-muted-foreground/70">
                            Loads up to 25 matching available assets.
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        <div class="flex items-center justify-between">
                            <Label
                                for="asset_id"
                                class="text-xs font-medium tracking-wider text-muted-foreground/70 uppercase"
                                >Selected asset
                                <span class="text-rose-500">*</span></Label
                            >
                            <QrScannerDialog
                                button-label="Scan QR"
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
                            class="h-9 w-full rounded-lg border border-input bg-background px-3 text-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                            required
                        >
                            <option value="" disabled>Select an asset</option>
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
                            class="text-[11px] text-muted-foreground/80"
                        >
                            {{ assetScanFeedback }}
                        </div>
                        <InputError :message="errors.asset_id" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5">
                            <Label
                                for="start_at"
                                class="text-xs font-medium tracking-wider text-muted-foreground/70 uppercase"
                                >Start
                                <span class="text-rose-500">*</span></Label
                            >
                            <Input
                                id="start_at"
                                name="start_at"
                                v-model="startAt"
                                data-testid="booking-start-input"
                                type="datetime-local"
                                required
                                class="h-9 rounded-lg text-sm"
                            />
                            <InputError :message="errors.start_at" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label
                                for="end_at"
                                class="text-xs font-medium tracking-wider text-muted-foreground/70 uppercase"
                                >End <span class="text-rose-500">*</span></Label
                            >
                            <Input
                                id="end_at"
                                name="end_at"
                                v-model="endAt"
                                data-testid="booking-end-input"
                                type="datetime-local"
                                required
                                class="h-9 rounded-lg text-sm"
                            />
                            <InputError :message="errors.end_at" />
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        <Label
                            for="purpose"
                            class="text-xs font-medium tracking-wider text-muted-foreground/70 uppercase"
                            >Purpose</Label
                        >
                        <Input
                            id="purpose"
                            name="purpose"
                            v-model="purpose"
                            data-testid="booking-purpose-input"
                            placeholder="e.g. Classroom demo"
                            class="h-9 rounded-lg text-sm"
                        />
                        <InputError :message="errors.purpose" />
                    </div>

                    <div class="pt-1">
                        <Button
                            type="submit"
                            :disabled="processing"
                            data-test="request-booking-button"
                            data-testid="request-booking-button"
                            class="w-full rounded-lg font-semibold shadow-sm"
                        >
                            Submit booking request
                        </Button>
                    </div>
                </Form>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-xl border border-border/50 bg-card shadow-sm">
                <div
                    class="flex items-center justify-between gap-3 border-b border-border/40 px-5 py-4"
                >
                    <div>
                        <div class="text-sm font-semibold tracking-tight">
                            Approval queue
                        </div>
                        <div class="text-xs text-muted-foreground/80">
                            Custodians can approve or reject pending requests
                            here.
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border border-amber-500/20 bg-amber-500/10 px-2.5 py-1 text-[11px] font-semibold tracking-wider text-amber-600 uppercase dark:text-amber-400"
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full bg-amber-500"
                            />
                            {{ approval_queue.length }} pending
                        </span>
                        <Checkbox
                            v-if="approval_queue.length > 0"
                            :model-value="allQueueSelected ? true : someQueueSelected ? 'indeterminate' : false"
                            @update:model-value="toggleSelectAllQueue"
                            aria-label="Select all pending bookings"
                        />
                    </div>
                </div>

                <div class="px-5 py-4">
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
                            class="group relative rounded-xl border border-border/40 bg-card p-4 transition-all hover:border-border/60 hover:shadow-sm"
                            :class="{ 'bg-primary/5': selectedIds.has(booking.id) }"
                        >
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div class="flex items-start gap-3">
                                    <Checkbox
                                        :model-value="selectedIds.has(booking.id)"
                                        @update:model-value="
                                            () => toggleSelectQueue(booking.id)
                                        "
                                        aria-label="Select booking"
                                        class="mt-1"
                                    />
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2">
                                            <div class="text-sm font-semibold">
                                                {{ booking.title }}
                                            </div>
                                            <span
                                                :class="[
                                                    'inline-flex shrink-0 items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-semibold tracking-wider uppercase',
                                                    statusColor(booking.status)
                                                        .pill,
                                                ]"
                                            >
                                                <span
                                                    :class="[
                                                        'h-1 w-1 rounded-full',
                                                        statusColor(
                                                            booking.status,
                                                        ).bg,
                                                    ]"
                                                />
                                                {{ booking.status }}
                                            </span>
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            Requested by
                                            {{
                                                booking.requester?.name ??
                                                booking.requester?.email ??
                                                'Unknown requester'
                                            }}
                                        </div>
                                        <div
                                            v-if="booking.requester_position"
                                            class="text-xs text-muted-foreground/80"
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
                                            class="text-[11px] text-muted-foreground/70 tabular-nums"
                                        >
                                            {{
                                                formatDateTime(booking.start)
                                            }}
                                            — {{ formatDateTime(booking.end) }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="mt-3 flex flex-wrap items-center gap-2 pl-7"
                            >
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
                                <template v-if="can.approve">
                                    <Form
                                        v-bind="
                                            BookingController.update.form(
                                                booking.id,
                                            )
                                        "
                                        class="inline"
                                    >
                                        <Button
                                            type="submit"
                                            name="action"
                                            value="approve"
                                            data-test="approve-booking-button"
                                            data-testid="approve-booking-button"
                                            size="sm"
                                            class="h-7 rounded-lg text-xs"
                                            >Approve</Button
                                        >
                                    </Form>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="h-7 rounded-lg text-xs"
                                        @click="selectedRejectBooking = booking"
                                        >Reject</Button
                                    >
                                </template>
                                <Button
                                    v-if="booking.can_delete"
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 rounded-lg text-xs text-rose-600 hover:text-rose-700"
                                    @click="openDeleteDialog(booking)"
                                    >Delete</Button
                                >
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border/50 py-10 text-center"
                    >
                        <div
                            class="rounded-full border border-border/40 bg-muted/40 p-3"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="text-muted-foreground"
                            >
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                                <polyline points="22 4 12 14.01 9 11.01" />
                            </svg>
                        </div>
                        <div class="text-sm font-medium text-muted-foreground">
                            No pending requests
                        </div>
                        <div class="max-w-xs text-xs text-muted-foreground/70">
                            The approval queue is clear. New booking requests
                            will appear here for review.
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-border/50 bg-card shadow-sm">
                <div
                    class="flex items-center justify-between gap-3 border-b border-border/40 px-5 py-4"
                >
                    <div>
                        <div class="text-sm font-semibold tracking-tight">
                            Booking records
                        </div>
                        <div class="text-xs text-muted-foreground/80">
                            Full history with pagination.
                        </div>
                    </div>
                    <span
                        class="inline-flex items-center rounded-full border border-border/40 bg-muted/40 px-2.5 py-1 text-[11px] font-medium text-muted-foreground"
                        >{{ bookings.total }} total</span
                    >
                </div>

                <div class="px-5 py-4">
                    <div v-if="bookings.data.length" class="grid gap-3">
                        <div
                            v-for="booking in bookings.data"
                            :key="booking.id"
                            class="group rounded-xl border border-border/40 p-4 text-sm transition-all hover:border-border/60 hover:shadow-sm"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <div class="font-semibold">
                                            {{ booking.title }}
                                        </div>
                                    </div>
                                    <div
                                        class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
                                    >
                                        <span
                                            v-if="booking.asset_label"
                                            class="inline-flex items-center gap-1"
                                        >
                                            <span
                                                class="h-1 w-1 rounded-full bg-primary/40"
                                            />
                                            {{ booking.asset_label }}
                                        </span>
                                        <span
                                            class="inline-flex items-center gap-1 tabular-nums"
                                        >
                                            <span
                                                class="h-1 w-1 rounded-full bg-primary/40"
                                            />
                                            {{
                                                formatDateTime(booking.start)
                                            }}
                                            — {{ formatDateTime(booking.end) }}
                                        </span>
                                    </div>
                                    <div
                                        class="text-xs text-muted-foreground/80"
                                    >
                                        {{
                                            booking.requester?.name ??
                                            booking.requester?.email ??
                                            'Unknown requester'
                                        }}
                                        <span
                                            v-if="booking.approver"
                                            class="text-muted-foreground/60"
                                            >&middot; Processed by
                                            {{ booking.approver.name }}</span
                                        >
                                    </div>
                                </div>
                                <span
                                    :class="[
                                        'inline-flex shrink-0 items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-semibold tracking-wider uppercase',
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
                            <div class="mt-3 flex items-center gap-2">
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
                                    >Delete</Button
                                >
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border/50 py-10 text-center"
                    >
                        <div
                            class="rounded-full border border-border/40 bg-muted/40 p-3"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="text-muted-foreground"
                            >
                                <path
                                    d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"
                                />
                                <polyline points="14 2 14 8 20 8" />
                                <line x1="16" x2="8" y1="13" y2="13" />
                                <line x1="16" x2="8" y1="17" y2="17" />
                                <polyline points="10 9 9 9 8 9" />
                            </svg>
                        </div>
                        <div class="text-sm font-medium text-muted-foreground">
                            No booking records
                        </div>
                        <div class="max-w-xs text-xs text-muted-foreground/70">
                            Submitted bookings will appear here once processed.
                        </div>
                    </div>

                    <div
                        v-if="bookings.links.length"
                        class="mt-4 flex flex-wrap items-center justify-center gap-1"
                    >
                        <Button
                            v-for="(link, index) in bookings.links"
                            :key="index"
                            variant="ghost"
                            size="sm"
                            :disabled="!link.url"
                            as-child
                            class="h-8 rounded-lg px-3 text-xs"
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
