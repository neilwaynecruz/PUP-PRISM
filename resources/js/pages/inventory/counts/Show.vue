<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    RotateCcw,
    Send,
    XCircle,
} from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    approve,
    cancel,
    complete,
    index,
    show,
    submit,
} from '@/routes/inventory/counts';
import { review, update } from '@/routes/inventory/counts/lines';

type Person = { id: number; name: string };
type CountLine = {
    id: number;
    expected_qty_snapshot: number;
    expected_qty_at_count: number | null;
    counted_qty: number | null;
    variance_qty: number | null;
    count_notes: string | null;
    review_status: string;
    review_notes: string | null;
    counted_by: Person | null;
    reviewed_by: Person | null;
    adjustment_movement: {
        id: number;
        qty_delta: number;
        qty_before: number;
        qty_after: number;
        performed_at: string;
    } | null;
    product: { id: number; sku: string; name: string };
    stock_lot: {
        id: number;
        reference_no: string | null;
        qty_remaining: number;
        expires_at: string | null;
    } | null;
    asset: {
        id: number;
        tag_code: string;
        status: string;
        lifecycle_status: string;
        condition_status: string;
    } | null;
};
type CountSession = {
    id: number;
    reference_no: string;
    scope: string;
    status: string;
    counting_date: string;
    snapshot_at: string;
    remarks: string | null;
    location: {
        code: string;
        name: string;
        building: string | null;
        room: string | null;
    } | null;
    creator: Person;
    assigned_user: Person;
    approver: Person | null;
    lines: CountLine[];
};

const props = defineProps<{
    session: CountSession;
    can: {
        update: boolean;
        review: boolean;
        complete: boolean;
        cancel: boolean;
    };
}>();

defineOptions({
    name: 'InventoryCountShowPage',
    layout: {
        breadcrumbs: [
            { title: 'Physical counts', href: index() },
            {
                title: 'Count details',
                href: show(
                    Number(window.location.pathname.split('/').pop()) || 1,
                ),
            },
        ],
    },
});

const countValues = reactive<Record<number, string>>(
    Object.fromEntries(
        props.session.lines.map((line) => [
            line.id,
            line.counted_qty === null ? '' : String(line.counted_qty),
        ]),
    ),
);
const countNotes = reactive<Record<number, string>>(
    Object.fromEntries(
        props.session.lines.map((line) => [line.id, line.count_notes ?? '']),
    ),
);
const reviewNotes = reactive<Record<number, string>>(
    Object.fromEntries(
        props.session.lines.map((line) => [line.id, line.review_notes ?? '']),
    ),
);
const busyLine = ref<number | null>(null);
const busyAction = ref('');
const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);

function saveCount(line: CountLine): void {
    busyLine.value = line.id;
    router.put(
        update([props.session.id, line.id]).url,
        {
            counted_qty: Number(countValues[line.id]),
            count_notes: countNotes[line.id] || null,
        },
        { preserveScroll: true, onFinish: () => (busyLine.value = null) },
    );
}

function reviewLine(
    line: CountLine,
    status: 'approved' | 'recount_requested' | 'rejected',
): void {
    busyLine.value = line.id;
    router.put(
        review([props.session.id, line.id]).url,
        {
            review_status: status,
            review_notes: reviewNotes[line.id] || null,
        },
        { preserveScroll: true, onFinish: () => (busyLine.value = null) },
    );
}

function runAction(name: 'submit' | 'approve' | 'complete' | 'cancel'): void {
    const prompts = {
        submit: 'Submit this physical count for review? Counted quantities will be locked until a recount is requested.',
        approve:
            'Approve this count and post the accepted stock differences? This changes official stock balances.',
        complete: 'Mark this approved count session as complete?',
        cancel: 'Cancel this count session? Saved observations will remain in the history.',
    };

    if (!window.confirm(prompts[name])) {
        return;
    }

    const route =
        name === 'submit'
            ? submit(props.session.id)
            : name === 'approve'
              ? approve(props.session.id)
              : name === 'complete'
                ? complete(props.session.id)
                : cancel(props.session.id);
    busyAction.value = name;
    router.post(
        route.url,
        {},
        { preserveScroll: true, onFinish: () => (busyAction.value = '') },
    );
}

function label(value: string): string {
    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeZone: 'Asia/Manila',
    }).format(new Date(value));
}

function varianceClass(value: number | null): string {
    if (value === null || value === 0) {
        return 'text-emerald-700 dark:text-emerald-300';
    }

    return value > 0
        ? 'text-sky-700 dark:text-sky-300'
        : 'text-rose-700 dark:text-rose-300';
}

