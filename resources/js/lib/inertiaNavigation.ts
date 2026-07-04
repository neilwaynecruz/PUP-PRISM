import { isAppNavigating } from '@/composables/useAppNavigation';

export function shouldApplyDebouncedVisit(pathPrefix: string): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    if (isAppNavigating()) {
        return false;
    }

    return window.location.pathname.startsWith(pathPrefix);
}
