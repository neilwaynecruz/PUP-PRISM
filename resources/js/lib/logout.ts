import { router } from '@inertiajs/vue3';
import { clearSessionClientState } from '@/lib/sessionGuard';
import { logout } from '@/routes';
import { switchMethod as switchAccount } from '@/routes/account';

export function performAccountSwitch(resumeHandover = false): void {
    router.flushAll();
    clearSessionClientState();
    router.post(
        switchAccount.url(),
        { resume_handover: resumeHandover },
        {
            replace: true,
            preserveScroll: false,
            preserveState: false,
        },
    );
}

export function performLogout(): void {
    router.flushAll();
    clearSessionClientState();

    router.post(
        logout.url(),
        {},
        {
            replace: true,
            preserveScroll: false,
            preserveState: false,
        },
    );
}
