<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useTwoFactorRoutes } from '@/composables/useTwoFactorRoutes';
import type { User } from '@/types/auth';
defineProps<{ role: string }>();
const page = usePage<{ auth: { user: User } }>();
const security = useTwoFactorRoutes();
const roles: Record<string, string> = {
    owner: 'Vlasnik',
    manager: 'Manager',
    instructor: 'Instruktor',
    customer: 'Polaznik',
};
</script>
<template>
    <div class="grid max-w-2xl gap-6">
        <Head title="Moj račun" />
        <h1 class="text-3xl font-semibold">Moj račun</h1>
        <dl class="grid gap-4 rounded-xl border p-6 wrap-anywhere">
            <div>
                <dt class="text-sm text-muted-foreground">Ime i prezime</dt>
                <dd>{{ page.props.auth.user.name }}</dd>
            </div>
            <div>
                <dt class="text-sm text-muted-foreground">Email</dt>
                <dd>{{ page.props.auth.user.email }}</dd>
            </div>
            <div>
                <dt class="text-sm text-muted-foreground">
                    Uloga u ovom studiju
                </dt>
                <dd>{{ roles[role] }}</dd>
            </div>
        </dl>
        <p v-if="role === 'customer'" class="text-muted-foreground">
            Dobro došli u portal polaznika. Rezervacija termina dolazi kasnije;
            trenutačno nije dostupna.
        </p>
        <Link :href="security.settings()" class="underline underline-offset-4"
            >Sigurnost računa i 2FA</Link
        >
    </div>
</template>
