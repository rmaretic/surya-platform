<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { update } from '@/routes/studio/profile';
type Profile = {
    name: string;
    short_description: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
};
const props = defineProps<{ profile: Profile; statusMessage?: string }>();
const form = useForm({
    name: props.profile.name,
    short_description: props.profile.short_description ?? '',
    email: props.profile.email ?? '',
    phone: props.profile.phone ?? '',
    address: props.profile.address ?? '',
});
const fields = [
    { key: 'name', label: 'Naziv studija', max: 255 },
    { key: 'email', label: 'Kontakt email', max: 255 },
    { key: 'phone', label: 'Telefon', max: 50 },
    { key: 'address', label: 'Adresa', max: 255 },
] as const;
</script>
<template>
    <div class="grid max-w-2xl gap-6">
        <Head title="Javni profil" />
        <h1 class="text-3xl font-semibold">Javni profil studija</h1>
        <p class="text-muted-foreground">
            Spremljene promjene odmah su javno vidljive. Unesite samo podatke
            koje želite objaviti.
        </p>
        <p v-if="statusMessage" role="status" class="rounded-lg border p-4">
            {{ statusMessage }}
        </p>
        <form @submit.prevent="form.submit(update())" class="grid gap-5">
            <div v-for="field in fields" :key="field.key" class="grid gap-2">
                <Label :for="field.key">{{ field.label }}</Label
                ><Input
                    :id="field.key"
                    v-model="form[field.key]"
                    :maxlength="field.max"
                    :required="field.key === 'name'"
                    :type="
                        field.key === 'email'
                            ? 'email'
                            : field.key === 'phone'
                              ? 'tel'
                              : 'text'
                    "
                /><InputError :message="form.errors[field.key]" />
            </div>
            <div class="grid gap-2">
                <Label for="short_description">Kratki opis</Label
                ><textarea
                    id="short_description"
                    v-model="form.short_description"
                    maxlength="5000"
                    rows="5"
                    class="w-full rounded-md border bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring"
                /><InputError :message="form.errors.short_description" />
            </div>
            <Button class="justify-self-start" :disabled="form.processing"
                >Spremi i objavi</Button
            >
        </form>
    </div>
</template>
