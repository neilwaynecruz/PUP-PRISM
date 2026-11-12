import workerPath from 'tesseract.js/dist/worker.min.js?url';
import corePath from 'tesseract.js-core/tesseract-core-lstm.wasm.js?url';

export async function readAssetLabel(
    image: HTMLCanvasElement,
    signal: AbortSignal,
    onProgress: (message: string) => void,
    layout: 'label' | 'document' = 'label',
): Promise<string> {
    if (typeof Worker === 'undefined' || typeof WebAssembly === 'undefined') {
        throw new Error(
            'Text recognition is unavailable in this browser. Use manual asset search.',
        );
    }

    const { createWorker, OEM, PSM } = await import('tesseract.js');
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

    let rejectWorker: (reason: unknown) => void = () => {};
    const workerFailure = new Promise<never>((_, reject) => {
        rejectWorker = reject;
    });
    const cancel = () => rejectWorker(signal.reason);
    signal.addEventListener('abort', cancel, { once: true });
    const pendingWorker = createWorker('eng', OEM.LSTM_ONLY, {
        workerPath: new URL(workerPath, window.location.href).href,
        corePath: new URL(corePath, window.location.href).href,
        langPath: new URL('/ocr', window.location.origin).href,
        workerBlobURL: false,
        gzip: false,
        cacheMethod: 'none',
        errorHandler: (reason: unknown) => rejectWorker(reason),
        logger: ({ status, progress }) => {
            if (!signal.aborted) {
                const stage =
                    status === 'recognizing text'
                        ? 'Reading printed text'
                        : 'Preparing text recognition';
                onProgress(`${stage}: ${Math.round(progress * 100)}%`);
            }
        },
    });
    void pendingWorker
        .then((ready) => {
            if (signal.aborted) {
                void ready.terminate();
            }
        })
        .catch(() => {});

    try {
        const worker = await Promise.race([pendingWorker, workerFailure]);
        signal.throwIfAborted();

        try {
            await worker.setParameters({
                tessedit_pageseg_mode:
                    layout === 'document' ? PSM.AUTO : PSM.SPARSE_TEXT,
            });
            const result = await Promise.race([
                worker.recognize(image),
                workerFailure,
            ]);
            signal.throwIfAborted();

            return result.data.text.trim();
        } finally {
            await worker.terminate();
        }
    } finally {
        signal.removeEventListener('abort', cancel);
    }
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
