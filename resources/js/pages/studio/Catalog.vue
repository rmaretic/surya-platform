<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CatalogForm from '@/components/CatalogForm.vue';
import type { CatalogField, CatalogValues } from '@/components/CatalogForm.vue';
import { Button } from '@/components/ui/button';
import {
    store as storeType,
    update as updateType,
} from '@/routes/studio/catalog/training-types';
import {
    store as storeRoom,
    update as updateRoom,
} from '@/routes/studio/catalog/rooms';
import {
    store as storeInstructor,
    update as updateInstructor,
} from '@/routes/studio/catalog/instructors';
import {
    store as storePackage,
    update as updatePackage,
} from '@/routes/studio/catalog/packages';
import { store as storeRules } from '@/routes/studio/catalog/rules';

type Entry = { id: number; archived_at: string | null };
type TrainingType = Entry & {
    name: string;
    mode: string;
    duration_minutes: number;
    capacity: number;
    description: string | null;
};
type Room = Entry & { name: string; capacity: number };
type Instructor = Entry & {
    user_id: number;
    display_name: string;
    biography: string | null;
};
type Package = Entry & {
    name: string;
    price_cents: number;
    credits: number;
    validity_days: number;
    training_type_ids: number[];
};
type Rules = {
    minimum_notice_minutes: number;
    booking_horizon_days: number;
    group_cancellation_minutes: number;
    private_cancellation_minutes: number;
    studio_refund_validity_days: number;
};
const props = defineProps<{
    trainingTypes: TrainingType[];
    rooms: Room[];
    instructors: Instructor[];
    packages: Package[];
    staff: { id: number; name: string; is_active: boolean }[];
    rules: (Rules & { version: number }) | null;
    ruleDefaults: Rules;
    canManagePricing: boolean;
    statusMessage?: string;
}>();
const selectedType = ref<TrainingType | null>(null);
const selectedRoom = ref<Room | null>(null);
const selectedInstructor = ref<Instructor | null>(null);
const selectedPackage = ref<Package | null>(null);
const revision = ref({ type: 0, room: 0, instructor: 0, package: 0 });
function saved(kind: keyof typeof revision.value) {
    revision.value[kind]++;
}
const activeField: CatalogField = {
    key: 'is_active',
    label: 'Status',
    type: 'select',
    options: [
        { value: 1, label: 'Aktivno' },
        { value: 0, label: 'Arhivirano' },
    ],
};
const nameField: CatalogField = { key: 'name', label: 'Naziv', max: 255 };
const capacityField: CatalogField = {
    key: 'capacity',
    label: 'Kapacitet (osobe)',
    type: 'number',
    min: 1,
    max: 500,
};
const typeFields = computed<CatalogField[]>(() => [
    nameField,
    {
        key: 'mode',
        label: 'Način treninga',
        type: 'select',
        disabled: selectedType.value !== null,
        options: [
            { value: 'group', label: 'Grupni' },
            { value: 'private', label: 'Privatni (jedna osoba)' },
        ],
    },
    {
        key: 'duration_minutes',
        label: 'Trajanje (minute)',
        type: 'number',
        min: 1,
        max: 480,
    },
    capacityField,
    {
        key: 'description',
        label: 'Javni opis',
        type: 'textarea',
        max: 5000,
        required: false,
    },
    activeField,
]);
const typeValues = computed<CatalogValues>(() => ({
    name: selectedType.value?.name ?? '',
    mode: selectedType.value?.mode ?? 'group',
    duration_minutes: selectedType.value?.duration_minutes ?? 60,
    capacity: selectedType.value?.capacity ?? 1,
    description: selectedType.value?.description ?? '',
    is_active: selectedType.value?.archived_at ? 0 : 1,
}));
const roomValues = computed<CatalogValues>(() => ({
    name: selectedRoom.value?.name ?? '',
    capacity: selectedRoom.value?.capacity ?? 10,
    is_active: selectedRoom.value?.archived_at ? 0 : 1,
}));
const instructorFields = computed<CatalogField[]>(() => [
    {
        key: 'user_id',
        label: 'Aktivni član osoblja',
        type: 'select',
        disabled: selectedInstructor.value !== null,
        options: props.staff
            .filter(
                (user) =>
                    user.is_active ||
                    user.id === selectedInstructor.value?.user_id,
            )
            .map((user) => ({
                value: user.id,
                label: user.name + (user.is_active ? '' : ' (neaktivan račun)'),
            })),
    },
    { key: 'display_name', label: 'Javno ime instruktora', max: 255 },
    {
        key: 'biography',
        label: 'Javna biografija',
        type: 'textarea',
        max: 5000,
        required: false,
    },
    activeField,
]);
const instructorValues = computed<CatalogValues>(() => ({
    user_id: selectedInstructor.value?.user_id ?? '',
    display_name: selectedInstructor.value?.display_name ?? '',
    biography: selectedInstructor.value?.biography ?? '',
    is_active: selectedInstructor.value?.archived_at ? 0 : 1,
}));
const packageFields = computed<CatalogField[]>(() => [
    nameField,
    {
        key: 'price_cents',
        label: 'Cijena (EUR centi; 100 = 1 EUR)',
        type: 'number',
        min: 1,
        max: 10000000,
    },
    {
        key: 'credits',
        label: 'Broj kredita',
        type: 'number',
        min: 1,
        max: 1000,
    },
    {
        key: 'validity_days',
        label: 'Valjanost od dodjele (kalendarski dani)',
        type: 'number',
        min: 1,
        max: 3650,
    },
    {
        key: 'training_type_ids',
        label: 'Dopuštene vrste treninga',
        type: 'multiple',
        options: props.trainingTypes.map((type) => ({
            value: type.id,
            label: type.name + (type.archived_at ? ' (arhivirano)' : ''),
        })),
    },
    activeField,
]);
const packageValues = computed<CatalogValues>(() => ({
    name: selectedPackage.value?.name ?? '',
    price_cents: selectedPackage.value?.price_cents ?? 100,
    credits: selectedPackage.value?.credits ?? 5,
    validity_days: selectedPackage.value?.validity_days ?? 45,
    training_type_ids: selectedPackage.value?.training_type_ids ?? [],
    is_active: selectedPackage.value?.archived_at ? 0 : 1,
}));
const ruleFields: CatalogField[] = [
    {
        key: 'minimum_notice_minutes',
        label: 'Minimalna najava (minute)',
        type: 'number',
        min: 1,
        max: 525600,
    },
    {
        key: 'booking_horizon_days',
        label: 'Horizont rezervacije (dani)',
        type: 'number',
        min: 1,
        max: 365,
    },
    {
        key: 'group_cancellation_minutes',
        label: 'Pravovremeni otkaz grupnog treninga (minute prije početka)',
        type: 'number',
        min: 1,
        max: 525600,
    },
    {
        key: 'private_cancellation_minutes',
        label: 'Pravovremeni otkaz privatnog treninga (minute prije početka)',
        type: 'number',
        min: 1,
        max: 525600,
    },
    {
        key: 'studio_refund_validity_days',
        label: 'Najmanja valjanost vraćenog kredita kod otkaza studija (dani)',
        type: 'number',
        min: 7,
        max: 365,
    },
];
const ruleValues = computed<CatalogValues>(() =>
    Object.fromEntries(
        ruleFields.map((field) => [
            field.key,
            (props.rules ?? props.ruleDefaults)[field.key as keyof Rules],
        ]),
    ),
);
const price = (cents: number) =>
    new Intl.NumberFormat('hr-HR', {
        style: 'currency',
        currency: 'EUR',
    }).format(cents / 100);
