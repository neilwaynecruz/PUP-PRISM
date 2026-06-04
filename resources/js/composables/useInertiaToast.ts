import { usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { watch } from 'vue';

type ToastType = 'success' | 'error' | 'warning' | 'info';

interface FlashToast {
    type: ToastType;
    message: string;
}

export function useInertiaToast(): void {
    const page = usePage();

    watch(
        () => {
            const flash = (page.props as Record<string, unknown>).flash as Record<string, unknown> | undefined;
            return flash?.toast as FlashToast | undefined;
        },
        (flashToast) => {
            if (!flashToast?.message) {
                return;
            }

            const { type, message } = flashToast;

            switch (type) {
                case 'success':
                    toast.success(message);
                    break;
                case 'error':
                    toast.error(message);
                    break;
                case 'warning':
                    toast.warning(message);
                    break;
                case 'info':
                default:
                    toast.info(message);
                    break;
            }
        },
        { immediate: true },
    );
}
