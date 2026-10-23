<script setup lang="ts">
import { Download, RefreshCw, WifiOff, X } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { usePwa } from '@/composables/usePwa';

const {
    applyUpdate,
    canInstall,
    dismissInstall,
    installApplication,
    isOnline,
    updateAvailable,
} = usePwa();
</script>

<template>
    <div
        class="pointer-events-none fixed inset-x-3 bottom-3 z-[90] flex justify-center sm:inset-x-auto sm:right-5 sm:bottom-5"
        aria-live="polite"
        aria-atomic="true"
    >
        <section
            v-if="!isOnline"
            class="pointer-events-auto flex w-full max-w-md items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-950 shadow-lg dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100"
            role="status"
        >
            <WifiOff class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
            <div class="min-w-0">
                <p class="font-semibold">You are offline</p>
                <p
                    class="mt-1 text-sm leading-5 text-amber-800 dark:text-amber-200"
                >
                    Reconnect before submitting changes. PRISM does not save new
                    inventory work while offline.
                </p>
            </div>
        </section>

        <section
            v-else-if="updateAvailable"
            class="pointer-events-auto flex w-full max-w-md items-start gap-3 rounded-xl border border-border bg-background p-4 text-foreground shadow-lg"
            role="status"
        >
            <RefreshCw
                class="mt-0.5 h-5 w-5 shrink-0 text-primary"
                aria-hidden="true"
            />
            <div class="min-w-0 flex-1">
                <p class="font-semibold">A PRISM update is ready</p>
                <p class="mt-1 text-sm leading-5 text-muted-foreground">
                    Refresh now to use the latest version.
                </p>
                <Button class="mt-3" size="sm" @click="applyUpdate">
                    Refresh PRISM
                </Button>
            </div>
        </section>

        <section
            v-else-if="canInstall"
            class="pointer-events-auto flex w-full max-w-md items-start gap-3 rounded-xl border border-border bg-background p-4 text-foreground shadow-lg"
            role="status"
        >
            <Download
                class="mt-0.5 h-5 w-5 shrink-0 text-primary"
                aria-hidden="true"
            />
            <div class="min-w-0 flex-1">
                <p class="font-semibold">Install PUP PRISM</p>
                <p class="mt-1 text-sm leading-5 text-muted-foreground">
                    Add PRISM to this device for quicker access. An internet
                    connection is still required for inventory changes.
                </p>
                <Button class="mt-3" size="sm" @click="installApplication">
                    Install app
                </Button>
            </div>
            <Button
                variant="ghost"
                size="icon"
                aria-label="Dismiss install suggestion"
                @click="dismissInstall"
            >
                <X class="h-4 w-4" aria-hidden="true" />
            </Button>
        </section>
    </div>
</template>
