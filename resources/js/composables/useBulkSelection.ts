import {   computed, ref } from 'vue';
import type {Ref, ComputedRef} from 'vue';

export interface UseBulkSelectionReturn {
    selectedIds: Ref<Set<number>>;
    allSelected: ComputedRef<boolean>;
    someSelected: ComputedRef<boolean>;
    hasSelection: ComputedRef<boolean>;
    toggleSelectAll: () => void;
    toggleSelect: (id: number) => void;
    clearSelection: () => void;
}

export function useBulkSelection<T extends { id: number }>(
    getItems: () => T[],
): UseBulkSelectionReturn {
    const selectedIds = ref<Set<number>>(new Set());

    const visibleIds = computed(() => getItems().map((i) => i.id));

    const allSelected = computed(
        () =>
            visibleIds.value.length > 0 &&
            visibleIds.value.every((id) => selectedIds.value.has(id)),
    );

    const someSelected = computed(
        () =>
            visibleIds.value.some((id) => selectedIds.value.has(id)) &&
            !allSelected.value,
    );

    const hasSelection = computed(() => selectedIds.value.size > 0);

    function toggleSelectAll(): void {
        const next = new Set(selectedIds.value);

        if (allSelected.value) {
            visibleIds.value.forEach((id) => next.delete(id));
        } else {
            visibleIds.value.forEach((id) => next.add(id));
        }

        selectedIds.value = next;
    }

    function toggleSelect(id: number): void {
        const next = new Set(selectedIds.value);

        if (next.has(id)) {
            next.delete(id);
        } else {
            next.add(id);
        }

        selectedIds.value = next;
    }

    function clearSelection(): void {
        selectedIds.value = new Set();
    }

    return {
        selectedIds,
        allSelected,
        someSelected,
        hasSelection,
        toggleSelectAll,
        toggleSelect,
        clearSelection,
    };
}
