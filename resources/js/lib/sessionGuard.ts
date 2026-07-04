const ACTIVITY_STORAGE_KEY = 'prism:last-activity-at';

const PUBLIC_PATH_PREFIXES = [
    '/login',
    '/register',
    '/forgot-password',
    '/reset-password',
    '/two-factor-challenge',
    '/user/confirm-password',
];

let loginUrl = '/login';
let statusUrl = '/session/status';
let isVerifying = false;

function isPublicPath(pathname: string): boolean {
    return PUBLIC_PATH_PREFIXES.some((prefix) => pathname.startsWith(prefix));
}

function redirectToLogin(): void {
    if (window.location.pathname.startsWith(loginUrl)) {
        return;
    }

    window.location.replace(loginUrl);
}

export function clearSessionClientState(): void {
    localStorage.removeItem(ACTIVITY_STORAGE_KEY);
}

export async function verifyAuthenticatedSession(): Promise<boolean> {
    if (typeof window === 'undefined' || isPublicPath(window.location.pathname)) {
        return true;
    }

    if (isVerifying) {
        return true;
    }

    isVerifying = true;

    try {
        const response = await fetch(statusUrl, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (response.status === 401) {
            clearSessionClientState();
            redirectToLogin();

            return false;
        }

        return response.ok || response.status === 204;
    } catch {
        return true;
    } finally {
        isVerifying = false;
    }
}

export function initializeSessionGuard(options: {
    loginUrl?: string;
    statusUrl?: string;
} = {}): void {
    if (typeof window === 'undefined') {
        return;
    }

    loginUrl = options.loginUrl ?? loginUrl;
    statusUrl = options.statusUrl ?? statusUrl;

    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            void verifyAuthenticatedSession();
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            void verifyAuthenticatedSession();
        }
    });

    window.addEventListener('popstate', () => {
        void verifyAuthenticatedSession();
    });

    window.addEventListener('focus', () => {
        void verifyAuthenticatedSession();
    });
}
