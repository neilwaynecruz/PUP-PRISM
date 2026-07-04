import { router } from '@inertiajs/vue3';
import { clearSessionClientState } from '@/lib/sessionGuard';
import { logout } from '@/routes';

export function performLogout(): void {
    router.flushAll();
    clearSessionClientState();

    router.post(logout.url(), {}, {
        replace: true,
        preserveScroll: false,
        preserveState: false,
    });
}
