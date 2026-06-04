<script setup lang="ts">
import { CameraOff, ScanLine } from 'lucide-vue-next';
import { ref, watch } from 'vue';
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

withDefaults(
    defineProps<{
        buttonLabel?: string;
        title?: string;
        description?: string;
    }>(),
    {
        buttonLabel: 'Scan QR',
        title: 'Scan QR code',
        description:
            'Use your phone or tablet camera. Manual entry stays available if the camera is unavailable.',
    },
);

const emit = defineEmits<{
    scanned: [value: string];
}>();

const isOpen = ref(false);

const {
    canvasRef,
    error,
    hasCameraSupport,
    isRunning,
    isStarting,
    start,
    status,
    stop,
    videoRef,
} = useQrScanner({
    onDetected: (value) => {
        emit('scanned', value);
        isOpen.value = false;
    },
});

watch(isOpen, async (open) => {
    if (!open) {
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
        @click="isOpen = true"
    >
        <ScanLine class="mr-2 size-4" />
        {{ buttonLabel }}
    </Button>

    <Dialog :open="isOpen" @update:open="isOpen = $event">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>

            <div class="grid gap-4">
                <div class="flex flex-wrap items-center gap-2">
                    <Badge v-if="isRunning" variant="default"
                        >Live camera</Badge
                    >
                    <Badge v-else-if="isStarting" variant="secondary"
                        >Starting</Badge
                    >
                    <Badge v-else variant="outline"
                        >Manual fallback ready</Badge
                    >
                    <span class="text-sm text-muted-foreground">{{
                        status
                    }}</span>
                </div>

                <div
                    class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-muted/30 dark:border-sidebar-border"
                >
                    <div class="relative aspect-4/3">
                        <video
                            ref="videoRef"
                            class="size-full object-cover"
                            playsinline
                            muted
                        />
                        <canvas ref="canvasRef" class="hidden" />

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
                                    class="mx-auto size-8 text-muted-foreground"
                                />
                                <Heading
                                    variant="small"
                                    title="Camera not supported"
                                    description="This browser cannot open the camera. Use the manual input field on the page instead."
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="error"
                    class="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"
                >
                    {{ error }}
                </div>

                <div class="flex flex-wrap justify-end gap-2">
                    <Button
                        v-if="hasCameraSupport && !isRunning"
                        type="button"
                        variant="secondary"
                        @click="start"
                    >
                        Retry camera
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        @click="isOpen = false"
                        >Close</Button
                    >
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
