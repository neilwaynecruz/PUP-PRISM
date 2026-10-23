import { readonly, ref } from 'vue';

interface BeforeInstallPromptEvent extends Event {
    prompt: () => Promise<void>;
    userChoice: Promise<{
        outcome: 'accepted' | 'dismissed';
        platform: string;
    }>;
}

const isOnline = ref(true);
const canInstall = ref(false);
const updateAvailable = ref(false);
const registrationFailed = ref(false);

let installPrompt: BeforeInstallPromptEvent | null = null;
let serviceWorkerRegistration: ServiceWorkerRegistration | null = null;
let isInitialized = false;

function setOnlineState(): void {
    isOnline.value = navigator.onLine;
}

function trackServiceWorkerUpdate(
    registration: ServiceWorkerRegistration,
): void {
    serviceWorkerRegistration = registration;
    updateAvailable.value = Boolean(registration.waiting);

    registration.addEventListener('updatefound', () => {
        const installingWorker = registration.installing;

        if (!installingWorker) {
            return;
        }

        installingWorker.addEventListener('statechange', () => {
            if (
                installingWorker.state === 'installed' &&
                navigator.serviceWorker.controller
            ) {
                updateAvailable.value = true;
            }
        });
    });
}

async function registerServiceWorker(): Promise<void> {
    try {
        const registration = await navigator.serviceWorker.register('/sw.js');

        registrationFailed.value = false;
        trackServiceWorkerUpdate(registration);
    } catch {
        registrationFailed.value = true;
    }
}

export function initializePwa(): void {
    if (typeof window === 'undefined' || isInitialized) {
        return;
    }

    isInitialized = true;
    setOnlineState();

    window.addEventListener('online', setOnlineState);
    window.addEventListener('offline', setOnlineState);
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        installPrompt = event as BeforeInstallPromptEvent;
        canInstall.value = true;
    });
    window.addEventListener('appinstalled', () => {
        installPrompt = null;
        canInstall.value = false;
    });

    if (!('serviceWorker' in navigator) || !window.isSecureContext) {
        return;
    }

    if (document.readyState === 'complete') {
        void registerServiceWorker();
    } else {
        window.addEventListener(
            'load',
            () => {
                void registerServiceWorker();
            },
            { once: true },
        );
    }
}

export function usePwa() {
    async function installApplication(): Promise<void> {
        if (!installPrompt) {
            return;
        }

        await installPrompt.prompt();
        const choice = await installPrompt.userChoice;

        if (choice.outcome === 'accepted') {
            installPrompt = null;
            canInstall.value = false;
        }
    }

    function dismissInstall(): void {
        canInstall.value = false;
    }

    function applyUpdate(): void {
        const waitingWorker = serviceWorkerRegistration?.waiting;

        if (!waitingWorker) {
            updateAvailable.value = false;

            return;
        }

        navigator.serviceWorker.addEventListener(
            'controllerchange',
            () => window.location.reload(),
            { once: true },
        );
        waitingWorker.postMessage({ type: 'SKIP_WAITING' });
    }

    return {
        applyUpdate,
        canInstall: readonly(canInstall),
        dismissInstall,
        installApplication,
        isOnline: readonly(isOnline),
        registrationFailed: readonly(registrationFailed),
        updateAvailable: readonly(updateAvailable),
    };
}
