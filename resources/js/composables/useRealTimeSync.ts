import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

interface SyncOptions {
    interval?: number;
    enabled?: boolean;
    onUpdate?: (data: unknown) => void;
    compareFn?: (oldData: unknown, newData: unknown) => boolean;
}

/**
 * Real-time sync composable using polling-based updates
 * Works without WebSocket infrastructure - can be upgraded to Echo later
 */
export function useRealTimeSync<T>(options: SyncOptions = {}) {
    const { interval = 30000, enabled = true, onUpdate } = options;

    const lastSyncedAt = ref<Date | null>(null);
    const isSyncing = ref(false);
    const syncError = ref<Error | null>(null);
    let syncTimer: number | null = null;

    const page = usePage();
    const currentData = computed(() => page.props as T);

    /**
     * Perform a sync by refreshing the current page data
     */
    const sync = async (preserveScroll = true): Promise<void> => {
        if (isSyncing.value) {
            return;
        }

        isSyncing.value = true;
        syncError.value = null;

        try {
            router.reload({
                only: [], // Reload all props
                preserveScroll,
                onSuccess: () => {
                    lastSyncedAt.value = new Date();
                    onUpdate?.(currentData.value);
                },
                onError: (errors: Record<string, string>) => {
                    syncError.value = new Error(JSON.stringify(errors));
                },
            } as Record<string, unknown>);
        } catch (error) {
            syncError.value =
                error instanceof Error ? error : new Error('Sync failed');
        } finally {
            isSyncing.value = false;
        }
    };

    /**
     * Start auto-sync timer
     */
    const startAutoSync = (): void => {
        stopAutoSync();

        if (enabled && interval > 0) {
            syncTimer = window.setInterval(() => sync(true), interval);
        }
    };

    /**
     * Stop auto-sync timer
     */
    const stopAutoSync = (): void => {
        if (syncTimer !== null) {
            window.clearInterval(syncTimer);
            syncTimer = null;
        }
    };

    /**
     * Smart sync that only updates if data has changed
     */
    const smartSync = async (expectedChanges?: string[]): Promise<boolean> => {
        const oldData = JSON.stringify(currentData.value);
        await sync(true);
        const newData = JSON.stringify(currentData.value);

        const hasChanged = oldData !== newData;

        if (hasChanged && expectedChanges) {
            // Check if expected changes are present
            return expectedChanges.every((change) => newData.includes(change));
        }

        return hasChanged;
    };

    onMounted(() => {
        if (enabled) {
            startAutoSync();
        }
    });

    onUnmounted(() => {
        stopAutoSync();
    });

    return {
        sync,
        smartSync,
        startAutoSync,
        stopAutoSync,
        lastSyncedAt: computed(() => lastSyncedAt.value),
        isSyncing: computed(() => isSyncing.value),
        syncError: computed(() => syncError.value),
    };
}

/**
 * Dashboard-specific real-time sync
 */
export function useDashboardSync(updateInterval = 30000) {
    return useRealTimeSync({
        interval: updateInterval,
        onUpdate: () => {
            // Dashboard stats are automatically refreshed via Inertia
            console.log('Dashboard synced at', new Date().toISOString());
        },
    });
}

/**
 * Table-specific real-time sync with optimistic updates
 */
export function useTableSync<T extends { data: unknown[]; total?: number }>(
    options: { interval?: number; dataKey?: string } = {},
) {
    const { interval = 30000 } = options;

    const pendingChanges = ref<Map<string, 'create' | 'update' | 'delete'>>(
        new Map(),
    );

    const { sync, isSyncing } = useRealTimeSync<T>({
        interval,
        onUpdate: () => {
            // Clear pending changes after successful sync
            pendingChanges.value.clear();
        },
    });

    const markPending = (
        id: string,
        action: 'create' | 'update' | 'delete',
    ): void => {
        pendingChanges.value.set(id, action);
    };

    const isPending = (id: string): boolean => {
        return pendingChanges.value.has(id);
    };

    return {
        sync,
        isSyncing,
        pendingChanges: computed(() => pendingChanges.value),
        markPending,
        isPending,
    };
}
