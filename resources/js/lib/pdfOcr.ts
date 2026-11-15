import workerPath from 'pdfjs-dist/legacy/build/pdf.worker.min.mjs?url';

export type OcrPdfSession = {
    pageCount: number;
    renderPage: (
        pageNumber: number,
        signal: AbortSignal,
    ) => Promise<HTMLCanvasElement>;
    close: () => Promise<void>;
};

/** Render local PDF bytes only; no viewer, scripting, links, uploads, or external document URLs. */
export async function openOcrPdf(
    file: File,
    signal: AbortSignal,
): Promise<OcrPdfSession> {
    if (!file.size || file.size > 8 * 1024 * 1024) {
        throw new Error('Choose a non-empty PDF smaller than 8 MB.');
    }

    const data = new Uint8Array(await file.arrayBuffer());
    signal.throwIfAborted();

    if (
        !new TextDecoder('ascii').decode(data.slice(0, 1024)).includes('%PDF-')
    ) {
        throw new Error(
            'This PDF could not be read. Choose a valid, unlocked PDF or use a photo or manual entry.',
        );
    }

    const pdfjs = await import('pdfjs-dist/legacy/build/pdf.mjs');
    signal.throwIfAborted();
    pdfjs.GlobalWorkerOptions.workerSrc = new URL(
        workerPath,
        window.location.href,
    ).href;

    const createLoadingTask = (disableWorkerRendering: boolean) =>
        pdfjs.getDocument({
            data,
            enableXfa: false,
            stopAtErrors: true,
            useWorkerFetch: false,
            useWasm: false,
            useSystemFonts: true,
            isOffscreenCanvasSupported:
                !disableWorkerRendering &&
                typeof OffscreenCanvas !== 'undefined',
            isImageDecoderSupported: false,
            disableWorkerRendering,
            canvasMaxAreaInBytes: 16_000_000,
            maxImageSize: 24_000_000,
        });

    let loading = createLoadingTask(false);
    let fallbackActivated = false;
    let closing: Promise<void> | undefined;
    const close = (): Promise<void> => (closing ??= loading.destroy());
    const abort = (): void => {
        void close().catch(() => {});
    };
    signal.addEventListener('abort', abort, { once: true });

    try {
        let pdfDocument: Awaited<typeof loading.promise>;

        try {
            pdfDocument = await loading.promise;
        } catch (primaryError) {
            if (signal.aborted) {
                throw primaryError;
            }

            await loading.destroy().catch(() => {});
            fallbackActivated = true;
            loading = createLoadingTask(true);
            pdfDocument = await loading.promise;
        }

        signal.throwIfAborted();

        if (pdfDocument.numPages > 20) {
            throw new Error(
                'This PDF is too large for on-device OCR: use a PDF with at most 20 pages, or export the needed page as an image.',
            );
        }

        const renderPageWithDocument = async (
            doc: typeof pdfDocument,
            pageNumber: number,
            renderSignal: AbortSignal,
        ): Promise<HTMLCanvasElement> => {
            const page = await doc.getPage(pageNumber);
            renderSignal.throwIfAborted();
            const original = page.getViewport({ scale: 1 });

            if (
                !Number.isFinite(original.width + original.height) ||
                original.width <= 0 ||
                original.height <= 0
            ) {
                throw new Error(
                    'This PDF page could not be rendered. Use a photo or manual entry.',
                );
            }

            const scale = Math.min(
                2,
                2000 / Math.max(original.width, original.height),
            );
            const viewport = page.getViewport({ scale });
            const output = window.document.createElement('canvas');
            output.width = Math.max(1, Math.floor(viewport.width));
            output.height = Math.max(1, Math.floor(viewport.height));
            const render = page.render({
                canvas: output,
                viewport,
                annotationMode: pdfjs.AnnotationMode.DISABLE,
                background: 'white',
            });
            const cancelRender = (): void => render.cancel();
            renderSignal.addEventListener('abort', cancelRender, {
                once: true,
            });

            try {
                await render.promise;
                renderSignal.throwIfAborted();

                return output;
            } catch (error) {
                output.width = 0;
                output.height = 0;

                throw error;
            } finally {
                renderSignal.removeEventListener('abort', cancelRender);
                page.cleanup();
            }
        };

        return {
            pageCount: pdfDocument.numPages,
            close,
            async renderPage(pageNumber, renderSignal) {
                renderSignal.throwIfAborted();

                if (
                    closing ||
                    !Number.isInteger(pageNumber) ||
                    pageNumber < 1 ||
                    pageNumber > pdfDocument.numPages
                ) {
                    throw new Error(
                        'Choose a valid PDF page before reading it.',
                    );
                }

                try {
                    return await renderPageWithDocument(
                        pdfDocument,
                        pageNumber,
                        renderSignal,
                    );
                } catch (error) {
                    if (
                        fallbackActivated ||
                        closing ||
                        renderSignal.aborted ||
                        (error instanceof Error &&
                            error.message.startsWith(
                                'This PDF page could not be rendered',
                            ))
                    ) {
                        throw error;
                    }

                    fallbackActivated = true;
                    await loading.destroy().catch(() => {});
                    loading = createLoadingTask(true);
                    pdfDocument = await loading.promise;
                    renderSignal.throwIfAborted();

                    return await renderPageWithDocument(
                        pdfDocument,
                        pageNumber,
                        renderSignal,
                    );
                }
            },
        };
    } catch (error) {
        await close().catch(() => {});

        if (signal.aborted) {
            throw signal.reason;
        }

        if (
            error instanceof Error &&
            error.message.startsWith('This PDF is too large')
        ) {
            throw error;
        }

        throw new Error(
            'This PDF could not be read. Choose a valid, unlocked PDF or export the needed page as an image.',
        );
    } finally {
        signal.removeEventListener('abort', abort);
    }
}
