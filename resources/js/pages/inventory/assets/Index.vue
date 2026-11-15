<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Activity,
    Boxes,
    MapPin,
    PackageSearch,
    RotateCcw,
    Search,
    SlidersHorizontal,
    Tag,
} from 'lucide-vue-next';
import {
    computed,
    defineAsyncComponent,
    onBeforeUnmount,
    ref,
    shallowRef,
    watch,
} from 'vue';
import type { Component } from 'vue';
import AdminCodeBadge from '@/components/admin/AdminCodeBadge.vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { shouldApplyDebouncedVisit } from '@/lib/inertiaNavigation';
import { index, show } from '@/routes/inventory/assets';

const QrScannerDialog = defineAsyncComponent(
    () => import('@/components/inventory/QrScannerDialog.vue'),
);

type Location = { id: number; code: string; name: string };
type Asset = {
    id: number;
    tag_code: string;
    serial_number: string | null;
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
const lookupWorkflow = ref<'maintenance' | 'transfer' | ''>('');
const ocrComponent = shallowRef<Component | null>(null);
const ocrOpen = ref(false);
const ocrLoading = ref(false);
const ocrLoadError = ref('');
const ocrTrigger = ref<InstanceType<typeof Button> | null>(null);
let activeOcrTrigger: HTMLElement | null = null;

async function openOcr(event?: MouseEvent): Promise<void> {
    if (ocrLoading.value) {
        return;
    }

    ocrLoading.value = true;
    activeOcrTrigger =
        event?.currentTarget instanceof HTMLElement
            ? event.currentTarget
            : null;
    ocrLoadError.value = '';

    try {
        ocrComponent.value ??= (
            await import('@/components/inventory/AssetOcrDialog.vue')
        ).default;
        ocrOpen.value = true;
    } catch {
        ocrLoadError.value =
            'The printed-tag reader could not load. Check your connection and reload the page to retry, or use manual asset search below.';
    } finally {
        ocrLoading.value = false;
    }
}

function restoreOcrFocus(): void {
    (activeOcrTrigger ?? ocrTrigger.value?.$el)?.focus();
}

function searchIdentifier(value: string): void {
    locationId.value = '';
    lifecycleStatus.value = '';
    search.value = value.trim();
}
const locationId = ref(
    props.filters.location_id ? String(props.filters.location_id) : '',
);
const lifecycleStatus = ref(props.filters.lifecycle_status ?? '');

const hasActiveFilters = computed(
    () =>
        search.value.trim() !== '' ||
        locationId.value !== '' ||
        lifecycleStatus.value !== '',
);

function resetFilters(): void {
    search.value = '';
    locationId.value = '';
    lifecycleStatus.value = '';
}

let timer: number | undefined;

function cancelPendingSearch(): void {
    window.clearTimeout(timer);
}

watch([search, locationId, lifecycleStatus], () => {
    window.clearTimeout(timer);
    timer = window.setTimeout(() => {
        if (!shouldApplyDebouncedVisit('/inventory/assets')) {
            return;
        }

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

function lifecycleTone(value: string): string {
    if (['active', 'serviceable', 'Available'].includes(value)) {
        return 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-300';
    }

    if (['retired', 'Unserviceable'].includes(value)) {
        return 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-300';
    }

    return 'border-rose-500/20 bg-rose-500/10 text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-300';
}

function statusTone(value: string): string {
    if (
        ['serviceable', 'available', 'active', 'operational'].includes(
            value.toLowerCase(),
        )
    ) {
        return 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    if (['in_use', 'assigned'].includes(value.toLowerCase())) {
        return 'border-blue-500/20 bg-blue-500/10 text-blue-700 dark:text-blue-300';
    }

    if (['maintenance', 'under_repair'].includes(value.toLowerCase())) {
        return 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    return 'border-muted bg-muted/60 text-muted-foreground';
}
</script>

<template>
    <Head title="Asset registry" />
    <div
        class="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 sm:p-6 lg:p-8"
        data-testid="assets-index-page"
    >
        <!-- Header -->
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <Heading
                        variant="small"
                        title="Asset registry"
                        description="Find tagged university property, review physical locations, and track maintenance and lifecycle history."
                    />
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    ref="ocrTrigger"
                    variant="outline"
                    class="min-h-11 w-full sm:w-auto"
                    :disabled="ocrLoading"
                    :aria-busy="ocrLoading"
                    @click="
                        lookupWorkflow = '';
                        openOcr($event);
                    "
                    >{{
                        ocrLoading ? 'Loading tag reader…' : 'Read printed tag'
                    }}</Button
                >
                <QrScannerDialog
                    button-label="Scan asset QR code"
                    title="Scan asset QR code"
                    @scanned="searchIdentifier"
                />
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-11 whitespace-normal"
                    :disabled="ocrLoading"
                    :aria-busy="ocrLoading"
                    @click="
                        lookupWorkflow = 'maintenance';
                        openOcr($event);
                    "
                    >Find maintenance asset from label</Button
                >
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-11 whitespace-normal"
                    :disabled="ocrLoading"
                    :aria-busy="ocrLoading"
                    @click="
                        lookupWorkflow = 'transfer';
                        openOcr($event);
                    "
                    >Find transfer asset from label</Button
                >
                <div
                    class="flex items-center gap-1.5 rounded-lg border border-border/60 bg-muted/20 px-3 py-1.5 text-xs text-muted-foreground"
                >
                    <Boxes class="h-3.5 w-3.5 text-primary" />
                    <span>Total assets:</span>
                    <strong class="font-semibold text-foreground">{{
                        assets.total
                    }}</strong>
                </div>
            </div>
        </div>

        <p
            v-if="ocrLoadError"
            role="alert"
            class="rounded-lg border border-destructive/30 bg-card p-3 text-sm text-destructive"
        >
            {{ ocrLoadError }}
        </p>

        <!-- Filter Bar -->
        <p
            v-if="lookupWorkflow"
            role="status"
            class="rounded-lg border border-border bg-card p-3 text-sm text-muted-foreground"
        >
            Review the matching asset below to open
            {{
                lookupWorkflow === 'maintenance'
                    ? 'maintenance'
                    : 'location transfers'
            }}. Its current location, status, and assignment come from the
            database; requests and approvals remain explicit.
        </p>
        <div class="rounded-xl border border-border/70 bg-card p-4 shadow-xs">
            <div class="mb-3 flex items-center justify-between">
                <div
                    class="flex items-center gap-2 text-xs font-semibold tracking-wide text-foreground uppercase"
                >
                    <SlidersHorizontal class="h-3.5 w-3.5 text-primary" />
                    <span>Search & Filters</span>
                    <span
                        v-if="hasActiveFilters"
                        class="rounded-full bg-primary/10 px-2 py-0.5 text-[0.65rem] font-bold text-primary"
                    >
                        Active
                    </span>
                </div>

                <Button
                    v-if="hasActiveFilters"
                    variant="ghost"
                    size="sm"
                    class="h-7 gap-1 px-2 text-xs text-muted-foreground hover:text-foreground"
                    @click="resetFilters"
                >
                    <RotateCcw class="h-3 w-3" />
                    Clear filters
                </Button>
            </div>

            <fieldset class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <legend class="sr-only">Filter assets</legend>
                <div class="grid gap-1">
                    <label
                        for="asset-search"
                        class="text-xs font-medium text-muted-foreground"
                    >
                        Search
                    </label>
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground/60"
                        />
                        <Input
                            id="asset-search"
                            v-model="search"
                            placeholder="Asset tag, serial number, product, variant, or SKU"
                            autocomplete="off"
                            class="h-10 rounded-lg pl-9 text-sm"
                        />
                    </div>
                </div>

                <div class="grid gap-1">
                    <label
                        for="asset-location"
                        class="text-xs font-medium text-muted-foreground"
                    >
                        Location
                    </label>
                    <div class="relative">
                        <MapPin
                            class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground/60"
                        />
                        <select
                            id="asset-location"
                            v-model="locationId"
                            class="h-10 w-full rounded-lg border border-input bg-background pr-8 pl-9 text-sm text-foreground transition focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
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
                    </div>
                </div>

                <div class="grid gap-1 sm:col-span-2 lg:col-span-1">
                    <label
                        for="asset-lifecycle"
                        class="text-xs font-medium text-muted-foreground"
                    >
                        Lifecycle state
                    </label>
                    <div class="relative">
                        <Activity
                            class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground/60"
                        />
                        <select
                            id="asset-lifecycle"
                            v-model="lifecycleStatus"
                            class="h-10 w-full rounded-lg border border-input bg-background pr-8 pl-9 text-sm text-foreground transition focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                        >
                            <option value="">All lifecycle states</option>
                            <option value="active">Active</option>
                            <option value="retired">Retired</option>
                            <option value="disposed">Disposed</option>
                        </select>
                    </div>
                </div>
            </fieldset>
        </div>

        <!-- Asset Cards Grid -->
        <div
            v-if="assets.data.length"
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
        >
            <Link
                v-for="asset in assets.data"
                :key="asset.id"
                :href="
                    lookupWorkflow
                        ? `${show.url(asset.id)}#${lookupWorkflow === 'maintenance' ? 'maintenance-title' : 'transfer-title'}`
                        : show(asset.id)
                "
                @click="cancelPendingSearch"
                class="group flex flex-col justify-between rounded-xl border border-border/70 bg-card p-5 shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <div>
                    <!-- Top row: Tag Code & Lifecycle Badge -->
                    <div class="flex items-center justify-between gap-2">
                        <AdminCodeBadge :value="asset.tag_code" />
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium"
                            :class="lifecycleTone(asset.lifecycle_status)"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-current" />
                            {{ label(asset.lifecycle_status) }}
                        </span>
                    </div>

                    <!-- Product identity -->
                    <div class="mt-3">
                        <h2
                            class="line-clamp-2 text-sm font-semibold text-foreground transition-colors group-hover:text-primary"
                        >
                            {{ asset.product.name }}
                        </h2>
                        <p
                            class="mt-0.5 flex items-center gap-1 font-mono text-xs text-muted-foreground"
                        >
                            <Tag class="h-3 w-3 text-muted-foreground/70" />
                            {{ asset.product.sku }}
                        </p>
                        <p
                            v-if="asset.serial_number"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            Serial:
                            <span class="font-mono">{{
                                asset.serial_number
                            }}</span>
                        </p>
                    </div>

                    <!-- Location & Accountability panel -->
                    <div
                        class="mt-4 flex items-start gap-2.5 rounded-lg border border-border/50 bg-muted/30 p-3 text-xs text-foreground"
                    >
                        <MapPin
                            aria-hidden="true"
                            class="mt-0.5 h-3.5 w-3.5 shrink-0 text-primary"
                        />
                        <div class="min-w-0 flex-1">
                            <strong
                                class="block truncate font-medium text-foreground"
                            >
                                {{
                                    asset.current_location?.name ??
                                    'No recorded location'
                                }}
                            </strong>
                            <span
                                v-if="asset.position"
                                class="mt-0.5 block truncate text-[0.7rem] text-muted-foreground"
                            >
                                Accountable: {{ asset.position.title }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Footer status tags -->
                <div
                    class="mt-4 flex flex-wrap items-center gap-1.5 border-t border-border/40 pt-3"
                >
                    <span
                        class="rounded-md border px-2 py-0.5 text-[0.7rem] font-medium"
                        :class="statusTone(asset.status)"
                    >
                        {{ label(asset.status) }}
                    </span>
                    <span
                        class="rounded-md border border-border/60 bg-muted/40 px-2 py-0.5 text-[0.7rem] font-medium text-muted-foreground"
                    >
                        {{ label(asset.condition_status) }}
                    </span>
                </div>
            </Link>
        </div>

        <!-- Empty state -->
        <div
            v-else
            class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border/80 bg-card p-12 text-center"
        >
            <div
                class="flex h-14 w-14 items-center justify-center rounded-full bg-muted/50"
            >
                <PackageSearch
                    aria-hidden="true"
                    class="h-7 w-7 text-muted-foreground/60"
                />
            </div>
            <h2 class="mt-4 text-base font-semibold text-foreground">
                No assets found
            </h2>
            <p class="mt-1 max-w-sm text-xs text-muted-foreground">
                We couldn't find any assets matching your current search or
                filter criteria.
            </p>
            <Button
                v-if="hasActiveFilters"
                variant="outline"
                size="sm"
                class="mt-4 gap-1.5 rounded-lg text-xs"
                @click="resetFilters"
            >
                <RotateCcw class="h-3.5 w-3.5" />
                Clear all filters
            </Button>
        </div>

        <AdminPagination :links="assets.links" />
        <component
            :is="ocrComponent"
            v-if="ocrComponent"
            v-model:open="ocrOpen"
            @search="searchIdentifier"
            @closed="restoreOcrFocus"
        />
    </div>
</template>
