<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { dashboard as platformDashboard } from '@/routes/platform';
import { index as attendance } from '@/routes/studio/attendance';
import type { OperationalDashboardData } from '@/types/operational-dashboard';

const props = defineProps<OperationalDashboardData>();
const reportRoute = computed(() =>
    props.tenants ? platformDashboard : dashboard,
);
const pagination = computed(() => props.tenants ?? props.sessions);
const cards = computed(() => [
    ['Termini', props.summary.sessions],
    ['Potvrđena mjesta', props.summary.confirmed],
    ['Slobodna mjesta', props.summary.available],
    ['Čeka evidenciju', props.summary.pending],
    ['Prisutni', props.summary.present],
    ['Potvrđeni nedolasci', props.summary.no_show],
    ['Kasni otkazi', props.summary.late_cancelled],
    ['Otkazani termini', props.summary.cancelled],
]);
const statuses: Record<string, string> = {
    published: 'Objavljen',
    completed: 'Završen',
    cancelled: 'Otkazan',
    active: 'Aktivan',
    pending: 'Čeka aktivaciju',
    suspended: 'Suspendiran',
};
function percentage(value: number | null): string {
    return value === null
        ? 'Nema kapaciteta'
        : `${value.toLocaleString('hr-HR')} %`;
}
</script>

