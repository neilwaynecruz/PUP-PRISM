<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    Calendar,
    ClipboardCheck,
    Compass,
    Info,
    MapPin,
    Plus,
    ScanLine,
    User,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AdminCodeBadge from '@/components/admin/AdminCodeBadge.vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import QrScannerDialog from '@/components/inventory/QrScannerDialog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index, show, store } from '@/routes/inventory/counts';

type Person = { id: number; name: string; email: string };
type Location = { id: number; code: string; name: string };
type Product = { id: number; sku: string; name: string; countable_qty: number };
type Asset = {
    id: number;
    tag_code: string;
    status: string;
    product: { name: string };
    current_location: Location | null;
};
type Session = {
    id: number;
    reference_no: string;
    scope: string;
    counting_date: string;
    status: string;
    lines_count: number;
    counted_lines_count: number;
    discrepancy_lines_count: number;
    location: Location | null;
    creator: { name: string };
    assigned_user: { name: string };
};
type PageLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    sessions: { data: Session[]; links: PageLink[] };
    products: Product[];
    assets: Asset[];
    locations: Location[];
    users: Person[];
}>();

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

defineOptions({
    name: 'InventoryCountIndexPage',
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: index() },
            { title: 'Physical counts', href: index() },
        ],
    },
});

const form = useForm({
    counting_date: todayInManila(),
    scope: '',
    location_id: '',
    assigned_to: '',
    product_ids: [] as number[],
    asset_ids: [] as number[],
    remarks: '',
});
const productSearch = ref('');
const assetSearch = ref('');
const scanFeedback = ref('');

const filteredProducts = computed(() => {
    const query = productSearch.value.trim().toLocaleLowerCase();

    return props.products.filter((product) =>
        query === ''
            ? true
            : `${product.sku} ${product.name}`
                  .toLocaleLowerCase()
                  .includes(query),
    );
});

const filteredAssets = computed(() => {
    const query = assetSearch.value.trim().toLocaleLowerCase();

    return props.assets.filter((asset) => {
        const matchesLocation =
            form.location_id === '' ||
            asset.current_location?.id === Number(form.location_id);
        const matchesSearch =
            query === '' ||
            `${asset.tag_code} ${asset.product.name}`
                .toLocaleLowerCase()
                .includes(query);

        return matchesLocation && matchesSearch;
    });
});

function submit(): void {
    form.post(store().url, { preserveScroll: true });
}

function selectScannedItem(value: string): void {
    const scannedValue = value.trim().toLocaleLowerCase();
    const asset = props.assets.find(
        (candidate) => candidate.tag_code.toLocaleLowerCase() === scannedValue,
    );

    if (asset) {
        if (
            form.location_id !== '' &&
            asset.current_location?.id !== Number(form.location_id)
        ) {
            scanFeedback.value = `${asset.tag_code} is recorded at another location and was not selected.`;

            return;
        }

        if (!form.asset_ids.includes(asset.id)) {
            form.asset_ids.push(asset.id);
        }

        scanFeedback.value = `${asset.tag_code} was added to this count.`;

        return;
    }

    const product = props.products.find(
        (candidate) => candidate.sku.toLocaleLowerCase() === scannedValue,
    );

    if (product) {
        if (!form.product_ids.includes(product.id)) {
            form.product_ids.push(product.id);
        }

        scanFeedback.value = `${product.sku} was added to this count.`;

        return;
    }

    scanFeedback.value = `No countable product or asset matches “${value}”.`;
}

