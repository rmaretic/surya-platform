<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    index,
    store,
    update,
    exception,
    close,
} from '@/actions/App/Http/Controllers/StudioAvailabilityController';
import CatalogForm from '@/components/CatalogForm.vue';
import type { CatalogField, CatalogValues } from '@/components/CatalogForm.vue';
import { Button } from '@/components/ui/button';

type Exception = {
    local_date: string;
    is_unavailable: boolean;
    local_start_time: string;
    local_end_time: string;
    reason: string;
};
type Availability = {
    id: number;
    revision: string;
    training_type_id: number;
    instructor_profile_id: number;
    room_id: number;
    weekday: number;
    local_start_time: string;
    local_end_time: string;
    starts_on: string;
    ends_on: string | null;
    duration_minutes: number;
    slot_step_minutes: number;
    buffer_before_minutes: number;
    buffer_after_minutes: number;
    archived: boolean;
    training_type: string;
    instructor: string;
    room: string;
    notice_minutes: number;
    horizon_days: number;
    exceptions: Exception[];
};
const props = defineProps<{
    timezone: string;
    date: string;
    operationKey: string;
    availability: Availability[];
    preview: {
        slots: {
            availability_rule_id: number;
            starts_at: string;
            ends_at: string;
            local_start: string;
            local_end: string;
            utc_offset: string;
            training_type: string;
            instructor: string;
            room: string;
            duration_minutes: number;
        }[];
        warnings: string[];
    };
    trainingTypes: { id: number; name: string }[];
    instructors: { id: number; display_name: string }[];
    rooms: { id: number; name: string }[];
    currentRules: {
        minimum_notice_minutes: number;
        booking_horizon_days: number;
    } | null;
    statusMessage?: string;
}>();
const mode = ref<'rule' | 'exception' | null>(null);
const editing = ref<Availability | null>(null);
const editingException = ref<Exception | null>(null);
const filters = useForm({ date: props.date });
const closing = useForm({ operation_key: '', revision: '' });
const weekdays = [
    'Ponedjeljak',
    'Utorak',
    'Srijeda',
    'Četvrtak',
    'Petak',
    'Subota',
    'Nedjelja',
];
function openRule(rule: Availability | null) {
    editing.value = rule;
    mode.value = 'rule';
}
function openException(rule: Availability, item: Exception | null = null) {
    editing.value = rule;
    editingException.value = item;
    mode.value = 'exception';
}
function ruleFields(): CatalogField[] {
    return [
        {
            key: 'training_type_id',
            label: 'Privatna vrsta',
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
            key: 'weekday',
            label: 'Dan u tjednu',
            type: 'select',
            options: weekdays.map((label, i) => ({ value: i + 1, label })),
        },
        { key: 'local_start_time', label: 'Dostupnost od (HH:MM)' },
        { key: 'local_end_time', label: 'Dostupnost do (HH:MM)' },
        { key: 'starts_on', label: 'Početni datum (GGGG-MM-DD)' },
        {
            key: 'ends_on',
            label: 'Završni datum (GGGG-MM-DD, neobvezno)',
            required: false,
        },
        {
            key: 'duration_minutes',
            label: 'Trajanje treninga (min)',
            type: 'number',
            min: 1,
            max: 480,
        },
        {
            key: 'slot_step_minutes',
            label: 'Korak početaka (min)',
            type: 'number',
            min: 1,
            max: 480,
        },
        {
            key: 'buffer_before_minutes',
            label: 'Buffer prije (min)',
            type: 'number',
            min: 0,
            max: 240,
        },
        {
            key: 'buffer_after_minutes',
            label: 'Buffer poslije (min)',
            type: 'number',
            min: 0,
            max: 240,
        },
    ];
}
function ruleValues(): CatalogValues {
    const values: CatalogValues = {
        training_type_id: '',
        instructor_profile_id: '',
        room_id: '',
        weekday: 1,
        local_start_time: '09:00',
        local_end_time: '17:00',
        starts_on: props.date,
        ends_on: '',
        duration_minutes: 60,
        slot_step_minutes: 30,
        buffer_before_minutes: 15,
        buffer_after_minutes: 15,
        operation_key: props.operationKey,
    };
    if (editing.value) {
        for (const field of ruleFields()) {
            values[field.key] =
                (editing.value[field.key as keyof Availability] as
                    | string
                    | number) ?? '';
        }
        values.revision = editing.value.revision;
    }
    return values;
}
const exceptionFields: CatalogField[] = [
    { key: 'local_date', label: 'Datum iznimke (GGGG-MM-DD)' },
    {
        key: 'is_unavailable',
        label: 'Vrsta iznimke',
        type: 'select',
        options: [
            { value: 1, label: 'Nedostupno cijeli dan' },
            { value: 0, label: 'Zamjenski prozor dostupnosti' },
        ],
    },
    {
        key: 'local_start_time',
        label: 'Zamjenski početak (HH:MM; prazno za nedostupnost)',
        required: false,
    },
    {
        key: 'local_end_time',
        label: 'Zamjenski kraj (HH:MM; prazno za nedostupnost)',
        required: false,
    },
    { key: 'reason', label: 'Razlog', type: 'textarea', max: 2000 },
];
function exceptionValues(): CatalogValues {
    return {
        local_date: editingException.value?.local_date ?? props.date,
        is_unavailable: editingException.value
            ? Number(editingException.value.is_unavailable)
            : 1,
        local_start_time: editingException.value?.local_start_time ?? '',
        local_end_time: editingException.value?.local_end_time ?? '',
        reason: editingException.value?.reason ?? '',
        revision: editing.value?.revision ?? '',
        operation_key: props.operationKey,
    };
}
function closeRule(rule: Availability) {
    closing.operation_key = props.operationKey;
    closing.revision = rule.revision;
    closing.submit(close(rule.id), { preserveScroll: true });
}
</script>

