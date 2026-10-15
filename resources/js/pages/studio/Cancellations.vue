<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    index,
    cancel,
    refund,
} from '@/actions/App/Http/Controllers/StudioCancellationController';
import { Button } from '@/components/ui/button';

type Booking = {
    id: number;
    name: string;
    status: string;
    affected: boolean;
    can_refund: boolean;
    refund_expires_at: string;
};
const props = defineProps<{
    date: string;
    timezone: string;
    sessions: { id: number; name: string; status: string; time: string }[];
    selected: {
        id: number;
        name: string;
        local_start: string;
        status: string;
        can_cancel: boolean;
        preview_token: string;
        bookings: Booking[];
    } | null;
    statusMessage?: string;
}>();
const filters = useForm({ date: props.date });
const form = useForm({ reason: '', operation_key: '', preview_token: '' });
const exception = ref<Booking | null>(null);
const affected = computed(
    () =>
        props.selected?.bookings.filter((booking) => booking.affected).length ??
        0,
);
const statuses: Record<string, string> = {
    draft: 'Nacrt',
    published: 'Objavljeno',
    cancelled: 'Otkazano',
    completed: 'Dovršeno',
    confirmed: 'Potvrđeno',
};
watch(
    () => props.selected,
    () => {
        form.reset();
        form.clearErrors();
        exception.value = null;
    },
);
function submit() {
    if (!props.selected) return;
    form.operation_key ||= crypto.randomUUID();
    if (exception.value) {
        form.transform(({ reason, operation_key }) => ({
            reason,
            operation_key,
        })).post(refund.url(exception.value.id), { preserveScroll: true });
    } else {
        form.preview_token = props.selected.preview_token;
        form.transform((data) => data).post(cancel.url(props.selected.id), {
            preserveScroll: true,
        });
    }
}
function chooseException(booking: Booking | null) {
    exception.value = booking;
    form.reset();
    form.clearErrors();
}
</script>

<template>
    <div class="space-y-6">
        <Head title="Otkazi i iznimke" />
        <header class="space-y-2">
            <h1 class="text-2xl font-semibold">Otkazi i iznimke</h1>
            <p class="text-muted-foreground">
                Grupni i privatni termini · {{ timezone }}. Zamjenski termin
                kreira se zasebno; polaznici se ne premještaju automatski.
            </p>
        </header>
        <p v-if="statusMessage" role="status" class="rounded-lg border p-4">
            {{ statusMessage }}
        </p>
        <form
            class="flex flex-wrap items-end gap-3"
            @submit.prevent="filters.get(index.url())"
        >
            <label class="grid gap-2"
                >Datum
                <input
                    v-model="filters.date"
                    type="date"
                    required
                    class="rounded-md border bg-background p-2"
            /></label>
            <Button :disabled="filters.processing">Prikaži termine</Button>
            <p v-if="filters.errors.date" role="alert">
                {{ filters.errors.date }}
            </p>
        </form>
        <div class="grid gap-6 lg:grid-cols-[16rem_1fr]">
            <nav aria-label="Termini za otkaz" class="space-y-3">
                <p v-if="!sessions.length" class="text-muted-foreground">
                    Nema termina za odabrani datum.
                </p>
                <Link
                    v-for="session in sessions"
                    :key="session.id"
                    :href="index({ query: { date, session: session.id } })"
                    :aria-current="
                        selected?.id === session.id ? 'page' : undefined
                    "
                    class="block rounded-lg border p-4 hover:bg-muted focus-visible:outline-2"
                    :class="selected?.id === session.id ? 'bg-muted' : ''"
                >
                    <span class="block font-medium"
                        >{{ session.time }} · {{ session.name }}</span
                    >
                    <span class="text-sm text-muted-foreground">{{
                        statuses[session.status]
                    }}</span>
                </Link>
            </nav>
            <section
                v-if="selected"
                class="min-w-0 space-y-5 rounded-lg border p-5"
                aria-label="Pregled posljedica"
            >
                <h2 class="text-xl font-semibold">
                    {{ selected.name }} · {{ selected.local_start }}
                </h2>
                <p>
                    {{ statuses[selected.status] }} · Pogođene rezervacije:
                    {{ affected }}
                </p>
                <p class="text-sm text-muted-foreground">
                    Otkaz termina vraća po jedan kredit potvrđenim
                    rezervacijama. Samo vraćeni kredit dobiva najmanje sedam
                    dana valjanosti prema spremljenim pravilima. Nema povrata
                    novca. Obavijesti se evidentiraju za naknadnu isporuku.
                </p>
                <ul class="space-y-3">
                    <li
                        v-for="booking in selected.bookings"
                        :key="booking.id"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-md bg-muted/40 p-3"
                    >
                        <div class="min-w-0 wrap-anywhere">
                            <p class="font-medium">
                                {{ booking.name }} ·
                                {{ statuses[booking.status] }}
                            </p>
                            <p
                                v-if="booking.affected || booking.can_refund"
                                class="text-sm"
                            >
                                Povrat: 1 kredit, valjan do
                                {{ booking.refund_expires_at }}.
                            </p>
                        </div>
                        <Button
                            v-if="booking.can_refund"
                            type="button"
                            variant="outline"
                            :disabled="form.processing"
                            @click="chooseException(booking)"
                            >Iznimni povrat</Button
                        >
                    </li>
                </ul>
                <p v-if="!selected.bookings.length">Termin nema rezervacija.</p>
                <form
                    v-if="selected.can_cancel || exception"
                    class="space-y-4 border-t pt-5"
                    @submit.prevent="submit"
                >
                    <h3 class="font-semibold">
                        {{
                            exception
                                ? `Iznimni povrat: ${exception.name}`
                                : 'Otkaz cijelog termina'
                        }}
                    </h3>
                    <p v-if="exception" class="text-sm">
                        Vraća se jedan kredit mimo otkaznog roka. Potvrđena
                        rezervacija bit će otkazana. Evidencija dolaska ostaje
                        sačuvana.
                    </p>
                    <label class="grid gap-2"
                        >Razlog (obvezno)<textarea
                            v-model="form.reason"
                            required
                            maxlength="2000"
                            rows="3"
                            class="w-full rounded-md border bg-background p-3"
                        />
                    </label>
                    <ul
                        v-if="form.hasErrors"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        <li v-for="(error, key) in form.errors" :key="key">
                            {{ error }}
                        </li>
                    </ul>
                    <div class="flex flex-wrap gap-3">
                        <Button
                            :disabled="form.processing"
                            variant="destructive"
                            >{{
                                exception
                                    ? 'Potvrdi iznimni povrat'
                                    : `Otkaži termin i vrati ${affected} kredita`
                            }}</Button
                        >
                        <Button
                            v-if="exception"
                            type="button"
                            variant="outline"
                            :disabled="form.processing"
                            @click="chooseException(null)"
                            >Odustani od iznimke</Button
                        >
                        <Link
                            :href="
                                index({ query: { date, session: selected.id } })
                            "
                            class="self-center text-sm underline"
                            >Osvježi pregled</Link
                        >
                    </div>
                </form>
            </section>
            <p v-else class="text-muted-foreground">
                Odaberite termin za pregled rezervacija i posljedica otkaza.
            </p>
        </div>
    </div>
</template>
