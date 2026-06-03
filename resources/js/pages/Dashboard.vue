<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { Chart } from 'chart.js/auto';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { dashboard } from '@/routes';

type Alert = { id: number; type: string; message: string; detected_at: string };

const props = defineProps<{
    alerts: Alert[];
    lowStock: { id: number; sku: string; name: string; category: string | null; on_hand_qty: number | null; reorder_threshold: number }[];
    unserviceableAssets: { id: number; tag_code: string; status: string; name: string | null }[];
    assetStatusCounts: { labels: string[]; data: number[] };
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
let assetStatusChart: Chart | null = null;

onMounted(() => {
    if (!isAdmin.value || !assetStatusCanvas.value) {
        return;
    }

    assetStatusChart = new Chart(assetStatusCanvas.value, {
        type: 'bar',
        data: {
            labels: props.assetStatusCounts.labels,
            datasets: [
                {
                    label: 'Assets',
                    data: props.assetStatusCounts.data,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
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
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <Heading
            variant="small"
            title="Dashboard"
            description="Overview and decision support."
        />

        <div v-if="isAdmin" class="grid gap-4 lg:grid-cols-2">
            <div
                class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <div class="mb-3 text-sm font-medium">Unserviceable / Condemned assets</div>
                <div class="h-64">
                    <canvas ref="assetStatusCanvas" />
                </div>
            </div>

            <div
                class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <div class="mb-3 text-sm font-medium">Low-stock consumables</div>
                <div v-if="lowStock.length === 0" class="text-sm text-muted-foreground">
                    No low-stock items found.
                </div>
                <ul v-else class="space-y-2 text-sm">
                    <li v-for="p in lowStock" :key="p.id" class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="truncate font-medium">{{ p.name }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ p.category ?? '—' }} • <span class="font-mono">{{ p.sku }}</span>
                            </div>
                        </div>
                        <div class="shrink-0 text-right">
                            <div class="font-mono text-xs">{{ p.on_hand_qty ?? 0 }} / {{ p.reorder_threshold ?? 0 }}</div>
                            <div class="text-xs text-muted-foreground">on hand / threshold</div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <div v-if="isAdmin" class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <div class="mb-3 text-sm font-medium">Unserviceable / Condemned assets (list)</div>
            <div v-if="unserviceableAssets.length === 0" class="text-sm text-muted-foreground">
                No assets in these statuses.
            </div>
            <ul v-else class="space-y-2 text-sm">
                <li
                    v-for="a in unserviceableAssets"
                    :key="a.id"
                    class="flex items-center justify-between gap-3"
                >
                    <span class="font-medium">{{ a.name ?? 'Asset' }}</span>
                    <span class="text-xs text-muted-foreground">
                        {{ a.status }} • <span class="font-mono">{{ a.tag_code }}</span>
                    </span>
                </li>
            </ul>
        </div>

        <div v-if="isAdmin" class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <div class="mb-3 text-sm font-medium">Alerts</div>
            <div v-if="alerts.length === 0" class="text-sm text-muted-foreground">
                No active alerts.
            </div>
            <ul v-else class="space-y-2 text-sm">
                <li v-for="a in alerts" :key="a.id" class="rounded-md bg-muted/30 p-3">
                    <div class="text-xs text-muted-foreground">
                        {{ a.type }} • {{ a.detected_at }}
                    </div>
                    <div class="mt-1">{{ a.message }}</div>
                </li>
            </ul>
        </div>
    </div>
</template>