const editable = computed(
    () =>
        props.can.update &&
        ['draft', 'in_progress'].includes(props.session.status),
);
const submitted = computed(() => props.session.status === 'submitted');
</script>

<template>
    <Head :title="`Physical count ${session.reference_no}`" />
    <div
        class="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 sm:p-6 lg:p-8"
    >
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
        >
            <div>
                <Link
                    :href="index()"
                    class="mb-3 inline-flex min-h-10 items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                    ><ArrowLeft aria-hidden="true" class="h-4 w-4" />All count
                    sessions</Link
                >
                <Heading
                    variant="small"
                    :title="session.reference_no"
                    :description="session.scope"
                />
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="editable"
                    variant="outline"
                    :disabled="busyAction !== ''"
                    @click="runAction('submit')"
                    ><Send aria-hidden="true" class="mr-2 h-4 w-4" />Submit for
                    Review</Button
                >
                <Button
                    v-if="can.review && submitted"
                    :disabled="busyAction !== ''"
                    @click="runAction('approve')"
                    ><CheckCircle2
                        aria-hidden="true"
                        class="mr-2 h-4 w-4"
                    />Post Approved Differences</Button
                >
                <Button
                    v-if="can.complete && session.status === 'approved'"
                    :disabled="busyAction !== ''"
                    @click="runAction('complete')"
                    >Complete Session</Button
                >
                <Button
                    v-if="
                        can.cancel &&
                        ['draft', 'in_progress', 'submitted'].includes(
                            session.status,
                        )
                    "
                    variant="destructive"
                    :disabled="busyAction !== ''"
                    @click="runAction('cancel')"
                    >Cancel Session</Button
                >
            </div>
        </div>

        <div
            v-if="errors.session || errors.counted_qty || errors.review_status"
            role="alert"
            class="rounded-lg border border-rose-500/30 bg-rose-500/10 p-3 text-sm text-rose-800 dark:text-rose-200"
        >
            {{ errors.session || errors.counted_qty || errors.review_status }}
        </div>

        <section
            aria-label="Count session summary"
            class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5"
        >
            <div class="rounded-xl border border-border/70 bg-card p-4">
                <p class="text-xs text-muted-foreground">Status</p>
                <p class="mt-1 font-semibold">{{ label(session.status) }}</p>
            </div>
            <div class="rounded-xl border border-border/70 bg-card p-4">
                <p class="text-xs text-muted-foreground">Counting date</p>
                <p class="mt-1 font-semibold">
                    {{ formatDate(session.counting_date) }}
                </p>
            </div>
            <div class="rounded-xl border border-border/70 bg-card p-4">
                <p class="text-xs text-muted-foreground">Assigned counter</p>
                <p class="mt-1 font-semibold">
                    {{ session.assigned_user.name }}
                </p>
            </div>
            <div class="rounded-xl border border-border/70 bg-card p-4">
                <p class="text-xs text-muted-foreground">Location</p>
                <p class="mt-1 font-semibold">
                    {{ session.location?.name ?? 'Not specified' }}
                </p>
            </div>
            <div class="rounded-xl border border-border/70 bg-card p-4">
                <p class="text-xs text-muted-foreground">Snapshot saved</p>
                <p class="mt-1 font-semibold">
                    {{ new Date(session.snapshot_at).toLocaleString() }}
                </p>
            </div>
        </section>

        <section aria-labelledby="count-lines-title" class="space-y-3">
            <div>
                <h2 id="count-lines-title" class="text-lg font-semibold">
                    Items to count
                </h2>
                <p class="text-sm text-muted-foreground">
                    Expected at count includes receipts and issues recorded
                    after the session snapshot and before each item was counted.
                </p>
            </div>
            <article
                v-for="line in session.lines"
                :key="line.id"
                class="rounded-xl border border-border/70 bg-card p-4 shadow-xs sm:p-5"
            >
                <div
                    class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-semibold">
                                {{ line.product.name }}
                            </h3>
                            <span
                                class="rounded-md bg-muted px-2 py-1 font-mono text-xs"
                                >{{
                                    line.asset?.tag_code ??
                                    line.stock_lot?.reference_no ??
                                    'No lot reference'
                                }}</span
                            >
                            <span
                                class="rounded-full bg-muted px-2.5 py-1 text-xs font-medium"
                                >{{
                                    line.asset ? 'Tagged asset' : 'Stock lot'
                                }}</span
                            >
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ line.product.sku
                            }}<template v-if="line.stock_lot?.expires_at">
                                · Expires
                                {{ line.stock_lot.expires_at }}</template
                            >
                        </p>
                    </div>
                    <dl
                        class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-4"
                    >
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Snapshot
                            </dt>
                            <dd class="font-mono font-semibold">
                                {{ line.expected_qty_snapshot }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Expected at count
                            </dt>
                            <dd class="font-mono font-semibold">
                                {{ line.expected_qty_at_count ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Counted
                            </dt>
                            <dd class="font-mono font-semibold">
                                {{ line.counted_qty ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Difference
                            </dt>
                            <dd
                                class="font-mono font-semibold"
                                :class="varianceClass(line.variance_qty)"
                            >
                                {{
                                    line.variance_qty === null
                                        ? '—'
                                        : line.variance_qty > 0
                                          ? `+${line.variance_qty}`
                                          : line.variance_qty
                                }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <form
                    v-if="editable"
                    class="mt-4 grid gap-3 border-t border-border/60 pt-4 md:grid-cols-[160px_1fr_auto] md:items-end"
                    @submit.prevent="saveCount(line)"
                >
                    <label class="grid gap-1.5 text-sm font-medium"
                        >Counted quantity
                        <input
                            v-model="countValues[line.id]"
                            type="number"
                            min="0"
                            :max="line.asset ? 1 : undefined"
                            required
                            inputmode="numeric"
                            class="min-h-11 rounded-md border border-input bg-background px-3 text-sm focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none sm:min-h-9"
                        />
                    </label>
                    <label class="grid gap-1.5 text-sm font-medium"
                        >Counting remarks
                        <input
                            v-model="countNotes[line.id]"
                            type="text"
                            placeholder="Optional note about this count"
                            class="min-h-11 rounded-md border border-input bg-background px-3 text-sm focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none sm:min-h-9"
                        />
                    </label>
                    <Button type="submit" :disabled="busyLine === line.id">{{
                        busyLine === line.id ? 'Saving…' : 'Save Count'
                    }}</Button>
                </form>

                <div
                    v-else
                    class="mt-4 flex flex-col gap-3 border-t border-border/60 pt-4 lg:flex-row lg:items-end lg:justify-between"
                >
                    <div class="text-sm">
                        <p>
                            <span class="text-muted-foreground"
                                >Recorded by:</span
                            >
                            {{ line.counted_by?.name ?? 'Not recorded' }}
                        </p>
                        <p>
                            <span class="text-muted-foreground">Review:</span>
                            {{ label(line.review_status)
                            }}<template v-if="line.reviewed_by">
                                by {{ line.reviewed_by.name }}</template
                            >
                        </p>
                        <p
                            v-if="line.adjustment_movement"
                            class="mt-1 text-emerald-700 dark:text-emerald-300"
                        >
                            Posted
                            {{
                                line.adjustment_movement.qty_delta > 0
                                    ? '+'
                                    : ''
                            }}{{ line.adjustment_movement.qty_delta }} units ({{
                                line.adjustment_movement.qty_before
                            }}
                            → {{ line.adjustment_movement.qty_after }}).
                        </p>
                    </div>
                    <form
                        v-if="
                            can.review && submitted && line.variance_qty !== 0
                        "
                        class="grid w-full gap-2 lg:max-w-xl lg:grid-cols-[1fr_auto]"
                        @submit.prevent
                    >
                        <label class="grid gap-1 text-sm font-medium"
                            >Review notes
                            <input
                                v-model="reviewNotes[line.id]"
                                type="text"
                                placeholder="Explain the review or recount request"
                                class="min-h-11 rounded-md border border-input bg-background px-3 text-sm sm:min-h-9"
                            />
                        </label>
                        <div class="flex flex-wrap items-end gap-2">
                            <Button
                                size="sm"
                                :disabled="busyLine === line.id"
                                @click="reviewLine(line, 'approved')"
                                ><CheckCircle2
                                    aria-hidden="true"
                                    class="mr-1 h-4 w-4"
                                />Approve</Button
                            >
                            <Button
                                size="sm"
                                variant="outline"
                                :disabled="busyLine === line.id"
                                @click="reviewLine(line, 'recount_requested')"
                                ><RotateCcw
                                    aria-hidden="true"
                                    class="mr-1 h-4 w-4"
                                />Request Recount</Button
                            >
                            <Button
                                size="sm"
                                variant="destructive"
                                :disabled="busyLine === line.id"
                                @click="reviewLine(line, 'rejected')"
                                ><XCircle
                                    aria-hidden="true"
                                    class="mr-1 h-4 w-4"
                                />Reject</Button
                            >
                        </div>
                    </form>
                </div>
            </article>
        </section>
    </div>
</template>
