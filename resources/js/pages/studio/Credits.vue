<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    store,
    adjust,
    index,
} from '@/actions/App/Http/Controllers/StudioCreditController';
import CatalogForm from '@/components/CatalogForm.vue';
import type { CatalogField } from '@/components/CatalogForm.vue';
import { Button } from '@/components/ui/button';

type Grant = {
    id: number;
    name: string;
    customer: string;
    author: string;
    source: string;
    source_reference: string | null;
    reason: string | null;
    credits: number;
    granted_at: string;
    valid_from: string;
    expires_at: string;
    training_types: string[];
    totals: {
        balance: number;
        available: number;
        reserved: number;
        expired: number;
        pending: number;
    };
    entries: {
        id: number;
        kind: string;
        amount: number;
        reason: string | null;
        booking_id: number | null;
        occurred_at: string;
    }[];
};
const props = defineProps<{
    grants: { data: Grant[]; current_page: number; last_page: number };
    customers: { id: number; name: string; email: string }[];
    packages: {
        id: number;
        name: string;
        credits: number;
        validity_days: number;
    }[];
    operationKey: string;
    timezone: string;
    statusMessage?: string;
}>();
const adding = ref(false);
const editing = ref<Grant | null>(null);
const sources: Record<string, string> = {
    demo: 'Demo',
    external_purchase: 'Vanjska kupnja',
    complimentary: 'Besplatna dodjela',
};
const kinds: Record<string, string> = {
    grant: 'Dodjela',
    debit: 'Rezervacija',
    reversal: 'Povrat',
    adjustment: 'Korekcija',
};
function grantFields(): CatalogField[] {
    return [
        {
            key: 'user_id',
            label: 'Polaznik',
            type: 'select',
            options: props.customers.map((x) => ({
                value: x.id,
                label: `${x.name} (${x.email})`,
            })),
        },
        {
            key: 'package_product_id',
            label: 'Paket',
            type: 'select',
            options: props.packages.map((x) => ({
                value: x.id,
                label: `${x.name} · ${x.credits} kredita / ${x.validity_days} dana`,
            })),
        },
        {
            key: 'source',
            label: 'Izvor prava',
            type: 'select',
            options: Object.entries(sources).map(([value, label]) => ({
                value,
                label,
            })),
        },
        {
            key: 'source_reference',
            label: 'Referenca računa/evidencije (obvezno za vanjsku kupnju)',
            required: false,
            max: 255,
        },
        {
            key: 'reason',
            label: 'Razlog (obvezno za besplatnu dodjelu)',
            type: 'textarea',
            required: false,
            max: 2000,
        },
    ];
}
const adjustmentFields: CatalogField[] = [
    {
        key: 'amount',
        label: 'Promjena broja kredita (npr. −1 ili 1)',
        type: 'number',
        min: -1000,
        max: 1000,
    },
    { key: 'reason', label: 'Razlog korekcije', type: 'textarea', max: 2000 },
];
</script>

