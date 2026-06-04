<script setup lang="ts">
import { Loader2, RotateCcw, X } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';

interface Props {
    id: string;
    resourceType: 'product' | 'booking' | 'requisition';
    resourceId: number;
    resourceName: string;
    timeout?: number; // milliseconds before auto-dismiss (default: 30000)
    onUndo?: (id: string) => Promise<boolean>;
    onDismiss?: (id: string) => void;
}

const props = withDefaults(defineProps<Props>(), {
    timeout: 30000,
});

const isUndoing = ref(false);
const isDismissed = ref(false);
const remainingTime = ref(props.timeout);
let countdownTimer: number | null = null;

const progressPercentage = computed(() => {
    return Math.max(0, (remainingTime.value / props.timeout) * 100);
});

const startCountdown = (): void => {
    remainingTime.value = props.timeout;

    countdownTimer = window.setInterval(() => {
        remainingTime.value -= 100;

        if (remainingTime.value <= 0) {
            dismiss();
        }
    }, 100);
};

const stopCountdown = (): void => {
    if (countdownTimer !== null) {
        clearInterval(countdownTimer);
        countdownTimer = null;
    }
};

const handleUndo = async (): Promise<void> => {
    if (isUndoing.value) {
        return;
    }

    isUndoing.value = true;
    stopCountdown();

    try {
        if (props.onUndo) {
            const success = await props.onUndo(props.id);

            if (success) {
                isDismissed.value = true;
            } else {
                // Undo failed, keep toast visible
                isUndoing.value = false;
                startCountdown();
            }
        }
    } catch (error) {
        console.error('Undo failed:', error);
        isUndoing.value = false;
        startCountdown();
    }
};

const dismiss = (): void => {
    stopCountdown();
    isDismissed.value = true;
    props.onDismiss?.(props.id);
};

onMounted(() => {
    startCountdown();
});
</script>

<template>
    <div
        v-if="!isDismissed"
        class="relative overflow-hidden rounded-lg border border-border/60 bg-card p-4 shadow-lg"
        role="alert"
    >
        <!-- Progress bar -->
        <div
            class="absolute bottom-0 left-0 h-1 bg-primary/20 transition-all duration-100"
            :style="{ width: `${progressPercentage}%` }"
        />

        <div class="flex items-start gap-3">
            <div
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30"
            >
                <RotateCcw
                    v-if="!isUndoing"
                    class="h-4 w-4 text-amber-600 dark:text-amber-400"
                />
                <Loader2
                    v-else
                    class="h-4 w-4 animate-spin text-amber-600 dark:text-amber-400"
                />
            </div>

            <div class="flex-1">
                <div class="font-medium">
                    {{
                        resourceType === 'product'
                            ? 'Product'
                            : resourceType === 'booking'
                              ? 'Booking'
                              : 'Requisition'
                    }}
                    deleted
                </div>
                <div class="text-sm text-muted-foreground">
                    <span class="font-medium">{{ resourceName }}</span> has been
                    moved to trash.
                    <span class="text-xs"
                        >({{ Math.ceil(remainingTime / 1000) }}s)</span
                    >
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    variant="secondary"
                    size="sm"
                    :disabled="isUndoing"
                    @click="handleUndo"
                >
                    {{ isUndoing ? 'Restoring...' : 'Undo' }}
                </Button>

                <Button
                    variant="ghost"
                    size="sm"
                    class="h-8 w-8 p-0"
                    @click="dismiss"
                >
                    <X class="h-4 w-4" />
                </Button>
            </div>
        </div>
    </div>
</template>
