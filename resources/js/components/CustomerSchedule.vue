<script setup lang="ts">
import { Link, router, useForm, useHttp } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { store, move } from '@/actions/App/Http/Controllers/BookingController';
import { schedule, reserve, portal } from '@/routes';
import type { ScheduleProps, ScheduleItem } from '@/types/customer-portal';
const props = defineProps<ScheduleProps>();
const filters = useForm({ ...props.filters });
const selected = ref<string | null>(props.intent);
const item = computed(() =>
    props.items.find((entry) => entry.key === selected.value),
);
const request = useHttp<
    { operation_key: string },
    { booking: { id: number; status: string } }
>({ operation_key: props.operationKey });
const failure = ref('');
const completed = ref(false);
const keys = new Map<string, string>();
function operationKey() {
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6]! & 15) | 64;
    bytes[8] = (bytes[8]! & 63) | 128;
    const hex = Array.from(bytes, (byte) =>
        byte.toString(16).padStart(2, '0'),
    ).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}
function date(value: string) {
    return new Intl.DateTimeFormat('hr-HR', {
        timeZone: props.timezone,
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
function choose(entry: ScheduleItem) {
    selected.value = entry.key;
    failure.value = '';
    request.clearErrors();
}
function filter() {
    filters.get(
        (props.authenticated ? reserve : schedule).url({
            query: props.moving ? { move: props.moving.id } : {},
        }),
        { preserveState: false },
    );
}
async function confirm() {
    if (!item.value || request.processing || completed.value) return;
    const key = item.value.key;
    if (!keys.has(key))
        keys.set(key, keys.size === 0 ? props.operationKey : operationKey());
    request.operation_key = keys.get(key)!;
    request.transform((data) => ({ ...data, ...item.value!.target }));
    failure.value = '';
    try {
        await request.post(
            props.moving ? move.url(props.moving.id) : store.url(),
            {
                onSuccess: (response) => {
                    completed.value = true;
                    router.visit(
                        portal.url({ query: { booking: response.booking.id } }),
                    );
                },
                onError: () => {
                    failure.value =
                        'Rezervacija nije potvrđena. Provjerite poruku i osvježite raspored.';
                },
            },
        );
    } catch {
        failure.value = request.hasErrors
            ? 'Rezervacija nije potvrđena. Provjerite poruku i osvježite raspored.'
            : 'Nije moguće potvrditi ishod. Pokušajte ponovno istim gumbom ili provjerite Moje rezervacije.';
    }
}
</script>
<template>
    <div class="customer-content grid gap-8">
        <header class="grid gap-3">
            <h1 class="text-4xl font-semibold">
                {{ moving ? 'Odaberite novi termin' : 'Raspored treninga' }}
            </h1>
            <p>
                Vrijeme studija: <strong>{{ timezone }}</strong
                >. Jedan termin = jedan kredit odgovarajućeg paketa.
            </p>
            <Link :href="portal()" class="underline"
                >Moje rezervacije i paketi →</Link
            >
        </header>
        <p v-if="moving" class="portal-card">
            {{ moving.preview.message }} Premještanje čuva izvorni kredit i
            vrijedi za istu vrstu i trajanje. Stari termin ostaje rezerviran ako
            novi nije dostupan.
        </p>
        <form class="flex flex-wrap items-end gap-4" @submit.prevent="filter">
            <label class="grid gap-2"
                >Datum<input v-model="filters.date" type="date" required
            /></label>
            <label class="grid gap-2"
                >Vrsta<select v-model="filters.type">
                    <option :value="null">Sve vrste</option>
                    <option
                        v-for="type in types"
                        :key="type.id"
                        :value="type.id"
                    >
                        {{ type.name }}
                    </option>
                </select></label
            >
            <label class="grid gap-2"
                >Instruktor<select v-model="filters.instructor">
                    <option :value="null">Svi instruktori</option>
                    <option
                        v-for="instructor in instructors"
                        :key="instructor.id"
                        :value="instructor.id"
                    >
                        {{ instructor.name }}
                    </option>
                </select></label
            >
            <button :disabled="filters.processing || request.processing">
                {{ filters.processing ? 'Učitavanje…' : 'Prikaži raspored' }}
            </button>
            <p
                v-for="error in filters.errors"
                :key="String(error)"
                role="alert"
            >
                {{ error }}
            </p>
        </form>
        <p v-if="notice" role="status">{{ notice }}</p>
        <p v-if="!items.length" class="portal-card">
            Nema termina za odabrane filtre. Odaberite drugi datum ili
            kontaktirajte studio.
        </p>
        <div class="grid gap-4 md:grid-cols-2">
            <article
                v-for="entry in items"
                :key="entry.key"
                class="portal-card grid gap-3"
            >
                <p class="text-sm tracking-wider uppercase">
                    {{
                        entry.mode === 'private'
                            ? 'Privatni trening'
                            : 'Grupni trening'
                    }}
                </p>
                <h2 class="text-2xl font-semibold">{{ entry.name }}</h2>
                <p>
                    {{ date(entry.starts_at) }} ·
                    {{ entry.duration_minutes }} min
                </p>
                <p>{{ entry.instructor }} · {{ entry.room }}</p>
                <p>
                    Slobodno {{ entry.remaining }} / {{ entry.capacity }} · 1
                    kredit
                </p>
                <p class="text-sm">
                    Povrat kredita do {{ entry.cancellation_minutes / 60 }} h
                    prije početka; kasnije bez povrata. Nakon početka nema
                    otkaza ni premještanja.
                </p>
                <p v-if="!entry.available" role="status">
                    {{ entry.unavailable_reason }}
                </p>
                <Link
                    v-else-if="!authenticated"
                    class="portal-action"
                    :href="
                        reserve({
                            query: { ...filters.data(), intent: entry.key },
                        })
                    "
                    >Odaberi i prijavi se →</Link
                >
                <p v-else-if="entry.has_credit === false">
                    Nemate valjan kredit za ovaj termin. Kontaktirajte studio.
                </p>
                <button
                    v-else
                    :disabled="
                        request.processing ||
                        completed ||
                        (moving !== null && !moving.preview.can_move)
                    "
                    :aria-pressed="selected === entry.key"
                    @click="choose(entry)"
                >
                    {{ selected === entry.key ? 'Odabrano' : 'Odaberi termin' }}
                </button>
            </article>
        </div>
        <section
            v-if="authenticated && selected"
            class="portal-card grid gap-4"
            aria-label="Potvrda rezervacije"
            aria-live="polite"
        >
            <template v-if="item"
                ><h2 class="text-2xl">
                    {{ item.name }} · {{ date(item.starts_at) }}
                </h2>
                <p>
                    Odabir ne čuva mjesto. Pri potvrdi ponovno provjeravamo
                    dostupnost i pravo za datum treninga.
                </p>
                <p>
                    Bez valjanog kredita rezervacija nije moguća. Za paket
                    kontaktirajte studio.
                </p>
                <button
                    :disabled="
                        !item.available ||
                        item.has_credit === false ||
                        request.processing ||
                        completed ||
                        (moving !== null && !moving.preview.can_move)
                    "
                    @click="confirm"
                >
                    {{
                        request.processing
                            ? 'Provjera i potvrda…'
                            : completed
                              ? 'Promjena je potvrđena'
                              : moving
                                ? 'Potvrdi premještanje'
                                : 'Potvrdi rezervaciju · 1 kredit'
                    }}
                </button>
            </template>
            <p v-else>
                Odabrani termin više nije prikazan. Osvježite raspored i
                odaberite dostupan termin.
            </p>
            <p v-if="failure" role="alert">{{ failure }}</p>
            <p
                v-for="error in request.errors"
                :key="String(error)"
                role="alert"
            >
                {{ error }}
            </p>
            <Link
                :href="
                    reserve({ query: { ...filters.data(), move: moving?.id } })
                "
                class="underline"
                >Osvježi dostupnost</Link
            >
        </section>
    </div>
</template>
