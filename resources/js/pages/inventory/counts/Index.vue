<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ClipboardCheck, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
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
                <p
                    class="rounded-lg bg-sky-500/10 p-3 text-sm text-sky-800 dark:text-sky-200"
                >
                    Creating a session saves a system snapshot. Entering counts
                    will not change official stock until an authorized reviewer
                    approves the discrepancies.
                </p>
                <div
                    class="flex flex-col gap-2 rounded-xl border border-border/60 bg-muted/20 p-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <p class="text-sm font-medium">
                            Scan a product or asset code
                        </p>
                        <p class="text-xs text-muted-foreground">
                            A matching SKU or asset tag will be selected
                            automatically.
                        </p>
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
                    class="text-sm text-muted-foreground"
                >
                    {{ scanFeedback }}
                </p>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <label class="grid gap-1.5 text-sm font-medium"
                        >Counting date
                        <Input
                            v-model="form.counting_date"
                            type="date"
                            required
                        />
                        <InputError :message="form.errors.counting_date" />
                    </label>
                    <label class="grid gap-1.5 text-sm font-medium"
                        >Scope or area
                        <Input
                            v-model="form.scope"
                            placeholder="Example: Main supply room"
                            required
                        />
                        <InputError :message="form.errors.scope" />
                    </label>
                    <label class="grid gap-1.5 text-sm font-medium"
                        >Physical location
                        <select
                            v-model="form.location_id"
                            class="min-h-11 rounded-md border border-input bg-background px-3 text-sm sm:min-h-9"
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
                        <InputError :message="form.errors.location_id" />
                    </label>
                    <label class="grid gap-1.5 text-sm font-medium"
                        >Assigned counter
                        <select
                            v-model="form.assigned_to"
                            class="min-h-11 rounded-md border border-input bg-background px-3 text-sm sm:min-h-9"
                            required
                        >
                            <option value="" disabled>Select a person</option>
                            <option
                                v-for="user in users"
                                :key="user.id"
                                :value="String(user.id)"
                            >
                                {{ user.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.assigned_to" />
                    </label>
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
                class="grid gap-3 md:grid-cols-2 xl:grid-cols-3"
            >
                <Link
                    v-for="session in sessions.data"
                    :key="session.id"
                    :href="show(session.id)"
                    class="group rounded-xl border border-border/70 bg-card p-4 shadow-xs transition hover:border-primary/40 hover:shadow-sm focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-mono text-xs text-muted-foreground">
                                {{ session.reference_no }}
                            </p>
                            <h3 class="mt-1 font-semibold">
                                {{ session.scope }}
                            </h3>
                        </div>
                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-medium"
                            :class="statusClass(session.status)"
                            >{{ statusLabel(session.status) }}</span
                        >
                    </div>
                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Progress
                            </dt>
                            <dd class="font-medium">
                                {{ session.counted_lines_count }} of
                                {{ session.lines_count }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Differences
                            </dt>
                            <dd class="font-medium">
                                {{ session.discrepancy_lines_count }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Counter
                            </dt>
                            <dd>{{ session.assigned_user.name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Location
                            </dt>
                            <dd>
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