</script>

<template>
    <div class="grid gap-10">
        <Head title="Katalog i pravila" />
        <div class="grid gap-3">
            <h1 class="text-3xl font-semibold">Katalog i pravila</h1>
            <p class="max-w-3xl text-muted-foreground">
                Vrste treninga, prostori i instruktori vašeg studija.
                Arhiviranje čuva povijest. Promjene zadanog trajanja i
                kapaciteta ne mijenjaju već stvorene termine.
            </p>
            <nav
                aria-label="Dijelovi kataloga"
                class="flex flex-wrap gap-4 text-sm underline"
            >
                <a href="#types">Vrste</a><a href="#rooms">Prostori</a
                ><a href="#instructors">Instruktori</a
                ><a href="#packages">Paketi</a><a href="#rules">Pravila</a>
            </nav>
            <p v-if="statusMessage" role="status" class="rounded-lg border p-4">
                {{ statusMessage }}
            </p>
        </div>
        <section id="types" class="grid gap-5 border-t pt-6">
            <h2 class="text-2xl font-semibold">Vrste treninga</h2>
            <div class="grid items-start gap-8 md:grid-cols-2">
                <div class="grid gap-3">
                    <p
                        v-if="!trainingTypes.length"
                        class="text-muted-foreground"
                    >
                        Još nema vrsta treninga. Dodajte prvu vrstu.
                    </p>
                    <article
                        v-for="entry in trainingTypes"
                        :key="entry.id"
                        class="flex items-center justify-between gap-4 rounded-lg border p-4"
                    >
                        <div class="min-w-0">
                            <h3 class="font-medium wrap-anywhere">
                                {{ entry.name }}
                            </h3>
                            <p class="text-sm text-muted-foreground">
                                {{
                                    entry.mode === 'private'
                                        ? 'Privatni'
                                        : 'Grupni'
                                }}
                                · {{ entry.duration_minutes }} min ·
                                {{ entry.capacity }} mjesta{{
                                    entry.archived_at ? ' · Arhivirano' : ''
                                }}
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            :aria-label="`Uredi vrstu ${entry.name}`"
                            @click="selectedType = entry"
                            >Uredi</Button
                        >
                    </article>
                </div>
                <div class="grid gap-5 rounded-xl border bg-muted/20 p-5">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <h3 class="font-semibold">
                            {{ selectedType ? 'Uredi vrstu' : 'Nova vrsta' }}
                        </h3>
                        <Button
                            v-if="selectedType"
                            variant="ghost"
                            @click="selectedType = null"
                            >Nova vrsta</Button
                        >
                    </div>
                    <CatalogForm
                        :key="`type-${selectedType?.id}-${revision.type}`"
                        id="type"
                        :fields="typeFields"
                        :initial="typeValues"
                        :action="
                            selectedType
                                ? updateType(selectedType.id)
                                : storeType()
                        "
                        @saved="
                            selectedType = null;
                            saved('type');
                        "
                    />
                </div>
            </div>
        </section>
        <section id="rooms" class="grid gap-5 border-t pt-6">
            <h2 class="text-2xl font-semibold">Prostori</h2>
            <p class="text-muted-foreground">
                Prostorije na jednoj lokaciji studija.
            </p>
            <div class="grid items-start gap-8 md:grid-cols-2">
                <div class="grid gap-3">
                    <p v-if="!rooms.length" class="text-muted-foreground">
                        Još nema prostora.
                    </p>
                    <article
                        v-for="entry in rooms"
                        :key="entry.id"
                        class="flex items-center justify-between gap-4 rounded-lg border p-4"
                    >
                        <div class="min-w-0">
                            <h3 class="font-medium wrap-anywhere">
                                {{ entry.name }}
                            </h3>
                            <p class="text-sm text-muted-foreground">
                                {{ entry.capacity }} mjesta{{
                                    entry.archived_at ? ' · Arhivirano' : ''
                                }}
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            :aria-label="`Uredi prostor ${entry.name}`"
                            @click="selectedRoom = entry"
                            >Uredi</Button
                        >
                    </article>
                </div>
                <div class="grid gap-5 rounded-xl border bg-muted/20 p-5">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <h3 class="font-semibold">
                            {{
                                selectedRoom ? 'Uredi prostor' : 'Novi prostor'
                            }}
                        </h3>
                        <Button
                            v-if="selectedRoom"
                            variant="ghost"
                            @click="selectedRoom = null"
                            >Novi prostor</Button
                        >
                    </div>
                    <CatalogForm
                        :key="`room-${selectedRoom?.id}-${revision.room}`"
                        id="room"
                        :fields="[nameField, capacityField, activeField]"
                        :initial="roomValues"
                        :action="
                            selectedRoom
                                ? updateRoom(selectedRoom.id)
                                : storeRoom()
                        "
                        @saved="
                            selectedRoom = null;
                            saved('room');
                        "
                    />
                </div>
            </div>
        </section>
        <section id="instructors" class="grid gap-5 border-t pt-6">
            <h2 class="text-2xl font-semibold">Instruktori</h2>
            <p class="max-w-3xl text-muted-foreground">
                Profil povežite s aktivnim članom osoblja. Owner može voditi
                trening bez promjene uloge. Unesite samo ime i biografiju koje
                smiju biti javni. Prije deaktivacije potrebno je riješiti buduće
                termine i aktivni raspored.
            </p>
            <div class="grid items-start gap-8 md:grid-cols-2">
                <div class="grid gap-3">
                    <p v-if="!instructors.length" class="text-muted-foreground">
                        Još nema instruktorskih profila.
                    </p>
                    <article
                        v-for="entry in instructors"
                        :key="entry.id"
                        class="flex items-center justify-between gap-4 rounded-lg border p-4"
                    >
                        <div class="min-w-0">
                            <h3 class="font-medium wrap-anywhere">
                                {{ entry.display_name }}
                            </h3>
                            <p class="text-sm text-muted-foreground">
                                {{
                                    entry.archived_at ? 'Arhivirano' : 'Aktivno'
                                }}
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            :aria-label="`Uredi instruktora ${entry.display_name}`"
                            @click="selectedInstructor = entry"
                            >Uredi</Button
                        >
                    </article>
                </div>
                <div class="grid gap-5 rounded-xl border bg-muted/20 p-5">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <h3 class="font-semibold">
                            {{
                                selectedInstructor
                                    ? 'Uredi instruktora'
                                    : 'Novi instruktor'
                            }}
                        </h3>
                        <Button
                            v-if="selectedInstructor"
                            variant="ghost"
                            @click="selectedInstructor = null"
                            >Novi instruktor</Button
                        >
                    </div>
                    <CatalogForm
                        :key="`instructor-${selectedInstructor?.id}-${revision.instructor}`"
                        id="instructor"
                        :fields="instructorFields"
                        :initial="instructorValues"
                        :action="
                            selectedInstructor
                                ? updateInstructor(selectedInstructor.id)
                                : storeInstructor()
                        "
                        @saved="
                            selectedInstructor = null;
                            saved('instructor');
                        "
                    />
                </div>
            </div>
        </section>
        <section id="packages" class="grid gap-5 border-t pt-6">
            <h2 class="text-2xl font-semibold">Paketi i cijene</h2>
            <p class="max-w-3xl text-muted-foreground">
                Cjenik uređuje samo owner. Spremanje paketa ne naplaćuje novac i
                ne dodjeljuje kredite. Prethodno dodijeljena prava zadržavaju
                svoj sadržaj i valjanost.
            </p>
            <div
                class="grid items-start gap-8"
                :class="{ 'md:grid-cols-2': canManagePricing }"
            >
                <div class="grid gap-3">
                    <p v-if="!packages.length" class="text-muted-foreground">
                        Još nema paketa.
                    </p>
                    <article
                        v-for="entry in packages"
                        :key="entry.id"
                        class="flex items-center justify-between gap-4 rounded-lg border p-4"
                    >
                        <div class="min-w-0">
                            <h3 class="font-medium wrap-anywhere">
                                {{ entry.name }}
                            </h3>
                            <p class="text-sm text-muted-foreground">
                                {{ price(entry.price_cents) }} ·
                                {{ entry.credits }} kredita ·
                                {{ entry.validity_days }} dana{{
                                    entry.archived_at ? ' · Arhivirano' : ''
                                }}
                            </p>
                        </div>
                        <Button
                            v-if="canManagePricing"
                            variant="outline"
                            :aria-label="`Uredi paket ${entry.name}`"
                            @click="selectedPackage = entry"
                            >Uredi</Button
                        >
                    </article>
                </div>
                <div
                    v-if="canManagePricing"
                    class="grid gap-5 rounded-xl border bg-muted/20 p-5"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <h3 class="font-semibold">
                            {{ selectedPackage ? 'Uredi paket' : 'Novi paket' }}
                        </h3>
                        <Button
                            v-if="selectedPackage"
                            variant="ghost"
                            @click="selectedPackage = null"
                            >Novi paket</Button
                        >
                    </div>
                    <CatalogForm
                        :key="`package-${selectedPackage?.id}-${revision.package}`"
                        id="package"
                        :fields="packageFields"
                        :initial="packageValues"
                        :action="
                            selectedPackage
                                ? updatePackage(selectedPackage.id)
                                : storePackage()
                        "
                        @saved="
                            selectedPackage = null;
                            saved('package');
                        "
                    />
                </div>
            </div>
        </section>
        <section id="rules" class="grid gap-5 border-t pt-6">
            <h2 class="text-2xl font-semibold">Pravila rezervacija</h2>
            <p class="max-w-3xl text-muted-foreground">
                {{
                    rules
                        ? `Aktualna verzija: ${rules.version}.`
                        : 'Pravila još nisu spremljena. Prikazane su radne zadane vrijednosti.'
                }}
                Svako spremanje stvara novu verziju. Postojeće rezervacije
                zadržavaju svoja pravila. Rok za otkaz računa se unatrag od
                početka treninga.
            </p>
            <CatalogForm
                v-if="canManagePricing"
                :key="`rules-${rules?.version}`"
                id="rule"
                class="max-w-xl rounded-xl border bg-muted/20 p-5"
                :fields="ruleFields"
                :initial="ruleValues"
                :action="storeRules()"
                submit-label="Spremi novu verziju"
            />
            <dl v-else class="grid max-w-2xl gap-3">
                <div
                    v-for="field in ruleFields"
                    :key="field.key"
                    class="flex justify-between gap-4 border-b pb-3"
                >
                    <dt>{{ field.label }}</dt>
                    <dd class="font-medium">{{ ruleValues[field.key] }}</dd>
                </div>
            </dl>
        </section>
    </div>
</template>
