import { router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

interface FilterConfig {
    key: string;
    defaultValue?: string | number | boolean | null;
    persistInUrl?: boolean;
    persistInSession?: boolean;
}

interface FilterState {
    [key: string]: string | number | boolean | null | undefined;
}

interface PersistenceOptions {
    syncWithUrl?: boolean;
    preserveOnNavigate?: boolean;
    debounceMs?: number;
}

/**
 * Filter persistence composable for managing search, filters, and pagination state
 * Persists state in URL query parameters and optionally in sessionStorage
 */
export function useFilterPersistence(
    filters: FilterConfig[],
    options: PersistenceOptions = {},
) {
    const { syncWithUrl = true, debounceMs = 300 } = options;

    const page = usePage();
    const urlParams = new URLSearchParams(window.location.search);

    // Initialize state from URL or defaults
    const initialState: FilterState = {};

    for (const filter of filters) {
        const urlValue = urlParams.get(filter.key);
        initialState[filter.key] = (
            urlValue !== null
                ? parseValue(urlValue, filter.defaultValue)
                : filter.defaultValue
        ) as string | number | boolean | null | undefined;
    }

    const state = ref<FilterState>({ ...initialState });
    const pendingUpdate = ref(false);
    let debounceTimer: number | null = null;

    /**
     * Parse URL value based on default value type
     */
    function parseValue(value: string, defaultValue: unknown): unknown {
        if (typeof defaultValue === 'number') {
            const parsed = parseInt(value, 10);

            return isNaN(parsed) ? defaultValue : parsed;
        }

        if (typeof defaultValue === 'boolean') {
            return value === 'true' || value === '1';
        }

        return value;
    }

    /**
     * Serialize value for URL
     */
    function serializeValue(value: unknown): string | null {
        if (value === null || value === undefined || value === '') {
            return null;
        }

        return String(value);
    }

    /**
     * Update a single filter value
     */
    const setFilter = (
        key: string,
        value: string | number | boolean | null,
    ): void => {
        state.value[key] = value;
        scheduleSync();
    };

    /**
     * Update multiple filters at once
     */
    const setFilters = (updates: Partial<FilterState>): void => {
        Object.assign(state.value, updates);
        scheduleSync();
    };

    /**
     * Reset a single filter to default
     */
    const resetFilter = (key: string): void => {
        const filter = filters.find((f) => f.key === key);
        state.value[key] = filter?.defaultValue ?? null;
        scheduleSync();
    };

    /**
     * Reset all filters to defaults
     */
    const resetAll = (): void => {
        for (const filter of filters) {
            state.value[filter.key] = filter.defaultValue ?? null;
        }

        scheduleSync();
    };

    /**
     * Schedule URL sync with debounce
     */
    const scheduleSync = (): void => {
        if (!syncWithUrl) {
            return;
        }

        if (debounceTimer !== null) {
            clearTimeout(debounceTimer);
        }

        pendingUpdate.value = true;
        debounceTimer = window.setTimeout(() => {
            syncToUrl();
            pendingUpdate.value = false;
        }, debounceMs);
    };

    /**
     * Sync current state to URL
     */
    const syncToUrl = (
        options: { preserveScroll?: boolean; preserveState?: boolean } = {},
    ): void => {
        const params = new URLSearchParams();

        for (const [key, value] of Object.entries(state.value)) {
            const serialized = serializeValue(value);

            if (serialized !== null) {
                params.set(key, serialized);
            }
        }

        const queryString = params.toString();
        const url =
            window.location.pathname + (queryString ? `?${queryString}` : '');

        router.visit(url, {
            method: 'get',
            preserveScroll: options.preserveScroll ?? true,
            preserveState: options.preserveState ?? true,
            replace: true,
        });
    };

    /**
     * Get current query string for API calls
     */
    const getQueryString = computed(() => {
        const params = new URLSearchParams();

        for (const [key, value] of Object.entries(state.value)) {
            const serialized = serializeValue(value);

            if (serialized !== null) {
                params.set(key, serialized);
            }
        }

        return params.toString();
    });

    /**
     * Check if any filters are active (non-default)
     */
    const hasActiveFilters = computed(() => {
        for (const filter of filters) {
            const currentValue = state.value[filter.key];
            const defaultValue = filter.defaultValue;

            if (currentValue !== defaultValue) {
                // Handle null/undefined equivalence
                if (currentValue === null && defaultValue === undefined) {
                    continue;
                }

                if (currentValue === undefined && defaultValue === null) {
                    continue;
                }

                return true;
            }
        }

        return false;
    });

    /**
     * Get active filters count
     */
    const activeFilterCount = computed(() => {
        let count = 0;

        for (const filter of filters) {
            const currentValue = state.value[filter.key];
            const defaultValue = filter.defaultValue;

            if (currentValue !== defaultValue) {
                if (currentValue === null && defaultValue === undefined) {
                    continue;
                }

                if (currentValue === undefined && defaultValue === null) {
                    continue;
                }

                count++;
            }
        }

        return count;
    });

    /**
     * Apply filters immediately (without debounce)
     */
    const applyFilters = (): void => {
        if (debounceTimer !== null) {
            clearTimeout(debounceTimer);
        }

        syncToUrl({ preserveScroll: false });
    };

    onBeforeUnmount(() => {
        if (debounceTimer !== null) {
            clearTimeout(debounceTimer);
        }
    });

    // Watch for URL changes (browser back/forward)
    if (syncWithUrl) {
        watch(
            () => page.url,
            (newUrl) => {
                if (!newUrl) {
                    return;
                }

                const newParams = new URLSearchParams(window.location.search);

                for (const filter of filters) {
                    const urlValue = newParams.get(filter.key);
                    state.value[filter.key] = (
                        urlValue !== null
                            ? parseValue(urlValue, filter.defaultValue)
                            : filter.defaultValue
                    ) as string | number | boolean | null | undefined;
                }
            },
        );
    }

    return {
        state: computed(() => state.value),
        setFilter,
        setFilters,
        resetFilter,
        resetAll,
        applyFilters,
        hasActiveFilters,
        activeFilterCount,
        pendingUpdate: computed(() => pendingUpdate.value),
        getQueryString,
    };
}

/**
 * Pagination persistence helper
 */
export function usePaginationPersistence(defaultPerPage: number = 15) {
    const currentPage = ref(1);
    const perPage = ref(defaultPerPage);

    /**
     * Handle page change with auto-adjustment
     */
    const setPage = (
        page: number,
        totalItems?: number,
        currentItems?: number,
    ): void => {
        // Auto-adjust if deleting last item on page
        if (currentItems === 0 && page > 1 && totalItems !== undefined) {
            const totalPages = Math.ceil(totalItems / perPage.value);
            currentPage.value = Math.min(page - 1, totalPages);
        } else {
            currentPage.value = page;
        }
    };

    /**
     * Handle per page change
     */
    const setPerPage = (value: number): void => {
        perPage.value = value;
        currentPage.value = 1; // Reset to first page
    };

    /**
     * Calculate if we're on the last page with no items (needs adjustment)
     */
    const needsPageAdjustment = (totalItems: number): boolean => {
        const totalPages = Math.max(1, Math.ceil(totalItems / perPage.value));

        return currentPage.value > totalPages;
    };

    /**
     * Get adjusted page number
     */
    const getAdjustedPage = (totalItems: number): number => {
        const totalPages = Math.max(1, Math.ceil(totalItems / perPage.value));

        return Math.min(currentPage.value, totalPages);
    };

    return {
        currentPage: computed(() => currentPage.value),
        perPage: computed(() => perPage.value),
        setPage,
        setPerPage,
        needsPageAdjustment,
        getAdjustedPage,
        from: computed(() => (currentPage.value - 1) * perPage.value + 1),
        to: computed(() => 0), // Will be calculated with total parameter at runtime
    };
}
