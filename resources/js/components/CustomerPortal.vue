<script setup lang="ts">
import { Link, router, useHttp } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { cancel } from '@/actions/App/Http/Controllers/BookingController';
import { portal, reserve, account } from '@/routes';
import type { PortalProps } from '@/types/customer-portal';
const props = defineProps<PortalProps>();
const request = useHttp({ operation_key: props.operationKey });
const failure = ref('');
const confirmed = ref(false);
const completed = ref(false);
watch(
    () => props.selected?.id,
    () => {
        confirmed.value = false;
        completed.value = false;
        failure.value = '';
        request.clearErrors();
        request.operation_key = props.operationKey;
    },
);
const labels: Record<string, string> = {
    confirmed: 'Potvrđeno',
    cancelled: 'Otkazano',
    demo: 'Demo dodjela',
    complimentary: 'Besplatna dodjela',
    external_purchase: 'Kupnja izvan platforme',
    grant: 'Dodjela',
    debit: 'Rezervacija',
    reversal: 'Povrat',
    adjustment: 'Korekcija',
    studio_compensation: 'Kompenzacija studija',
};
function date(value: string) {
    return new Intl.DateTimeFormat('hr-HR', {
        timeZone: props.timezone,
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
async function submit() {
    if (
        !props.selected ||
        !confirmed.value ||
        request.processing ||
        completed.value
    )
        return;
    failure.value = '';
    try {
        await request.post(cancel.url(props.selected.id), {
            onSuccess: () => {
                completed.value = true;
                router.reload({
                    onSuccess: () => {
                        confirmed.value = false;
                    },
                });
            },
            onError: () => {
                failure.value = 'Otkaz nije potvrđen. Provjerite poruku.';
            },
        });
    } catch {
        failure.value = request.hasErrors
            ? 'Otkaz nije potvrđen. Provjerite poruku.'
            : 'Ishod nije potvrđen. Provjerite rezervaciju ili ponovite zahtjev.';
    }
}
</script>
<template>
    <div class="customer-content grid gap-10">
        <header class="grid gap-3">
            <h1 class="text-4xl font-semibold">Moj studio</h1>
            <p>Rezervacije i kreditna prava · {{ timezone }}</p>
            <div class="flex flex-wrap gap-6">
                <Link :href="reserve()" class="underline"
                    >Rezerviraj trening →</Link
                ><Link :href="account()" class="underline">Moj račun</Link>
            </div>
        </header>
        <section class="grid gap-4">
            <h2 class="text-2xl font-semibold">Moje rezervacije</h2>
            <nav aria-label="Razdoblje rezervacija" class="flex gap-6">
                <Link
                    :href="portal({ query: { period: 'upcoming' } })"
                    :aria-current="period === 'upcoming' ? 'page' : undefined"
                    >Buduće</Link
                ><Link
                    :href="portal({ query: { period: 'history' } })"
                    :aria-current="period === 'history' ? 'page' : undefined"
                    >Povijest</Link
                >
            </nav>
            <p v-if="!bookings.data.length">
                Nema rezervacija u ovom razdoblju.
            </p>
            <article
                v-for="booking in bookings.data"
                :key="booking.id"
                class="portal-card flex flex-wrap items-center justify-between gap-4"
            >
                <div>
                    <h3 class="text-xl">{{ booking.session.name }}</h3>
                    <p>
                        {{ date(booking.session.starts_at) }} ·
                        {{ labels[booking.status] }}
                    </p>
                </div>
                <Link
                    :href="portal({ query: { period, booking: booking.id } })"
                    class="underline"
                    >Detalji rezervacije #{{ booking.id }}</Link
                >
            </article>
            <nav class="flex gap-6" aria-label="Stranice rezervacija">
                <Link
                    v-if="bookings.prev_page_url"
                    :href="bookings.prev_page_url"
                    >← Prethodne</Link
                ><Link
                    v-if="bookings.next_page_url"
                    :href="bookings.next_page_url"
                    >Sljedeće →</Link
                >
            </nav>
        </section>
        <section
            v-if="selected"
            :key="selected.id"
            class="portal-card grid gap-4"
            aria-label="Detalji rezervacije"
        >
            <h2 class="text-2xl">
                Rezervacija #{{ selected.id }} · {{ labels[selected.status] }}
            </h2>
            <p>
                {{ selected.session.name }} ·
                {{ date(selected.session.starts_at) }} ·
                {{ selected.session.duration_minutes }} min
            </p>
            <p>
                {{ selected.session.instructor }} ·
                {{ selected.session.room }} · {{ selected.credits_used }} kredit
            </p>
            <p>{{ selected.preview.message }}</p>
            <p>
                Rok povrata:
                {{ date(selected.preview.cancellation_deadline) }} ({{
                    selected.preview.timezone
                }}).
            </p>
            <form
                v-if="selected.preview.can_cancel"
                class="grid gap-4"
                @submit.prevent="submit"
            >
                <label class="flex items-start gap-3"
                    ><input
                        v-model="confirmed"
                        type="checkbox"
                        required
                    />Razumijem posljedice i želim otkazati rezervaciju.</label
                ><button
                    :disabled="!confirmed || request.processing || completed"
                >
                    {{ request.processing ? 'Otkazivanje…' : 'Potvrdi otkaz' }}
                </button>
            </form>
            <Link
                v-if="selected.preview.can_move"
                :href="
                    reserve({
                        query: {
                            move: selected.id,
                            type: selected.session.training_type_id,
                        },
                    })
                "
                class="underline"
                >Premjesti na drugi termin →</Link
            >
            <p v-if="failure" role="alert">{{ failure }}</p>
            <p
                v-for="error in request.errors"
                :key="String(error)"
                role="alert"
            >
                {{ error }}
            </p>
        </section>
        <section class="grid gap-4">
            <h2 class="text-2xl font-semibold">Moji paketi</h2>
            <p v-if="!grants.data.length">
                Nemate dodijeljen paket. Za valjan kredit kontaktirajte studio;
                online kupnja nije dostupna.
            </p>
            <div class="grid gap-4 md:grid-cols-2">
                <article
                    v-for="grant in grants.data"
                    :key="grant.id"
                    class="portal-card grid gap-2"
                >
                    <h3 class="text-xl">{{ grant.name }} · #{{ grant.id }}</h3>
                    <p>{{ labels[grant.source] ?? grant.source }}</p>
                    <p>{{ grant.types.join(', ') }}</p>
                    <p>
                        {{ date(grant.valid_from) }} –
                        {{ date(grant.expires_at) }}
                    </p>
                    <p>
                        Raspoloživo: {{ grant.balance.available }} ·
                        Rezervirano: {{ grant.balance.reserved }} · Isteklo:
                        {{ grant.balance.expired }} · Buduće pravo:
                        {{ grant.balance.pending }}
                    </p>
                </article>
            </div>
            <nav aria-label="Stranice paketa" class="flex gap-6">
                <Link v-if="grants.prev_page_url" :href="grants.prev_page_url"
                    >← Prethodni</Link
                ><Link v-if="grants.next_page_url" :href="grants.next_page_url"
                    >Sljedeći →</Link
                >
            </nav>
        </section>
        <section class="grid gap-4">
            <h2 class="text-2xl font-semibold">Kreditna povijest</h2>
            <p v-if="!entries.data.length">Nema kreditnih promjena.</p>
            <ul class="grid gap-3">
                <li
                    v-for="entry in entries.data"
                    :key="entry.id"
                    class="portal-card"
                >
                    {{ date(entry.occurred_at) }} ·
                    {{ labels[entry.kind] ?? entry.kind }} ·
                    {{ entry.amount > 0 ? '+' : '' }}{{ entry.amount }} · Paket
                    #{{ entry.credit_grant_id
                    }}<span v-if="entry.booking_id">
                        · Rezervacija #{{ entry.booking_id }}</span
                    >
                </li>
            </ul>
            <nav aria-label="Stranice kreditne povijesti" class="flex gap-6">
                <Link v-if="entries.prev_page_url" :href="entries.prev_page_url"
                    >← Prethodne</Link
                ><Link
                    v-if="entries.next_page_url"
                    :href="entries.next_page_url"
                    >Sljedeće →</Link
                >
            </nav>
        </section>
    </div>
</template>