<template>
    <div class="space-y-8">
        <Head title="Privatna dostupnost" />
        <header class="space-y-3">
            <h1 class="text-3xl font-semibold">Privatna dostupnost</h1>
            <p class="max-w-3xl text-muted-foreground">
                Tjedni prozori i iznimke u zoni {{ timezone }}. Slotovi su
                prijedlozi, nisu potvrđene rezervacije. Zauzeće grupnih i
                privatnih termina provjerava se za instruktora i prostor.
            </p>
            <p v-if="statusMessage" role="status" class="text-sm">
                {{ statusMessage }}
            </p>
            <Button @click="openRule(null)">Dodaj dostupnost</Button>
        </header>
        <section
            v-if="mode"
            class="max-w-2xl space-y-5 rounded-xl border p-5"
            aria-labelledby="availability-form-title"
        >
            <div class="flex items-center justify-between gap-3">
                <h2 id="availability-form-title" class="text-xl font-semibold">
                    {{
                        mode === 'exception'
                            ? 'Iznimka dostupnosti'
                            : editing
                              ? 'Uredi dostupnost'
                              : 'Nova dostupnost'
                    }}
                </h2>
                <Button variant="ghost" @click="mode = null"
                    >Zatvori obrazac</Button
                >
            </div>
            <p v-if="mode === 'rule'" class="text-sm text-muted-foreground">
                Cijeli trening i oba buffera moraju stati u prozor. Počeci prate
                korak od početka prozora. Spremanje koristi aktualna ownerova
                pravila:
                <template v-if="currentRules"
                    >najava {{ currentRules.minimum_notice_minutes }} min,
                    horizont
                    {{ currentRules.booking_horizon_days }} dana.</template
                ><template v-else>prvo ih treba spremiti u katalogu.</template>
            </p>
            <p v-else class="text-sm text-muted-foreground">
                Jedna iznimka zamjenjuje prozor za taj datum, uključujući drugi
                dan tjedna. Ponovno spremanje istog datuma uređuje iznimku. Za
                povrat tjednog prozora unesite njegove sate.
            </p>
            <CatalogForm
                v-if="mode === 'rule'"
                :key="`rule-${editing?.id ?? 'new'}`"
                id="availability"
                :fields="ruleFields()"
                :initial="ruleValues()"
                :action="editing ? update(editing.id) : store()"
                @saved="mode = null"
            />
            <CatalogForm
                v-else-if="editing"
                :key="`exception-${editing.id}-${editingException?.local_date ?? 'new'}`"
                id="availability-exception"
                :fields="exceptionFields"
                :initial="exceptionValues()"
                :action="exception(editing.id)"
                @saved="mode = null"
            />
        </section>
        <section aria-labelledby="weekly-title" class="space-y-4">
            <h2 id="weekly-title" class="text-xl font-semibold">
                Tjedni prozori
            </h2>
            <p
                v-if="!availability.length"
                class="rounded-xl border p-6 text-muted-foreground"
            >
                Još nema privatne dostupnosti. Dodajte privatnu vrstu,
                instruktora i prostor u katalogu, zatim prvi prozor.
            </p>
            <div
                v-for="rule in availability"
                :key="rule.id"
                class="space-y-3 rounded-xl border p-5"
            >
                <h3 class="font-semibold">
                    {{ rule.training_type }} · {{ weekdays[rule.weekday - 1] }}
                    {{ rule.local_start_time }}–{{ rule.local_end_time }}
                    <span v-if="rule.archived" class="text-muted-foreground"
                        >(zatvoreno)</span
                    >
                </h3>
                <p class="text-sm">
                    {{ rule.instructor }} · {{ rule.room }} ·
                    {{ rule.starts_on }} – {{ rule.ends_on ?? 'bez kraja' }}
                </p>
                <p class="text-sm text-muted-foreground">
                    Trajanje {{ rule.duration_minutes }} min · korak
                    {{ rule.slot_step_minutes }} min · buffer
                    {{ rule.buffer_before_minutes }}/{{
                        rule.buffer_after_minutes
                    }}
                    min · najava {{ rule.notice_minutes }} min · horizont
                    {{ rule.horizon_days }} dana
                </p>
                <div v-if="!rule.archived" class="flex flex-wrap gap-3">
                    <Button variant="outline" @click="openRule(rule)"
                        >Uredi prozor</Button
                    >
                    <Button variant="outline" @click="openException(rule)"
                        >Dodaj iznimku</Button
                    >
                    <Button
                        variant="ghost"
                        :disabled="closing.processing"
                        @click="closeRule(rule)"
                        >Zatvori dostupnost</Button
                    >
                </div>
                <ul v-if="rule.exceptions.length" class="space-y-2 text-sm">
                    <li
                        v-for="item in rule.exceptions"
                        :key="item.local_date"
                        class="flex flex-wrap items-center gap-2"
                    >
                        <span
                            >{{ item.local_date }}:
                            {{
                                item.is_unavailable
                                    ? 'Nedostupno'
                                    : `${item.local_start_time}–${item.local_end_time}`
                            }}
                            · {{ item.reason }}</span
                        >
                        <Button
                            v-if="!rule.archived"
                            variant="ghost"
                            size="sm"
                            @click="openException(rule, item)"
                            >Uredi iznimku</Button
                        >
                    </li>
                </ul>
            </div>
            <div
                v-if="Object.keys(closing.errors).length"
                role="alert"
                class="text-sm text-destructive"
            >
                <p v-for="(error, key) in closing.errors" :key="key">
                    {{ error }}
                </p>
            </div>
        </section>
        <section class="space-y-4" aria-labelledby="slots-title">
            <h2 id="slots-title" class="text-xl font-semibold">
                Pregled slobodnih slotova
            </h2>
            <form
                class="flex flex-wrap items-end gap-3"
                @submit.prevent="
                    filters.submit(index(), { preserveScroll: true })
                "
            >
                <div class="grid gap-2">
                    <label for="slot-date" class="text-sm"
                        >Datum ({{ timezone }})</label
                    ><input
                        id="slot-date"
                        v-model="filters.date"
                        type="date"
                        required
                        class="rounded-md border bg-background px-3 py-2"
                    />
                    <p
                        v-if="filters.errors.date"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ filters.errors.date }}
                    </p>
                </div>
                <Button :disabled="filters.processing">{{
                    filters.processing ? 'Učitavanje…' : 'Prikaži slotove'
                }}</Button>
            </form>
            <p class="text-sm text-muted-foreground">
                Bez provjere vanjskog Google kalendara. Dostupnost se ponovno
                provjerava pri budućoj potvrdi rezervacije.
            </p>
            <p
                v-for="warning in preview.warnings"
                :key="warning"
                role="alert"
                class="rounded-lg border p-4 text-sm"
            >
                {{ warning }}
            </p>
            <p
                v-if="!preview.slots.length"
                role="status"
                class="rounded-xl border p-6 text-muted-foreground"
            >
                Za {{ date }} nema slobodnih slotova unutar dostupnosti i rokova
                rezervacije.
            </p>
            <ul v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <li
                    v-for="slot in preview.slots"
                    :key="`${slot.availability_rule_id}-${slot.starts_at}`"
                    class="space-y-2 rounded-xl border p-4"
                >
                    <p class="font-semibold">
                        {{ slot.local_start }}–{{ slot.local_end }}
                        <span class="text-xs font-normal"
                            >UTC{{ slot.utc_offset }}</span
                        >
                    </p>
                    <p class="text-sm">
                        {{ slot.training_type }} ·
                        {{ slot.duration_minutes }} min
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ slot.instructor }} · {{ slot.room }}
                    </p>
                </li>
            </ul>
        </section>
    </div>
</template>
