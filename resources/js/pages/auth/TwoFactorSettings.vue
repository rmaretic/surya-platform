<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ManageTwoFactor from '@/components/ManageTwoFactor.vue';
import { useAuthRoutes } from '@/composables/useAuthRoutes';
import { account } from '@/routes';
import { account as platformAccount } from '@/routes/platform';
import { usePage } from '@inertiajs/vue3';

defineOptions({
    layout: {
        title: 'Sigurnost računa',
        description: 'Dvostruka autentikacija (2FA)',
    },
});
defineProps<{ twoFactorEnabled: boolean; twoFactorRequired: boolean }>();
const routes = useAuthRoutes();
const page = usePage<{ platformAuth: boolean }>();
</script>

<template>
    <div class="space-y-6">
        <Head title="Sigurnost računa" />
        <p v-if="twoFactorRequired && !twoFactorEnabled" class="text-sm">
            Za pristup administraciji obavezno dovršite postavljanje 2FA.
            Skenirajte QR kod u aplikaciji za autentikaciju, potvrdite
            šesteroznamenkasti kod i spremite kodove za oporavak.
        </p>
        <p v-if="twoFactorRequired && twoFactorEnabled" class="text-sm">
            2FA je obvezan za ovaj račun i ne može se isključiti.
        </p>
        <ManageTwoFactor
            :can-manage-two-factor="true"
            :requires-confirmation="true"
            :two-factor-enabled="twoFactorEnabled"
            :two-factor-required="twoFactorRequired"
        />
        <div class="flex flex-wrap gap-4">
            <Link
                v-if="twoFactorEnabled || !twoFactorRequired"
                :href="page.props.platformAuth ? platformAccount() : account()"
                class="underline underline-offset-4"
                >Natrag na račun</Link
            >
            <Link
                :href="routes.logout()"
                as="button"
                class="underline underline-offset-4"
                >Odjava</Link
            >
        </div>
    </div>
</template>
