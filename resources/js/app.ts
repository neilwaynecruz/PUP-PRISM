import { createInertiaApp, router } from '@inertiajs/vue3';
import { configureEcho } from '@laravel/echo-vue';
import { toast } from 'vue-sonner';
import { initializeTheme } from '@/composables/useAppearance';
import { initializeAppNavigation } from '@/composables/useAppNavigation';
import { initializePwa } from '@/composables/usePwa';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { initializeSessionGuard } from '@/lib/sessionGuard';

const reverbAppKey = import.meta.env.VITE_REVERB_APP_KEY;

if (
    typeof window !== 'undefined' &&
    typeof reverbAppKey === 'string' &&
    reverbAppKey.trim() !== ''
) {
    configureEcho({
        broadcaster: 'reverb',
    });
}

const appName = import.meta.env.VITE_APP_NAME || 'PUP PRISM';

if (typeof window !== 'undefined') {
    let lastNetworkToastAt = 0;

    router.on('networkError', (event) => {
        event.preventDefault();

        const now = Date.now();

        if (now - lastNetworkToastAt < 4000) {
            return;
        }

        lastNetworkToastAt = now;

        const isOffline =
            typeof navigator !== 'undefined' && navigator.onLine === false;

        if (isOffline) {
            toast.warning(
                'You are currently offline. Viewing cached data where available; changes cannot be submitted until your connection returns.',
            );

            return;
        }

        toast.error(
            'Unable to reach the server. Please check your connection and try again.',
        );
    });

    router.on('httpException', (event) => {
        const status = event.detail?.response?.status;

        if (status === 419) {
            event.preventDefault();
            toast.warning(
                'Your session has expired. Refreshing your security token—please try your action again.',
            );
            router.reload({ only: ['auth', 'flash'] });
        }
    });
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();

// This keeps module navigation feedback in sync across persistent layouts.
initializeAppNavigation();

initializeSessionGuard();

initializePwa();
