<script setup lang="ts">
import { Head, Form, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { index, update, suspend } from '@/routes/platform/tenants';
import { store as addDomain, verify } from '@/routes/platform/tenants/domains';
import { send } from '@/routes/platform/tenants/invitation';
defineProps<{
    tenant: { id: number; name: string; status: string; design_key: string };
    profile: {
        short_description: string | null;
        email: string | null;
        phone: string | null;
        address: string | null;
    } | null;
    domains: {
        id: number;
        hostname: string;
        verification_status: string;
        verified_at: string | null;
        is_primary: boolean;
        is_active: boolean;
    }[];
    invitation: {
        email: string;
        accepted_at: string | null;
        revoked_at: string | null;
        sent_at: string | null;
        delivery_failed_at: string | null;
        expires_at: string;
    } | null;
    audits: {
        id: number;
        actor: string;
        action: string;
        subject_type: string;
        subject_id: number;
        created_at: string;
        changes: Record<string, string | number>;
    }[];
    designs: Record<string, string>;
    statusMessage: string | null;
}>();
const statuses: Record<string, string> = {
    pending: 'Čeka aktivaciju',
    active: 'Aktivan',
    suspended: 'Suspendiran',
};
</script>

<template>
    <div class="space-y-8">
        <Head :title="tenant.name" />
        <Link :href="index()" class="text-sm underline"
            >← Registar studija</Link
        >
        <div>
            <h1 class="text-3xl font-semibold break-words">
                {{ tenant.name }}
            </h1>
            <p class="mt-2 text-muted-foreground">
                {{ statuses[tenant.status] }} · Studio #{{ tenant.id }}
            </p>
        </div>
        <p
            v-if="statusMessage"
            role="status"
            class="rounded-lg border bg-muted p-4"
        >
            {{ statusMessage }}
        </p>
        <div class="grid gap-6 lg:grid-cols-2">
            <section class="space-y-4 rounded-xl border p-5">
                <h2 class="text-xl font-semibold">Javni profil</h2>
                <p class="break-words">
                    {{ profile?.short_description || 'Opis nije unesen.' }}
                </p>
                <dl class="grid gap-3 text-sm">
                    <div>
                        <dt class="text-muted-foreground">Email</dt>
                        <dd class="break-words">{{ profile?.email || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Telefon</dt>
                        <dd>{{ profile?.phone || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Adresa</dt>
                        <dd class="break-words">
                            {{ profile?.address || '—' }}
                        </dd>
                    </div>
                </dl>
            </section>
            <section class="space-y-4 rounded-xl border p-5">
                <h2 class="text-xl font-semibold">Dizajn</h2>
                <Form
                    v-bind="update.form(tenant.id)"
                    v-slot="{ errors, processing }"
                    class="space-y-3"
                    ><Label for="design_key">Registrirani dizajn</Label
                    ><select
                        id="design_key"
                        name="design_key"
                        :value="tenant.design_key"
                        class="h-10 w-full rounded-md border bg-background px-3"
                    >
                        <option
                            v-for="(label, key) in designs"
                            :key="key"
                            :value="key"
                        >
                            {{ label }}
                        </option></select
                    ><InputError :message="errors.design_key" /><Button
                        :disabled="processing"
                        >Spremi dizajn</Button
                    ></Form
                >
            </section>
        </div>
        <section class="space-y-5 rounded-xl border p-5">
            <h2 class="text-xl font-semibold">Domene i aktivacija</h2>
            <p class="text-sm text-muted-foreground">
                Prije potvrde provjerite kontrolu domene, DNS, usmjeravanje na
                aplikaciju i TLS (lokalno hosts i HTTP). Unesite oznaku
                operativnog zapisa s dokazima, bez tajni. Verifikacija primarne
                domene aktivira novi studio. Ova radnja ne konfigurira
                infrastrukturu.
            </p>
            <div
                v-for="domain in domains"
                :key="domain.id"
                class="space-y-3 border-t pt-4"
            >
                <h3 class="font-medium break-all">
                    {{ domain.hostname }}
                    <span
                        v-if="domain.is_primary"
                        class="text-sm text-muted-foreground"
                        >· primarna</span
                    >
                </h3>
                <p class="text-sm">
                    {{
                        domain.verification_status === 'verified'
                            ? 'Verificirana'
                            : 'Čeka verifikaciju'
                    }}{{ !domain.is_active ? ' · neaktivna' : '' }}
                </p>
                <Form
                    v-if="
                        domain.verification_status === 'pending' &&
                        tenant.status !== 'suspended'
                    "
                    v-bind="
                        verify.form({ tenant: tenant.id, domain: domain.id })
                    "
                    v-slot="{ errors, processing }"
                    class="grid max-w-xl gap-3"
                >
                    <Label :for="`reference-${domain.id}`"
                        >Referenca dokaza / operativnog zapisa</Label
                    ><Input
                        :id="`reference-${domain.id}`"
                        name="reference"
                        required
                        maxlength="120"
                        placeholder="OPS-123"
                    /><InputError :message="errors.reference" />
                    <label class="flex items-start gap-2 text-sm"
                        ><input
                            type="checkbox"
                            name="confirmed"
                            value="1"
                            required
                            class="mt-1"
                        />Provjerio/la sam kontrolu domene i
                        infrastrukturu.</label
                    ><InputError :message="errors.confirmed" /><Button
                        :disabled="processing"
                        class="justify-self-start"
                        >Potvrdi verifikaciju</Button
                    >
                </Form>
            </div>
            <Form
                v-bind="addDomain.form(tenant.id)"
                v-slot="{ errors, processing }"
                class="grid max-w-xl gap-3 border-t pt-4"
                ><Label for="hostname">Dodaj domenu u pending statusu</Label
                ><Input
                    id="hostname"
                    name="hostname"
                    required
                    maxlength="253"
                    placeholder="studio.example.test"
                /><InputError :message="errors.hostname" /><Button
                    :disabled="processing"
                    variant="outline"
                    class="justify-self-start"
                    >Dodaj domenu</Button
                ></Form
            >
        </section>
        <section class="space-y-4 rounded-xl border p-5">
            <h2 class="text-xl font-semibold">Početni vlasnik</h2>
            <template v-if="invitation"
                ><p class="break-all">{{ invitation.email }}</p>
                <p class="text-sm">
                    {{
                        invitation.accepted_at
                            ? 'Poziv prihvaćen.'
                            : invitation.revoked_at
                              ? 'Poziv opozvan.'
                              : invitation.delivery_failed_at
                                ? 'Slanje nije uspjelo. Ponovite poziv nakon provjere email transporta.'
                                : invitation.sent_at
                                  ? `Poziv poslan. Istječe: ${invitation.expires_at}`
                                  : 'Poziv pripremljen; još nije poslan.'
                    }}
                </p>
                <Form
                    v-if="
                        !invitation.accepted_at &&
                        !invitation.revoked_at &&
                        tenant.status === 'active'
                    "
                    v-bind="send.form(tenant.id)"
                    v-slot="{ errors, processing }"
                    class="space-y-3"
                    ><p class="text-sm text-muted-foreground">
                        Ponovno slanje poništava prethodnu poveznicu i daje
                        novih sedam dana za prihvat.
                    </p>
                    <InputError :message="errors.invitation" /><Button
                        :disabled="processing"
                        >{{
                            processing
                                ? 'Slanje…'
                                : invitation.sent_at ||
                                    invitation.delivery_failed_at
                                  ? 'Ponovi poziv'
                                  : 'Pošalji poziv'
                        }}</Button
                    ></Form
                >
                <p
                    v-else-if="
                        !invitation.accepted_at && tenant.status !== 'active'
                    "
                    class="text-sm text-muted-foreground"
                >
                    Slanje je dostupno nakon aktivacije studija.
                </p>
            </template>
            <p v-else class="text-sm text-muted-foreground">
                Za ovaj studio nije kreirana početna owner pozivnica.
            </p>
        </section>
        <section
            v-if="tenant.status !== 'suspended'"
            class="space-y-4 rounded-xl border border-destructive/40 p-5"
        >
            <h2 class="text-xl font-semibold">Deaktivacija studija</h2>
            <p class="text-sm">
                Suspenzija odmah onemogućuje javnu stranicu, prijavu i postojeće
                studio sesije te obradu tenant jobova. Podaci se čuvaju; nema
                brisanja.
            </p>
            <Form
                v-bind="suspend.form(tenant.id)"
                v-slot="{ errors, processing }"
                class="grid max-w-xl gap-3"
                ><Label for="suspension-reference">Referenca odluke</Label
                ><Input
                    id="suspension-reference"
                    name="reference"
                    required
                    maxlength="120"
                    placeholder="OPS-124"
                /><InputError :message="errors.reference" /><label
                    class="flex items-start gap-2 text-sm"
                    ><input
                        type="checkbox"
                        name="confirmed"
                        value="1"
                        required
                        class="mt-1"
                    />Razumijem posljedice i potvrđujem suspenziju
                    studija.</label
                ><InputError :message="errors.confirmed" /><Button
                    variant="destructive"
                    :disabled="processing"
                    class="justify-self-start"
                    >Suspendiraj studio</Button
                ></Form
            >
        </section>
        <section class="space-y-4">
            <h2 class="text-xl font-semibold">
                Posljednjih 30 administrativnih promjena
            </h2>
            <p v-if="!audits.length" class="text-sm text-muted-foreground">
                Nema zabilježenih promjena.
            </p>
            <ol class="space-y-3">
                <li
                    v-for="audit in audits"
                    :key="audit.id"
                    class="space-y-2 rounded-lg border p-4 text-sm"
                >
                    <p class="font-medium break-words">
                        {{ audit.action }} · {{ audit.actor }}
                    </p>
                    <p class="text-muted-foreground">
                        {{ audit.created_at }} · {{ audit.subject_type }} #{{
                            audit.subject_id
                        }}
                    </p>
                    <dl class="space-y-1">
                        <div
                            v-for="(value, key) in audit.changes"
                            :key="key"
                            class="flex flex-wrap gap-2 break-all"
                        >
                            <dt class="text-muted-foreground">{{ key }}:</dt>
                            <dd>{{ value }}</dd>
                        </div>
                    </dl>
                </li>
            </ol>
        </section>
    </div>
</template>
