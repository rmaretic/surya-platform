import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    confirm,
    disable,
    enable,
    qrCode,
    recoveryCodes,
    regenerateRecoveryCodes,
    secretKey,
    settings,
} from '@/routes/two-factor';
import {
    confirm as platformConfirm,
    disable as platformDisable,
    enable as platformEnable,
    qrCode as platformQrCode,
    recoveryCodes as platformRecoveryCodes,
    regenerateRecoveryCodes as platformRegenerateRecoveryCodes,
    secretKey as platformSecretKey,
    settings as platformSettings,
} from '@/routes/platform/two-factor';
import { store } from '@/routes/two-factor/login';
import { store as platformStore } from '@/routes/platform/two-factor/login';
import { store as confirmPassword } from '@/routes/password/confirm';
import { store as platformConfirmPassword } from '@/routes/platform/password/confirm';

export function useTwoFactorRoutes() {
    const page = usePage<{ platformAuth: boolean }>();
    return computed(() =>
        page.props.platformAuth
            ? {
                  confirm: platformConfirm,
                  disable: platformDisable,
                  enable: platformEnable,
                  qrCode: platformQrCode,
                  recoveryCodes: platformRecoveryCodes,
                  regenerateRecoveryCodes: platformRegenerateRecoveryCodes,
                  secretKey: platformSecretKey,
                  settings: platformSettings,
                  store: platformStore,
                  confirmPassword: platformConfirmPassword,
              }
            : {
                  confirm,
                  disable,
                  enable,
                  qrCode,
                  recoveryCodes,
                  regenerateRecoveryCodes,
                  secretKey,
                  settings,
                  store,
                  confirmPassword,
              },
    );
}