<template>
    <section class="grid min-w-0 gap-6" aria-label="Operativni izvještaj">
        <Form
            :action="reportRoute.url()"
            method="get"
            v-slot="{ errors, processing }"
            class="flex flex-wrap items-end gap-3"
        >
            <div class="grid gap-2">
                <Label for="from">Od datuma</Label
                ><Input
                    id="from"
                    name="from"
                    type="date"
                    :default-value="period.from"
                    required
                /><InputError :message="errors.from" />
            </div>
            <div class="grid gap-2">
                <Label for="to">Do datuma (uključivo)</Label
                ><Input
                    id="to"
                    name="to"
                    type="date"
                    :default-value="period.to"
                    required
                /><InputError :message="errors.to" />
            </div>
            <Button :disabled="processing">Prikaži</Button>
            <Link :href="reportRoute()" class="p-2 text-sm underline"
                >Danas i idućih šest dana</Link
            >
        </Form>
        <div class="grid gap-2 text-sm text-muted-foreground">
            <p class="font-medium text-foreground">
                {{ period.from }} – {{ period.to }} · {{ period.timezone }} ·
                {{
                    tenants
                        ? 'Svi studiji, uključujući neaktivne'
                        : scope === 'own'
                          ? 'Samo vaši termini'
                          : 'Svi termini ovog studija'
                }}
            </p>
            <p>
                Razdoblje se odnosi na početak treninga. Uključeni su grupni i
                privatni objavljeni i završeni termini. Nacrti su isključeni;
                otkazani termini prikazani su zasebno.
            </p>
            <p>
                Potvrđena mjesta uključuju prisutne, nedolaske i one koji čekaju
                evidenciju. Nedolazak se ne pretpostavlja. Kasni otkazi broje se
                zasebno, uključujući naknadno otkazane termine.
            </p>
        </div>
        <div class="grid gap-3 rounded-xl border bg-muted/20 p-6">
            <h2 class="text-lg font-semibold">Popunjenost</h2>
            <p class="text-3xl font-semibold tabular-nums">
                {{ percentage(summary.occupancy) }}
            </p>
            <p class="text-sm text-muted-foreground">
                {{ summary.confirmed }} potvrđenih mjesta /
                {{ summary.capacity }} mjesta kapaciteta. Zbrojevi cijelog
                odabranog obuhvata, bez otkazanih termina.
            </p>
        </div>
        <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div
                v-for="[label, value] in cards"
                :key="label"
                class="rounded-xl border p-4"
            >
                <dt class="text-sm text-muted-foreground">{{ label }}</dt>
                <dd class="mt-2 text-2xl font-semibold tabular-nums">
                    {{ value }}
                </dd>
            </div>
        </dl>
        <p
            v-if="summary.sessions === 0"
            class="rounded-lg bg-muted/30 p-4 text-sm"
        >
            U odabranom obuhvatu nema objavljenih ni završenih termina.
        </p>
        <div v-if="sessions" class="grid min-w-0 gap-3">
            <h2 class="text-xl font-semibold">Termini u razdoblju</h2>
            <p class="text-sm text-muted-foreground">
                Kapacitet i status pripadaju pojedinom terminu. Povijesni
                termini zadržavaju svoj kapacitet; promjena kataloga ga ne
                mijenja. Slobodna mjesta prošlih termina nisu dostupnost za
                rezervaciju.
            </p>
            <div
                class="overflow-x-auto rounded-xl border"
                tabindex="0"
                role="region"
                aria-label="Tablica termina"
            >
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="p-4" scope="col">Termin</th>
                            <th class="p-4" scope="col">Status</th>
                            <th class="p-4" scope="col">
                                Potvrđeno / kapacitet
                            </th>
                            <th class="p-4" scope="col">Slobodno</th>
                            <th class="p-4" scope="col">Čeka</th>
                            <th class="p-4" scope="col">Prisutni</th>
                            <th class="p-4" scope="col">Nedolasci</th>
                            <th class="p-4" scope="col">Kasni otkazi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="session in sessions.data"
                            :key="session.id"
                            class="border-t"
                        >
                            <td class="p-4">
                                <Link
                                    :href="
                                        attendance({
                                            query: {
                                                date: session.local_start.slice(
                                                    0,
                                                    10,
                                                ),
                                                session: session.id,
                                            },
                                        })
                                    "
                                    class="font-medium underline"
                                    >{{ session.name }}</Link
                                >
                                <p
                                    class="mt-1 whitespace-nowrap text-muted-foreground"
                                >
                                    {{ session.local_start }}
                                </p>
                            </td>
                            <td class="p-4">{{ statuses[session.status] }}</td>
                            <td class="p-4">
                                {{ session.confirmed }} / {{ session.capacity }}
                            </td>
                            <td class="p-4">{{ session.available ?? '—' }}</td>
                            <td class="p-4">{{ session.pending }}</td>
                            <td class="p-4">{{ session.present }}</td>
                            <td class="p-4">{{ session.no_show }}</td>
                            <td class="p-4">{{ session.late_cancelled }}</td>
                        </tr>
                    </tbody>
                </table>
                <p
                    v-if="!sessions.data.length"
                    class="p-5 text-muted-foreground"
                >
                    Nema termina za prikaz.
                </p>
            </div>
        </div>
        <div v-if="tenants" class="grid min-w-0 gap-3">
            <h2 class="text-xl font-semibold">Agregati po studiju</h2>
            <p class="text-sm text-muted-foreground">
                Zajednička vremenska zona omogućuje usporedbu istog intervala.
                Ukupna stopa računa se iz ukupnih mjesta i kapaciteta svih
                studija, ne iz prosjeka njihovih postotaka.
            </p>
            <div
                class="overflow-x-auto rounded-xl border"
                tabindex="0"
                role="region"
                aria-label="Tablica studija"
            >
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="p-4" scope="col">Studio</th>
                            <th class="p-4" scope="col">Termini / otkazani</th>
                            <th class="p-4" scope="col">
                                Potvrđeno / kapacitet
                            </th>
                            <th class="p-4" scope="col">Popunjenost</th>
                            <th class="p-4" scope="col">Slobodno</th>
                            <th class="p-4" scope="col">Čeka</th>
                            <th class="p-4" scope="col">Prisutni</th>
                            <th class="p-4" scope="col">Nedolasci</th>
                            <th class="p-4" scope="col">Kasni otkazi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="tenant in tenants.data"
                            :key="tenant.id"
                            class="border-t"
                        >
                            <td class="p-4 font-medium">
                                {{ tenant.name }}
                                <p
                                    class="mt-1 text-xs font-normal text-muted-foreground"
                                >
                                    {{ statuses[tenant.status] }}
                                </p>
                            </td>
                            <td class="p-4">
                                {{ tenant.metrics.sessions }} /
                                {{ tenant.metrics.cancelled }}
                            </td>
                            <td class="p-4">
                                {{ tenant.metrics.confirmed }} /
                                {{ tenant.metrics.capacity }}
                            </td>
                            <td class="p-4">
                                {{ percentage(tenant.metrics.occupancy) }}
                            </td>
                            <td class="p-4">{{ tenant.metrics.available }}</td>
                            <td class="p-4">{{ tenant.metrics.pending }}</td>
                            <td class="p-4">{{ tenant.metrics.present }}</td>
                            <td class="p-4">{{ tenant.metrics.no_show }}</td>
                            <td class="p-4">
                                {{ tenant.metrics.late_cancelled }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p
                    v-if="!tenants.data.length"
                    class="p-5 text-muted-foreground"
                >
                    Nema studija za prikaz.
                </p>
            </div>
        </div>
        <nav
            v-if="pagination"
            aria-label="Stranice izvještaja"
            class="flex flex-wrap items-center justify-between gap-4 text-sm"
        >
            <span
                >{{ pagination.total }} rezultata · Stranica
                {{ pagination.current_page }} / {{ pagination.last_page }}</span
            >
            <div class="flex gap-5">
                <Link
                    v-if="pagination.current_page > 1"
                    :href="
                        reportRoute({
                            query: {
                                from: period.from,
                                to: period.to,
                                page: pagination.current_page - 1,
                            },
                        })
                    "
                    class="underline"
                    >Prethodna</Link
                ><Link
                    v-if="pagination.current_page < pagination.last_page"
                    :href="
                        reportRoute({
                            query: {
                                from: period.from,
                                to: period.to,
                                page: pagination.current_page + 1,
                            },
                        })
                    "
                    class="underline"
                    >Sljedeća</Link
                >
            </div>
        </nav>
    </section>
</template>