function statusLabel(value: string): string {
    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function statusClass(value: string): string {
    if (['approved', 'completed'].includes(value)) {
        return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    if (value === 'cancelled') {
        return 'bg-rose-500/10 text-rose-700 dark:text-rose-300';
    }

    if (value === 'submitted') {
        return 'bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    return 'bg-sky-500/10 text-sky-700 dark:text-sky-300';
}
</script>

<template>
    <Head title="Physical inventory counts" />
    <div
        class="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 sm:p-6 lg:p-8"
    >
        <Heading
            variant="small"
            title="Physical inventory counts"
            description="Record what is physically present, review any difference, and post approved stock corrections separately."
        />

        <details
            class="group rounded-2xl border border-border/70 bg-card shadow-sm"
            open
        >
            <summary
                class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 font-semibold sm:px-5"
            >
                <span class="flex items-center gap-2"
                    ><Plus aria-hidden="true" class="h-4 w-4" />Start a count
                    session</span
                >
                <span
                    class="text-xs font-normal text-muted-foreground group-open:hidden"
                    >Open form</span
                >
            </summary>
            <form
                class="grid gap-5 border-t border-border/60 p-4 sm:p-5"
                @submit.prevent="submit"
            >
                <div
                    class="flex items-start gap-2.5 rounded-lg border border-sky-500/20 bg-sky-500/10 p-3 text-xs text-sky-900 dark:text-sky-200"
                >
                    <Info
                        class="mt-0.5 h-4 w-4 shrink-0 text-sky-600 dark:text-sky-400"
                    />
                    <span
                        >Creating a session saves a system snapshot. Entering
                        counts will not change official stock until an
                        authorized reviewer approves the discrepancies.</span
                    >
                </div>
                <div
                    class="flex flex-col gap-3 rounded-xl border border-border/70 bg-muted/30 p-3.5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <ScanLine class="h-4 w-4" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-foreground">
                                Scan a product or asset code
                            </p>
                            <p class="text-xs text-muted-foreground">
                                A matching SKU or asset tag will be selected
                                automatically.
                            </p>
                        </div>
                    </div>
                    <QrScannerDialog
                        button-label="Scan Count Item"
                        title="Scan a product or asset"
                        description="Scan a PRISM product label or asset tag. You can still use the search fields below."
                        trigger-test-id="count-scanner-button"
                        @scanned="selectScannedItem"
                    />
                </div>
                <p
                    v-if="scanFeedback"
                    role="status"
                    aria-live="polite"
                    class="rounded-md border border-border/60 bg-muted/40 px-3 py-1.5 text-xs font-medium text-foreground"
                >
                    {{ scanFeedback }}
                </p>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="grid gap-1.5">
                        <label
                            for="count-date"
                            class="text-xs font-medium text-muted-foreground"
                            >Counting date</label
                        >
                        <div class="relative">
                            <Calendar
                                class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground/60"
                            />
                            <Input
                                id="count-date"
                                v-model="form.counting_date"
                                type="date"
                                required
                                class="h-10 rounded-lg pl-9 text-sm"
                            />
                        </div>
                        <InputError :message="form.errors.counting_date" />
                    </div>
                    <div class="grid gap-1.5">
                        <label
                            for="count-scope"
                            class="text-xs font-medium text-muted-foreground"
                            >Scope or area</label
                        >
                        <div class="relative">
                            <Compass
                                class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground/60"
                            />
                            <Input
                                id="count-scope"
                                v-model="form.scope"
                                placeholder="Example: Main supply room"
                                required
                                class="h-10 rounded-lg pl-9 text-sm"
                            />
                        </div>
                        <InputError :message="form.errors.scope" />
                    </div>
                    <div class="grid gap-1.5">
                        <label
                            for="count-location"
                            class="text-xs font-medium text-muted-foreground"
                            >Physical location</label
                        >
                        <div class="relative">
                            <MapPin
                                class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground/60"
                            />
                            <select
                                id="count-location"
                                v-model="form.location_id"
                                class="h-10 w-full rounded-lg border border-input bg-background pr-8 pl-9 text-sm text-foreground transition focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                            >
                                <option value="">No location selected</option>
                                <option
                                    v-for="location in locations"
                                    :key="location.id"
                                    :value="String(location.id)"
                                >
                                    {{ location.code }} — {{ location.name }}
                                </option>
                            </select>
                        </div>
                        <InputError :message="form.errors.location_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <label
                            for="count-assigned"
                            class="text-xs font-medium text-muted-foreground"
                            >Assigned counter</label
                        >
                        <div class="relative">
                            <User
                                class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground/60"
                            />
                            <select
                                id="count-assigned"
                                v-model="form.assigned_to"
                                class="h-10 w-full rounded-lg border border-input bg-background pr-8 pl-9 text-sm text-foreground transition focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                                required
                            >
                                <option value="" disabled>
                                    Select a person
                                </option>
                                <option
                                    v-for="user in users"
                                    :key="user.id"
                                    :value="String(user.id)"
                                >
                                    {{ user.name }}
                                </option>
                            </select>
                        </div>
                        <InputError :message="form.errors.assigned_to" />
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <fieldset class="rounded-xl border border-border/70 p-4">
                        <legend class="px-1 text-sm font-semibold">
                            Consumable products
                        </legend>
                        <p class="mb-3 text-xs text-muted-foreground">
                            Each stock lot with remaining quantity becomes a
                            separate count line.
                        </p>
                        <label
                            class="mb-3 grid gap-1 text-xs font-medium text-muted-foreground"
                        >
                            Search products
                            <Input
                                v-model="productSearch"
                                placeholder="Product name or SKU"
                                autocomplete="off"
                            />
                        </label>
                        <div class="max-h-56 space-y-2 overflow-y-auto pr-1">
                            <label
                                v-for="product in filteredProducts"
                                :key="product.id"
                                class="flex min-h-11 items-center gap-3 rounded-lg border border-border/60 px-3 py-2 text-sm hover:bg-muted/40"
                            >
                                <input
                                    v-model="form.product_ids"
                                    type="checkbox"
                                    :value="product.id"
                                    class="h-4 w-4 accent-primary"
                                />
                                <span class="min-w-0 flex-1"
                                    ><span class="block font-medium">{{
                                        product.name
                                    }}</span
                                    ><span class="text-xs text-muted-foreground"
                                        >{{ product.sku }} ·
                                        {{ product.countable_qty }} on
                                        hand</span
                                    ></span
                                >
                            </label>
                            <p
                                v-if="filteredProducts.length === 0"
                                class="py-5 text-center text-sm text-muted-foreground"
                            >
                                No consumable stock lots are available to count.
                            </p>
                        </div>
                    </fieldset>

                    <fieldset class="rounded-xl border border-border/70 p-4">
                        <legend class="px-1 text-sm font-semibold">
                            Tagged assets
                        </legend>
                        <p class="mb-3 text-xs text-muted-foreground">
                            Record each selected asset as present or missing.
                        </p>
                        <label
                            class="mb-3 grid gap-1 text-xs font-medium text-muted-foreground"
                        >
                            Search assets
                            <Input
                                v-model="assetSearch"
                                placeholder="Asset tag or product name"
                                autocomplete="off"
                            />
                        </label>
                        <div class="max-h-56 space-y-2 overflow-y-auto pr-1">
                            <label
                                v-for="asset in filteredAssets"
                                :key="asset.id"
                                class="flex min-h-11 items-center gap-3 rounded-lg border border-border/60 px-3 py-2 text-sm hover:bg-muted/40"
                            >
                                <input
                                    v-model="form.asset_ids"
                                    type="checkbox"
                                    :value="asset.id"
                                    class="h-4 w-4 accent-primary"
                                />
                                <span class="min-w-0 flex-1"
                                    ><span class="block font-medium"
                                        >{{ asset.tag_code }} ·
                                        {{ asset.product.name }}</span
                                    ><span
                                        class="text-xs text-muted-foreground"
                                        >{{
                                            asset.current_location?.name ??
                                            'No recorded location'
                                        }}</span
                                    ></span
                                >
                            </label>
                            <p
                                v-if="filteredAssets.length === 0"
                                class="py-5 text-center text-sm text-muted-foreground"
                            >
                                No active tagged assets are available.
                            </p>
                        </div>
                    </fieldset>
                </div>
                <InputError
                    :message="form.errors.product_ids || form.errors.asset_ids"
                />
                <label class="grid gap-1.5 text-sm font-medium"
                    >Remarks
                    <textarea
                        v-model="form.remarks"
                        rows="3"
                        placeholder="Optional instructions for the counting team"
                        class="rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                    />
                </label>
                <div class="flex justify-end">
                    <Button type="submit" :disabled="form.processing">{{
                        form.processing
                            ? 'Creating session…'
                            : 'Create Count Session'
                    }}</Button>
                </div>
            </form>
        </details>

        <section aria-labelledby="count-history-title" class="space-y-3">
            <div>
                <h2 id="count-history-title" class="text-lg font-semibold">
                    Count sessions
                </h2>
                <p class="text-sm text-muted-foreground">
                    Track counting progress, discrepancies, review, and
                    completion.
                </p>
            </div>
            <div
                v-if="sessions.data.length"
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
            >
                <Link
                    v-for="session in sessions.data"
                    :key="session.id"
                    :href="show(session.id)"
                    class="group flex flex-col justify-between rounded-xl border border-border/70 bg-card p-5 shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <AdminCodeBadge :value="session.reference_no" />
                            <span
                                class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-semibold"
                                :class="statusClass(session.status)"
                            >
                                <span
                                    class="h-1.5 w-1.5 rounded-full bg-current"
                                />
                                {{ statusLabel(session.status) }}
                            </span>
                        </div>
                        <h3
                            class="mt-3 line-clamp-1 font-semibold text-foreground transition-colors group-hover:text-primary"
                        >
                            {{ session.scope }}
                        </h3>
                        <p
                            class="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground"
                        >
                            <Calendar
                                class="h-3 w-3 text-muted-foreground/70"
                            />
                            {{ session.counting_date }}
                        </p>

                        <!-- Progress Bar -->
                        <div class="mt-4 space-y-1.5">
                            <div
                                class="flex justify-between text-xs text-muted-foreground"
                            >
                                <span>Progress</span>
                                <span class="font-medium text-foreground">
                                    {{ session.counted_lines_count }} /
                                    {{ session.lines_count }} lines
                                </span>
                            </div>
                            <div
                                class="h-1.5 w-full overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full bg-primary transition-all duration-300"
                                    :style="{
                                        width: `${session.lines_count > 0 ? Math.round((session.counted_lines_count / session.lines_count) * 100) : 0}%`,
                                    }"
                                />
                            </div>
                        </div>
                    </div>

                    <dl
                        class="mt-4 grid grid-cols-2 gap-2 border-t border-border/40 pt-3 text-xs"
                    >
                        <div>
                            <dt class="text-muted-foreground">Discrepancies</dt>
                            <dd
                                class="mt-0.5 font-semibold"
                                :class="
                                    session.discrepancy_lines_count > 0
                                        ? 'text-amber-600 dark:text-amber-400'
                                        : 'text-foreground'
                                "
                            >
                                {{ session.discrepancy_lines_count }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Location</dt>
                            <dd
                                class="mt-0.5 truncate font-medium text-foreground"
                            >
                                {{ session.location?.name ?? 'Not specified' }}
                            </dd>
                        </div>
                    </dl>
                </Link>
            </div>
            <div
                v-else
                class="rounded-xl border border-dashed border-border p-10 text-center"
            >
                <ClipboardCheck
                    aria-hidden="true"
                    class="mx-auto h-9 w-9 text-muted-foreground/60"
                />
                <h3 class="mt-3 font-semibold">No physical counts yet</h3>
                <p class="mt-1 text-sm text-muted-foreground">
                    Create a session when your team is ready to compare physical
                    items with PRISM records.
                </p>
            </div>
            <AdminPagination :links="sessions.links" />
        </section>
    </div>
</template>
