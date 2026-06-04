import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

interface UndoableAction {
    id: string;
    type: 'delete' | 'restore' | 'update';
    resourceType: 'product' | 'booking' | 'requisition';
    resourceId: number;
    data: Record<string, unknown>;
    timestamp: number;
    expiresAt: number;
}

interface UndoOptions {
    timeout?: number; // Time before action expires (default: 30000ms = 30s)
    maxActions?: number; // Maximum number of actions to track
}

/**
 * Undo manager for delete/restore operations with toast integration
 */
export function useUndoManager(options: UndoOptions = {}) {
    const { timeout = 30000, maxActions = 10 } = options;

    const actions = ref<Map<string, UndoableAction>>(new Map());
    const processingUndos = ref<Set<string>>(new Set());

    /**
     * Get all pending undoable actions
     */
    const pendingActions = computed(() => {
        return Array.from(actions.value.values())
            .filter(action => Date.now() < action.expiresAt)
            .sort((a, b) => b.timestamp - a.timestamp);
    });

    /**
     * Check if there are any pending actions
     */
    const hasPendingActions = computed(() => pendingActions.value.length > 0);

    /**
     * Register a delete action for potential undo
     */
    const registerDelete = (
        resourceType: 'product' | 'booking' | 'requisition',
        resourceId: number,
        data: Record<string, unknown>
    ): string => {
        const id = `${resourceType}-${resourceId}-${Date.now()}`;

        // Remove expired actions
        cleanupExpired();

        // Limit number of tracked actions
        if (actions.value.size >= maxActions) {
            const oldestKey = Array.from(actions.value.entries())
                .sort(([, a], [, b]) => a.timestamp - b.timestamp)[0]?.[0];
            if (oldestKey) {
                actions.value.delete(oldestKey);
            }
        }

        const action: UndoableAction = {
            id,
            type: 'delete',
            resourceType,
            resourceId,
            data,
            timestamp: Date.now(),
            expiresAt: Date.now() + timeout,
        };

        actions.value.set(id, action);

        // Auto-cleanup after timeout
        setTimeout(() => {
            actions.value.delete(id);
        }, timeout);

        return id;
    };

    /**
     * Register a restore action for potential undo (re-delete)
     */
    const registerRestore = (
        resourceType: 'product' | 'booking' | 'requisition',
        resourceId: number,
        data: Record<string, unknown>
    ): string => {
        const id = `${resourceType}-${resourceId}-restore-${Date.now()}`;

        cleanupExpired();

        const action: UndoableAction = {
            id,
            type: 'restore',
            resourceType,
            resourceId,
            data,
            timestamp: Date.now(),
            expiresAt: Date.now() + timeout,
        };

        actions.value.set(id, action);

        setTimeout(() => {
            actions.value.delete(id);
        }, timeout);

        return id;
    };

    /**
     * Perform undo action
     */
    const undo = async (actionId: string): Promise<boolean> => {
        const action = actions.value.get(actionId);
        if (!action || Date.now() > action.expiresAt) {
            return false;
        }

        if (processingUndos.value.has(actionId)) {
            return false;
        }

        processingUndos.value.add(actionId);

        try {
            let success = false;

            switch (action.type) {
                case 'delete':
                    success = await performUndoDelete(action);
                    break;
                case 'restore':
                    success = await performUndoRestore(action);
                    break;
            }

            if (success) {
                actions.value.delete(actionId);
            }

            return success;
        } catch (error) {
            console.error('Undo failed:', error);
            return false;
        } finally {
            processingUndos.value.delete(actionId);
        }
    };

    /**
     * Undo a delete (restore the item)
     */
    const performUndoDelete = async (action: UndoableAction): Promise<boolean> => {
        return new Promise((resolve) => {
            const restoreUrl = `/inventory/${action.resourceType}s/${action.resourceId}/restore`;

            router.put(restoreUrl, {}, {
                preserveScroll: true,
                onSuccess: () => {
                    resolve(true);
                },
                onError: () => {
                    resolve(false);
                },
            });
        });
    };

    /**
     * Undo a restore (re-delete the item)
     */
    const performUndoRestore = async (action: UndoableAction): Promise<boolean> => {
        return new Promise((resolve) => {
            const deleteUrl = `/inventory/${action.resourceType}s/${action.resourceId}`;

            router.delete(deleteUrl, {
                preserveScroll: true,
                onSuccess: () => {
                    resolve(true);
                },
                onError: () => {
                    resolve(false);
                },
            });
        });
    };

    /**
     * Cancel an undoable action (remove from tracking)
     */
    const cancel = (actionId: string): void => {
        actions.value.delete(actionId);
    };

    /**
     * Clean up expired actions
     */
    const cleanupExpired = (): void => {
        const now = Date.now();
        for (const [id, action] of actions.value.entries()) {
            if (now > action.expiresAt) {
                actions.value.delete(id);
            }
        }
    };

    /**
     * Get action details
     */
    const getAction = (actionId: string): UndoableAction | undefined => {
        return actions.value.get(actionId);
    };

    /**
     * Check if an action is being processed
     */
    const isProcessing = (actionId: string): boolean => {
        return processingUndos.value.has(actionId);
    };

    /**
     * Clear all actions
     */
    const clearAll = (): void => {
        actions.value.clear();
        processingUndos.value.clear();
    };

    return {
        pendingActions,
        hasPendingActions,
        registerDelete,
        registerRestore,
        undo,
        cancel,
        getAction,
        isProcessing,
        clearAll,
    };
}

/**
 * Global undo manager instance for app-wide undo functionality
 */
let globalUndoManager: ReturnType<typeof useUndoManager> | null = null;

export function getGlobalUndoManager(): ReturnType<typeof useUndoManager> {
    if (!globalUndoManager) {
        globalUndoManager = useUndoManager({ timeout: 30000 });
    }
    return globalUndoManager;
}
