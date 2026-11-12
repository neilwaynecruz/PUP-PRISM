<script setup lang="ts">
import {
    Camera,
    ChevronLeft,
    ChevronRight,
    FileText,
    FileUp,
    Lightbulb,
    Loader2,
    Sparkles,
} from 'lucide-vue-next';
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
import { isValidDocumentDate, suggestProcurementFields } from '@/lib/ocrReview';
import type { ProcurementOcrDraft } from '@/lib/ocrReview';
import type { OcrPdfSession } from '@/lib/pdfOcr';

const props = withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        fieldLabel?: string;
        submitLabel?: string;
        help?: string;
        multiline?: boolean;
        document?: boolean;
    }>(),
    {
        title: 'Read printed asset tag',
        description:
            'Read a tag or serial number from a photo, review the text, then search existing assets.',
        fieldLabel: 'Asset tag or serial number (editable)',
        submitLabel: 'Search existing assets',
        help: 'Search requires a connection to the application. Offline OCR is not guaranteed. Nothing is created or changed; select the correct record from the results.',
        multiline: false,
        document: false,
    },
);

const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{
    search: [value: string];
    document: [value: ProcurementOcrDraft];
    closed: [];
}>();

const draft = ref<ProcurementOcrDraft>({
    documentType: 'quotation',
    reference: '',
    documentDate: '',
    supplier: '',
    productCode: '',
});
const targetField = ref<
    'reference' | 'documentDate' | 'supplier' | 'productCode'
>('reference');
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

const activeStep = computed(() => {
    if (busy.value) {
        return 2;
    }

    if (
        extracted.value ||
        identifier.value ||
        (props.document &&
            (draft.value.reference ||
                draft.value.documentDate ||
                draft.value.supplier ||
                draft.value.productCode))
    ) {
        return 3;
    }

    return 1;
});

let controller: AbortController | undefined;
let pdfSession: OcrPdfSession | undefined;
const pdfPageCount = ref(0);
const pdfPage = ref(1);
const pdfPreview = ref('');

function closePdf(): void {
    const previous = pdfSession;
    pdfSession = undefined;
    pdfPageCount.value = 0;

    if (pdfPreview.value.startsWith('blob:')) {
        URL.revokeObjectURL(pdfPreview.value);
    }

    pdfPreview.value = '';
    void previous?.close().catch(() => {});
}

function cancel(): void {
    controller?.abort();
    controller = undefined;
    busy.value = false;
}

watch(open, () => {
    cancel();
    closePdf();
    status.value = '';
    error.value = '';
    extracted.value = '';
    identifier.value = '';
    draft.value = {
        documentType: 'quotation',
        reference: '',
        documentDate: '',
        supplier: '',
        productCode: '',
    };
});

onBeforeUnmount(() => {
    cancel();
    closePdf();
});

watch(pdfPage, () => {
    if (!pdfSession) {
        return;
    }

    extracted.value = '';
    identifier.value = '';

    if (pdfPreview.value.startsWith('blob:')) {
        URL.revokeObjectURL(pdfPreview.value);
    }

    pdfPreview.value = '';
    draft.value = {
        documentType: draft.value.documentType,
        reference: '',
        documentDate: '',
        supplier: '',
        productCode: '',
    };
    status.value =
        'PDF page changed. Read the selected page before applying suggestions.';
});

async function readFile(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';

    if (!file) {
        return;
    }

    closePdf();
    pdfPage.value = 1;
    await recognize(file);
}

