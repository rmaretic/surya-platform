import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { login, logout } from '@/routes';
import { store as loginStore } from '@/routes/login';
import { email, request, update } from '@/routes/password';
import {
    login as platformLogin,
    logout as platformLogout,
} from '@/routes/platform';
import { store as platformLoginStore } from '@/routes/platform/login';
import {
    email as platformEmail,
    request as platformRequest,
    update as platformUpdate,
} from '@/routes/platform/password';
import { send as platformSend } from '@/routes/platform/verification';
import { send } from '@/routes/verification';

export function useAuthRoutes() {
    const page = usePage<{ platformAuth: boolean }>();
    return computed(() =>
        page.props.platformAuth
            ? {
                  login: platformLogin,
                  logout: platformLogout,
                  loginStore: platformLoginStore,
                  email: platformEmail,
                  request: platformRequest,
                  update: platformUpdate,
                  send: platformSend,
              }
            : { login, logout, loginStore, email, request, update, send },
    );
}
