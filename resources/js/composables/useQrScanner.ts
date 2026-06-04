import jsQR from 'jsqr';
import { onBeforeUnmount, ref, useTemplateRef } from 'vue';

type UseQrScannerOptions = {
    onDetected: (value: string) => void;
};

export function useQrScanner(options: UseQrScannerOptions) {
    const videoRef = useTemplateRef<HTMLVideoElement>('videoRef');
    const canvasRef = useTemplateRef<HTMLCanvasElement>('canvasRef');

    const isRunning = ref(false);
    const isStarting = ref(false);
    const status = ref('Ready to scan a QR code.');
    const error = ref<string | null>(null);

    let stream: MediaStream | null = null;
    let frameRequestId: number | null = null;

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
            status.value = 'QR code detected.';
            options.onDetected(code.data);
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
            status.value = 'Camera ready. Point it at a QR code.';
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
        start,
        status,
        stop,
        videoRef,
    };
}
