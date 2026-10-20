<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { MapPin, PackageSearch } from 'lucide-vue-next';
import { onBeforeUnmount, ref, watch } from 'vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import Heading from '@/components/Heading.vue';
import { Input } from '@/components/ui/input';
import { index, show } from '@/routes/inventory/assets';

type Location = { id: number; code: string; name: string };
type Asset = {
    id: number;
    tag_code: string;
    status: string;
    lifecycle_status: string;
    condition_status: string;
    product: { name: string; sku: string };
    position: { title: string; department: { name: string } | null } | null;
    current_location: Location | null;
};
type PageLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    assets: { data: Asset[]; links: PageLink[]; total: number };
    filters: {
        search: string;
        location_id: number | null;
        lifecycle_status: string;
    };
    locations: Location[];
}>();

defineOptions({
    name: 'InventoryAssetIndexPage',
    layout: { breadcrumbs: [{ title: 'Assets', href: index() }] },
});

const search = ref(props.filters.search ?? '');
const locationId = ref(
    props.filters.location_id ? String(props.filters.location_id) : '',
);
const lifecycleStatus = ref(props.filters.lifecycle_status ?? '');
let timer: number | undefined;

watch([search, locationId, lifecycleStatus], () => {
    window.clearTimeout(timer);
    timer = window.setTimeout(() => {
        router.get(
            index().url,
            {
                search: search.value || undefined,
                location_id: locationId.value || undefined,
                lifecycle_status: lifecycleStatus.value || undefined,
            },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    }, 250);
});
onBeforeUnmount(() => window.clearTimeout(timer));

function label(value: string): string {
    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function tone(value: string): string {
    if (['active', 'serviceable', 'Available'].includes(value)) {
        return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    if (['retired', 'Unserviceable'].includes(value)) {
        return 'bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    return 'bg-rose-500/10 text-rose-700 dark:text-rose-300';
}
</script>

<template>
    <Head title="Asset registry" />
    <div
        class="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 sm:p-6 lg:p-8"
    >
        <Heading
            variant="small"
            title="Asset registry"
            description="Find tagged university property, review its current location, and open its maintenance and lifecycle history."
        />

        <fieldset
            class="grid gap-3 rounded-xl border border-border/70 bg-card p-4 sm:grid-cols-3"
        >
            <legend class="sr-only">Filter assets</legend>
            <label class="grid gap-1 text-xs font-medium text-muted-foreground"
                >Search
                <Input
                    v-model="search"
                    placeholder="Enter an asset tag, product, or SKU"
                    autocomplete="off"
                />
            </label>
            <label class="grid gap-1 text-xs font-medium text-muted-foreground"
                >Location
                <select
                    v-model="locationId"
                    class="min-h-11 rounded-md border border-input bg-background px-3 text-sm sm:min-h-9"
                >
                    <option value="">All locations</option>
                    <option
                        v-for="location in locations"
                        :key="location.id"
                        :value="String(location.id)"
                    >
                        {{ location.code }} — {{ location.name }}
                    </option>
                </select>
            </label>
            <label class="grid gap-1 text-xs font-medium text-muted-foreground"
                >Lifecycle
                <select
                    v-model="lifecycleStatus"
                    class="min-h-11 rounded-md border border-input bg-background px-3 text-sm sm:min-h-9"
                >
                    <option value="">All lifecycle states</option>
                    <option value="active">Active</option>
                    <option value="retired">Retired</option>
                    <option value="disposed">Disposed</option>
                </select>
            </label>
        </fieldset>

        <div
            v-if="assets.data.length"
            class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
        >
            <Link
                v-for="asset in assets.data"
                :key="asset.id"
                :href="show(asset.id)"
                class="group rounded-xl border border-border/70 bg-card p-4 shadow-xs transition hover:border-primary/40 hover:shadow-sm focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-mono text-xs text-muted-foreground">
                            {{ asset.tag_code }}
                        </p>
                        <h2 class="mt-1 truncate font-semibold">
                            {{ asset.product.name }}
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            {{ asset.product.sku }}
                        </p>
                    </div>
                    <span
                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="tone(asset.lifecycle_status)"
                        >{{ label(asset.lifecycle_status) }}</span
                    >
                </div>
                <div
                    class="mt-4 flex items-start gap-2 rounded-lg bg-muted/40 p-3 text-sm"
                >
                    <MapPin
                        aria-hidden="true"
                        class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground"
                    /><span
                        ><strong class="font-medium">{{
                            asset.current_location?.name ??
                            'No recorded location'
                        }}</strong
                        ><span
                            v-if="asset.position"
                            class="block text-xs text-muted-foreground"
                            >Accountable to {{ asset.position.title }}</span
                        ></span
                    >
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <span
                        class="rounded-full px-2 py-1 text-xs"
                        :class="tone(asset.status)"
                        >{{ label(asset.status) }}</span
                    ><span
                        class="rounded-full px-2 py-1 text-xs"
                        :class="tone(asset.condition_status)"
                        >{{ label(asset.condition_status) }}</span
                    >
                </div>
            </Link>
        </div>
        <div
            v-else
            class="rounded-xl border border-dashed border-border p-12 text-center"
        >
            <PackageSearch
                aria-hidden="true"
                class="mx-auto h-10 w-10 text-muted-foreground/60"
            />
            <h2 class="mt-3 font-semibold">No assets found</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Try changing the search, location, or lifecycle filter.
            </p>
        </div>

        <AdminPagination :links="assets.links" />
    </div>
</template>
