<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import {
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    Legend,
    LinearScale,
    Tooltip,
} from 'chart.js';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

Chart.register(
    BarController,
    BarElement,
    CategoryScale,
    Legend,
    LinearScale,
    Tooltip,
);

type Alert = { id: number; type: string; message: string; detected_at: string };

const props = defineProps<{
    alerts: Alert[];
    lowStock: { id: number; sku: string; name: string; category: string | null; on_hand_qty: number | null; reorder_threshold: number }[];
    unserviceableAssets: { id: number; tag_code: string; status: string; name: string | null }[];
    assetStatusCounts: { labels: string[]; data: number[] };
    exportUrls: { assetConditionsCsv: string; assetConditionsPdf: string } | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const page = usePage();
const roles = computed<string[]>(() => page.props.auth.roles ?? []);
const isAdmin = computed(() => roles.value.includes('Admin'));

const assetStatusCanvas = ref<HTMLCanvasElement | null>(null);
let assetStatusChart: Chart<'bar', number[], string> | null = null;

const chartColors = [
    'hsl(222 65% 52%)',
    'hsl(38 95% 55%)',
    'hsl(152 65% 45%)',
    'hsl(205 90% 55%)',
    'hsl(260 70% 60%)',
    'hsl(345 80% 58%)',
    'hsl(43 90% 55%)',
    'hsl(170 65% 45%)',
];

const chartHoverColors = [
    'hsl(222 65% 62%)',
    'hsl(38 95% 65%)',
    'hsl(152 65% 55%)',
    'hsl(205 90% 65%)',
    'hsl(260 70% 70%)',
    'hsl(345 80% 68%)',
    'hsl(43 90% 65%)',
    'hsl(170 65% 55%)',
];

function getCssVar(name: string): string {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || '#000';
}

onMounted(() => {
    if (!isAdmin.value || !assetStatusCanvas.value) {
        return;
    }

    const textColor = getCssVar('--foreground');
    const gridColor = getCssVar('--border');
    const mutedColor = getCssVar('--muted-foreground');

    const barColors = props.assetStatusCounts.labels.map((_, i) => chartColors[i % chartColors.length]);
    const barHoverColors = props.assetStatusCounts.labels.map((_, i) => chartHoverColors[i % chartHoverColors.length]);

    assetStatusChart = new Chart(assetStatusCanvas.value, {
        type: 'bar',
        data: {
            labels: props.assetStatusCounts.labels,
            datasets: [
                {
                    label: 'Assets',
                    data: props.assetStatusCounts.data,
                    backgroundColor: barColors,
                    hoverBackgroundColor: barHoverColors,
                    borderRadius: 8,
                    borderSkipped: false,
                    barPercentage: 0.6,
                    categoryPercentage: 0.8,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 1200,
                easing: 'easeOutQuart',
                delay: (context) => {
                    let delay = 0;
                    if (context.type === 'data' && context.mode === 'default') {
                        delay = context.dataIndex * 150 + context.datasetIndex * 100;
                    }
                    return delay;
                },
            },
            transitions: {
                active: {
                    animation: {
                        duration: 400,
                    },
                },
            },
            plugins: {
                legend: {
                    display: false,
                },
                tooltip: {
                    backgroundColor: getCssVar('--card'),
                    titleColor: textColor,
                    bodyColor: textColor,
                    borderColor: gridColor,
                    borderWidth: 1,
                    cornerRadius: 8,
                    padding: 12,
                    displayColors: true,
                    boxPadding: 4,
                    callbacks: {
                        label: (context) => ` ${context.parsed.y} assets`,
                    },
                },
            },
            scales: {
                x: {
                    ticks: {
                        color: mutedColor,
                        font: {
                            family: "'Outfit', sans-serif",
                            size: 11,
                            weight: '500',
                        },
                    },
                    grid: {
                        display: false,
                    },
                    border: {
                        display: false,
                    },
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: mutedColor,
                        font: {
                            family: "'Outfit', sans-serif",
                            size: 11,
                        },
                        padding: 8,
                    },
                    grid: {
                        color: gridColor,
                        drawBorder: false,
                        lineWidth: 1,
                    },
                    border: {
                        display: false,
                    },
                },
            },
            interaction: {
                mode: 'index',
                intersect: false,
            },
            onHover: (event, activeElements) => {
                if (event.native?.target instanceof HTMLElement) {
                    event.native.target.style.cursor = activeElements.length > 0 ? 'pointer' : 'default';
                }
            },
        },
    });
});

onBeforeUnmount(() => {
    assetStatusChart?.destroy();
    assetStatusChart = null;
});
</script>

<template>
    <Head title="Dashboard" />

    <div
        data-testid="dashboard-page"
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 sm:p-6"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <Heading
                variant="small"
                title="Dashboard"
                description="Overview and decision support."
            />

            <div v-if="props.exportUrls" class="flex flex-wrap gap-2">
                <Button variant="outline" size="sm" as-child class="rounded-lg border-dashed">
                    <a :href="props.exportUrls.assetConditionsCsv">
                        <span class="mr-1.5 inline-block h-1.5 w-1.5 rounded-full bg-primary" />Asset report CSV
                    </a>
                </Button>
                <Button variant="outline" size="sm" as-child class="rounded-lg border-dashed">
                    <a :href="props.exportUrls.assetConditionsPdf">
                        <span class="mr-1.5 inline-block h-1.5 w-1.5 rounded-full bg-primary" />Asset report PDF
                    </a>
                </Button>
            </div>
        </div>

        <!-- Quick Stats Row -->
        <div v-if="isAdmin" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="group relative overflow-hidden rounded-xl border border-border/60 bg-card p-5 shadow-sm transition-all duration-300 hover:shadow-md hover:border-primary/20">
                <div class="absolute right-3 top-3 h-2 w-2 rounded-full bg-primary/40 group-hover:bg-primary transition-colors" />
                <div class="text-xs font-medium uppercase tracking-wider text-muted-foreground/70">Assets</div>
                <div class="mt-2 font-display text-2xl font-bold text-foreground">{{ props.assetStatusCounts.data.reduce((a, b) => a + b, 0) }}</div>
                <div class="mt-1 text-xs text-muted-foreground">Total tracked</div>
            </div>
            <div class="group relative overflow-hidden rounded-xl border border-border/60 bg-card p-5 shadow-sm transition-all duration-300 hover:shadow-md hover:border-primary/20">
                <div class="absolute right-3 top-3 h-2 w-2 rounded-full bg-amber-500/40 group-hover:bg-amber-500 transition-colors" />
                <div class="text-xs font-medium uppercase tracking-wider text-muted-foreground/70">Low Stock</div>
                <div class="mt-2 font-display text-2xl font-bold text-foreground">{{ lowStock.length }}</div>
                <div class="mt-1 text-xs text-muted-foreground">Consumables below threshold</div>
            </div>
            <div class="group relative overflow-hidden rounded-xl border border-border/60 bg-card p-5 shadow-sm transition-all duration-300 hover:shadow-md hover:border-primary/20">
                <div class="absolute right-3 top-3 h-2 w-2 rounded-full bg-rose-500/40 group-hover:bg-rose-500 transition-colors" />
                <div class="text-xs font-medium uppercase tracking-wider text-muted-foreground/70">Unserviceable</div>
                <div class="mt-2 font-display text-2xl font-bold text-foreground">{{ unserviceableAssets.length }}</div>
                <div class="mt-1 text-xs text-muted-foreground">Assets needing attention</div>
            </div>
            <div class="group relative overflow-hidden rounded-xl border border-border/60 bg-card p-5 shadow-sm transition-all duration-300 hover:shadow-md hover:border-primary/20">
                <div class="absolute right-3 top-3 h-2 w-2 rounded-full bg-emerald-500/40 group-hover:bg-emerald-500 transition-colors" />
                <div class="text-xs font-medium uppercase tracking-wider text-muted-foreground/70">Alerts</div>
                <div class="mt-2 font-display text-2xl font-bold text-foreground">{{ alerts.length }}</div>
                <div class="mt-1 text-xs text-muted-foreground">Active system alerts</div>
            </div>
        </div>

        <div v-if="isAdmin" class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <div class="text-sm font-semibold tracking-tight">Unserviceable / Condemned assets</div>
                    <div class="h-1.5 w-1.5 rounded-full bg-primary/60" />
                </div>
                <div class="h-64">
                    <canvas ref="assetStatusCanvas" />
                </div>
            </div>

            <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <div class="text-sm font-semibold tracking-tight">Low-stock consumables</div>
                    <div class="h-1.5 w-1.5 rounded-full bg-amber-500/60" />
                </div>
                <div v-if="lowStock.length === 0" class="text-sm text-muted-foreground">
                    No low-stock items found.
                </div>
                <ul v-else class="space-y-3 text-sm">
                    <li v-for="p in lowStock" :key="p.id" class="flex items-center justify-between gap-3 rounded-lg border border-border/40 p-3 transition-colors hover:bg-muted/40">
                        <div class="min-w-0">
                            <div class="truncate font-medium">{{ p.name }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ p.category ?? '—' }} &middot; <span class="font-mono text-[11px]">{{ p.sku }}</span>
                            </div>
                        </div>
                        <div class="shrink-0 text-right">
                            <div class="font-mono text-xs font-semibold">{{ p.on_hand_qty ?? 0 }} <span class="font-sans font-normal text-muted-foreground">/ {{ p.reorder_threshold ?? 0 }}</span></div>
                            <div class="text-[10px] uppercase tracking-wider text-muted-foreground/70">on hand / threshold</div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <div v-if="isAdmin" class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <div class="text-sm font-semibold tracking-tight">Unserviceable / Condemned assets</div>
                <div class="h-1.5 w-1.5 rounded-full bg-rose-500/60" />
            </div>
            <div v-if="unserviceableAssets.length === 0" class="text-sm text-muted-foreground">
                No assets in these statuses.
            </div>
            <ul v-else class="space-y-2 text-sm">
                <li
                    v-for="a in unserviceableAssets"
                    :key="a.id"
                    class="flex items-center justify-between gap-3 rounded-lg border border-border/40 p-3 transition-colors hover:bg-muted/40"
                >
                    <span class="font-medium">{{ a.name ?? 'Asset' }}</span>
                    <span class="text-xs text-muted-foreground">
                        {{ a.status }} &middot; <span class="font-mono text-[11px]">{{ a.tag_code }}</span>
                    </span>
                </li>
            </ul>
        </div>

        <div v-if="isAdmin" class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <div class="text-sm font-semibold tracking-tight">System Alerts</div>
                <div class="h-1.5 w-1.5 rounded-full bg-emerald-500/60" />
            </div>
            <div v-if="alerts.length === 0" class="text-sm text-muted-foreground">
                No active alerts.
            </div>
            <ul v-else class="space-y-3 text-sm">
                <li v-for="a in alerts" :key="a.id" class="rounded-lg border border-border/40 bg-muted/20 p-4 transition-colors hover:bg-muted/40">
                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-primary/60" />
                        {{ a.type }} &middot; {{ a.detected_at }}
                    </div>
                    <div class="mt-1.5 leading-relaxed">{{ a.message }}</div>
                </li>
            </ul>
        </div>
    </div>
</template>
