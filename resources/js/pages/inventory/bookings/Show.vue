<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import BookingController from '@/actions/App/Http/Controllers/Inventory/BookingController';
import Heading from '@/components/Heading.vue';
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
import { index as bookingsIndex } from '@/routes/inventory/bookings';

type Person = { id: number; name: string; email: string };
type PositionSummary = { title: string; department: string | null };

defineProps<{
    booking: {
        id: number;
        asset_id: number;
        asset_label: string | null;
        title: string;
        start: string;
        end: string;
        status: string;
        purpose: string | null;
        requester: Person | null;
        requester_position: PositionSummary | null;
        approver: Person | null;
        approver_position: PositionSummary | null;
        requested_ip_address: string | null;
        approved_ip_address: string | null;
        deleted_at: string | null;
        deleted_by: Person | null;
        deletion_reason: string | null;
    };
    can: { approve: boolean; reject: boolean };
    isDeleted?: boolean;
}>();

const rejectDialogOpen = ref(false);

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

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: '/inventory' },
            { title: 'Bookings', href: bookingsIndex() },
            { title: 'Detail' },
        ],
    },
});
</script>

<template>
    <Head :title="`Booking #${booking.id}`" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <!-- Deleted warning -->
        <div
            v-if="isDeleted"
            class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300"
        >
            <div class="flex items-center gap-2 text-sm font-medium">
                <svg
                    class="h-5 w-5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                    />
                </svg>
                This booking has been deleted and is in the trash.
            </div>
            <div v-if="booking.deleted_by" class="mt-1 text-xs opacity-80">
                Deleted by {{ booking.deleted_by.name }} on
                {{ formatDateTime(booking.deleted_at) }}
                <span v-if="booking.deletion_reason"
                    >— Reason: {{ booking.deletion_reason }}</span
                >
            </div>
        </div>

        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                variant="small"
                :title="`Booking #${booking.id}`"
                :description="`Asset: ${booking.asset_label ?? '—'}`"
            />
            <div class="flex flex-wrap gap-2">
                <template v-if="!isDeleted">
                    <Form
                        v-if="can.approve"
                        v-bind="BookingController.update.form(booking.id)"
                    >
                        <Button
                            type="submit"
                            name="action"
                            value="approve"
                            class="rounded-lg"
                            >Approve</Button
                        >
                    </Form>
                    <Button
                        v-if="can.reject"
                        variant="outline"
                        class="rounded-lg border-dashed"
                        @click="rejectDialogOpen = true"
                    >
                        Reject
                    </Button>
                </template>
                <Button variant="outline" as-child class="rounded-lg">
                    <Link :href="bookingsIndex()">Back to bookings</Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="space-y-4">
                <div
                    class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
                >
                    <div class="mb-3 text-sm font-semibold tracking-tight">
                        Details
                    </div>
                    <div class="grid gap-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Status</span>
                            <span
                                class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium"
                                :class="
                                    booking.status === 'Approved'
                                        ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                        : booking.status === 'Rejected'
                                          ? 'border-rose-500/20 bg-rose-500/10 text-rose-700 dark:text-rose-300'
                                          : 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300'
                                "
                            >
                                {{ booking.status }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Asset</span>
                            <span class="font-medium">{{
                                booking.asset_label ?? '—'
                            }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Start</span>
                            <span>{{ formatDateTime(booking.start) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">End</span>
                            <span>{{ formatDateTime(booking.end) }}</span>
                        </div>
                        <div
                            v-if="booking.purpose"
                            class="flex justify-between"
                        >
                            <span class="text-muted-foreground">Purpose</span>
                            <span>{{ booking.purpose }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div
                    class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
                >
                    <div class="mb-3 text-sm font-semibold tracking-tight">
                        People
                    </div>
                    <div class="grid gap-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Requester</span>
                            <span class="text-right">
                                <span class="font-medium">{{
                                    booking.requester?.name ?? '—'
                                }}</span>
                                <div
                                    v-if="booking.requester_position"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ booking.requester_position.title
                                    }}{{
                                        booking.requester_position.department
                                            ? `, ${booking.requester_position.department}`
                                            : ''
                                    }}
                                </div>
                            </span>
                        </div>
                        <div
                            v-if="booking.approver"
                            class="flex justify-between"
                        >
                            <span class="text-muted-foreground">Approver</span>
                            <span class="text-right">
                                <span class="font-medium">{{
                                    booking.approver.name
                                }}</span>
                                <div
                                    v-if="booking.approver_position"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ booking.approver_position.title
                                    }}{{
                                        booking.approver_position.department
                                            ? `, ${booking.approver_position.department}`
                                            : ''
                                    }}
                                </div>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reject dialog -->
        <Dialog v-if="!isDeleted && can.reject" v-model:open="rejectDialogOpen">
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle>Reject booking request?</DialogTitle>
                    <DialogDescription>
                        This will permanently reject the booking request for
                        <strong>{{ booking.title }}</strong
                        >.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" type="button"
                            >Cancel</Button
                        >
                    </DialogClose>
                    <Form
                        v-bind="BookingController.update.form(booking.id)"
                        class="inline"
                    >
                        <Button
                            type="submit"
                            name="action"
                            value="reject"
                            variant="destructive"
                            >Confirm Reject</Button
                        >
                    </Form>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
