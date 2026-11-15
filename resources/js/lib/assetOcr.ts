import type { Worker as TesseractWorker } from 'tesseract.js';
import workerPath from 'tesseract.js/dist/worker.min.js?url';
import corePath from 'tesseract.js-core/tesseract-core-lstm.wasm.js?url';

const WORKER_IDLE_TIMEOUT_MS = 60_000;

let sharedWorker: TesseractWorker | null = null;
let sharedWorkerGeneration = 0;
let idleTimer: ReturnType<typeof setTimeout> | null = null;
let ocrQueue: Promise<void> = Promise.resolve();
let activeProgressHandler: ((message: string) => void) | null = null;
let activeErrorRejector: ((reason: unknown) => void) | null = null;
let unloadListenerRegistered = false;

function clearIdleTimer(): void {
    if (idleTimer !== null) {
        clearTimeout(idleTimer);
        idleTimer = null;
    }
}

async function terminateSharedWorker(): Promise<void> {
    sharedWorkerGeneration += 1;
    clearIdleTimer();

    const workerToStop = sharedWorker;
    sharedWorker = null;

    if (workerToStop !== null) {
        await workerToStop.terminate().catch(() => {});
    }
}

function scheduleIdleTerminate(): void {
    clearIdleTimer();
    idleTimer = setTimeout(() => {
        void terminateSharedWorker();
    }, WORKER_IDLE_TIMEOUT_MS);
}

function registerUnloadCleanup(): void {
    if (unloadListenerRegistered || typeof window === 'undefined') {
        return;
    }

    unloadListenerRegistered = true;
    window.addEventListener('beforeunload', () => {
        void terminateSharedWorker();
    });
}

async function getOrCreateWorker(
    signal: AbortSignal,
    rejectCurrent: (reason: unknown) => void,
): Promise<TesseractWorker> {
    clearIdleTimer();

    if (sharedWorker !== null) {
        return sharedWorker;
    }

    const { createWorker, OEM } = await import('tesseract.js');
    signal.throwIfAborted();

    const model = await fetch(
        new URL('/ocr/eng.traineddata', window.location.origin),
        {
            method: 'HEAD',
            signal,
        },
    );

    if (!model.ok) {
        throw new Error(
            'The OCR language model is unavailable. Use manual asset search.',
        );
    }

    const generation = ++sharedWorkerGeneration;
    registerUnloadCleanup();

    const workerFailure = new Promise<never>((_, reject) => {
        activeErrorRejector = reject;
    });

    const cancel = (): void => {
        void terminateSharedWorker();
        rejectCurrent(signal.reason);
    };
    signal.addEventListener('abort', cancel, { once: true });

    const pendingWorker = createWorker('eng', OEM.LSTM_ONLY, {
        workerPath: new URL(workerPath, window.location.href).href,
        corePath: new URL(corePath, window.location.href).href,
        langPath: new URL('/ocr', window.location.origin).href,
        workerBlobURL: false,
        gzip: false,
        cacheMethod: 'write',
        errorHandler: (reason: unknown) => activeErrorRejector?.(reason),
        logger: ({ status, progress }) => {
            if (activeProgressHandler === null) {
                return;
            }

            const stage =
                status === 'recognizing text'
                    ? 'Reading printed text'
                    : 'Preparing text recognition';
            activeProgressHandler(`${stage}: ${Math.round(progress * 100)}%`);
        },
    });

    void pendingWorker
        .then((ready) => {
            if (signal.aborted || generation !== sharedWorkerGeneration) {
                void ready.terminate().catch(() => {});
            }
        })
        .catch(() => {});

    try {
        const worker = await Promise.race([pendingWorker, workerFailure]);
        signal.throwIfAborted();

        if (generation !== sharedWorkerGeneration) {
            await worker.terminate().catch(() => {});

            throw signal.reason;
        }

        sharedWorker = worker;

        return worker;
    } catch (error) {
        await terminateSharedWorker();

        throw error;
    } finally {
        signal.removeEventListener('abort', cancel);
    }
}

export function readAssetLabel(
    image: HTMLCanvasElement,
    signal: AbortSignal,
    onProgress: (message: string) => void,
    layout: 'label' | 'document' = 'label',
): Promise<string> {
    if (typeof Worker === 'undefined' || typeof WebAssembly === 'undefined') {
        return Promise.reject(
            new Error(
                'Text recognition is unavailable in this browser. Use manual asset search.',
            ),
        );
    }

    signal.throwIfAborted();

    const run = async (): Promise<string> => {
        signal.throwIfAborted();

        const { PSM } = await import('tesseract.js');
        signal.throwIfAborted();

        let rejectRun: (reason: unknown) => void = () => {};
        const runFailure = new Promise<never>((_, reject) => {
            rejectRun = reject;
        });

        activeProgressHandler = (message: string) => {
            if (!signal.aborted) {
                onProgress(message);
            }
        };
        activeErrorRejector = rejectRun;

        const cancelActiveRun = (): void => {
            void terminateSharedWorker();
            rejectRun(signal.reason);
        };
        signal.addEventListener('abort', cancelActiveRun, { once: true });

        try {
            const worker = await getOrCreateWorker(signal, rejectRun);
            signal.throwIfAborted();

            await worker.setParameters({
                tessedit_pageseg_mode:
                    layout === 'document' ? PSM.AUTO : PSM.SPARSE_TEXT,
            });
            signal.throwIfAborted();

            const result = await Promise.race([
                worker.recognize(image),
                runFailure,
            ]);
            signal.throwIfAborted();

            return result.data.text.trim();
        } catch (error) {
            if (signal.aborted) {
                await terminateSharedWorker();
            }

            throw error;
        } finally {
            signal.removeEventListener('abort', cancelActiveRun);
            activeProgressHandler = null;
            activeErrorRejector = null;

            if (sharedWorker !== null) {
                scheduleIdleTerminate();
            }
        }
    };

    const queued = ocrQueue.then(run, run);
    ocrQueue = queued.then(
        () => undefined,
        () => undefined,
    );

    return queued;
}

export async function prepareLabelImage(
    file: File,
): Promise<HTMLCanvasElement> {
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        throw new Error(
            'Choose a JPEG, PNG, or WebP image. Convert HEIC photos to JPEG first.',
        );
    }

    if (file.size === 0 || file.size > 8 * 1024 * 1024) {
        throw new Error('Choose a non-empty image smaller than 8 MB.');
    }

    const url = URL.createObjectURL(file);

    try {
        const image = new Image();
        image.src = url;
        await image.decode();

        if (image.naturalWidth * image.naturalHeight > 24_000_000) {
            throw new Error(
                'This photo is too large. Crop it to the printed label and try again.',
            );
        }

        const scale = Math.min(
            1,
            2000 / Math.max(image.naturalWidth, image.naturalHeight),
        );
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
        canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));
        const context = canvas.getContext('2d');

        if (!context) {
            throw new Error(
                'Image processing is unavailable. Use manual asset search.',
            );
        }

        context.fillStyle = '#fff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(image, 0, 0, canvas.width, canvas.height);

        return canvas;
    } finally {
        URL.revokeObjectURL(url);
    }
}
