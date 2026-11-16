import type jsQR from 'jsqr';
import { onBeforeUnmount, ref, useTemplateRef } from 'vue';

type JsQrFunction = typeof jsQR;

type UseQrScannerOptions = {
    onDetected: (value: string) => void;
    continuous?: boolean | (() => boolean);
    cooldownMs?: number;
};

const SCAN_INTERVAL_MS = 85;
const MAX_DECODE_DIMENSION = 480;
const ROI_RATIO = 0.78;

let jsQrPromise: Promise<JsQrFunction> | null = null;

function loadJsQr(): Promise<JsQrFunction> {
    jsQrPromise ??= import('jsqr').then((module) => module.default);

    return jsQrPromise;
}

export function useQrScanner(options: UseQrScannerOptions) {
    const videoRef = useTemplateRef<HTMLVideoElement>('videoRef');
    const canvasRef = useTemplateRef<HTMLCanvasElement>('canvasRef');

    const isContinuous = (): boolean =>
        typeof options.continuous === 'function'
            ? options.continuous()
            : Boolean(options.continuous);

    const isRunning = ref(false);
    const isStarting = ref(false);
    const status = ref('Ready to scan a QR code.');
    const error = ref<string | null>(null);
    const lastDetectedValue = ref<string | null>(null);
    const totalDetections = ref(0);

    let stream: MediaStream | null = null;
    let frameRequestId: number | null = null;
    let lastDetectionAt = 0;
    let lastFrameDecodedAt = 0;
    let decodeIteration = 0;
    let isDecoding = false;
    let jsQrDecoder: JsQrFunction | null = null;
    let cameraSession = 0;

    const hasCameraSupport =
        typeof navigator !== 'undefined' &&
        !!navigator.mediaDevices?.getUserMedia;
    const isSecureContext =
        typeof window === 'undefined' || window.isSecureContext;

    function cameraErrorMessage(caughtError: unknown): string {
        const errorName =
            typeof DOMException !== 'undefined' &&
            caughtError instanceof DOMException
                ? caughtError.name
                : caughtError instanceof Error
                  ? caughtError.name
                  : '';

        if (['NotAllowedError', 'PermissionDeniedError'].includes(errorName)) {
            return 'Camera access was blocked. Allow camera permission or enter the code manually.';
        }

        if (['NotFoundError', 'DevicesNotFoundError'].includes(errorName)) {
            return 'No camera was found on this device. Enter the code manually.';
        }

        if (['NotReadableError', 'TrackStartError'].includes(errorName)) {
            return 'The camera is unavailable or already in use by another app. Close the other app and retry, or enter the code manually.';
        }

        if (errorName === 'OverconstrainedError') {
            return 'No compatible camera is available. Enter the code manually.';
        }

        if (['SecurityError', 'TypeError'].includes(errorName)) {
            return 'Camera scanning requires a secure HTTPS connection. Enter the code manually.';
        }

        return 'The camera could not start. Retry the camera, or enter the code manually.';
    }

    function stop(): void {
        cameraSession += 1;
        isDecoding = false;
        lastFrameDecodedAt = 0;
        decodeIteration = 0;

        if (frameRequestId !== null) {
            window.cancelAnimationFrame(frameRequestId);
            frameRequestId = null;
        }

        if (stream) {
            stream.getTracks().forEach((track) => track.stop());
            stream = null;
        }

        if (videoRef.value) {
            videoRef.value.pause();
            videoRef.value.srcObject = null;
        }

        isRunning.value = false;
        isStarting.value = false;
    }

    function triggerScanFeedback(): void {
        if (typeof navigator !== 'undefined' && 'vibrate' in navigator) {
            navigator.vibrate?.(35);
        }
    }

    function shouldIgnoreDetection(value: string): boolean {
        const cooldownMs = options.cooldownMs ?? 1500;

        return (
            value === lastDetectedValue.value &&
            Date.now() - lastDetectionAt < cooldownMs
        );
    }

    function scanFrame(timestamp: number = performance.now()): void {
        if (
            !videoRef.value ||
            !canvasRef.value ||
            !isRunning.value ||
            !jsQrDecoder
        ) {
            return;
        }

        if (
            (typeof document !== 'undefined' && document.hidden) ||
            isDecoding ||
            (lastFrameDecodedAt > 0 &&
                timestamp - lastFrameDecodedAt < SCAN_INTERVAL_MS)
        ) {
            frameRequestId = window.requestAnimationFrame(scanFrame);

            return;
        }

        const video = videoRef.value;
        const canvas = canvasRef.value;
        const context = canvas.getContext('2d', { willReadFrequently: true });

        if (!context) {
            error.value =
                'The scanner could not read the camera image. Retry the camera, or enter the code manually.';
            stop();

            return;
        }

        if (
            video.readyState < HTMLMediaElement.HAVE_ENOUGH_DATA ||
            video.videoWidth <= 0 ||
            video.videoHeight <= 0
        ) {
            frameRequestId = window.requestAnimationFrame(scanFrame);

            return;
        }

        isDecoding = true;
        lastFrameDecodedAt = timestamp;
        decodeIteration += 1;

        try {
            const useFullFrame = decodeIteration % 4 === 0;
            const vw = video.videoWidth;
            const vh = video.videoHeight;

            let sx = 0;
            let sy = 0;
            let sw = vw;
            let sh = vh;

            if (!useFullFrame) {
                const cropSize = Math.max(
                    1,
                    Math.round(Math.min(vw, vh) * ROI_RATIO),
                );
                sx = Math.floor((vw - cropSize) / 2);
                sy = Math.floor((vh - cropSize) / 2);
                sw = cropSize;
                sh = cropSize;
            }

            const scale = Math.min(1, MAX_DECODE_DIMENSION / Math.max(sw, sh));
            const targetWidth = Math.max(1, Math.round(sw * scale));
            const targetHeight = Math.max(1, Math.round(sh * scale));

            if (canvas.width !== targetWidth) {
                canvas.width = targetWidth;
            }

            if (canvas.height !== targetHeight) {
                canvas.height = targetHeight;
            }

            context.drawImage(
                video,
                sx,
                sy,
                sw,
                sh,
                0,
                0,
                canvas.width,
                canvas.height,
            );

            const frame = context.getImageData(
                0,
                0,
                canvas.width,
                canvas.height,
            );
            const code = jsQrDecoder(frame.data, frame.width, frame.height, {
                inversionAttempts: useFullFrame ? 'attemptBoth' : 'dontInvert',
            });

            if (code?.data) {
                const nextValue = code.data.trim();

                if (!nextValue) {
                    frameRequestId = window.requestAnimationFrame(scanFrame);

                    return;
                }

                if (shouldIgnoreDetection(nextValue)) {
                    status.value =
                        'This code was already captured. Move to another label or wait before scanning it again.';
                    frameRequestId = window.requestAnimationFrame(scanFrame);

                    return;
                }

                lastDetectedValue.value = nextValue;
                lastDetectionAt = Date.now();
                totalDetections.value += 1;
                triggerScanFeedback();
                options.onDetected(nextValue);

                if (isContinuous()) {
                    status.value = `Captured ${nextValue}. Keep scanning…`;
                    frameRequestId = window.requestAnimationFrame(scanFrame);

                    return;
                }

                status.value = 'QR code detected.';
                stop();

                return;
            }

            frameRequestId = window.requestAnimationFrame(scanFrame);
        } finally {
            isDecoding = false;
        }
    }

    async function start(): Promise<void> {
        if (!isSecureContext) {
            error.value =
                'Camera scanning requires a secure HTTPS connection. Enter the code manually.';
            status.value = 'Scanner unavailable.';

            return;
        }

        if (!hasCameraSupport) {
            error.value =
                'This browser does not support camera scanning. Enter the code manually.';
            status.value = 'Scanner unavailable.';

            return;
        }

        stop();

        const session = cameraSession;

        isStarting.value = true;
        error.value = null;
        lastDetectedValue.value = null;
        totalDetections.value = 0;
        status.value = 'Requesting camera access…';

        try {
            const [decoder, requestedStream] = await Promise.all([
                loadJsQr(),
                navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: { ideal: 'environment' },
                    },
                    audio: false,
                }),
            ]);

            if (session !== cameraSession) {
                requestedStream.getTracks().forEach((track) => track.stop());

                return;
            }

            jsQrDecoder = decoder;
            stream = requestedStream;

            if (!videoRef.value) {
                throw new Error('Scanner preview is not available.');
            }

            videoRef.value.srcObject = stream;
            await videoRef.value.play();

            if (session !== cameraSession) {
                return;
            }

            isRunning.value = true;
            isStarting.value = false;
            status.value = isContinuous()
                ? 'Camera ready. Keep the camera open to capture multiple QR codes.'
                : 'Camera ready. Point it at a QR code.';
            scanFrame();
        } catch (caughtError) {
            if (session !== cameraSession) {
                return;
            }

            stop();
            error.value = cameraErrorMessage(caughtError);
            status.value = 'Scanner unavailable.';
        }
    }

    onBeforeUnmount(() => {
        stop();
    });

    return {
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
    };
}
