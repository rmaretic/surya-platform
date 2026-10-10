<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import InputError from '@/components/InputError.vue';
import type { RouteDefinition } from '@/wayfinder';

export type CatalogField = {
    key: string;
    label: string;
    type?: 'number' | 'textarea' | 'select' | 'multiple';
    min?: number;
    max?: number;
    required?: boolean;
    disabled?: boolean;
    options?: { value: string | number; label: string }[];
};
export type CatalogValues = Record<string, string | number | number[]>;

const props = defineProps<{
    id: string;
    fields: CatalogField[];
    initial: CatalogValues;
    action: RouteDefinition<'post' | 'patch'>;
    submitLabel?: string;
}>();
const emit = defineEmits<{ saved: [] }>();
const form = useForm(props.initial);
const inputClass =
    'w-full rounded-md border bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-60';
function submit() {
    form.submit(props.action, {
        preserveScroll: true,
        onSuccess: () => emit('saved'),
    });
}
function updateText(key: string, event: Event) {
    form[key] = (event.target as HTMLTextAreaElement).value;
}
</script>

<template>
    <form class="grid gap-5" @submit.prevent="submit">
        <div v-for="field in fields" :key="field.key" class="grid gap-2">
            <label :for="`${id}-${field.key}`" class="text-sm font-medium">{{
                field.label
            }}</label>
            <textarea
                v-if="field.type === 'textarea'"
                :id="`${id}-${field.key}`"
                :value="String(form[field.key] ?? '')"
                rows="3"
                :maxlength="field.max"
                :class="inputClass"
                :aria-describedby="`${id}-${field.key}-error`"
                @input="updateText(field.key, $event)"
            />
            <select
                v-else-if="field.type === 'select' || field.type === 'multiple'"
                :id="`${id}-${field.key}`"
                v-model="form[field.key]"
                :multiple="field.type === 'multiple'"
                :required="field.required !== false"
                :disabled="field.disabled"
                :class="inputClass"
                :aria-describedby="`${id}-${field.key}-error`"
            >
                <option v-if="field.type === 'select'" value="" disabled>
                    Odaberite
                </option>
                <option
                    v-for="option in field.options"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
            <input
                v-else
                :id="`${id}-${field.key}`"
                v-model="form[field.key]"
                :type="field.type ?? 'text'"
                :min="field.min"
                :max="field.type === 'number' ? field.max : undefined"
                :maxlength="field.type !== 'number' ? field.max : undefined"
                :step="field.type === 'number' ? 1 : undefined"
                :required="field.required !== false"
                :class="inputClass"
                :aria-describedby="`${id}-${field.key}-error`"
            />
            <p
                v-if="field.type === 'multiple'"
                class="text-xs text-muted-foreground"
            >
                Možete odabrati više vrsta (Ctrl/Cmd ili dodir na mobitelu).
            </p>
            <InputError
                :id="`${id}-${field.key}-error`"
                :message="form.errors[field.key]"
            />
        </div>
        <div
            v-if="
                Object.keys(form.errors).some(
                    (key) => !fields.some((field) => field.key === key),
                )
            "
            role="alert"
            class="text-sm text-destructive"
        >
            <p v-for="(error, key) in form.errors" :key="key">{{ error }}</p>
        </div>
        <Button class="justify-self-start" :disabled="form.processing">{{
            form.processing ? 'Spremanje…' : (submitLabel ?? 'Spremi')
        }}</Button>
    </form>
</template>
