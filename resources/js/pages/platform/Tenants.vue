<script setup lang="ts">
import { Head, Link, Form } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { index, create, show } from '@/routes/platform/tenants';

defineProps<{
    tenants: {
        data: {
            id: number;
            name: string;
            status: string;
            design_key: string;
        }[];
        current_page: number;
        last_page: number;
        total: number;
    };
    counts: { pending: number; active: number; suspended: number };
    search: string;
    designs: Record<string, string>;
}>();
const statuses: Record<string, string> = {
    pending: 'Čeka aktivaciju',
    active: 'Aktivan',
    suspended: 'Suspendiran',
};
</script>

<template>
    <div class="space-y-8">
        <Head title="Studiji — platforma" />
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="mb-2 text-sm text-muted-foreground">
                    Platform administracija
                </p>
                <h1 class="text-3xl font-semibold">Registar studija</h1>
            </div>
            <Button as-child
                ><Link :href="create()">Kreiraj studio</Link></Button
            >
        </div>
        <dl class="grid gap-4 sm:grid-cols-3">
            <div
                v-for="(count, status) in counts"
                :key="status"
                class="rounded-xl border p-5"
            >
                <dt class="text-sm text-muted-foreground">
                    {{ statuses[status] }}
                </dt>
                <dd class="mt-2 text-3xl font-semibold">{{ count }}</dd>
            </div>
        </dl>
        <Form
            v-bind="index.form()"
            v-slot="{ errors, processing }"
            class="flex flex-wrap items-end gap-3"
        >
            <div class="grid w-full gap-2 sm:max-w-sm">
                <Label for="search">Naziv studija ili domena</Label
                ><Input
                    id="search"
                    name="search"
                    :default-value="search"
                    maxlength="100"
                /><InputError :message="errors.search" />
            </div>
            <Button :disabled="processing" variant="outline">Pretraži</Button>
            <Link v-if="search" :href="index()" class="p-2 text-sm underline"
                >Očisti pretragu</Link
            >
        </Form>
        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">
                    Studiji i operativni statusi
                </caption>
                <thead class="bg-muted/50">
                    <tr>
                        <th scope="col" class="p-4">Studio</th>
                        <th scope="col" class="p-4">Status</th>
                        <th scope="col" class="p-4">Dizajn</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="tenant in tenants.data"
                        :key="tenant.id"
                        class="border-t"
                    >
                        <td class="p-4">
                            <Link
                                :href="show(tenant.id)"
                                class="font-medium underline underline-offset-4"
                                >{{ tenant.name }}</Link
                            >
                        </td>
                        <td class="p-4">{{ statuses[tenant.status] }}</td>
                        <td class="p-4">
                            {{
                                designs[tenant.design_key] ??
                                'Neregistriran dizajn'
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!tenants.data.length" class="p-6 text-muted-foreground">
                Nema studija koji odgovaraju pretrazi.
            </p>
        </div>
        <nav
            aria-label="Stranice registra"
            class="flex flex-wrap items-center justify-between gap-4 text-sm"
        >
            <span
                >{{ tenants.total }} rezultata · Stranica
                {{ tenants.current_page }} / {{ tenants.last_page }}</span
            >
            <div class="flex gap-5">
                <Link
                    v-if="tenants.current_page > 1"
                    :href="
                        index({
                            query: { search, page: tenants.current_page - 1 },
                        })
                    "
                    class="underline"
                    >Prethodna</Link
                ><Link
                    v-if="tenants.current_page < tenants.last_page"
                    :href="
                        index({
                            query: { search, page: tenants.current_page + 1 },
                        })
                    "
                    class="underline"
                    >Sljedeća</Link
                >
            </div>
        </nav>
    </div>
</template>
