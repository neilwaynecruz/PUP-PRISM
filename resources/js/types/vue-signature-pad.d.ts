declare module 'vue-signature-pad' {
    import type { App, DefineComponent } from 'vue';

    export interface SignaturePadInstance {
        saveSignature(): {
            isEmpty: boolean;
            data: string;
        };
        clearSignature(): void;
    }

    export const VueSignaturePad: DefineComponent<{
        width?: string;
        height?: string;
    }>;

    const install: {
        install(app: App): void;
    };

    export default install;
}
