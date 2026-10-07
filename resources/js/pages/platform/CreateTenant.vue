<script setup lang="ts">
import { Head, Form, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { index, store } from '@/routes/platform/tenants';
defineProps<{ designs: Record<string, string> }>();
const fields = [
    {
        name: 'name',
        label: 'Naziv studija',
        required: true,
        type: 'text',
        max: 255,
    },
    {
        name: 'short_description',
        label: 'Kratki opis',
        required: false,
        type: 'text',
        max: 1000,
    },
    {
        name: 'email',
        label: 'Javni email',
        required: false,
        type: 'email',
        max: 255,
    },
    { name: 'phone', label: 'Telefon', required: false, type: 'text', max: 50 },
    {
        name: 'address',
        label: 'Adresa',
        required: false,
        type: 'text',
        max: 255,
    },
    {
        name: 'hostname',
        label: 'Primarna domena (bez protokola i porta)',
        required: true,
        type: 'text',
        max: 253,
    },
    {
        name: 'owner_email',
        label: 'Email početnog vlasnika',
        required: true,
        type: 'email',
        max: 255,
    },
];
</script>

<template>
    <div class="max-w-2xl space-y-6">
        <Head title="Novi studio" />
        <Link :href="index()" class="text-sm underline"
            >← Registar studija</Link
        >
        <h1 class="text-3xl font-semibold">Novi studio</h1>
        <p class="text-muted-foreground">
            Studio i domena čekaju ručnu verifikaciju. Početna owner pozivnica
            spremit će se zajedno s profilom, a šalje se nakon aktivacije
            domene.
        </p>
        <Form
            v-bind="store.form()"
            v-slot="{ errors, processing }"
            class="space-y-5"
        >
            <div v-for="field in fields" :key="field.name" class="grid gap-2">
                <Label :for="field.name"
                    >{{ field.label }}{{ field.required ? ' *' : '' }}</Label
                ><Input
                    :id="field.name"
                    :name="field.name"
                    :type="field.type"
                    :required="field.required"
                    :maxlength="field.max"
                /><InputError :message="errors[field.name]" />
            </div>
            <div class="grid gap-2">
                <Label for="design_key">Registrirani dizajn *</Label
                ><select
                    id="design_key"
                    name="design_key"
                    required
                    class="h-10 w-full rounded-md border bg-background px-3"
                >
                    <option
                        v-for="(label, key) in designs"
                        :key="key"
                        :value="key"
                    >
                        {{ label }}
                    </option></select
                ><InputError :message="errors.design_key" />
            </div>
            <p class="text-sm text-muted-foreground">
                Naziv, opis i kontakt postaju javni nakon aktivacije. U opis i
                kontakt ne unosite privatne podatke.
            </p>
            <Button :disabled="processing">{{
                processing ? 'Spremanje…' : 'Kreiraj studio i pripremi poziv'
            }}</Button>
        </Form>
    </div>
</template>
