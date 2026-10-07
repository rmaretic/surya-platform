<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { onMounted, onBeforeUnmount } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { store } from '@/routes/staff-invitations';
const props = defineProps<{ invitationId: number }>();
defineOptions({
    layout: {
        title: 'Prihvat poziva u osoblje',
        description: 'Postavite vlastitu lozinku za pristup studiju.',
    },
});
const form = useForm({
    token: '',
    name: '',
    password: '',
    password_confirmation: '',
});
onMounted(() => {
    form.token =
        new URLSearchParams(window.location.hash.slice(1)).get('token') ?? '';
    window.history.replaceState(
        window.history.state,
        '',
        window.location.pathname + window.location.search,
    );
});
onBeforeUnmount(() => form.reset());
function submit() {
    form.submit(store(props.invitationId), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <div>
        <Head title="Prihvat pozivnice" />
        <form @submit.prevent="submit" class="grid gap-5">
            <p v-if="!form.token" role="alert" class="text-sm text-destructive">
                Otvorite najnoviju poveznicu iz emaila. Ako ste osvježili ovu
                stranicu, ponovno otvorite poveznicu.
            </p>
            <InputError :message="form.errors.token" />
            <div class="grid gap-2">
                <Label for="name">Ime i prezime</Label
                ><Input
                    id="name"
                    v-model="form.name"
                    required
                    maxlength="255"
                    autocomplete="name"
                /><InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="password">Lozinka</Label
                ><Input
                    id="password"
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="new-password"
                /><InputError :message="form.errors.password" />
            </div>
            <div class="grid gap-2">
                <Label for="password_confirmation">Potvrda lozinke</Label
                ><Input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                />
            </div>
            <Button :disabled="form.processing || !form.token"
                >Prihvati poziv</Button
            >
        </form>
    </div>
</template>
