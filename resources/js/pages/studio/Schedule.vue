<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    index,
    store,
    update,
    storeSeries,
    generate,
    closeSeries,
} from '@/actions/App/Http/Controllers/StudioScheduleController';
import CatalogForm from '@/components/CatalogForm.vue';
import type { CatalogField, CatalogValues } from '@/components/CatalogForm.vue';
import { Button } from '@/components/ui/button';

type Session = {
    id: number;
    version: number;
    local_start: string;
    utc_offset: string;
    duration_minutes: number;
    training_type_id: number;
    instructor_profile_id: number;
    room_id: number;
    capacity: number;
    status: string;
    schedule_series_id: number | null;
    confirmed_count: number;
    type_name: string;
    instructor_name: string;
    room_name: string;
    editable: boolean;
};
const props = defineProps<{
    timezone: string;
    filters: { from: string; to: string };
    sessions: {
        data: Session[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    series: {
        id: number;
        starts_on: string;
        ends_on: string | null;
        weekdays: number[];
        local_start_time: string;
        timezone: string;
        archived_at: string | null;
    }[];
    trainingTypes: { id: number; name: string }[];
    instructors: { id: number; display_name: string }[];
    rooms: { id: number; name: string }[];
    statusMessage?: string;
}>();
const editing = ref<Session | null>(null);
const mode = ref<'session' | 'series' | null>(null);
const filters = useForm(props.filters);
const generation = useForm({
    corrections: [] as { date: string; time: string; offset: string }[],
});
const generating = ref<number | null>(null);
const closing = useForm({});
const weekdays = [
    'Ponedjeljak',
    'Utorak',
    'Srijeda',
    'Četvrtak',
    'Petak',
    'Subota',
    'Nedjelja',
];
const statuses: Record<string, string> = {
    draft: 'Nacrt',
    published: 'Objavljeno',
    cancelled: 'Otkazano',
    completed: 'Dovršeno',
};
function resourceFields(): CatalogField[] {
    return [
        {
            key: 'training_type_id',
            label: 'Vrsta treninga',
            type: 'select',
            options: props.trainingTypes.map((x) => ({
                value: x.id,
                label: x.name,
            })),
        },
        {
            key: 'instructor_profile_id',
            label: 'Instruktor',
            type: 'select',
            options: props.instructors.map((x) => ({
                value: x.id,
                label: x.display_name,
            })),
        },
        {
            key: 'room_id',
            label: 'Prostor',
            type: 'select',
            options: props.rooms.map((x) => ({ value: x.id, label: x.name })),
        },
        {
            key: 'duration_minutes',
            label: 'Trajanje u minutama',
            type: 'number',
            min: 1,
            max: 480,
        },
        {
            key: 'capacity',
            label: 'Kapacitet',
            type: 'number',
            min: 1,
            max: 500,
        },
    ];
}
function sessionFields(): CatalogField[] {
    return [
        ...resourceFields(),
        { key: 'local_start', label: 'Lokalni početak (GGGG-MM-DD HH:mm)' },
        {
            key: 'utc_offset',
            label: 'UTC pomak — ostavite prazno osim za dvostruko DST vrijeme (npr. +01:00)',
            required: false,
        },
        {
            key: 'status',
            label: 'Status',
            type: 'select',
            options: Object.entries(statuses)
                .filter(([key]) => key !== 'completed')
                .map(([value, label]) => ({ value, label })),
        },
        {
            key: 'cancellation_reason',
            label: 'Razlog otkaza (obvezan za otkaz)',
            type: 'textarea',
            required: false,
        },
        ...(editing.value?.schedule_series_id
            ? [
                  {
                      key: 'scope',
                      label: 'Opseg izmjene',
                      type: 'select' as const,
                      options: [
                          { value: 'single', label: 'Samo ovaj termin' },
                          {
                              value: 'future',
                              label: 'Ovaj i budući nerazervirani termini',
                          },
                      ],
                  },
              ]
            : []),
    ];
}
function initialSession(): CatalogValues {
    const item = editing.value;
    return item
        ? {
              training_type_id: item.training_type_id,
              instructor_profile_id: item.instructor_profile_id,
              room_id: item.room_id,
              capacity: item.capacity,
              duration_minutes: item.duration_minutes,
              local_start: item.local_start,
              utc_offset: item.utc_offset,
              status: item.status,
              version: item.version,
              cancellation_reason: '',
              scope: 'single',
          }
        : {
              training_type_id: '',
              instructor_profile_id: '',
              room_id: '',
              duration_minutes: 60,
              capacity: 10,
              local_start: `${props.filters.from} 18:00`,
              utc_offset: '',
              status: 'draft',
              cancellation_reason: '',
              scope: 'single',
          };
}
const seriesFields = (): CatalogField[] => [
    ...resourceFields(),
    {
        key: 'weekdays',
        label: 'Dani u tjednu',
        type: 'multiple',
        options: weekdays.map((label, i) => ({ value: i + 1, label })),
    },
    { key: 'local_start_time', label: 'Lokalno vrijeme (HH:mm)' },
    { key: 'starts_on', label: 'Početni datum (GGGG-MM-DD)' },
    { key: 'ends_on', label: 'Završni datum (neobvezno)', required: false },
];
function generateSeries(id: number) {
    generation.post(generate.url(id), {
        preserveScroll: true,
        onSuccess: () => {
            generating.value = null;
            generation.reset();
        },
    });
}
</script>

<template>
    <div>
        <Head title="Grupni raspored" />
        <div class="grid gap-8">
            <header class="grid gap-3">
                <h1 class="text-3xl font-semibold">Grupni raspored</h1>
                <p class="text-muted-foreground">
                    Zona studija: {{ timezone }}. Serije se generiraju kao
                    nacrti za sljedećih osam tjedana.
                </p>
                <p class="text-sm text-muted-foreground">
                    Rezervirani termini zadržavaju vrijeme, trajanje i vrstu.
                    Zamjene instruktora i prostora evidentiraju se. Slanje
                    obavijesti polaznicima još nije dostupno.
                </p>
                <p
                    v-if="statusMessage"
                    role="status"
                    class="rounded-lg border p-3"
                >
                    {{ statusMessage }}
                </p>
                <div class="flex flex-wrap gap-3">
                    <Button
                        @click="
                            editing = null;
                            mode = 'session';
                        "
                        >Novi termin</Button
                    >
                    <Button variant="outline" @click="mode = 'series'"
                        >Nova tjedna serija</Button
                    >
                </div>
            </header>
            <section v-if="mode" class="grid gap-5 rounded-xl border p-5">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-xl font-semibold">
                        {{
                            mode === 'series'
                                ? 'Tjedna serija'
                                : editing
                                  ? 'Uredi termin'
                                  : 'Novi termin'
                        }}
                    </h2>
                    <Button variant="ghost" @click="mode = null"
                        >Zatvori obrazac</Button
                    >
                </div>
                <p
                    v-if="editing?.schedule_series_id"
                    class="text-sm text-muted-foreground"
                >
                    Za ovaj i buduće termine zadržite datum, a promijenite
                    vrijeme ili resurse. Rezervirani i otkazani termini ostaju
                    sačuvani. Buduća generiranja koriste nova pravila; dani u
                    tjednu ostaju isti.
                </p>
                <CatalogForm
                    v-if="mode === 'session'"
                    :key="`session-${editing?.id ?? 'new'}`"
                    id="session"
                    :fields="sessionFields()"
                    :initial="initialSession()"
                    :action="editing ? update(editing.id) : store()"
                    @saved="mode = null"
                />
                <CatalogForm
                    v-else
                    id="series"
                    :fields="seriesFields()"
                    :initial="{
                        training_type_id: '',
                        instructor_profile_id: '',
                        room_id: '',
                        duration_minutes: 60,
                        capacity: 10,
                        weekdays: [1],
                        local_start_time: '18:00',
                        starts_on: filters.from,
                        ends_on: '',
                    }"
                    :action="storeSeries()"
                    @saved="mode = null"
                />
            </section>
            <section class="grid gap-4" aria-labelledby="sessions-heading">
                <h2 id="sessions-heading" class="text-xl font-semibold">
                    Termini
                </h2>
                <form
                    class="flex flex-wrap items-end gap-3"
                    @submit.prevent="
                        filters.get(index.url(), { preserveState: true })
                    "
                >
                    <label class="grid gap-1"
                        >Od<input
                            v-model="filters.from"
                            type="date"
                            required
                            class="rounded-md border bg-background p-2"
                    /></label>
                    <label class="grid gap-1"
                        >Do<input
                            v-model="filters.to"
                            type="date"
                            required
                            class="rounded-md border bg-background p-2"
                    /></label>
                    <Button variant="outline" :disabled="filters.processing"
                        >Prikaži</Button
                    >
                    <p
                        v-for="error in filters.errors"
                        :key="error"
                        role="alert"
                        class="text-destructive"
                    >
                        {{ error }}
                    </p>
                </form>
                <p
                    v-if="!sessions.data.length"
                    class="rounded-xl border p-6 text-muted-foreground"
                >
                    Nema termina u odabranom razdoblju. Dodajte termin ili
                    generirajte seriju.
                </p>
                <article
                    v-for="session in sessions.data"
                    :key="session.id"
                    class="flex flex-wrap items-center justify-between gap-4 rounded-xl border p-5"
                >
                    <div class="grid gap-1">
                        <h3 class="font-semibold">
                            {{ session.type_name }} · {{ session.local_start }}
                        </h3>
                        <p class="text-sm">
                            {{ session.instructor_name }} ·
                            {{ session.room_name }} ·
                            {{ session.duration_minutes }} min
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ statuses[session.status] }} ·
                            {{ session.confirmed_count }}/{{
                                session.capacity
                            }}
                            rezervirano · UTC{{ session.utc_offset }}
                        </p>
                    </div>
                    <Button
                        v-if="session.editable"
                        variant="outline"
                        @click="
                            editing = session;
                            mode = 'session';
                        "
                        >Uredi<span class="sr-only">
                            {{ session.type_name }}
                            {{ session.local_start }}</span
                        ></Button
                    >
                </article>
                <nav aria-label="Stranice rasporeda" class="flex gap-5">
                    <Link
                        v-if="sessions.prev_page_url"
                        :href="sessions.prev_page_url"
                        class="underline"
                        >Prethodna</Link
                    >
                    <Link
                        v-if="sessions.next_page_url"
                        :href="sessions.next_page_url"
                        class="underline"
                        >Sljedeća</Link
                    >
                </nav>
            </section>
            <section class="grid gap-4" aria-labelledby="series-heading">
                <h2 id="series-heading" class="text-xl font-semibold">
                    Tjedne serije
                </h2>
                <p class="text-sm text-muted-foreground">
                    Generiranje čuva postojeća i otkazana pojavljivanja.
                    Zatvaranje zaustavlja buduće generiranje; postojeće termine
                    uredite ili otkažite zasebno.
                </p>
                <p v-if="!series.length" class="text-muted-foreground">
                    Još nema serija.
                </p>
                <article
                    v-for="item in series"
                    :key="item.id"
                    class="grid gap-4 rounded-xl border p-5"
                >
                    <h3 class="font-semibold">
                        Serija #{{ item.id }} ·
                        {{
                            item.weekdays
                                .map((day) => weekdays[day - 1])
                                .join(', ')
                        }}
                        · {{ item.local_start_time }}
                    </h3>
                    <p class="text-sm">
                        {{ item.starts_on.slice(0, 10) }} —
                        {{
                            item.ends_on?.slice(0, 10) ?? 'bez završnog datuma'
                        }}
                        · {{ item.timezone }} ·
                        {{ item.archived_at ? 'Zatvorena' : 'Aktivna' }}
                    </p>
                    <div v-if="!item.archived_at" class="flex flex-wrap gap-3">
                        <Button
                            variant="outline"
                            @click="
                                generating = item.id;
                                generation.clearErrors();
                                generation.reset();
                            "
                            >Generiraj termine</Button
                        >
                        <Button
                            variant="outline"
                            :disabled="closing.processing"
                            @click="
                                closing.post(closeSeries.url(item.id), {
                                    preserveScroll: true,
                                })
                            "
                            >Zatvori seriju</Button
                        >
                    </div>
                    <form
                        v-if="generating === item.id"
                        class="grid gap-4"
                        @submit.prevent="generateSeries(item.id)"
                    >
                        <p class="text-sm">
                            Ako DST vrijeme ne postoji ili je dvostruko, unesite
                            datum pojavljivanja, ispravljeno lokalno vrijeme i
                            po potrebi UTC pomak. Bez ispravaka zadržavaju se
                            pravila serije.
                        </p>
                        <div
                            v-for="(correction, i) in generation.corrections"
                            :key="i"
                            class="flex flex-wrap gap-3"
                        >
                            <label class="grid gap-1"
                                >Datum<input
                                    v-model="correction.date"
                                    type="date"
                                    required
                                    class="rounded border bg-background p-2"
                            /></label>
                            <label class="grid gap-1"
                                >Vrijeme<input
                                    v-model="correction.time"
                                    type="time"
                                    required
                                    class="rounded border bg-background p-2"
                            /></label>
                            <label class="grid gap-1"
                                >UTC pomak<input
                                    v-model="correction.offset"
                                    placeholder="+01:00"
                                    class="w-32 rounded border bg-background p-2"
                            /></label>
                            <Button
                                type="button"
                                variant="ghost"
                                @click="generation.corrections.splice(i, 1)"
                                >Ukloni ispravak</Button
                            >
                        </div>
                        <div role="alert" class="text-sm text-destructive">
                            <p v-for="error in generation.errors" :key="error">
                                {{ error }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                @click="
                                    generation.corrections.push({
                                        date: '',
                                        time: '',
                                        offset: '',
                                    })
                                "
                                >Dodaj DST ispravak</Button
                            >
                            <Button :disabled="generation.processing"
                                >Generiraj osam tjedana</Button
                            >
                        </div>
                    </form>
                </article>
                <p
                    v-for="error in closing.errors"
                    :key="error"
                    role="alert"
                    class="text-destructive"
                >
                    {{ error }}
                </p>
            </section>
        </div>
    </div>
</template>
