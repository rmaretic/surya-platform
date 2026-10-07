import { useHttp } from '@inertiajs/vue3';
import type { ComputedRef, Ref } from 'vue';
import { computed, ref, onUnmounted } from 'vue';
import { useTwoFactorRoutes } from '@/composables/useTwoFactorRoutes';

export type UseTwoFactorAuthReturn = {
    qrCodeSvg: Ref<string | null>;
    manualSetupKey: Ref<string | null>;
    recoveryCodesList: Ref<string[]>;
    errors: Ref<string[]>;
    hasSetupData: ComputedRef<boolean>;
    clearSetupData: () => void;
    clearErrors: () => void;
    clearTwoFactorAuthData: () => void;
    fetchQrCode: () => Promise<void>;
    fetchSetupKey: () => Promise<void>;
    fetchSetupData: () => Promise<void>;
    fetchRecoveryCodes: () => Promise<void>;
};

export const useTwoFactorAuth = (): UseTwoFactorAuthReturn => {
    const http = useHttp();
    const routes = useTwoFactorRoutes();
    const errors = ref<string[]>([]);
    const manualSetupKey = ref<string | null>(null);
    const qrCodeSvg = ref<string | null>(null);
    const recoveryCodesList = ref<string[]>([]);
    const hasSetupData = computed<boolean>(
        () => qrCodeSvg.value !== null && manualSetupKey.value !== null,
    );
    onUnmounted(() => {
        http.cancel();
        clearTwoFactorAuthData();
    });

    const fetchQrCode = async (): Promise<void> => {
        try {
            const { svg } = (await http.submit(routes.value.qrCode())) as {
                svg: string;
                url: string;
            };

            qrCodeSvg.value = svg;
        } catch {
            errors.value.push(
                'QR kod nije dostupan. Osvježite stranicu i potvrdite lozinku.',
            );
            qrCodeSvg.value = null;
        }
    };

    const fetchSetupKey = async (): Promise<void> => {
        try {
            const { secretKey: key } = (await http.submit(
                routes.value.secretKey(),
            )) as {
                secretKey: string;
            };

            manualSetupKey.value = key;
        } catch {
            errors.value.push(
                'Ključ nije dostupan. Osvježite stranicu i potvrdite lozinku.',
            );
            manualSetupKey.value = null;
        }
    };

    const clearSetupData = (): void => {
        manualSetupKey.value = null;
        qrCodeSvg.value = null;
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
                routes.value.recoveryCodes(),
            )) as string[];
        } catch {
            errors.value.push(
                'Kodovi za oporavak nisu dostupni. Osvježite stranicu i potvrdite lozinku.',
            );
            recoveryCodesList.value = [];
        }
    };

    const fetchSetupData = async (): Promise<void> => {
        try {
            clearErrors();
            await fetchQrCode();
            await fetchSetupKey();
        } catch {
            qrCodeSvg.value = null;
            manualSetupKey.value = null;
        }
    };

    return {
        qrCodeSvg,
        manualSetupKey,
        recoveryCodesList,
        errors,
        hasSetupData,
        clearSetupData,
        clearErrors,
        clearTwoFactorAuthData,
        fetchQrCode,
        fetchSetupKey,
        fetchSetupData,
        fetchRecoveryCodes,
    };
};
