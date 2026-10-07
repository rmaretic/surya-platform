<script setup lang="ts">
import { Form, Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import {
    invite,
    send,
    revoke,
    update,
    deactivate,
} from '@/routes/studio/staff';
type Staff = {
    id: number;
    name: string;
    email: string;
    role: string;
    is_active: boolean;
};
type Invitation = {
    id: number;
    email: string;
    role: string;
    expires_at: string;
    accepted_at: string | null;
    revoked_at: string | null;
    sent_at: string | null;
    delivery_failed_at: string | null;
};
type Page<T> = {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
};
defineProps<{
    staff: Page<Staff>;
    invitations: Page<Invitation>;
    statusMessage?: string;
}>();
const form = useForm({ email: '', role: 'instructor' });
const roles: Record<string, string> = {
    owner: 'Vlasnik',
    manager: 'Manager',
    instructor: 'Instruktor',
};
const selectClass =
    'w-full rounded-md border bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring';
</script>
<template>
    <div class="grid gap-8">
        <Head title="Osoblje" />
        <h1 class="text-3xl font-semibold">Osoblje studija</h1>
        <p v-if="statusMessage" role="status" class="rounded-lg border p-4">
            {{ statusMessage }}
        </p>
        <form
            @submit.prevent="
                form.submit(invite(), { onSuccess: () => form.reset('email') })
            "
            class="grid max-w-xl gap-4 rounded-xl border p-5"
        >
            <h2 class="text-xl font-semibold">Pozovi djelatnika</h2>
            <p class="text-sm text-muted-foreground">
                Poziv vrijedi sedam dana. Postojeći račun ne mijenja ulogu
                prihvatom poziva.
            </p>
            <div class="grid gap-2">
                <Label for="invite-email">Email</Label
                ><Input
                    id="invite-email"
                    v-model="form.email"
                    type="email"
                    required
                    maxlength="255"
                /><InputError :message="form.errors.email" />
            </div>
            <div class="grid gap-2">
                <Label for="invite-role">Uloga</Label
                ><select
                    id="invite-role"
                    v-model="form.role"
                    :class="selectClass"
                >
                    <option value="instructor">Instruktor</option>
                    <option value="manager">Manager</option></select
                ><InputError :message="form.errors.role" />
            </div>
            <Button class="justify-self-start" :disabled="form.processing"
                >Pošalji poziv</Button
            >
        </form>
        <section class="grid gap-4">
            <h2 class="text-xl font-semibold">Članovi osoblja</h2>
            <article
                v-for="member in staff.data"
                :key="member.id"
                class="grid gap-4 rounded-xl border p-5"
            >
                <div class="wrap-anywhere">
                    <h3 class="font-semibold">{{ member.name }}</h3>
                    <p class="text-sm text-muted-foreground">
                        {{ member.email }}
                    </p>
                    <p class="mt-2 text-sm">
                        {{ roles[member.role] }} ·
                        {{ member.is_active ? 'Aktivan' : 'Deaktiviran' }}
                    </p>
                </div>
                <div
                    v-if="member.role !== 'owner'"
                    class="grid gap-5 md:grid-cols-2"
                >
                    <Form
                        v-bind="update.form(member.id)"
                        v-slot="{ errors, processing }"
                        class="grid content-start gap-2"
                        ><Label :for="`role-${member.id}`"
                            >Uloga djelatnika</Label
                        ><select
                            :id="`role-${member.id}`"
                            name="role"
                            :value="member.role"
                            :class="selectClass"
                        >
                            <option value="manager">Manager</option>
                            <option value="instructor">
                                Instruktor
                            </option></select
                        ><InputError :message="errors.role" /><Button
                            variant="outline"
                            :disabled="processing"
                            class="justify-self-start"
                            >Spremi ulogu</Button
                        ></Form
                    >
                    <Form
                        v-if="member.is_active"
                        v-bind="deactivate.form(member.id)"
                        v-slot="{ errors, processing }"
                        class="grid content-start gap-3"
                        ><label class="flex items-start gap-2 text-sm"
                            ><input
                                type="checkbox"
                                name="confirmed"
                                value="1"
                                required
                                class="mt-1"
                            />Potvrđujem deaktivaciju: djelatnik odmah gubi
                            pristup, uključujući postojeće sesije.</label
                        ><InputError :message="errors.confirmed" /><Button
                            variant="destructive"
                            :disabled="processing"
                            class="justify-self-start"
                            >Deaktiviraj</Button
                        ></Form
                    >
                </div>
            </article>
            <div class="flex gap-5">
                <Link
                    v-if="staff.prev_page_url"
                    :href="staff.prev_page_url"
                    class="underline"
                    >Prethodni djelatnici</Link
                ><Link
                    v-if="staff.next_page_url"
                    :href="staff.next_page_url"
                    class="underline"
                    >Sljedeći djelatnici</Link
                >
            </div>
        </section>
        <section class="grid gap-4">
            <h2 class="text-xl font-semibold">Pozivnice</h2>
            <p v-if="!invitations.data.length" class="text-muted-foreground">
                Još nema pozivnica za osoblje.
            </p>
            <article
                v-for="invitation in invitations.data"
                :key="invitation.id"
                class="grid gap-3 rounded-xl border p-5"
            >
                <p class="font-medium wrap-anywhere">
                    {{ invitation.email }} · {{ roles[invitation.role] }}
                </p>
                <p class="text-sm text-muted-foreground">
                    {{
                        invitation.accepted_at
                            ? 'Prihvaćena'
                            : invitation.revoked_at
                              ? 'Opozvana'
                              : invitation.delivery_failed_at
                                ? 'Slanje nije uspjelo'
                                : invitation.sent_at
                                  ? 'Poslana'
                                  : 'Pripremljena'
                    }}
                    · Rok (UTC):
                    {{ invitation.expires_at.slice(0, 16).replace('T', ' ') }}
                </p>
                <div
                    v-if="!invitation.accepted_at && !invitation.revoked_at"
                    class="flex flex-wrap gap-3"
                >
                    <Form
                        v-bind="send.form(invitation.id)"
                        v-slot="{ errors, processing }"
                        ><Button variant="outline" :disabled="processing"
                            >Pošalji novi poziv</Button
                        ><InputError :message="errors.invitation"
                    /></Form>
                    <Form
                        v-bind="revoke.form(invitation.id)"
                        v-slot="{ processing }"
                        ><Button variant="outline" :disabled="processing"
                            >Opozovi</Button
                        ></Form
                    >
                </div>
            </article>
            <p class="text-sm text-muted-foreground">
                Ponovno slanje poništava staru poveznicu i postavlja novi rok od
                sedam dana.
            </p>
            <div class="flex gap-5">
                <Link
                    v-if="invitations.prev_page_url"
                    :href="invitations.prev_page_url"
                    class="underline"
                    >Prethodne pozivnice</Link
                ><Link
                    v-if="invitations.next_page_url"
                    :href="invitations.next_page_url"
                    class="underline"
                    >Sljedeće pozivnice</Link
                >
            </div>
        </section>
    </div>
</template>