async function recognize(file?: File): Promise<void> {
    cancel();
    const session = new AbortController();
    controller = session;
    busy.value = true;
    error.value = '';
    extracted.value = '';
    identifier.value = '';
    status.value = 'Preparing image…';
    draft.value = {
        documentType: draft.value.documentType,
        reference: '',
        documentDate: '',
        supplier: '',
        productCode: '',
    };
    let canvas: HTMLCanvasElement | undefined;

    try {
        if (
            file &&
            (file.type === 'application/pdf' || /\.pdf$/i.test(file.name))
        ) {
            status.value = 'Opening PDF locally…';
            const { openOcrPdf } = await import('@/lib/pdfOcr');
            session.signal.throwIfAborted();
            const opened = await openOcrPdf(file, session.signal);

            if (
                session.signal.aborted ||
                controller !== session ||
                !open.value
            ) {
                await opened.close();

                return;
            }

            pdfSession = opened;
            pdfPageCount.value = opened.pageCount;
        }

        if (pdfSession) {
            status.value = `Rendering PDF page ${pdfPage.value} of ${pdfPageCount.value}…`;
            canvas = await pdfSession.renderPage(pdfPage.value, session.signal);
            session.signal.throwIfAborted();
            pdfPreview.value = canvas.toDataURL('image/jpeg', 0.75);
        } else if (file) {
            canvas = await prepareLabelImage(file);
        } else {
            throw new Error('Choose an image or PDF before reading it.');
        }

        session.signal.throwIfAborted();
        const text = await readAssetLabel(
            canvas,
            session.signal,
            (message) => {
                status.value = message;
            },
            props.document ? 'document' : 'label',
        );
        session.signal.throwIfAborted();

        if (controller !== session || !open.value) {
            return;
        }

        extracted.value = text;

        if (props.document) {
            draft.value = {
                documentType: draft.value.documentType,
                ...suggestProcurementFields(text),
            };
        }

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
                /Choose|too large|unavailable|PDF/.test(caught.message)
                    ? caught.message
                    : 'The image could not be read. Try a clear JPEG or PNG, or enter the identifier below. OCR files must be reachable from this application.';
            status.value = '';
        }
    } finally {
        if (canvas) {
            const ctx = canvas.getContext('2d');
            ctx?.clearRect(0, 0, canvas.width, canvas.height);
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
    if (props.document) {
        if (busy.value || !isValidDocumentDate(draft.value.documentDate)) {
            return;
        }

        emit('document', { ...draft.value });
        open.value = false;

        return;
    }

    const value = identifier.value.trim();

    if (!value || busy.value) {
        return;
    }

    emit('search', value);
    open.value = false;
}

function chooseLine(line: string): void {
    if (props.document) {
        draft.value[targetField.value] = line.slice(
            0,
            targetField.value === 'supplier' ? 120 : 64,
        );
    } else if (props.multiline) {
        identifier.value = [identifier.value, line.slice(0, 64)]
            .filter(Boolean)
            .join('\n');
    } else {
        identifier.value = line.slice(0, 120);
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="max-h-[calc(100dvh-2rem)] overflow-y-auto p-4 pr-4 sm:max-w-lg sm:p-6 sm:pr-6"
            @close-auto-focus="
                (event) => {
                    event.preventDefault();
                    emit('closed');
                }
            "
        >
            <DialogHeader class="space-y-1.5 pr-10 pb-1">
                <div class="flex items-center gap-2">
                    <span
                        class="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <FileText
                            v-if="document"
                            class="size-4"
                            aria-hidden="true"
                        />
                        <Sparkles v-else class="size-4" aria-hidden="true" />
                    </span>
                    <DialogTitle
                        class="text-base leading-none font-semibold break-words"
                    >
                        {{ title }}
                    </DialogTitle>
                </div>
                <DialogDescription
                    class="text-xs leading-relaxed text-muted-foreground"
                >
                    {{ description }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid min-w-0 gap-4">
                <!-- Step progress navigation -->
                <nav
                    aria-label="OCR scanning stages"
                    class="flex items-center justify-between rounded-lg border border-border/60 bg-muted/40 px-3 py-2 text-xs"
                >
                    <div
                        class="flex items-center gap-1.5 font-medium transition-colors"
                        :class="
                            activeStep === 1
                                ? 'font-semibold text-primary'
                                : 'text-muted-foreground'
                        "
                    >
                        <span
                            class="flex size-5 items-center justify-center rounded-full text-[11px] font-semibold"
                            :class="
                                activeStep === 1
                                    ? 'bg-primary text-primary-foreground shadow-xs'
                                    : 'border border-border bg-background text-muted-foreground'
                            "
                        >
                            1
                        </span>
                        <span>Source</span>
                    </div>
                    <span class="font-mono text-muted-foreground/40">→</span>
                    <div
                        class="flex items-center gap-1.5 font-medium transition-colors"
                        :class="
                            activeStep === 2
                                ? 'font-semibold text-primary'
                                : 'text-muted-foreground'
                        "
                    >
                        <span
                            class="flex size-5 items-center justify-center rounded-full text-[11px] font-semibold"
                            :class="
                                activeStep === 2
                                    ? 'bg-primary text-primary-foreground shadow-xs'
                                    : 'border border-border bg-background text-muted-foreground'
                            "
                        >
                            2
                        </span>
                        <span>Scan</span>
                    </div>
                    <span class="font-mono text-muted-foreground/40">→</span>
                    <div
                        class="flex items-center gap-1.5 font-medium transition-colors"
                        :class="
                            activeStep === 3
                                ? 'font-semibold text-primary'
                                : 'text-muted-foreground'
                        "
                    >
                        <span
                            class="flex size-5 items-center justify-center rounded-full text-[11px] font-semibold"
                            :class="
                                activeStep === 3
                                    ? 'bg-primary text-primary-foreground shadow-xs'
                                    : 'border border-border bg-background text-muted-foreground'
                            "
                        >
                            3
                        </span>
                        <span>Review</span>
                    </div>
                </nav>

                <!-- Recognition tips callout -->
                <div
                    class="flex items-start gap-2.5 rounded-lg border border-amber-500/25 bg-amber-500/10 p-3 text-xs text-muted-foreground dark:border-amber-400/20 dark:bg-amber-500/10"
                >
                    <Lightbulb
                        class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400"
                        aria-hidden="true"
                    />
                    <div class="min-w-0 space-y-0.5 leading-relaxed">
                        <span class="font-medium text-foreground">
                            Tips for best recognition:
                        </span>
                        <p>
                            Ensure balanced lighting without glare, hold printed
                            text horizontal, and crop closely around the code or
                            label.
                        </p>
                    </div>
                </div>

                <p class="text-xs text-muted-foreground">
                    Images and selected PDF pages are processed on this device,
                    not uploaded or saved. OCR may confuse O/0 or I/1; always
                    check the original. Nothing is submitted automatically.
                </p>

                <!-- File & Camera inputs -->
                <div
                    class="grid min-w-0 gap-3 rounded-lg border border-border/80 bg-card p-3 shadow-xs"
                >
                    <div class="grid gap-1.5">
                        <label
                            for="asset-label-photo"
                            class="flex items-center gap-1.5 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            <FileUp
                                class="size-3.5 text-primary"
                                aria-hidden="true"
                            />
                            {{
                                document
                                    ? 'Choose a document image or PDF'
                                    : 'Choose a label photo or PDF'
                            }}
                        </label>
                        <input
                            id="asset-label-photo"
                            type="file"
                            accept="image/jpeg,image/png,image/webp,application/pdf,.pdf"
                            :disabled="busy"
                            class="min-h-11 w-full min-w-0 rounded-md border border-input bg-background/50 p-2 text-sm transition-colors file:mr-2 file:rounded file:border-0 file:bg-muted file:px-3 file:py-1 file:text-xs file:font-semibold file:text-foreground hover:bg-muted/30 focus-visible:ring-2 focus-visible:ring-ring"
                            @change="readFile"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <label
                            for="asset-label-camera"
                            class="flex items-center gap-1.5 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            <Camera
                                class="size-3.5 text-primary"
                                aria-hidden="true"
                            />
                            Or take a photo on a supported phone
                        </label>
                        <input
                            id="asset-label-camera"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            capture="environment"
                            :disabled="busy"
                            class="min-h-11 w-full min-w-0 rounded-md border border-input bg-background/50 p-2 text-sm transition-colors file:mr-2 file:rounded file:border-0 file:bg-muted file:px-3 file:py-1 file:text-xs file:font-semibold file:text-foreground hover:bg-muted/30 focus-visible:ring-2 focus-visible:ring-ring"
                            @change="readFile"
                        />
                    </div>
                    <p class="text-xs text-muted-foreground">
                        JPEG, PNG, WebP, or unlocked PDF, up to 8 MB. PDFs may
                        contain up to 20 pages; read one selected page at a
                        time. PDF annotations, interactive forms, and
                        attachments are not processed. Crop photos tightly
                        around the text. If the camera is unavailable, choose a
                        file or type below.
                    </p>
                </div>

                <!-- PDF Multi-page Navigation & Preview -->
                <div
                    v-if="pdfPageCount"
                    class="grid min-w-0 gap-3 rounded-lg border border-border bg-card p-3 shadow-xs"
                >
                    <div class="flex items-center justify-between">
                        <label for="ocr-pdf-page" class="text-sm font-semibold">
                            PDF page (1–{{ pdfPageCount }})
                        </label>
                        <span class="font-mono text-xs text-muted-foreground">
                            Page {{ pdfPage }} of {{ pdfPageCount }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            class="size-11 shrink-0"
                            :disabled="busy || pdfPage <= 1"
                            aria-label="Previous PDF page"
                            @click="pdfPage = Math.max(1, pdfPage - 1)"
                        >
                            <ChevronLeft class="size-4" aria-hidden="true" />
                        </Button>
                        <input
                            id="ocr-pdf-page"
                            v-model.number="pdfPage"
                            type="number"
                            min="1"
                            :max="pdfPageCount"
                            step="1"
                            :disabled="busy"
                            class="min-h-11 w-full rounded-md border border-input bg-background px-3 text-center text-sm font-medium"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            class="size-11 shrink-0"
                            :disabled="busy || pdfPage >= pdfPageCount"
                            aria-label="Next PDF page"
                            @click="
                                pdfPage = Math.min(pdfPageCount, pdfPage + 1)
                            "
                        >
                            <ChevronRight class="size-4" aria-hidden="true" />
                        </Button>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        class="min-h-11 w-full"
                        :disabled="busy"
                        @click="recognize()"
                    >
                        Read selected PDF page
                    </Button>
                    <div
                        v-if="pdfPreview"
                        class="relative overflow-hidden rounded-md border border-border bg-white dark:bg-muted/20"
                    >
                        <img
                            :src="pdfPreview"
                            :alt="`Rendered PDF page ${pdfPage} for visual review`"
                            class="max-h-48 w-full object-contain"
                        />
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Changing the page clears previous suggestions. Check
                        this preview against your source PDF; OCR does not
                        verify signatures or document authenticity.
                    </p>
                </div>

                <!-- Processing state -->
                <div
                    v-if="busy"
                    class="flex flex-col items-center justify-center gap-2.5 rounded-lg border border-primary/20 bg-primary/5 p-4 text-center"
                >
                    <div
                        class="flex items-center gap-2 text-sm font-semibold text-primary"
                    >
                        <Loader2
                            class="size-4 animate-spin"
                            aria-hidden="true"
                        />
                        <span>Optical Recognition in Progress…</span>
                    </div>
                    <div
                        class="h-1.5 w-full max-w-xs overflow-hidden rounded-full bg-primary/20"
                    >
                        <div
                            class="h-full w-2/3 animate-pulse rounded-full bg-primary"
                        />
                    </div>
                </div>

                <p
                    role="status"
                    aria-live="polite"
                    class="text-sm text-muted-foreground"
                >
                    {{ status }}
                </p>
                <p
                    v-if="error"
                    role="alert"
                    class="text-sm font-medium text-destructive"
                >
                    {{ error }}
                </p>

                <!-- Extracted candidates & suggestions -->
                <div
                    v-if="extracted"
                    class="grid min-w-0 gap-2.5 rounded-lg border border-border bg-card p-3 shadow-xs"
                >
                    <div class="flex items-center justify-between">
                        <p
                            class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            Detected text — choose a line, then correct it below
                        </p>
                        <span
                            v-if="suggestions.length"
                            class="rounded bg-muted px-1.5 py-0.5 font-mono text-[11px] text-muted-foreground"
                        >
                            {{ suggestions.length }} candidates
                        </span>
                    </div>
                    <pre
                        class="max-h-32 overflow-auto rounded-md border border-border/60 bg-muted/50 p-3 font-mono text-xs break-words whitespace-pre-wrap text-foreground select-all"
                        >{{ extracted }}</pre
                    >
                    <div v-if="suggestions.length" class="space-y-1.5">
                        <p
                            class="text-[11px] font-medium text-muted-foreground"
                        >
                            Candidate suggestions:
                        </p>
                        <div class="flex flex-wrap gap-1.5">
                            <Button
                                v-for="line in suggestions"
                                :key="line"
                                type="button"
                                variant="outline"
                                size="sm"
                                class="min-h-9 max-w-full font-mono text-xs break-all whitespace-normal hover:border-primary/50 hover:bg-primary/10 hover:text-primary"
                                @click="chooseLine(line)"
                            >
                                {{ line }}
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Form Inputs -->
                <form class="grid gap-3" @submit.prevent="search">
                    <template v-if="document">
                        <div class="grid gap-1.5">
                            <label
                                for="ocr-document-type"
                                class="text-sm font-medium"
                            >
                                Document type
                            </label>
                            <select
                                id="ocr-document-type"
                                v-model="draft.documentType"
                                class="min-h-11 w-full rounded-md border border-input bg-background px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <option value="quotation">
                                    Supplier quotation
                                </option>
                                <option value="delivery_receipt">
                                    Delivery receipt
                                </option>
                                <option value="invoice">Invoice</option>
                            </select>
                        </div>
                        <div class="grid gap-1.5">
                            <label
                                for="ocr-target-field"
                                class="text-sm font-medium"
                            >
                                Put a detected line into
                            </label>
                            <select
                                id="ocr-target-field"
                                v-model="targetField"
                                class="min-h-11 w-full rounded-md border border-input bg-background px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <option value="reference">
                                    Document reference
                                </option>
                                <option value="documentDate">
                                    Document date
                                </option>
                                <option value="supplier">Supplier name</option>
                                <option value="productCode">
                                    Product code
                                </option>
                            </select>
                        </div>
                        <div class="grid gap-1.5">
                            <label
                                for="ocr-reference"
                                class="text-sm font-medium"
                            >
                                Document reference (editable)
                            </label>
                            <Input
                                id="ocr-reference"
                                v-model="draft.reference"
                                :maxlength="64"
                                class="min-h-11 font-mono text-sm"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <label
                                for="ocr-document-date"
                                class="text-sm font-medium"
                            >
                                Document date — YYYY-MM-DD (not delivery date)
                            </label>
                            <Input
                                id="ocr-document-date"
                                v-model="draft.documentDate"
                                :maxlength="10"
                                class="min-h-11 font-mono text-sm"
                                :aria-invalid="
                                    !isValidDocumentDate(draft.documentDate)
                                "
                            />
                            <p
                                v-if="!isValidDocumentDate(draft.documentDate)"
                                role="alert"
                                class="text-sm text-destructive"
                            >
                                Check the date. Use an unambiguous valid
                                YYYY-MM-DD date, or leave it blank.
                            </p>
                        </div>
                        <div class="grid gap-1.5">
                            <label
                                for="ocr-supplier"
                                class="text-sm font-medium"
                            >
                                Supplier name to match
                            </label>
                            <Input
                                id="ocr-supplier"
                                v-model="draft.supplier"
                                :maxlength="120"
                                class="min-h-11"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <label
                                for="ocr-product-code"
                                class="text-sm font-medium"
                            >
                                Product code to search
                            </label>
                            <Input
                                id="ocr-product-code"
                                v-model="draft.productCode"
                                :maxlength="64"
                                class="min-h-11 font-mono text-sm"
                            />
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Unmatched fields stay unresolved. No quantities,
                            prices, units, taxes, or totals are extracted.
                            Review the source image yourself.
                        </p>
                    </template>
                    <template v-else>
                        <div class="grid gap-1.5">
                            <label
                                for="ocr-identifier"
                                class="text-sm font-medium"
                            >
                                {{ fieldLabel }}
                            </label>
                            <textarea
                                v-if="multiline"
                                id="ocr-identifier"
                                v-model="identifier"
                                rows="4"
                                maxlength="4000"
                                class="min-h-11 w-full rounded-md border border-input bg-background p-3 font-mono text-sm focus-visible:ring-2 focus-visible:ring-ring"
                            />
                            <Input
                                v-else
                                id="ocr-identifier"
                                v-model="identifier"
                                :maxlength="120"
                                autocomplete="off"
                                class="min-h-11 font-mono text-sm"
                            />
                            <p class="text-xs text-muted-foreground">
                                {{ help }}
                            </p>
                        </div>
                    </template>
                    <Button
                        type="submit"
                        :disabled="
                            busy ||
                            (document
                                ? !isValidDocumentDate(draft.documentDate) ||
                                  !Object.values(draft)
                                      .slice(1)
                                      .some((value) => value.trim())
                                : !identifier.trim())
                        "
                        class="min-h-11 w-full"
                    >
                        {{ submitLabel }}
                    </Button>
                </form>
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-11 w-full"
                    @click="open = false"
                >
                    {{
                        busy
                            ? 'Cancel and use manual search'
                            : 'Close and use manual search'
                    }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
