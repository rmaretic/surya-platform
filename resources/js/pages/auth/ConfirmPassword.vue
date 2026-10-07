<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTwoFactorRoutes } from '@/composables/useTwoFactorRoutes';

const routes = useTwoFactorRoutes();

defineOptions({
    layout: {
        title: 'Potvrdite lozinku',
        description: 'Za ovu radnju potrebno je ponovno potvrditi lozinku.',
    },
});
</script>

<template>
    <div>
        <Head title="Potvrdite lozinku" />

        <Form
            v-bind="routes.confirmPassword.form()"
            reset-on-success
            v-slot="{ errors, processing }"
        >
            <div class="space-y-6">
                <div class="grid gap-2">
                    <Label for="password">Lozinka</Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        class="mt-1 block w-full"
                        required
                        autocomplete="current-password"
                        autofocus
                    />

                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center">
                    <Button
                        class="w-full"
                        :disabled="processing"
                        data-test="confirm-password-button"
                    >
                        <Spinner v-if="processing" />
                        Potvrdite lozinku
                    </Button>
                </div>
            </div>
        </Form>
    </div>
</template>
