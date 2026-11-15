<script setup lang="ts">
import type {
    CalendarOptions,
    DateSelectArg,
    EventClickArg,
} from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import type { DateClickArg } from '@fullcalendar/interaction';
import timeGridPlugin from '@fullcalendar/timegrid';
import FullCalendar from '@fullcalendar/vue3';
import { Form, Head, Link, router } from '@inertiajs/vue3';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import BookingController from '@/actions/App/Http/Controllers/Inventory/BookingController';
import AdminPagination from '@/components/admin/AdminPagination.vue';
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
import { shouldApplyDebouncedVisit } from '@/lib/inertiaNavigation';
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
    filters: {
        asset_search: string;
        search: string;
        status: string | null;
        date_from: string | null;
        date_to: string | null;
        requester_id: number | null;
    };
    statusOptions: Array<{ value: string; label: string }>;
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
    timezone: string;
}>();

defineOptions({
    name: 'BookingIndexPage',
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
const listSearch = ref<string>(props.filters.search ?? '');
const listStatus = ref<string>(props.filters.status ?? 'all');
const listDateFrom = ref<string>(props.filters.date_from ?? '');
const listDateTo = ref<string>(props.filters.date_to ?? '');
const assetScanFeedback = ref<string>('');
const selectedRejectBooking = ref<BookingBase | null>(null);
const selectedBooking = ref<BookingBase | null>(null);
const deleteDialogOpen = ref(false);
const deleteReason = ref('');
const deleteReasonCustom = ref('');
const isCompactCalendar = ref(false);
const isTabletCalendar = ref(false);
const calendarComponent = ref<InstanceType<typeof FullCalendar> | null>(null);

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

    if (Number.isNaN(d.getTime())) {
        return '—';
    }

    return d.toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
        timeZone: props.timezone || 'Asia/Manila',
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
const bulkActionProcessing = ref(false);

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
    if (!pendingBulkAction.value || bulkActionProcessing.value) {
        return;
    }

    const endpoint =
        pendingBulkAction.value === 'approve'
            ? bookingsBulkApprove().url
            : bookingsBulkReject().url;

    bulkActionProcessing.value = true;

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
            onFinish: () => {
                bulkActionProcessing.value = false;
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

// ── Calendar filters and availability ──
type CalendarStatus = BookingEvent['status'];

const visibleStatuses = ref<Set<CalendarStatus>>(
    new Set<CalendarStatus>(['Approved', 'Requested']),
);
const focusSelectedAsset = ref(true);

function toggleStatus(status: CalendarStatus): void {
    const next = new Set(visibleStatuses.value);

    if (next.has(status)) {
        next.delete(status);
    } else {
        next.add(status);
    }

    visibleStatuses.value = next;
}

const selectedAsset = computed(
    () =>
        props.assets.find(
            (asset) => String(asset.id) === selectedAssetId.value,
        ) ?? null,
);

const filteredCalendarEvents = computed(() =>
    props.calendar_events.filter(
        (event) =>
            visibleStatuses.value.has(event.status) &&
            (!focusSelectedAsset.value ||
                !selectedAsset.value ||
                event.asset_id === selectedAsset.value.id),
    ),
);

const selectedAssetSchedule = computed(() => {
    if (!selectedAsset.value) {
        return [];
    }

    const now = Date.now();

    return props.calendar_events
        .filter(
            (event) =>
                event.asset_id === selectedAsset.value?.id &&
                (event.status === 'Approved' || event.status === 'Requested') &&
                new Date(event.end).getTime() > now,
        )
        .sort((a, b) => a.start.localeCompare(b.start))
        .slice(0, 5);
});

const scheduleConflicts = computed(() => {
    const empty = { approved: [], requested: [] } as {
        approved: typeof props.calendar_events;
        requested: typeof props.calendar_events;
    };

    if (!selectedAsset.value || !startAt.value || !endAt.value) {
        return empty;
    }

    const start = new Date(startAt.value).getTime();
    const end = new Date(endAt.value).getTime();

    if (Number.isNaN(start) || Number.isNaN(end) || end <= start) {
        return empty;
    }

    const overlapping = props.calendar_events.filter(
        (event) =>
            event.asset_id === selectedAsset.value?.id &&
            new Date(event.start).getTime() < end &&
            new Date(event.end).getTime() > start,
    );

    return {
        approved: overlapping.filter((event) => event.status === 'Approved'),
        requested: overlapping.filter((event) => event.status === 'Requested'),
    };
});

const scheduleError = computed(() => {
    if (!startAt.value || !endAt.value) {
        return '';
    }

    if (new Date(endAt.value).getTime() <= new Date(startAt.value).getTime()) {
        return 'The end time must be later than the start time.';
    }

    return '';
});

const durationLabel = computed(() => {
    if (!startAt.value || !endAt.value || scheduleError.value) {
        return '';
    }

    const minutes = Math.round(
        (new Date(endAt.value).getTime() - new Date(startAt.value).getTime()) /
            60000,
    );
    const days = Math.floor(minutes / 1440);
    const hours = Math.floor((minutes % 1440) / 60);
    const mins = minutes % 60;

    return [
        days ? `${days} day${days === 1 ? '' : 's'}` : '',
        hours ? `${hours} hr` : '',
        mins ? `${mins} min` : '',
    ]
        .filter(Boolean)
        .join(' ');
});

const browserTimezone =
    typeof Intl !== 'undefined'
        ? Intl.DateTimeFormat().resolvedOptions().timeZone
        : props.timezone;
const timezoneMismatch = computed(
    () => Boolean(props.timezone) && browserTimezone !== props.timezone,
);

function toLocalInput(date: Date): string {
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

const minStartInput = ref(toLocalInput(new Date()));
let minStartRefreshTimer: ReturnType<typeof setInterval> | null = null;

function refreshMinStartInput(): void {
    minStartInput.value = toLocalInput(new Date());
}

function prefillSchedule(start: Date, end: Date): void {
    refreshMinStartInput();

    if (end.getTime() <= Date.now()) {
        return;
    }

    startAt.value = toLocalInput(start);
    endAt.value = toLocalInput(end);
    document
        .getElementById('start_at')
        ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function onCalendarSelect(selection: DateSelectArg): void {
    if (selection.allDay) {
        const start = new Date(selection.start);
        start.setHours(8, 0, 0, 0);
        const end = new Date(selection.end);
        end.setDate(end.getDate() - 1);
        end.setHours(17, 0, 0, 0);
        prefillSchedule(start, end);

        return;
    }

    prefillSchedule(selection.start, selection.end);
}

function onCalendarDateClick(click: DateClickArg): void {
    if (!click.allDay) {
        const end = new Date(click.date);
        end.setHours(end.getHours() + 1);
        prefillSchedule(click.date, end);
    }
}

function onCalendarEventClick(click: EventClickArg): void {
    click.jsEvent.preventDefault();
    router.visit(`/inventory/bookings/${click.event.id}`);
}

const events = computed(() =>
    filteredCalendarEvents.value.map((b) => ({
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
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    initialView: isCompactCalendar.value
        ? 'timeGridDay'
        : isTabletCalendar.value
          ? 'timeGridThreeDay'
          : 'dayGridMonth',
    views: {
        timeGridThreeDay: {
            type: 'timeGrid',
            duration: { days: 3 },
            buttonText: '3 Days',
        },
    },
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: isCompactCalendar.value
            ? 'timeGridDay,dayGridMonth'
            : isTabletCalendar.value
              ? 'timeGridThreeDay,dayGridMonth,timeGridDay'
              : 'dayGridMonth,timeGridThreeDay,timeGridWeek,timeGridDay',
    },
    buttonText: { today: 'Today', month: 'Month', week: 'Week', day: 'Day' },
    events: events.value,
    height: isCompactCalendar.value ? 520 : isTabletCalendar.value ? 560 : 620,
    dayMaxEventRows: isCompactCalendar.value ? 2 : 3,
    eventDisplay: 'block',
    nowIndicator: true,
    slotMinTime: '06:00:00',
    slotMaxTime: '22:00:00',
    scrollTime: '08:00:00',
    allDaySlot: false,
    selectable: true,
    selectMirror: true,
    selectAllow: (span) => span.end.getTime() > Date.now(),
    select: onCalendarSelect,
    dateClick: onCalendarDateClick,
    eventClick: onCalendarEventClick,
    eventTimeFormat: { hour: 'numeric', minute: '2-digit', meridiem: 'short' },
}));

const listQuery = computed(() => ({
    asset_search: assetSearch.value || undefined,
    search: listSearch.value || undefined,
    status: listStatus.value === 'all' ? undefined : listStatus.value,
    date_from: listDateFrom.value || undefined,
    date_to: listDateTo.value || undefined,
}));

let assetSearchTimer: number | undefined;
let compactCalendarMediaQuery: MediaQueryList | null = null;
let tabletCalendarMediaQuery: MediaQueryList | null = null;
let isComponentActive = true;

watch([assetSearch, listSearch, listStatus, listDateFrom, listDateTo], () => {
    window.clearTimeout(assetSearchTimer);
    assetSearchTimer = window.setTimeout(() => {
        if (
            !isComponentActive ||
            !shouldApplyDebouncedVisit('/inventory/bookings')
        ) {
            return;
        }

        router.get(bookingsIndex().url, listQuery.value, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 250);
});

onBeforeUnmount(() => {
    isComponentActive = false;
    window.clearTimeout(assetSearchTimer);

    if (minStartRefreshTimer !== null) {
        clearInterval(minStartRefreshTimer);
        minStartRefreshTimer = null;
    }

    compactCalendarMediaQuery?.removeEventListener(
        'change',
        syncCompactCalendarMode,
    );
    tabletCalendarMediaQuery?.removeEventListener(
        'change',
        syncCompactCalendarMode,
    );
});

function syncCompactCalendarMode(): void {
    isCompactCalendar.value = compactCalendarMediaQuery?.matches ?? false;
    isTabletCalendar.value = tabletCalendarMediaQuery?.matches ?? false;

    void nextTick(() => {
        calendarComponent.value
            ?.getApi()
            .changeView(
                isCompactCalendar.value
                    ? 'timeGridDay'
                    : isTabletCalendar.value
                      ? 'timeGridThreeDay'
                      : 'dayGridMonth',
            );
    });
}

onMounted(() => {
    if (typeof window === 'undefined') {
        return;
    }

    refreshMinStartInput();
    minStartRefreshTimer = setInterval(refreshMinStartInput, 30_000);

    compactCalendarMediaQuery = window.matchMedia('(max-width: 767px)');
    tabletCalendarMediaQuery = window.matchMedia(
        '(min-width: 768px) and (max-width: 1023px)',
    );
    syncCompactCalendarMode();
    compactCalendarMediaQuery.addEventListener(
        'change',
        syncCompactCalendarMode,
    );
    tabletCalendarMediaQuery.addEventListener(
        'change',
        syncCompactCalendarMode,
    );
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

function selectedBookingRequestLabel(): string {
    const count = selectedIds.value.size;

    return `${count} selected ${count === 1 ? 'booking request' : 'booking requests'}`;
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
                description="Request an accountable asset for specific dates. Approved bookings reserve the asset, while new requests wait for review."
            />

            <div class="flex flex-wrap items-center gap-2">
                <Button variant="outline" size="sm" as-child class="rounded-lg">
                    <a :href="props.exportUrls.csv">Download CSV</a>
                </Button>
                <Button variant="outline" size="sm" as-child class="rounded-lg">
                    <a :href="props.exportUrls.pdf">Download PDF</a>
                </Button>
                <Button variant="outline" size="sm" as-child class="rounded-lg">
                    <Link href="/inventory/bookings/trash">Trash</Link>
                </Button>
            </div>
        </div>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_400px]">
            <div
                class="order-2 rounded-xl border border-border/50 bg-card shadow-sm lg:order-1"
            >
                <div
                    class="flex flex-col gap-3 border-b border-border/40 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <div class="text-sm font-semibold tracking-tight">
                            Calendar overview
                        </div>
                        <div class="text-xs text-muted-foreground/80">
                            Click a date or drag across times to fill in the
                            booking form. Click a booking to see its details.
                        </div>
                    </div>
                    <div
                        class="flex flex-wrap items-center gap-1.5"
                        role="group"
                        aria-label="Show bookings by status"
                    >
                        <button
                            v-for="status in [
                                'Approved',
                                'Requested',
                                'Rejected',
                                'Cancelled',
                            ] as CalendarStatus[]"
                            :key="status"
                            type="button"
                            :aria-pressed="visibleStatuses.has(status)"
                            class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold transition"
                            :class="
                                visibleStatuses.has(status)
                                    ? statusColor(status).pill
                                    : 'border-dashed border-border text-muted-foreground/70 hover:text-foreground'
                            "
                            @click="toggleStatus(status)"
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full"
                                :class="
                                    visibleStatuses.has(status)
                                        ? statusColor(status).bg
                                        : 'bg-muted-foreground/40'
                                "
                            />
                            {{ status }}
                        </button>
                    </div>
                </div>
                <div
                    v-if="selectedAsset"
                    class="flex flex-wrap items-center justify-between gap-2 border-b border-border/40 bg-primary/3 px-5 py-2.5 text-xs"
                >
                    <span class="text-muted-foreground">
                        <template v-if="focusSelectedAsset">
                            Showing only
                            <span class="font-semibold text-foreground">{{
                                selectedAsset.tag_code
                            }}</span>
                            — {{ selectedAsset.name ?? 'Asset' }}
                        </template>
                        <template v-else>Showing all assets</template>
                    </span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-7 rounded-lg text-xs"
                        data-testid="booking-calendar-focus-toggle"
                        @click="focusSelectedAsset = !focusSelectedAsset"
                    >
                        {{
                            focusSelectedAsset
                                ? 'Show all assets'
                                : `Show only ${selectedAsset.tag_code}`
                        }}
                    </Button>
                </div>
                <div data-testid="booking-calendar" class="p-3">
                    <FullCalendar
                        ref="calendarComponent"
                        :options="calendarOptions"
                    >
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
                        v-if="isCompactCalendar"
                        class="mt-3 rounded-lg border border-border/60 bg-muted/20 px-3 py-2 text-xs text-muted-foreground"
                    >
                        On mobile, the calendar shows one day at a time. Use the
                        arrows to move between days, or switch to Month.
                    </div>
                    <div
                        v-if="filteredCalendarEvents.length === 0"
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
                            {{
                                selectedAsset && focusSelectedAsset
                                    ? `${selectedAsset.tag_code} has no bookings in this period`
                                    : 'No bookings match the selected statuses'
                            }}
                        </div>
                        <div class="max-w-xs text-xs text-muted-foreground/70">
                            {{
                                selectedAsset && focusSelectedAsset
                                    ? 'The asset looks free. Pick a date on the calendar to start your request.'
                                    : 'Turn on more statuses above, or submit a new request with the form.'
                            }}
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="order-1 self-start rounded-xl border border-border/50 bg-card shadow-sm lg:order-2"
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
                            Enter a property tag or product name. The list shows
                            up to 25 matching assets.
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                        >
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
                                trigger-test-id="booking-scan-button"
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
                            class="rounded-lg border border-primary/15 bg-primary/5 px-3 py-2 text-xs text-muted-foreground"
                        >
                            {{ assetScanFeedback }}
                        </div>
                        <p
                            v-if="assets.length === 0"
                            class="text-xs text-muted-foreground"
                        >
                            No available assets match this search. Only assets
                            with an Available status can be booked.
                        </p>
                        <InputError :message="errors.asset_id" />

                        <div
                            v-if="selectedAsset"
                            class="rounded-lg border border-border/60 bg-muted/25 px-3 py-2.5 text-xs"
                            data-testid="booking-selected-asset"
                        >
                            <div class="font-medium text-foreground">
                                {{ selectedAsset.name ?? 'Asset' }}
                                <span class="font-mono text-muted-foreground">{{
                                    selectedAsset.tag_code
                                }}</span>
                            </div>
                            <div
                                v-if="selectedAsset.position"
                                class="text-muted-foreground"
                            >
                                Accountable:
                                {{ selectedAsset.position.title
                                }}{{
                                    selectedAsset.position.department
                                        ? `, ${selectedAsset.position.department}`
                                        : ''
                                }}
                            </div>
                            <div class="mt-2 font-medium text-foreground/85">
                                Upcoming schedule
                            </div>
                            <ul
                                v-if="selectedAssetSchedule.length"
                                class="mt-1 space-y-1"
                            >
                                <li
                                    v-for="event in selectedAssetSchedule"
                                    :key="event.id"
                                    class="flex items-center justify-between gap-2"
                                >
                                    <span class="tabular-nums">
                                        {{ formatDateTime(event.start) }} –
                                        {{ formatDateTime(event.end) }}
                                    </span>
                                    <span
                                        class="shrink-0 rounded-full border px-1.5 py-0.5 text-[10px] font-semibold"
                                        :class="statusColor(event.status).pill"
                                        >{{ event.status }}</span
                                    >
                                </li>
                            </ul>
                            <p v-else class="mt-1 text-muted-foreground">
                                No upcoming bookings. The asset is free for now.
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
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
                                :min="minStartInput"
                                aria-describedby="booking-start-error"
                                required
                                class="h-9 rounded-lg text-sm"
                            />
                            <InputError
                                id="booking-start-error"
                                :message="errors.start_at"
                            />
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
                                :min="startAt || minStartInput"
                                aria-describedby="booking-end-error"
                                required
                                class="h-9 rounded-lg text-sm"
                            />
                            <InputError
                                id="booking-end-error"
                                :message="errors.end_at"
                            />
                        </div>
                    </div>

                    <div
                        v-if="
                            scheduleError || durationLabel || timezoneMismatch
                        "
                        class="-mt-2 space-y-1 text-xs"
                        aria-live="polite"
                    >
                        <p
                            v-if="scheduleError"
                            class="font-medium text-rose-600 dark:text-rose-400"
                        >
                            {{ scheduleError }}
                        </p>
                        <p
                            v-else-if="durationLabel"
                            class="text-muted-foreground"
                        >
                            Duration: {{ durationLabel }}
                        </p>
                        <p
                            v-if="timezoneMismatch"
                            class="text-muted-foreground"
                        >
                            Times are entered in your device's time zone ({{
                                browserTimezone
                            }}) and stored in PRISM's time zone ({{
                                timezone
                            }}).
                        </p>
                    </div>

                    <div
                        v-if="scheduleConflicts.approved.length"
                        class="rounded-lg border border-rose-500/30 bg-rose-500/5 px-3 py-2 text-xs text-rose-700 dark:text-rose-300"
                        role="alert"
                        data-testid="booking-conflict-warning"
                    >
                        <span class="font-semibold">Not available:</span>
                        this asset already has an approved booking from
                        {{
                            formatDateTime(scheduleConflicts.approved[0].start)
                        }}
                        to
                        {{ formatDateTime(scheduleConflicts.approved[0].end) }}.
                        Choose another time or asset.
                    </div>
                    <div
                        v-else-if="scheduleConflicts.requested.length"
                        class="rounded-lg border border-amber-500/30 bg-amber-500/5 px-3 py-2 text-xs text-amber-800 dark:text-amber-300"
                        role="status"
                    >
                        {{ scheduleConflicts.requested.length }} other
                        {{
                            scheduleConflicts.requested.length === 1
                                ? 'request overlaps'
                                : 'requests overlap'
                        }}
                        this time. You can still submit; whichever is approved
                        first reserves the asset.
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

                    <div
                        class="sticky bottom-4 z-10 rounded-xl border border-border/60 bg-background/95 p-3 shadow-lg backdrop-blur supports-backdrop-filter:bg-background/80 md:static md:border-0 md:bg-transparent md:p-0 md:shadow-none"
                    >
                        <Button
                            type="submit"
                            :disabled="
                                processing ||
                                Boolean(scheduleError) ||
                                scheduleConflicts.approved.length > 0
                            "
                            data-test="request-booking-button"
                            data-testid="request-booking-button"
                            class="w-full rounded-lg font-semibold shadow-sm"
                        >
                            {{
                                processing
                                    ? 'Submitting Request…'
                                    : 'Submit Booking Request'
                            }}
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
                            :model-value="
                                allQueueSelected
                                    ? true
                                    : someQueueSelected
                                      ? 'indeterminate'
                                      : false
                            "
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
                            :class="{
                                'bg-primary/5': selectedIds.has(booking.id),
                            }"
                        >
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div class="flex items-start gap-3">
                                    <Checkbox
                                        :model-value="
                                            selectedIds.has(booking.id)
                                        "
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
                                            {{ formatDateTime(booking.start) }}
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
                                    class="min-h-11 rounded-lg text-xs sm:h-8 sm:min-h-8"
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
                                            class="min-h-11 rounded-lg text-xs sm:h-8 sm:min-h-8"
                                            >Approve</Button
                                        >
                                    </Form>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="min-h-11 rounded-lg text-xs sm:h-8 sm:min-h-8"
                                        @click="selectedRejectBooking = booking"
                                        >Reject</Button
                                    >
                                </template>
                                <Button
                                    v-if="booking.can_delete"
                                    variant="ghost"
                                    size="sm"
                                    class="min-h-11 rounded-lg text-xs text-rose-600 hover:text-rose-700 sm:h-8 sm:min-h-8"
                                    @click="openDeleteDialog(booking)"
                                    >Move to Trash</Button
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
                            Search and review current and past booking requests.
                        </div>
                    </div>
                    <span
                        class="inline-flex items-center rounded-full border border-border/40 bg-muted/40 px-2.5 py-1 text-[11px] font-medium text-muted-foreground"
                        >{{ bookings.total }} total</span
                    >
                </div>

                <div class="px-5 py-4">
                    <fieldset
                        class="mb-4 grid gap-3 md:grid-cols-2 lg:grid-cols-4"
                    >
                        <legend class="sr-only">Filter booking records</legend>
                        <label
                            class="grid gap-1 text-xs font-medium text-muted-foreground"
                        >
                            Search
                            <Input
                                v-model="listSearch"
                                name="booking_search"
                                autocomplete="off"
                                placeholder="Booking, asset, or requester…"
                            />
                        </label>
                        <label
                            class="grid gap-1 text-xs font-medium text-muted-foreground"
                        >
                            Status
                            <Select v-model="listStatus">
                                <SelectTrigger aria-label="Booking status">
                                    <SelectValue placeholder="All statuses" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all"
                                        >All statuses</SelectItem
                                    >
                                    <SelectItem
                                        v-for="option in statusOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </label>
                        <label
                            class="grid gap-1 text-xs font-medium text-muted-foreground"
                        >
                            Starts on or after
                            <Input
                                v-model="listDateFrom"
                                name="booking_date_from"
                                type="date"
                            />
                        </label>
                        <label
                            class="grid gap-1 text-xs font-medium text-muted-foreground"
                        >
                            Starts on or before
                            <Input
                                v-model="listDateTo"
                                name="booking_date_to"
                                type="date"
                            />
                        </label>
                    </fieldset>

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
                                            {{ formatDateTime(booking.start) }}
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
                                    class="min-h-11 rounded-lg text-xs sm:h-8 sm:min-h-8"
                                >
                                    <Link
                                        :href="`/inventory/bookings/${booking.id}`"
                                        >View Details</Link
                                    >
                                </Button>
                                <Button
                                    v-if="booking.can_delete"
                                    variant="ghost"
                                    size="sm"
                                    class="min-h-11 rounded-lg text-xs text-rose-600 hover:text-rose-700 sm:h-8 sm:min-h-8"
                                    @click="openDeleteDialog(booking)"
                                    >Move to Trash</Button
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
                            No bookings are available in this view. Try changing
                            the search, status, or dates.
                        </div>
                    </div>

                    <AdminPagination
                        :links="bookings.links"
                        label="Booking pages"
                        class="mt-4"
                    />
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
                        <Button
                            variant="secondary"
                            type="button"
                            :disabled="bulkActionProcessing"
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
                            >Reject Booking</Button
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
                        <strong>{{ selectedBookingRequestLabel() }}</strong>
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
                        :disabled="bulkActionProcessing"
                        @click="confirmBulkAction"
                    >
                        {{
                            bulkActionProcessing
                                ? 'Processing…'
                                : pendingBulkAction === 'approve'
                                  ? 'Approve Bookings'
                                  : 'Reject Bookings'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="deleteDialogOpen">
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle>Move booking to trash?</DialogTitle>
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
                                <SelectValue placeholder="Select a reason…" />
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
                            placeholder="Enter your reason…"
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
                        >Move to Trash</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
