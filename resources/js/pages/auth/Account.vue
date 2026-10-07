<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useTwoFactorRoutes } from '@/composables/useTwoFactorRoutes';
import { useAuthRoutes } from '@/composables/useAuthRoutes';
import type { User } from '@/types/auth';
import { index as platformTenants } from '@/routes/platform/tenants';

defineOptions({
    layout: { title: 'Vaš račun', description: 'Prijava je uspješna.' },
});
const page = usePage<{
    auth: { user: User };
    platformAuth: boolean;
    studioName: string | null;
}>();
const routes = useAuthRoutes();
const twoFactorRoutes = useTwoFactorRoutes();
</script>

<template>
    <div class="space-y-6 text-center">
        <Head title="Vaš račun" />
        <p class="font-medium">
            {{
                page.props.platformAuth
                    ? 'Surya platforma'
                    : page.props.studioName
            }}
        </p>
        <p>{{ page.props.auth.user.name }}</p>
        <p class="text-sm text-muted-foreground">
            {{ page.props.auth.user.email }}
        </p>
        <Link
            v-if="page.props.platformAuth"
            :href="platformTenants()"
            class="block underline underline-offset-4"
            >Administracija studija</Link
        >
        <p v-else class="text-sm text-muted-foreground">
            Funkcionalnosti računa bit će dostupne u sljedećim razvojnim
            koracima.
        </p>
        <Link
            :href="twoFactorRoutes.settings()"
            class="block underline underline-offset-4"
            >Sigurnost računa i 2FA</Link
        >
        <Link
            :href="routes.logout()"
            as="button"
            class="underline underline-offset-4"
            >Odjava</Link
        >
    </div>
</template>
