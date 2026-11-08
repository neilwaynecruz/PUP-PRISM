<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { prepareLabelImage, readAssetLabel } from '@/lib/assetOcr';

const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ search: [value: string]; closed: [] }>();
const busy = ref(false);
const status = ref('');
const error = ref('');
const extracted = ref('');
const identifier = ref('');
const suggestions = computed(() =>
    [
        ...new Set(
            extracted.value
                .split(/\r?\n/)
                .map((line) => line.trim())
                .filter(Boolean),
        ),
    ].slice(0, 10),
);
let controller: AbortController | undefined;

function cancel(): void {
    controller?.abort();
    controller = undefined;
    busy.value = false;
}

watch(open, () => {
    cancel();
    status.value = '';
    error.value = '';
    extracted.value = '';
    identifier.value = '';
});
onBeforeUnmount(cancel);

async function readImage(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';

    if (!file) {
        return;
    }

    cancel();
    const session = new AbortController();
    controller = session;
    busy.value = true;
    error.value = '';
    extracted.value = '';
    identifier.value = '';
    status.value = 'Preparing image…';
    let canvas: HTMLCanvasElement | undefined;

    try {
        canvas = await prepareLabelImage(file);
        session.signal.throwIfAborted();
        extracted.value = await readAssetLabel(
            canvas,
            session.signal,
            (message) => {
                status.value = message;
            },
        );

        if (!extracted.value) {
            error.value =
                'No readable text found. Try a closer, well-lit photo or enter the identifier below.';
        }

        status.value = extracted.value
            ? 'Text ready. Review it before searching.'
            : '';
    } catch (caught) {
        if (!session.signal.aborted) {
            error.value =
                caught instanceof Error &&
                /Choose|too large|unavailable/.test(caught.message)
                    ? caught.message
                    : 'The image could not be read. Try a clear JPEG or PNG, or enter the identifier below. OCR files must be reachable from this application.';
            status.value = '';
        }
    } finally {
        if (canvas) {
            canvas.width = 0;
            canvas.height = 0;
        }

        if (controller === session) {
            busy.value = false;
            controller = undefined;
        }
    }
}

function search(): void {
    const value = identifier.value.trim();

    if (!value || busy.value) {
        return;
    }

    emit('search', value);
    open.value = false;
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-lg"
            @close-auto-focus="
                (event) => {
                    event.preventDefault();
                    emit('closed');
                }
            "
        >
            <DialogHeader>
                <DialogTitle>Read printed asset tag</DialogTitle>
                <DialogDescription
                    >Read a tag or serial number from a photo, review the text,
                    then search existing assets.</DialogDescription
                >
            </DialogHeader>
            <div class="grid min-w-0 gap-4">
                <p class="text-sm text-muted-foreground">
                    Photos are processed on this device and are not uploaded or
                    saved. OCR may confuse O/0 or I/1; always check the printed
                    label. PDFs are not supported.
                </p>
                <div class="grid gap-2">
                    <label for="asset-label-photo" class="text-sm font-medium"
                        >Choose a label photo</label
                    >
                    <input
                        id="asset-label-photo"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        :disabled="busy"
                        class="min-h-11 w-full min-w-0 rounded-md border border-input p-2 text-sm file:mr-2 file:rounded file:border-0 file:bg-muted file:px-3 file:py-1 focus-visible:ring-2 focus-visible:ring-ring"
                        @change="readImage"
                    />
                    <label for="asset-label-camera" class="text-sm font-medium"
                        >Or take a photo on a supported phone</label
                    >
                    <input
                        id="asset-label-camera"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        capture="environment"
                        :disabled="busy"
                        class="min-h-11 w-full min-w-0 rounded-md border border-input p-2 text-sm file:mr-2 file:rounded file:border-0 file:bg-muted file:px-3 file:py-1 focus-visible:ring-2 focus-visible:ring-ring"
                        @change="readImage"
                    />
                    <p class="text-xs text-muted-foreground">
                        JPEG, PNG, or WebP, up to 8 MB. Crop tightly around the
                        label. If the camera is unavailable or permission is
                        denied, choose an existing image or type below.
                    </p>
                </div>
                <p
                    role="status"
                    aria-live="polite"
                    class="text-sm text-muted-foreground"
                >
                    {{ status }}
                </p>
                <p v-if="error" role="alert" class="text-sm text-destructive">
                    {{ error }}
                </p>
                <div v-if="extracted" class="grid min-w-0 gap-2">
                    <p class="text-sm font-medium">
                        Detected text — choose a line, then correct it below
                    </p>
                    <pre
                        class="max-h-32 overflow-auto rounded-md bg-muted p-3 text-sm break-words whitespace-pre-wrap"
                        >{{ extracted }}</pre
                    >
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-for="line in suggestions"
                            :key="line"
                            variant="outline"
                            class="min-h-11 max-w-full break-all whitespace-normal"
                            @click="identifier = line.slice(0, 120)"
                            >{{ line }}</Button
                        >
                    </div>
                </div>
                <form class="grid gap-3" @submit.prevent="search">
                    <label for="ocr-identifier" class="text-sm font-medium"
                        >Asset tag or serial number (editable)</label
                    >
                    <Input
                        id="ocr-identifier"
                        v-model="identifier"
                        :maxlength="120"
                        autocomplete="off"
                        class="min-h-11"
                    />
                    <p class="text-xs text-muted-foreground">
                        Search requires a connection to the application. Offline
                        OCR is not guaranteed. Nothing is created or changed;
                        select the correct record from the results.
                    </p>
                    <Button
                        type="submit"
                        :disabled="busy || !identifier.trim()"
                        class="min-h-11"
                        >Search existing assets</Button
                    >
                </form>
                <Button
                    variant="outline"
                    class="min-h-11"
                    @click="open = false"
                    >{{
                        busy
                            ? 'Cancel and use manual search'
                            : 'Close and use manual search'
                    }}</Button
                >
            </div>
        </DialogContent>
    </Dialog>
</template>
