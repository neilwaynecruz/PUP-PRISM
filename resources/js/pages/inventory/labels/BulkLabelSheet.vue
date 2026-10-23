<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Download,
    Eye,
    Leaf,
    Printer,
    SlidersHorizontal,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as productsIndex } from '@/routes/inventory/products';
import { pdf as productLabelsPdf } from '@/routes/inventory/products/labels';

type LabelProduct = {
    id: number;
    sku: string;
    name: string;
    type: 'consumable' | 'asset';
    category: string | null;
    qr_svg: string;
};

const props = defineProps<{
    labels: LabelProduct[];
    total_products: number;
    initial_config: {
        copies: number;
        paper_size: 'a4' | 'letter';
        grid_preset:
            | 'compact_30'
            | 'standard_24'
            | 'medium_18'
            | 'large_12'
            | 'single_1';
        show_sku: boolean;
        show_name: boolean;
        show_category: boolean;
    };
    filters?: {
        search: string | null;
        category_id: number | null;
        type: string | null;
    };
}>();

defineOptions({
    name: 'BulkLabelSheetPage',
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: productsIndex() },
            { title: 'Products', href: productsIndex() },
            { title: 'Bulk QR Labels', href: '#' },
        ],
    },
});

const copies = ref(props.initial_config.copies ?? 1);
const paperSize = ref<'a4' | 'letter'>(props.initial_config.paper_size ?? 'a4');
const gridPreset = ref<
    'compact_30' | 'standard_24' | 'medium_18' | 'large_12' | 'single_1'
>(props.initial_config.grid_preset ?? 'standard_24');
const showSku = ref(props.initial_config.show_sku ?? true);
const showName = ref(props.initial_config.show_name ?? true);
const showCategory = ref(props.initial_config.show_category ?? false);
const maxCopies = computed(() =>
    Math.max(1, Math.min(50, Math.floor(500 / props.labels.length))),
);

watch(copies, (value) => {
    if (!Number.isFinite(value)) {
        copies.value = 1;

        return;
    }

    copies.value = Math.min(maxCopies.value, Math.max(1, Math.trunc(value)));
});

const gridPresets = [
    {
        id: 'compact_30',
        name: 'Compact (30 / sheet)',
        description: '3 columns × 10 rows • Maximum paper savings',
        cols: 3,
        rows: 10,
        perSheet: 30,
    },
    {
        id: 'standard_24',
        name: 'Standard (24 / sheet)',
        description: '3 columns × 8 rows • Balanced size and readability',
        cols: 3,
        rows: 8,
        perSheet: 24,
    },
    {
        id: 'medium_18',
        name: 'Medium (18 / sheet)',
        description: '3 columns × 6 rows • Higher QR readability',
        cols: 3,
        rows: 6,
        perSheet: 18,
    },
    {
        id: 'large_12',
        name: 'Large (12 / sheet)',
        description: '2 columns × 6 rows • Ideal for asset property tags',
        cols: 2,
        rows: 6,
        perSheet: 12,
    },
    {
        id: 'single_1',
        name: 'Single (1 / sheet)',
        description: '1 label per sheet • Full-page placard',
        cols: 1,
        rows: 1,
        perSheet: 1,
    },
] as const;

const activePreset = computed(() => {
    return gridPresets.find((p) => p.id === gridPreset.value) ?? gridPresets[1];
});

const activeLayout = computed(() => {
    const isLetter = paperSize.value === 'letter';

    switch (gridPreset.value) {
        case 'compact_30':
            return {
                marginMm: 6,
                gapMm: 1,
                cellHeightMm: isLetter ? 25 : 26,
            };
        case 'standard_24':
            return {
                marginMm: 8,
                gapMm: 2,
                cellHeightMm: isLetter ? 30 : 32,
            };
        case 'medium_18':
        case 'large_12':
            return {
                marginMm: 8,
                gapMm: 2,
                cellHeightMm: isLetter ? 41 : 42,
            };
        case 'single_1':
            return { marginMm: 20, gapMm: 0, cellHeightMm: 220 };
        default:
            return { marginMm: 8, gapMm: 2, cellHeightMm: 32 };
    }
});

let printPageStyle: HTMLStyleElement | null = null;

