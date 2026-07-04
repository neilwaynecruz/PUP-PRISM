import jsQR from 'jsqr';
import { onBeforeUnmount, ref, useTemplateRef } from 'vue';

type UseQrScannerOptions = {
    onDetected: (value: string) => void;
    continuous?: boolean;
    cooldownMs?: number;
};

export function useQrScanner(options: UseQrScannerOptions) {
    const videoRef = useTemplateRef<HTMLVideoElement>('videoRef');
    const canvasRef = useTemplateRef<HTMLCanvasElement>('canvasRef');

    const isRunning = ref(false);
    const isStarting = ref(false);
    const status = ref('Ready to scan a QR code.');
    const error = ref<string | null>(null);
    const lastDetectedValue = ref<string | null>(null);
    const totalDetections = ref(0);

    let stream: MediaStream | null = null;
    let frameRequestId: number | null = null;
    let lastDetectionAt = 0;

    const hasCameraSupport =
        typeof navigator !== 'undefined' &&
        !!navigator.mediaDevices?.getUserMedia;

    function stop(): void {
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

    function scanFrame(): void {
        if (!videoRef.value || !canvasRef.value || !isRunning.value) {
            return;
        }

        const video = videoRef.value;
        const canvas = canvasRef.value;
        const context = canvas.getContext('2d', { willReadFrequently: true });

        if (!context) {
            error.value = 'Unable to access the scanner canvas.';
            stop();

            return;
        }

        if (video.readyState < HTMLMediaElement.HAVE_ENOUGH_DATA) {
            frameRequestId = window.requestAnimationFrame(scanFrame);

            return;
        }

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        context.drawImage(video, 0, 0, canvas.width, canvas.height);

        const frame = context.getImageData(0, 0, canvas.width, canvas.height);
        const code = jsQR(frame.data, frame.width, frame.height, {
            inversionAttempts: 'attemptBoth',
        });

        if (code?.data) {
            const nextValue = code.data.trim();

            if (!nextValue || shouldIgnoreDetection(nextValue)) {
                frameRequestId = window.requestAnimationFrame(scanFrame);

                return;
            }

            lastDetectedValue.value = nextValue;
            lastDetectionAt = Date.now();
            totalDetections.value += 1;
            triggerScanFeedback();
            options.onDetected(nextValue);

            if (options.continuous) {
                status.value = `Captured ${nextValue}. Keep scanning...`;
                frameRequestId = window.requestAnimationFrame(scanFrame);

                return;
            }

            status.value = 'QR code detected.';
            stop();

            return;
        }

        frameRequestId = window.requestAnimationFrame(scanFrame);
    }

    async function start(): Promise<void> {
        if (!hasCameraSupport) {
            error.value = 'This browser does not support camera scanning.';

            return;
        }

        stop();

        isStarting.value = true;
        error.value = null;
        lastDetectedValue.value = null;
        totalDetections.value = 0;
        status.value = 'Requesting camera access...';

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: { ideal: 'environment' },
                },
                audio: false,
            });

            if (!videoRef.value) {
                throw new Error('Scanner preview is not available.');
            }

            videoRef.value.srcObject = stream;
            await videoRef.value.play();

            isRunning.value = true;
            isStarting.value = false;
            status.value = options.continuous
                ? 'Camera ready. Keep the camera open to capture multiple QR codes.'
                : 'Camera ready. Point it at a QR code.';
            scanFrame();
        } catch (caughtError) {
            stop();

            const message =
                caughtError instanceof Error
                    ? caughtError.message
                    : 'Unable to start the camera.';

            error.value = message.includes('Permission')
                ? 'Camera access was blocked. Allow camera permission or enter the code manually.'
                : message;
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