<template>
    <div class="grid gap-8">
        <Head title="Paketi i krediti" />
        <header class="grid gap-3">
            <h1 class="text-3xl font-semibold">Paketi i krediti</h1>
            <p class="max-w-3xl text-muted-foreground">
                Dodijeljena prava nisu prihod naplaćen platformom. Vanjska
                kupnja već je riješena izvan platforme; ovdje se evidentira samo
                pravo na trening.
            </p>
            <p class="text-sm text-muted-foreground">
                Zona: {{ timezone }}. Valjanost počinje dodjelom; trening mora
                početi prije isteka.
            </p>
            <p v-if="statusMessage" role="status" class="rounded-lg border p-4">
                {{ statusMessage }}
            </p>
            <Button
                v-if="customers.length && packages.length"
                class="justify-self-start"
                @click="
                    adding = !adding;
                    editing = null;
                "
                >{{ adding ? 'Zatvori dodjelu' : 'Dodijeli paket' }}</Button
            >
            <p v-else class="text-sm text-muted-foreground">
                Za dodjelu su potrebni aktivan polaznik i paket iz kataloga.
            </p>
        </header>
        <section
            v-if="adding"
            class="max-w-xl rounded-xl border p-5"
            aria-label="Dodjela paketa"
        >
            <CatalogForm
                id="grant"
                :fields="grantFields()"
                :initial="{
                    user_id: '',
                    package_product_id: '',
                    source: 'demo',
                    source_reference: '',
                    reason: '',
                    operation_key: operationKey,
                }"
                :action="store()"
                submit-label="Dodijeli pravo"
                @saved="adding = false"
            />
        </section>
        <section
            v-if="editing"
            class="grid max-w-xl gap-4 rounded-xl border p-5"
            aria-label="Korekcija kredita"
        >
            <h2 class="text-lg font-semibold">
                Korekcija: {{ editing.customer }} · {{ editing.name }}
            </h2>
            <p class="text-sm text-muted-foreground">
                Korekcija čuva povijest i izvorni istek. Rezervirani krediti ne
                mogu se oduzeti.
            </p>
            <CatalogForm
                :key="editing.id"
                id="adjustment"
                :fields="adjustmentFields"
                :initial="{
                    amount: '',
                    reason: '',
                    operation_key: operationKey,
                }"
                :action="adjust(editing.id)"
                submit-label="Zapiši korekciju"
                @saved="editing = null"
            />
            <Button
                variant="outline"
                class="justify-self-start"
                @click="editing = null"
                >Zatvori</Button
            >
        </section>
        <p
            v-if="!grants.data.length"
            class="rounded-xl border border-dashed p-8 text-muted-foreground"
        >
            Još nema dodijeljenih paketa.
        </p>
        <article
            v-for="grant in grants.data"
            :key="grant.id"
            class="grid min-w-0 gap-5 rounded-xl border p-5"
        >
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="grid min-w-0 gap-1 wrap-anywhere">
                    <h2 class="text-xl font-semibold">
                        {{ grant.customer }} · {{ grant.name }}
                    </h2>
                    <p class="text-sm">
                        {{ sources[grant.source] }} ·
                        {{ grant.credits }} izvornih kredita
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Dodijelio/la {{ grant.author }} · {{ grant.granted_at }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ grant.valid_from }} –
                        {{ grant.expires_at }} (isključivo)
                    </p>
                    <p class="text-sm">
                        Vrste: {{ grant.training_types.join(', ') }}
                    </p>
                    <p v-if="grant.source_reference" class="text-sm">
                        Referenca: {{ grant.source_reference }}
                    </p>
                    <p v-if="grant.reason" class="text-sm">
                        Razlog: {{ grant.reason }}
                    </p>
                </div>
                <Button
                    variant="outline"
                    @click="
                        editing = grant;
                        adding = false;
                    "
                    >Korigiraj kredite</Button
                >
            </div>
            <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <dt class="text-sm text-muted-foreground">Raspoloživo</dt>
                    <dd class="text-2xl font-semibold">
                        {{ grant.totals.available }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-muted-foreground">Rezervirano</dt>
                    <dd class="text-2xl font-semibold">
                        {{ grant.totals.reserved }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-muted-foreground">Isteklo</dt>
                    <dd class="text-2xl font-semibold">
                        {{ grant.totals.expired }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-muted-foreground">Ledger saldo</dt>
                    <dd class="text-2xl font-semibold">
                        {{ grant.totals.balance }}
                    </dd>
                </div>
            </dl>
            <p class="text-xs text-muted-foreground">
                Rezervirano je već oduzeto iz salda i odnosi se na potvrđene
                termine koji još nisu završili. Istek ne briše saldo.
            </p>
            <details>
                <summary class="cursor-pointer text-sm font-medium">
                    Kreditna povijest
                </summary>
                <ul class="mt-4 grid gap-3">
                    <li
                        v-for="entry in grant.entries"
                        :key="entry.id"
                        class="border-t pt-3 text-sm wrap-anywhere"
                    >
                        {{ entry.occurred_at }} · {{ kinds[entry.kind] }} ·
                        {{ entry.amount > 0 ? '+' : '' }}{{ entry.amount }}
                        <span v-if="entry.booking_id">
                            · Rezervacija #{{ entry.booking_id }}</span
                        >
                        <p v-if="entry.reason">{{ entry.reason }}</p>
                    </li>
                </ul>
            </details>
        </article>
        <nav
            v-if="grants.last_page > 1"
            aria-label="Stranice paketa"
            class="flex items-center gap-4"
        >
            <Link
                v-if="grants.current_page > 1"
                :href="index({ query: { page: grants.current_page - 1 } })"
                class="underline"
                >Prethodna</Link
            >
            <span>{{ grants.current_page }} / {{ grants.last_page }}</span>
            <Link
                v-if="grants.current_page < grants.last_page"
                :href="index({ query: { page: grants.current_page + 1 } })"
                class="underline"
                >Sljedeća</Link
            >
        </nav>
    </div>
</template>