function updatePrintPageStyle(): void {
    if (!printPageStyle) {
        return;
    }

    printPageStyle.textContent = `@media print { @page { size: ${paperSize.value} portrait; margin: ${activeLayout.value.marginMm}mm; } }`;
}

onMounted(() => {
    printPageStyle = document.createElement('style');
    printPageStyle.dataset.productLabelPrintLayout = 'true';
    document.head.appendChild(printPageStyle);
    updatePrintPageStyle();
});

watch([paperSize, gridPreset], updatePrintPageStyle);

onBeforeUnmount(() => {
    printPageStyle?.remove();
    printPageStyle = null;
});

// Build flat array of items based on copies
const expandedLabels = computed(() => {
    const list: LabelProduct[] = [];
    const count = Math.max(1, Math.min(maxCopies.value, copies.value));

    for (const label of props.labels) {
        for (let i = 0; i < count; i++) {
            list.push(label);
        }
    }

    return list;
});

const totalLabelsCount = computed(() => expandedLabels.value.length);

const sheetsCount = computed(() => {
    const perSheet = activePreset.value.perSheet;

    return Math.ceil(totalLabelsCount.value / perSheet) || 1;
});

// Group labels by sheet
const paginatedSheets = computed(() => {
    const perSheet = activePreset.value.perSheet;
    const sheets: LabelProduct[][] = [];

    for (let i = 0; i < expandedLabels.value.length; i += perSheet) {
        sheets.push(expandedLabels.value.slice(i, i + perSheet));
    }

    return sheets.length > 0 ? sheets : [[]];
});

const paperSavedSheets = computed(() => {
    const singleSheets = totalLabelsCount.value;
    const bulkSheets = sheetsCount.value;

    return Math.max(0, singleSheets - bulkSheets);
});

function handlePrint(): void {
    window.print();
}

function handleDownloadPdf(): void {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = productLabelsPdf.url();

    // CSRF Token
    const csrfToken = (
        document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement
    )?.content;

    if (csrfToken) {
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = csrfToken;
        form.appendChild(csrfInput);
    }

    const appendField = (name: string, value: string) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    };

    appendField('copies', String(copies.value));
    appendField('paper_size', paperSize.value);
    appendField('grid_preset', gridPreset.value);
    appendField('show_sku', showSku.value ? '1' : '0');
    appendField('show_name', showName.value ? '1' : '0');
    appendField('show_category', showCategory.value ? '1' : '0');

    for (const label of props.labels) {
        appendField('product_ids[]', String(label.id));
    }

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
</script>

