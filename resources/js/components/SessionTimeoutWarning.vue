<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { SessionMeta } from '@/types/auth';

const page = usePage();
const session = computed(() => page.props.session as SessionMeta);
const activityStorageKey = 'prism:last-activity-at';
const activityEvents = ['mousedown', 'keydown', 'touchstart', 'scroll'];

const showWarning = ref(false);
const isRefreshing = ref(false);
const minutesRemaining = ref<number | null>(null);

let checkTimer: ReturnType<typeof setInterval> | null = null;
let lastActivity = Date.now();
let wasExpired = false;

function updateActivity(): void {
    lastActivity = Date.now();
    localStorage.setItem(activityStorageKey, String(lastActivity));
}

function syncActivityFromStorage(event?: StorageEvent): void {
    if (event && event.key !== activityStorageKey) {
        return;
    }

    const rawValue = localStorage.getItem(activityStorageKey);
    const nextActivity = rawValue ? Number(rawValue) : NaN;

    if (!Number.isNaN(nextActivity) && nextActivity > lastActivity) {
        lastActivity = nextActivity;
    }
}

function checkSession(): void {
    const inactiveMinutes = (Date.now() - lastActivity) / 1000 / 60;
    const minutesUntilExpiry = session.value.lifetimeMinutes - inactiveMinutes;

    minutesRemaining.value = Math.max(0, Math.ceil(minutesUntilExpiry));

    if (
        minutesUntilExpiry <= session.value.warningMinutes &&
        minutesUntilExpiry > 0
    ) {
        showWarning.value = true;
        wasExpired = false;
    } else if (minutesUntilExpiry <= 0) {
        showWarning.value = false;

        if (!wasExpired) {
            wasExpired = true;
            window.location.href = session.value.loginUrl;
        }
    } else {
        showWarning.value = false;
        wasExpired = false;
    }
}

async function keepAlive(): Promise<void> {
    if (isRefreshing.value) {
        return;
    }

    isRefreshing.value = true;

    try {
        const response = await fetch(session.value.keepAliveUrl, {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Session refresh failed.');
        }

        updateActivity();
        showWarning.value = false;
    } catch {
        window.location.href = session.value.loginUrl;
    } finally {
        isRefreshing.value = false;
    }
}

onMounted(() => {
    syncActivityFromStorage();
    activityEvents.forEach((event) =>
        document.addEventListener(event, updateActivity),
    );
    window.addEventListener('storage', syncActivityFromStorage);

    checkTimer = setInterval(checkSession, 30000);
    checkSession();
});

onUnmounted(() => {
    activityEvents.forEach((event) =>
        document.removeEventListener(event, updateActivity),
    );
    window.removeEventListener('storage', syncActivityFromStorage);

    if (checkTimer) {
        clearInterval(checkTimer);
    }
});
</script>

<template>
    <Dialog
        :open="showWarning"
        @update:open="
            (open) => {
                if (!open) showWarning = false;
            }
        "
    >
        <DialogContent>
            <DialogHeader class="space-y-3">
                <DialogTitle>Session expiring soon</DialogTitle>
                <DialogDescription>
                    Your session will expire in about
                    <span class="font-medium">{{
                        minutesRemaining ?? session.warningMinutes
                    }}</span>
                    minute<span v-if="(minutesRemaining ?? 0) !== 1">s</span>
                    due to inactivity. Would you like to stay logged in?
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <Button variant="secondary" @click="showWarning = false"
                    >Dismiss</Button
                >
                <Button :disabled="isRefreshing" @click="keepAlive"
                    >Keep me logged in</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
