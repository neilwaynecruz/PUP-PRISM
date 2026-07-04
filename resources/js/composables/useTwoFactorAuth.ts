import { HttpResponseError } from '@inertiajs/core';
import { useHttp } from '@inertiajs/vue3';
import type { ComputedRef, Ref } from 'vue';
import { computed, ref } from 'vue';
import {
    setupData as securitySetupData,
} from '@/actions/App/Http/Controllers/Settings/SecurityController';
import { recoveryCodes } from '@/routes/two-factor';

export type UseTwoFactorAuthReturn = {
    qrCodeSvg: Ref<string | null>;
    manualSetupKey: Ref<string | null>;
    recoveryCodesList: Ref<string[]>;
    errors: Ref<string[]>;
    isLoadingSetupData: Ref<boolean>;
    hasSetupData: ComputedRef<boolean>;
    clearSetupData: () => void;
    clearErrors: () => void;
    clearTwoFactorAuthData: () => void;
    fetchSetupData: () => Promise<void>;
    fetchRecoveryCodes: () => Promise<void>;
};

const errors = ref<string[]>([]);
const manualSetupKey = ref<string | null>(null);
const qrCodeSvg = ref<string | null>(null);
const recoveryCodesList = ref<string[]>([]);
const isLoadingSetupData = ref<boolean>(false);

const hasSetupData = computed<boolean>(
    () => qrCodeSvg.value !== null && manualSetupKey.value !== null,
);

const resolveErrorMessage = (
    error: unknown,
    fallbackMessage: string,
): string => {
    if (
        error instanceof HttpResponseError &&
        error.response.status === 423
    ) {
        return 'Password confirmation expired. Please confirm your password again and retry two-factor setup.';
    }

    return fallbackMessage;
};

export const useTwoFactorAuth = (): UseTwoFactorAuthReturn => {
    const http = useHttp();

    const clearSetupData = (): void => {
        manualSetupKey.value = null;
        qrCodeSvg.value = null;
        isLoadingSetupData.value = false;
        clearErrors();
    };

    const clearErrors = (): void => {
        errors.value = [];
    };

    const clearTwoFactorAuthData = (): void => {
        clearSetupData();
        clearErrors();
        recoveryCodesList.value = [];
    };

    const fetchRecoveryCodes = async (): Promise<void> => {
        try {
            clearErrors();
            recoveryCodesList.value = (await http.submit(
                recoveryCodes(),
            )) as string[];
        } catch (error: unknown) {
            errors.value.push(
                resolveErrorMessage(error, 'Failed to fetch recovery codes'),
            );
            recoveryCodesList.value = [];
        }
    };

    const fetchSetupData = async (): Promise<void> => {
        try {
            clearErrors();
            isLoadingSetupData.value = true;

            const response = (await http.submit(securitySetupData())) as {
                svg: string;
                secretKey: string;
                url: string;
            };

            qrCodeSvg.value = response.svg;
            manualSetupKey.value = response.secretKey;
        } catch (error: unknown) {
            errors.value.push(
                resolveErrorMessage(error, 'Failed to load two-factor setup data'),
            );
            qrCodeSvg.value = null;
            manualSetupKey.value = null;
        } finally {
            isLoadingSetupData.value = false;
        }
    };

    return {
        qrCodeSvg,
        manualSetupKey,
        recoveryCodesList,
        errors,
        isLoadingSetupData,
        hasSetupData,
        clearSetupData,
        clearErrors,
        clearTwoFactorAuthData,
        fetchSetupData,
        fetchRecoveryCodes,
    };
};