<template>
    <Head title="Bulk QR Labels & Printable Sheets" />

    <div class="flex flex-col gap-6 p-4 sm:p-6 print:p-0">
        <!-- Top Toolbar Header (Hidden when printing) -->
        <div
            class="flex flex-col gap-4 border-b border-border/60 pb-5 sm:flex-row sm:items-center sm:justify-between print:hidden"
        >
            <div>
                <div class="flex items-center gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-8 gap-1.5 px-2.5 text-xs text-muted-foreground"
                        as-child
                    >
                        <Link :href="productsIndex()">
                            <ArrowLeft class="h-3.5 w-3.5" />
                            <span>Back to Products</span>
                        </Link>
                    </Button>
                    <Badge
                        variant="outline"
                        class="text-xs font-normal text-muted-foreground"
                    >
                        Phase 7 Workflow Optimization
                    </Badge>
                </div>
                <h1
                    class="mt-2 text-2xl font-bold tracking-tight text-foreground"
                >
                    Bulk QR Label Printing
                </h1>
                <p class="mt-1 text-xs text-muted-foreground">
                    Paper-saving multi-label sheet generator. Preview your
                    sheets, adjust copy count and layouts, or download a
                    combined PDF.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <Button
                    variant="outline"
                    size="sm"
                    class="h-9 gap-2 rounded-lg text-xs font-semibold"
                    @click="handleDownloadPdf"
                >
                    <Download class="h-4 w-4 text-primary" />
                    <span>Download Combined PDF</span>
                </Button>
                <Button
                    size="sm"
                    class="h-9 gap-2 rounded-lg text-xs font-semibold shadow-sm"
                    @click="handlePrint"
                >
                    <Printer class="h-4 w-4" />
                    <span>Print Labels</span>
                </Button>
            </div>
        </div>

        <!-- Layout Controls and Paper-Saving Metrics Bar (Hidden in Print) -->
        <div class="grid gap-4 lg:grid-cols-[1fr_340px] print:hidden">
            <!-- Sheet Configuration Controls -->
            <div class="rounded-xl border border-border/60 bg-card p-4 sm:p-5">
                <div
                    class="flex items-center gap-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    <SlidersHorizontal class="h-4 w-4 text-primary" />
                    <span>Sheet Layout & Preferences</span>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2 md:grid-cols-4">
                    <!-- Grid Preset -->
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-medium"
                            >Sheet arrangement</Label
                        >
                        <select
                            v-model="gridPreset"
                            class="h-9 rounded-lg border border-border/60 bg-background px-3 text-xs font-medium text-foreground transition focus:ring-1 focus:ring-ring focus:outline-none"
                        >
                            <option
                                v-for="preset in gridPresets"
                                :key="preset.id"
                                :value="preset.id"
                            >
                                {{ preset.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Paper Size -->
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-medium">Paper format</Label>
                        <select
                            v-model="paperSize"
                            class="h-9 rounded-lg border border-border/60 bg-background px-3 text-xs font-medium text-foreground transition focus:ring-1 focus:ring-ring focus:outline-none"
                        >
                            <option value="a4">A4 (210 × 297 mm)</option>
                            <option value="letter">Letter (8.5 × 11 in)</option>
                        </select>
                    </div>

                    <!-- Copies per product -->
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-medium"
                            >Copies per item</Label
                        >
                        <Input
                            v-model.number="copies"
                            type="number"
                            min="1"
                            :max="maxCopies"
                            class="h-9 rounded-lg text-xs font-semibold"
                        />
                        <p class="text-[11px] text-muted-foreground">
                            Maximum {{ maxCopies }} per product for this batch.
                        </p>
                    </div>

                    <!-- Label Content Toggles -->
                    <div class="grid gap-2 sm:col-span-2 md:col-span-1">
                        <Label class="text-xs font-medium"
                            >Included fields</Label
                        >
                        <div class="flex flex-col gap-1.5">
                            <label
                                class="flex cursor-pointer items-center gap-2 text-xs font-medium"
                            >
                                <Checkbox
                                    aria-label="Include product SKU"
                                    :model-value="showSku"
                                    @update:model-value="
                                        (value) => (showSku = value === true)
                                    "
                                />
                                <span>Product SKU</span>
                            </label>
                            <label
                                class="flex cursor-pointer items-center gap-2 text-xs font-medium"
                            >
                                <Checkbox
                                    aria-label="Include product name"
                                    :model-value="showName"
                                    @update:model-value="
                                        (value) => (showName = value === true)
                                    "
                                />
                                <span>Product Name</span>
                            </label>
                            <label
                                class="flex cursor-pointer items-center gap-2 text-xs font-medium"
                            >
                                <Checkbox
                                    aria-label="Include product category"
                                    :model-value="showCategory"
                                    @update:model-value="
                                        (value) =>
                                            (showCategory = value === true)
                                    "
                                />
                                <span>Category</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Efficiency & Sustainability Summary Card -->
            <div
                class="flex flex-col justify-between rounded-xl border border-emerald-500/20 bg-emerald-50/50 p-4 sm:p-5 dark:border-emerald-950 dark:bg-emerald-950/20"
            >
                <div>
                    <div
                        class="flex items-center gap-2 text-xs font-semibold text-emerald-800 dark:text-emerald-300"
                    >
                        <Leaf class="h-4 w-4" />
                        <span>Sustainability & Resource Savings</span>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-3 text-left">
                        <div>
                            <div class="text-[11px] text-muted-foreground">
                                Total Labels
                            </div>
                            <div class="text-lg font-bold text-foreground">
                                {{ totalLabelsCount }}
                            </div>
                        </div>
                        <div>
                            <div class="text-[11px] text-muted-foreground">
                                Pages Needed
                            </div>
                            <div
                                class="text-lg font-bold text-emerald-700 dark:text-emerald-400"
                            >
                                {{ sheetsCount }}
                                {{ sheetsCount === 1 ? 'page' : 'pages' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="mt-3 border-t border-emerald-200/50 pt-2 text-[11px] text-emerald-800 dark:border-emerald-800/40 dark:text-emerald-300"
                >
                    <span class="font-semibold">Paper Saved:</span>
                    {{ paperSavedSheets }}
                    {{ paperSavedSheets === 1 ? 'sheet' : 'sheets' }} compared
                    to printing single-product labels!
                </div>
            </div>
        </div>

        <!-- Live Print Preview Sheets -->
        <div class="print:block">
            <div class="mb-3 flex items-center justify-between print:hidden">
                <div
                    class="flex items-center gap-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    <Eye class="h-3.5 w-3.5 text-primary" />
                    <span
                        >Print Sheet Preview ({{ sheetsCount }}
                        {{ sheetsCount === 1 ? 'Sheet' : 'Sheets' }})</span
                    >
                </div>
                <div class="text-xs text-muted-foreground">
                    Paper size:
                    <span class="font-medium text-foreground uppercase">{{
                        paperSize
                    }}</span>
                    • Layout:
                    <span class="font-medium text-foreground">{{
                        activePreset.name
                    }}</span>
                </div>
            </div>

            <!-- Sheets Container -->
            <div class="space-y-8 print:space-y-0">
                <div
                    v-for="(sheet, sheetIndex) in paginatedSheets"
                    :key="sheetIndex"
                    class="sheet-page mx-auto overflow-hidden rounded-xl border border-border/80 bg-white p-6 shadow-sm dark:bg-white dark:text-black print:m-0 print:rounded-none print:border-none print:p-0 print:shadow-none"
                    :style="{
                        width: paperSize === 'a4' ? '210mm' : '8.5in',
                        maxWidth: '100%',
                        minHeight: paperSize === 'a4' ? '297mm' : '11in',
                        padding: `${activeLayout.marginMm}mm`,
                    }"
                >
                    <!-- Sheet grid -->
                    <div
                        class="grid gap-2"
                        :style="{
                            gridTemplateColumns: `repeat(${activePreset.cols}, minmax(0, 1fr))`,
                            gap: `${activeLayout.gapMm}mm`,
                        }"
                    >
                        <div
                            v-for="(item, itemIndex) in sheet"
                            :key="`${sheetIndex}-${itemIndex}`"
                            class="label-card flex flex-col items-center justify-center rounded-lg border border-dashed border-slate-300 bg-white p-2 text-center transition dark:border-slate-300 dark:bg-white dark:text-black"
                            :style="{
                                height: `${activeLayout.cellHeightMm}mm`,
                            }"
                        >
                            <div
                                v-if="showName"
                                class="w-full truncate text-[10px] font-bold text-slate-900"
                                :title="item.name"
                            >
                                {{ item.name }}
                            </div>

                            <div
                                class="my-1 flex items-center justify-center"
                                :style="{
                                    maxWidth:
                                        gridPreset === 'compact_30'
                                            ? '54px'
                                            : gridPreset === 'medium_18'
                                              ? '86px'
                                              : gridPreset === 'large_12'
                                                ? '94px'
                                                : gridPreset === 'single_1'
                                                  ? '240px'
                                                  : '72px',
                                }"
                                v-html="item.qr_svg"
                            />

                            <div
                                v-if="showSku"
                                class="font-mono text-[9px] font-semibold tracking-wider text-slate-700"
                            >
                                {{ item.sku }}
                            </div>

                            <div
                                v-if="showCategory && item.category"
                                class="text-[8px] font-medium tracking-tight text-slate-500 uppercase"
                            >
                                {{ item.category }}
                            </div>

                            <div
                                class="mt-0.5 text-[7px] font-bold tracking-widest text-[#880000] uppercase"
                            >
                                PUP PRISM
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media print {
    body {
        background-color: white !important;
        color: black !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .sheet-page {
        min-height: 100vh;
        max-width: 100% !important;
        padding: 0 !important;
        box-shadow: none !important;
        border: none !important;
    }

    .sheet-page + .sheet-page {
        page-break-before: always;
        break-before: page;
    }

    .label-card {
        page-break-inside: avoid;
        break-inside: avoid;
    }
}
</style>
