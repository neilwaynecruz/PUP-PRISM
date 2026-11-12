<script setup lang="ts">
import { CameraOff, QrCode } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useQrScanner } from '@/composables/useQrScanner';

const props = withDefaults(
    defineProps<{
        buttonLabel?: string;
        title?: string;
        description?: string;
        continuous?: boolean;
        closeOnScan?: boolean;
        triggerTestId?: string;
        buttonSize?: 'default' | 'sm' | 'lg' | 'icon';
    }>(),
    {
        buttonLabel: 'Scan QR Code',
        title: 'Scan QR code',
        description:
            'Use your phone or tablet camera to scan a QR code. Manual entry stays available if the camera is unavailable.',
        continuous: false,
        closeOnScan: undefined,
        triggerTestId: undefined,
        buttonSize: 'default',
    },
);

const emit = defineEmits<{
    scanned: [value: string];
}>();

const isOpen = ref(false);
const sessionScanCount = ref(0);
const closesAfterScan = computed(() => props.closeOnScan ?? !props.continuous);

const {
    canvasRef,
    error,
    hasCameraSupport,
    isRunning,
    isStarting,
    lastDetectedValue,
    start,
    status,
    stop,
    totalDetections,
    videoRef,
} = useQrScanner({
    continuous: props.continuous,
    cooldownMs: 1600,
    onDetected: (value) => {
        emit('scanned', value);

        sessionScanCount.value = totalDetections.value;

        if (closesAfterScan.value) {
            isOpen.value = false;
        }
    },
});

watch(isOpen, async (open) => {
    if (!open) {
        sessionScanCount.value = 0;
        stop();

        return;
    }

    await start();
});
</script>

<template>
    <Dialog :open="isOpen" @update:open="isOpen = $event">
        <DialogTrigger as-child>
            <Button
                type="button"
                variant="outline"
                :size="props.buttonSize"
                class="min-h-11 w-full sm:w-auto"
                :data-testid="triggerTestId"
            >
                <QrCode aria-hidden="true" class="mr-2 size-4" />
                {{ props.buttonLabel }}
            </Button>
        </DialogTrigger>
        <DialogContent
            class="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-lg"
        >
            <DialogHeader>
                <DialogTitle>{{ props.title }}</DialogTitle>
                <DialogDescription>{{ props.description }}</DialogDescription>
            </DialogHeader>

            <div class="grid gap-4">
                <div
                    class="flex flex-wrap items-center gap-2"
                    role="status"
                    aria-live="polite"
                >
                    <Badge v-if="isRunning" variant="default"
                        >Live camera</Badge
                    >
                    <Badge v-else-if="isStarting" variant="secondary"
                        >Starting Camera…</Badge
                    >
                    <Badge v-else variant="outline"
                        >Manual Entry Available</Badge
                    >
                    <Badge v-if="props.continuous" variant="secondary">
                        Continuous mode
                    </Badge>
                    <span class="text-sm text-muted-foreground">{{
                        status
                    }}</span>
                </div>

                <div
                    v-if="props.continuous && sessionScanCount > 0"
                    class="rounded-lg border border-primary/15 bg-primary/5 px-3 py-2 text-sm"
                >
                    <div class="font-medium text-primary" aria-live="polite">
                        {{ sessionScanCount }}
                        {{ sessionScanCount === 1 ? 'code' : 'codes' }} captured
                        in this session
                    </div>
                    <div
                        v-if="lastDetectedValue"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        Last captured:
                        <span class="font-mono">{{ lastDetectedValue }}</span>
                    </div>
                </div>

                <div
                    class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-muted/30 dark:border-sidebar-border"
                >
                    <div class="relative aspect-4/3">
                        <video
                            ref="videoRef"
                            class="size-full object-cover"
                            aria-label="Live camera preview for QR scanning"
                            playsinline
                            muted
                        />
                        <canvas
                            ref="canvasRef"
                            aria-hidden="true"
                            class="hidden"
                        />

                        <!-- QR Alignment Reticle -->
                        <div
                            class="pointer-events-none absolute inset-0 flex items-center justify-center p-6"
                        >
                            <div
                                class="relative aspect-square w-3/4 max-w-[240px] rounded-xl border border-white/40 shadow-[0_0_0_9999px_rgba(0,0,0,0.45)]"
                            >
                                <!-- Reticle Corner Accents -->
                                <div
                                    class="absolute -top-1 -left-1 size-5 rounded-tl border-t-3 border-l-3 border-primary"
                                    aria-hidden="true"
                                />
                                <div
                                    class="absolute -top-1 -right-1 size-5 rounded-tr border-t-3 border-r-3 border-primary"
                                    aria-hidden="true"
                                />
                                <div
                                    class="absolute -bottom-1 -left-1 size-5 rounded-bl border-b-3 border-l-3 border-primary"
                                    aria-hidden="true"
                                />
                                <div
                                    class="absolute -right-1 -bottom-1 size-5 rounded-br border-r-3 border-b-3 border-primary"
                                    aria-hidden="true"
                                />
                                <!-- Laser Line -->
                                <div
                                    class="absolute inset-x-2 top-1/2 h-0.5 -translate-y-1/2 animate-pulse rounded-full bg-rose-500/80 shadow-[0_0_10px_rgba(244,63,94,0.9)]"
                                    aria-hidden="true"
                                />
                            </div>
                        </div>

                        <div
                            v-if="!hasCameraSupport"
                            class="absolute inset-0 flex items-center justify-center bg-background/95 p-6 text-center"
                        >
                            <div class="grid gap-2">
                                <CameraOff
                                    aria-hidden="true"
                                    class="mx-auto size-8 text-muted-foreground"
                                />
                                <Heading
                                    variant="small"
                                    :level="3"
                                    title="Camera not supported"
                                    description="This browser cannot open the camera. Use the manual input field on the page instead."
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="error"
                    role="alert"
                    aria-live="assertive"
                    class="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"
                >
                    {{ error }}
                </div>

                <div class="flex flex-wrap justify-end gap-2">
                    <Button
                        v-if="hasCameraSupport && !isRunning"
                        type="button"
                        variant="secondary"
                        :disabled="isStarting"
                        @click="start"
                    >
                        {{ isStarting ? 'Starting Camera…' : 'Retry Camera' }}
                    </Button>
                    <Button
                        v-if="hasCameraSupport && isRunning && props.continuous"
                        type="button"
                        variant="secondary"
                        @click="stop"
                    >
                        Pause Camera
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        @click="isOpen = false"
                        >{{
                            props.continuous ? 'Done Scanning' : 'Close'
                        }}</Button
                    >
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
