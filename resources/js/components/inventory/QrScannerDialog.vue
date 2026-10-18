<script setup lang="ts">
import { CameraOff, ScanLine } from 'lucide-vue-next';
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
    }>(),
    {
        buttonLabel: 'Scan QR Code',
        title: 'Scan QR code',
        description:
            'Use your phone or tablet camera. Manual entry stays available if the camera is unavailable.',
        continuous: false,
        closeOnScan: undefined,
        triggerTestId: undefined,
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
    <Button
        type="button"
        variant="outline"
        class="w-full sm:w-auto"
        :data-testid="triggerTestId"
        @click="isOpen = true"
    >
        <ScanLine aria-hidden="true" class="mr-2 size-4" />
        {{ props.buttonLabel }}
    </Button>

    <Dialog :open="isOpen" @update:open="isOpen = $event">
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

                        <div
                            class="pointer-events-none absolute inset-0 flex items-center justify-center p-6"
                        >
                            <div
                                class="size-full rounded-xl border-2 border-dashed border-white/80 shadow-[0_0_0_9999px_rgba(0,0,0,0.35)]"
                            />
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
