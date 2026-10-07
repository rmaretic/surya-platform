<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { edit } from '@/routes/studio/profile';
import { index } from '@/routes/studio/staff';
defineProps<{ role: string }>();
</script>
<template>
    <div class="grid gap-8">
        <Head title="Pregled studija" />
        <div class="grid gap-3">
            <p class="text-sm text-muted-foreground">Studio administracija</p>
            <h1 class="text-3xl font-semibold">Pregled studija</h1>
        </div>
        <div v-if="role === 'owner'" class="grid gap-5 md:grid-cols-2">
            <Link :href="edit()" class="rounded-xl border p-6 hover:bg-muted/40"
                ><h2 class="text-xl font-semibold">Javni profil</h2>
                <p class="mt-2 text-muted-foreground">
                    Uredite naziv, opis i kontakt koji se odmah objavljuju na
                    stranici studija.
                </p></Link
            >
            <Link
                :href="index()"
                class="rounded-xl border p-6 hover:bg-muted/40"
                ><h2 class="text-xl font-semibold">Osoblje</h2>
                <p class="mt-2 text-muted-foreground">
                    Pozovite managera ili instruktora i upravljajte njihovim
                    pristupom.
                </p></Link
            >
        </div>
        <section class="grid gap-3 rounded-xl border bg-muted/20 p-6">
            <h2 class="text-lg font-semibold">
                {{
                    role === 'instructor'
                        ? 'Prostor za instruktora'
                        : role === 'manager'
                          ? 'Prostor za managera'
                          : 'Sljedeći koraci platforme'
                }}
            </h2>
            <p class="max-w-2xl text-muted-foreground">
                Raspored, rezervacije i naplate dolaze u kasnijim razvojnim
                koracima. Trenutačno nisu dostupni.
            </p>
            <p v-if="role !== 'owner'" class="text-sm text-muted-foreground">
                Javni profil i pristup osoblja uređuje vlasnik studija.
            </p>
        </section>
    </div>
</template>
