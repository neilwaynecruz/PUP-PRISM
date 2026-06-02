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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as bookingsIndex } from '@/routes/inventory/bookings';

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

type BookingEvent = {
    id: number;
    asset_id: number;
    asset_label?: string | null;
    title: string;
    start: string;
    end: string;
    status: 'Requested' | 'Approved' | 'Rejected' | 'Cancelled';
    requester_id: number;
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
    calendar_events: Array<Pick<BookingEvent, 'id' | 'asset_id' | 'title' | 'start' | 'end' | 'status'>>;
    approval_queue: Omit<BookingEvent, 'requester_id'>[];
    bookings: Paginated<BookingEvent>;
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

const events = computed(() =>
    props.calendar_events.map((b) => ({
        id: String(b.id),
        title: b.title,
        start: b.start,
        end: b.end,
        backgroundColor: b.status === 'Approved' ? '#16a34a' : b.status === 'Requested' ? '#f59e0b' : '#ef4444',
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

        const exactMatch = assets.find((asset) => asset.tag_code === assetSearch.value.trim());

        if (!exactMatch) {
            return;
        }

        selectedAssetId.value = String(exactMatch.id);
        assetScanFeedback.value = `Matched ${exactMatch.tag_code} to ${exactMatch.name ?? 'Asset'}.`;
    },
    { deep: true },
);

function badgeVariant(status: BookingEvent['status']): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'Approved') {
        return 'default';
    }

    if (status === 'Requested') {
        return 'secondary';
    }

    if (status === 'Rejected') {
        return 'destructive';
    }

    return 'outline';
}

function applyScannedAsset(tagCode: string): void {
    const match = props.assets.find((asset) => asset.tag_code === tagCode.trim());

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

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <Heading
            variant="small"
            title="Asset booking calendar"
            description="Requests only block availability after approval, and each request stays attributable to the requesting position."
        />

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.7fr)_minmax(18rem,22rem)]">
            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="text-sm font-medium">Calendar overview</div>
                        <div class="text-sm text-muted-foreground">Green blocks approved use, amber blocks pending requests, red blocks rejected requests.</div>
                    </div>

                    <div class="flex flex-wrap gap-2 text-xs">
                        <Badge variant="default">Approved</Badge>
                        <Badge variant="secondary">Requested</Badge>
                        <Badge variant="destructive">Rejected</Badge>
                    </div>
                </div>

                <FullCalendar
                    :plugins="[dayGridPlugin, interactionPlugin]"
                    initialView="dayGridMonth"
                    :events="events"
                    height="auto"
                />
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <Form v-bind="BookingController.store.form()" v-slot="{ errors, processing }" class="grid gap-4">
                    <Heading
                        variant="small"
                        title="New booking request"
                        description="Select an accountable asset, then choose the requested schedule."
                    />

                    <div class="grid gap-2">
                        <Label for="asset_search">Find asset</Label>
                        <Input id="asset_search" v-model="assetSearch" placeholder="Search by tag or product name" />
                        <div class="text-sm text-muted-foreground">
                            The selector loads up to 25 matching available assets at a time.
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <Label for="asset_id">Asset</Label>
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
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                            required
                        >
                            <option value="" disabled>Select asset</option>
                            <option v-for="a in assets" :key="a.id" :value="String(a.id)">
                                {{ a.tag_code }} — {{ a.name ?? 'Asset' }}
                                <template v-if="a.position"> | {{ a.position.title }}{{ a.position.department ? `, ${a.position.department}` : '' }} </template>
                                ({{ a.status }})
                            </option>
                        </select>
                        <div v-if="assetScanFeedback" class="text-sm text-muted-foreground">
                            {{ assetScanFeedback }}
                        </div>
                        <InputError :message="errors.asset_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="start_at">Start</Label>
                        <Input id="start_at" name="start_at" v-model="startAt" type="datetime-local" required />
                        <InputError :message="errors.start_at" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="end_at">End</Label>
                        <Input id="end_at" name="end_at" v-model="endAt" type="datetime-local" required />
                        <InputError :message="errors.end_at" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="purpose">Purpose (optional)</Label>
                        <Input id="purpose" name="purpose" v-model="purpose" placeholder="e.g. Classroom demo" />
                        <InputError :message="errors.purpose" />
                    </div>

                    <div class="flex items-center justify-end gap-2">
                        <Button type="submit" :disabled="processing">Request booking</Button>
                    </div>
                </Form>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <div class="font-medium">Approval queue</div>
                        <div class="text-sm text-muted-foreground">Property custodians can approve or reject pending requests directly from this screen.</div>
                    </div>
                    <Badge variant="outline">{{ approval_queue.length }} pending</Badge>
                </div>

                <div v-if="approval_queue.length" class="grid gap-3">
                    <div
                        v-for="booking in approval_queue"
                        :key="booking.id"
                        class="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                    >
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="space-y-1 text-sm">
                                <div class="font-medium">{{ booking.title }}</div>
                                <div class="text-muted-foreground">
                                    Requested by
                                    {{ booking.requester?.name ?? booking.requester?.email ?? 'Unknown requester' }}
                                </div>
                                <div v-if="booking.requester_position" class="text-muted-foreground">
                                    {{ booking.requester_position.title }}{{ booking.requester_position.department ? `, ${booking.requester_position.department}` : '' }}
                                </div>
                                <div class="text-muted-foreground">Schedule: {{ booking.start }} to {{ booking.end }}</div>
                            </div>

                            <Badge :variant="badgeVariant(booking.status)">{{ booking.status }}</Badge>
                        </div>

                        <div v-if="can.approve" class="mt-4 flex flex-wrap gap-2">
                            <Form v-bind="BookingController.update.form(booking.id)">
                                <Button type="submit" name="action" value="approve">Approve</Button>
                            </Form>
                            <Form v-bind="BookingController.update.form(booking.id)">
                                <Button type="submit" name="action" value="reject" variant="secondary">Reject</Button>
                            </Form>
                        </div>
                    </div>
                </div>

                <div v-else class="rounded-lg border border-dashed border-sidebar-border/70 p-6 text-sm text-muted-foreground dark:border-sidebar-border">
                    No pending requests. The walkthrough queue is clear.
                </div>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <div class="mb-4">
                    <div class="font-medium">Booking records</div>
                    <div class="text-sm text-muted-foreground">Paginated records keep the page responsive while preserving access to the full booking history.</div>
                </div>

                <div class="grid gap-3">
                    <div
                        v-for="booking in bookings.data"
                        :key="booking.id"
                        class="rounded-lg border border-sidebar-border/70 p-4 text-sm dark:border-sidebar-border"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div class="font-medium">{{ booking.title }}</div>
                            <Badge :variant="badgeVariant(booking.status)">{{ booking.status }}</Badge>
                        </div>
                        <div class="mt-2 space-y-1 text-muted-foreground">
                            <div v-if="booking.asset_label">Tag: {{ booking.asset_label }}</div>
                            <div>{{ booking.start }} to {{ booking.end }}</div>
                            <div>{{ booking.requester?.name ?? booking.requester?.email ?? 'Unknown requester' }}</div>
                            <div v-if="booking.approver">Processed by {{ booking.approver.name }}</div>
                        </div>
                    </div>
                </div>

                <div v-if="bookings.links.length" class="mt-4 flex flex-wrap items-center justify-center gap-2">
                    <Button
                        v-for="(link, index) in bookings.links"
                        :key="index"
                        variant="ghost"
                        size="sm"
                        :disabled="!link.url"
                        as-child
                    >
                        <Link v-if="link.url" :href="link.url" preserve-scroll preserve-state>
                            <span v-html="link.label" />
                        </Link>
                        <span v-else v-html="link.label" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
