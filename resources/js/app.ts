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
    router.on('networkError', () => {
        toast.error(
            'Unable to reach the server. Check your connection and try again.',
        );
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
