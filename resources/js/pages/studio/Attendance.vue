<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import {
    index,
    store,
} from '@/actions/App/Http/Controllers/StudioAttendanceController';
import { Button } from '@/components/ui/button';

type AttendanceStatus = 'pending' | 'present' | 'no_show';
type Booking = {
    id: number;
    name: string;
    booking_status: string;
    status: AttendanceStatus;
    recorded_at: string | null;
    recorded_by: string | null;
};
const props = defineProps<{
    date: string;
    timezone: string;
    operationKey: string;
    sessions: { id: number; name: string; time: string; status: string }[];
    selected: {
        id: number;
        name: string;
        local_start: string;
        can_record: boolean;
        can_mark_no_show: boolean;
        bookings: Booking[];
    } | null;
    statusMessage?: string;
}>();
const filters = useForm({ date: props.date });
const form = useForm({
    status: 'present' as AttendanceStatus,
    expected_status: 'pending' as AttendanceStatus,
    reason: '',
    operation_key: '',
});
const editing = ref<Booking | null>(null);
const labels: Record<string, string> = {
    pending: 'Čeka evidenciju',
    present: 'Prisutan',
    no_show: 'Potvrđen nedolazak',
    cancelled: 'Otkazano',
    published: 'Objavljeno',
    completed: 'Dovršeno',
    draft: 'Nacrt',
};
function choose(booking: Booking, status: AttendanceStatus) {
    editing.value = booking;
    form.reset();
    form.clearErrors();
    form.status = status;
    form.expected_status = booking.status;
}
function submit() {
    if (!editing.value) return;
    form.operation_key ||= props.operationKey;
    form.post(store.url(editing.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
            form.reset();
        },
    });
}
watch(
    () => props.selected?.id,
    () => {
        editing.value = null;
        form.reset();
        form.clearErrors();
    },
);
</script>

<template>
    <div class="space-y-6">
        <Head title="Dolasci" />
        <header class="space-y-2">
            <h1 class="text-2xl font-semibold">Dolasci</h1>
            <p class="text-muted-foreground">
                Ručna evidencija · {{ timezone }}. Nedolazak se potvrđuje nakon
                kraja termina. Neevidentirani polaznici ostaju na čekanju.
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
                >Datum<input
                    v-model="filters.date"
                    type="date"
                    required
                    class="rounded-md border bg-background px-3 py-2"
            /></label>
            <Button :disabled="filters.processing">Prikaži termine</Button>
            <p v-if="filters.errors.date" role="alert">
                {{ filters.errors.date }}
            </p>
        </form>
        <div class="grid gap-6 md:grid-cols-[16rem_1fr]">
            <nav aria-label="Termini za evidenciju" class="space-y-2">
                <p v-if="!sessions.length" class="text-muted-foreground">
                    Nema termina za odabrani dan.
                </p>
                <Link
                    v-for="session in sessions"
                    :key="session.id"
                    :href="index({ query: { date, session: session.id } })"
                    :aria-current="
                        selected?.id === session.id ? 'page' : undefined
                    "
                    class="block rounded-lg border p-4 hover:bg-muted focus-visible:ring-2"
                    :class="{ 'bg-muted': selected?.id === session.id }"
                >
                    <span class="block font-medium"
                        >{{ session.time }} · {{ session.name }}</span
                    ><span class="text-sm text-muted-foreground">{{
                        labels[session.status]
                    }}</span>
                </Link>
            </nav>
            <section
                v-if="selected"
                class="min-w-0 space-y-4"
                aria-label="Popis polaznika"
            >
                <h2 class="text-xl font-semibold">
                    {{ selected.name }} · {{ selected.local_start }}
                </h2>
                <p v-if="!selected.can_record" class="text-muted-foreground">
                    Evidencija za ovaj termin nije dostupna.
                </p>
                <p v-if="!selected.bookings.length">Nema rezervacija.</p>
                <article
                    v-for="booking in selected.bookings"
                    :key="booking.id"
                    class="space-y-3 rounded-lg border p-4"
                >
                    <h3 class="font-semibold wrap-anywhere">
                        {{ booking.name }}
                    </h3>
                    <p>
                        {{
                            booking.booking_status === 'cancelled'
                                ? 'Otkazana rezervacija'
                                : labels[booking.status]
                        }}
                    </p>
                    <p
                        v-if="booking.recorded_at"
                        class="text-sm text-muted-foreground"
                    >
                        {{ labels[booking.status] }} ·
                        {{ booking.recorded_by }} · {{ booking.recorded_at }}
                    </p>
                    <p
                        v-if="
                            booking.status === 'pending' &&
                            selected.can_mark_no_show &&
                            booking.booking_status === 'confirmed'
                        "
                        class="text-sm text-muted-foreground"
                    >
                        Termin je završio. Provjerite dolazak prije potvrde
                        nedolaska.
                    </p>
                    <div
                        v-if="
                            selected.can_record &&
                            booking.booking_status === 'confirmed'
                        "
                        class="flex flex-wrap gap-2"
                    >
                        <Button
                            v-if="booking.status !== 'present'"
                            variant="outline"
                            :disabled="form.processing"
                            @click="choose(booking, 'present')"
                            >{{
                                booking.status === 'pending'
                                    ? 'Prisutan'
                                    : 'Ispravi u prisutan'
                            }}</Button
                        >
                        <Button
                            v-if="
                                booking.status !== 'no_show' &&
                                selected.can_mark_no_show
                            "
                            variant="outline"
                            :disabled="form.processing"
                            @click="choose(booking, 'no_show')"
                            >{{
                                booking.status === 'pending'
                                    ? 'Potvrdi nedolazak'
                                    : 'Ispravi u nedolazak'
                            }}</Button
                        >
                        <Button
                            v-if="booking.status !== 'pending'"
                            variant="outline"
                            :disabled="form.processing"
                            @click="choose(booking, 'pending')"
                            >Vrati na čekanje</Button
                        >
                    </div>
                    <form
                        v-if="editing?.id === booking.id"
                        class="space-y-3 border-t pt-3"
                        @submit.prevent="submit"
                    >
                        <p>
                            Spremiti:
                            <strong>{{ labels[form.status] }}</strong> za
                            {{ booking.name }}?
                        </p>
                        <label
                            v-if="booking.status !== 'pending'"
                            class="grid gap-2"
                            >Razlog ispravka<textarea
                                v-model="form.reason"
                                required
                                maxlength="2000"
                                class="rounded-md border bg-background p-3"
                            />
                        </label>
                        <p
                            v-for="(error, field) in form.errors"
                            :key="field"
                            role="alert"
                            class="text-destructive"
                        >
                            {{ error }}
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <Button :disabled="form.processing">{{
                                form.processing
                                    ? 'Spremanje…'
                                    : 'Spremi evidenciju'
                            }}</Button
                            ><Button
                                type="button"
                                variant="outline"
                                :disabled="form.processing"
                                @click="editing = null"
                                >Odustani</Button
                            >
                        </div>
                    </form>
                </article>
            </section>
            <p v-else class="text-muted-foreground">
                Odaberite termin za pregled i evidenciju dolazaka.
            </p>
        </div>
    </div>
</template>
