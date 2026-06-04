import { ref } from 'vue';

interface OptimisticItem {
    id: string | number;
    [key: string]: unknown;
}

interface PendingAction {
    id: string;
    type: 'create' | 'update' | 'delete';
    originalData?: OptimisticItem;
    optimisticData?: OptimisticItem;
    timestamp: number;
}

interface OptimisticOptions {
    maxRetries?: number;
    retryDelay?: number;
}

/**
 * Optimistic UI state management with automatic rollback on failure
 */
export function useOptimisticState(options: OptimisticOptions = {}) {
    const { maxRetries = 3, retryDelay = 1000 } = options;

    const items = ref<OptimisticItem[]>([]);
    const pendingActions = ref<Map<string, PendingAction>>(new Map());
    const processingIds = ref<Set<string | number>>(new Set());
    const errorMap = ref<Map<string, Error>>(new Map());

    /**
     * Initialize with existing data
     */
    const initialize = (data: OptimisticItem[]): void => {
        items.value = [...data];
    };

    /**
     * Check if an item is currently being processed
     */
    const isProcessing = (id: string | number): boolean => {
        return processingIds.value.has(id);
    };

    /**
     * Get error for a specific item
     */
    const getError = (id: string): Error | undefined => {
        return errorMap.value.get(id);
    };

    /**
     * Clear error for an item
     */
    const clearError = (id: string): void => {
        errorMap.value.delete(id);
    };

    /**
     * Apply optimistic create - add item immediately
     */
    const optimisticCreate = (id: string, tempItem: OptimisticItem): void => {
        processingIds.value.add(tempItem.id);
        pendingActions.value.set(id, {
            id,
            type: 'create',
            optimisticData: tempItem,
            timestamp: Date.now(),
        });
        items.value.unshift(tempItem);
    };

    /**
     * Apply optimistic update - modify item immediately
     */
    const optimisticUpdate = (id: string, itemId: string | number, newData: Partial<OptimisticItem>): OptimisticItem | null => {
        const index = items.value.findIndex(item => item.id === itemId);
        if (index === -1) return null;

        const originalData = { ...items.value[index] };
        processingIds.value.add(itemId);

        pendingActions.value.set(id, {
            id,
            type: 'update',
            originalData,
            optimisticData: { ...originalData, ...newData } as OptimisticItem,
            timestamp: Date.now(),
        });

        items.value[index] = { ...originalData, ...newData } as OptimisticItem;
        return originalData;
    };

    /**
     * Apply optimistic delete - remove item immediately
     */
    const optimisticDelete = (id: string, itemId: string | number): OptimisticItem | null => {
        const index = items.value.findIndex(item => item.id === itemId);
        if (index === -1) return null;

        const originalData = items.value[index];
        processingIds.value.add(itemId);

        pendingActions.value.set(id, {
            id,
            type: 'delete',
            originalData,
            timestamp: Date.now(),
        });

        items.value.splice(index, 1);
        return originalData;
    };

    /**
     * Confirm action success - keep optimistic state
     */
    const confirmSuccess = (id: string, serverData?: OptimisticItem): void => {
        const action = pendingActions.value.get(id);
        if (!action) return;

        processingIds.value.delete(action.optimisticData?.id ?? action.originalData?.id ?? id);
        pendingActions.value.delete(id);
        errorMap.value.delete(id);

        // Update with server data if provided (for creates/updates)
        if (serverData && action.type !== 'delete') {
            const index = items.value.findIndex(item =>
                item.id === action.optimisticData?.id || item.id === action.originalData?.id
            );
            if (index !== -1) {
                items.value[index] = serverData;
            }
        }
    };

    /**
     * Rollback on failure - restore original state
     */
    const rollback = (id: string, error?: Error): void => {
        const action = pendingActions.value.get(id);
        if (!action) return;

        const itemId = action.optimisticData?.id ?? action.originalData?.id ?? id;
        processingIds.value.delete(itemId);

        switch (action.type) {
            case 'create':
                // Remove the optimistically added item
                items.value = items.value.filter(item => item.id !== action.optimisticData?.id);
                break;

            case 'update':
                // Restore original data
                if (action.originalData) {
                    const index = items.value.findIndex(item => item.id === itemId);
                    if (index !== -1 && action.originalData) {
                        items.value[index] = action.originalData;
                    }
                }
                break;

            case 'delete':
                // Restore deleted item at original position
                if (action.originalData) {
                    items.value.push(action.originalData);
                }
                break;
        }

        if (error) {
            errorMap.value.set(id, error);
        }

        pendingActions.value.delete(id);
    };

    /**
     * Retry a failed action
     */
    const retry = async (id: string, actionFn: () => Promise<OptimisticItem>): Promise<boolean> => {
        const action = pendingActions.value.get(id);
        if (!action) return false;

        clearError(id);

        // Re-apply optimistic state
        switch (action.type) {
            case 'create':
                if (action.optimisticData) {
                    items.value.unshift(action.optimisticData);
                }
                break;
            case 'update':
                if (action.optimisticData) {
                    const index = items.value.findIndex(item => item.id === action.optimisticData?.id);
                    if (index !== -1) {
                        items.value[index] = action.optimisticData;
                    }
                }
                break;
            case 'delete':
                if (action.originalData) {
                    const index = items.value.findIndex(item => item.id === action.originalData?.id);
                    if (index !== -1) {
                        items.value.splice(index, 1);
                    }
                }
                break;
        }

        try {
            const result = await actionFn();
            confirmSuccess(id, result);
            return true;
        } catch (error) {
            rollback(id, error instanceof Error ? error : new Error('Retry failed'));
            return false;
        }
    };

    /**
     * Get all pending action IDs
     */
    const getPendingIds = (): string[] => {
        return Array.from(pendingActions.value.keys());
    };

    /**
     * Check if there are any pending actions
     */
    const hasPendingActions = (): boolean => {
        return pendingActions.value.size > 0;
    };

    return {
        items,
        pendingActions,
        processingIds,
        initialize,
        isProcessing,
        getError,
        clearError,
        optimisticCreate,
        optimisticUpdate,
        optimisticDelete,
        confirmSuccess,
        rollback,
        retry,
        getPendingIds,
        hasPendingActions,
    };
}
