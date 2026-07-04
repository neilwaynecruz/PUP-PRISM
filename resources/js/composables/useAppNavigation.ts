import type { InertiaLinkProps } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { computed, readonly, ref } from 'vue';
import { toUrl } from '@/lib/utils';

const isNavigating = ref(false);
const pendingLabel = ref<string | null>(null);
const pendingPath = ref<string | null>(null);

let navigationListenersRegistered = false;

export function isAppNavigating(): boolean {
    return isNavigating.value;
}

function normalizePath(
    href: NonNullable<InertiaLinkProps['href']> | string,
): string {
    const rawUrl = typeof href === 'string' ? href : toUrl(href);

    if (typeof window === 'undefined') {
        return rawUrl;
    }

    const normalizedUrl = new URL(rawUrl, window.location.origin);

    return `${normalizedUrl.pathname}${normalizedUrl.search}`;
}

function currentPath(): string {
    if (typeof window === 'undefined') {
        return '';
    }

    return `${window.location.pathname}${window.location.search}`;
}

function finishNavigation(): void {
    isNavigating.value = false;
    pendingLabel.value = null;
    pendingPath.value = null;
}

function closeOverlays(): void {
    window.dispatchEvent(new CustomEvent('app:close-overlays'));
}

export function initializeAppNavigation(): void {
    if (navigationListenersRegistered || typeof window === 'undefined') {
        return;
    }

    navigationListenersRegistered = true;

    router.on('start', (event) => {
        const visit = event.detail.visit;
        const isPartialReload =
            visit.only.length > 0 ||
            visit.except.length > 0 ||
            visit.reset.length > 0;

        if (visit.method !== 'get' || isPartialReload) {
            return;
        }

        isNavigating.value = true;
        pendingPath.value = `${visit.url.pathname}${visit.url.search}`;
    });

    router.on('finish', (event) => {
        const visit = event.detail.visit;
        const isPartialReload =
            visit.only.length > 0 ||
            visit.except.length > 0 ||
            visit.reset.length > 0;

        if (visit.method !== 'get' || isPartialReload) {
            return;
        }

        finishNavigation();
    });

    router.on('cancel', () => {
        if (isNavigating.value) {
            finishNavigation();
        }
    });

    router.on('error', () => {
        if (isNavigating.value) {
            finishNavigation();
        }
    });
}

export function useAppNavigation() {
    function prepareNavigation(label?: string): void {
        closeOverlays();

        if (label) {
            pendingLabel.value = label;
        }
    }

    function navigateTo(
        href: NonNullable<InertiaLinkProps['href']>,
        label?: string,
    ): void {
        const targetPath = normalizePath(href);

        prepareNavigation(label);

        if (targetPath === currentPath()) {
            return;
        }

        pendingPath.value = targetPath;
        isNavigating.value = true;

        router.visit(toUrl(href), {
            async: false,
            onCancel: finishNavigation,
            onError: finishNavigation,
            onFinish: finishNavigation,
        });
    }

    return {
        closeOverlays,
        isNavigating: readonly(computed(() => isNavigating.value)),
        navigateTo,
        pendingLabel: readonly(computed(() => pendingLabel.value)),
        pendingPath: readonly(computed(() => pendingPath.value)),
        prepareNavigation,
    };
}
