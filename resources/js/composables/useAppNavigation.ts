import type { InertiaLinkProps } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { computed, readonly, ref } from 'vue';
import { toUrl } from '@/lib/utils';

const isNavigating = ref(false);
const pendingLabel = ref<string | null>(null);
const pendingPath = ref<string | null>(null);

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

function finishNavigation(): void {
    isNavigating.value = false;
    pendingLabel.value = null;
    pendingPath.value = null;
}

export function initializeAppNavigation(): void {
    // Navigation state is managed directly by navigateTo() for shared module
    // transitions, so there is no global bootstrapping required here.
}

export function useAppNavigation() {
    function navigateTo(
        href: NonNullable<InertiaLinkProps['href']>,
        label?: string,
    ): void {
        const targetPath = normalizePath(href);
        const currentPath =
            typeof window === 'undefined'
                ? targetPath
                : `${window.location.pathname}${window.location.search}`;

        window.dispatchEvent(new CustomEvent('app:close-overlays'));

        if (targetPath === currentPath) {
            return;
        }

        pendingLabel.value = label ?? null;
        pendingPath.value = targetPath;
        isNavigating.value = true;

        router.cancelAll();
        router.visit(toUrl(href), {
            onCancel: finishNavigation,
            onError: () => finishNavigation(),
            onFinish: finishNavigation,
        });
    }

    return {
        isNavigating: readonly(computed(() => isNavigating.value)),
        navigateTo,
        pendingLabel: readonly(computed(() => pendingLabel.value)),
        pendingPath: readonly(computed(() => pendingPath.value)),
    };
}
