<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { usePublicMotion } from '@/composables/usePublicMotion';
import { home, about, login } from '@/routes';
defineProps<{ name: string; current: 'home' | 'about' }>();
const root = ref<HTMLElement | null>(null);
usePublicMotion(root);
</script>
<template>
    <div ref="root" class="balance-site min-h-screen">
        <a href="#public-content" class="skip-link">Preskoči na sadržaj</a>
        <header class="balance-header">
            <Link :href="home()" class="balance-brand"
                ><span class="brand-mark" aria-hidden="true">//</span>
                {{ name }}</Link
            >
            <nav aria-label="Javna navigacija">
                <Link
                    :href="home()"
                    :aria-current="current === 'home' ? 'page' : undefined"
                    >Naslovnica</Link
                >
                <Link
                    :href="about()"
                    :aria-current="current === 'about' ? 'page' : undefined"
                    >O studiju</Link
                >
                <Link :href="login()" class="login-link">Prijava ↗</Link>
            </nav>
        </header>
        <main id="public-content" tabindex="-1"><slot /></main>
        <footer class="balance-footer">
            <Link :href="home()" class="balance-brand">{{ name }}</Link>
            <p>Pokret. Dah. Ravnoteža.</p>
            <Link :href="login()">Prijava u studio ↗</Link>
        </footer>
    </div>
</template>
<style scoped>
.balance-site {
    overflow-wrap: anywhere;
    background: #142727;
    color: #f0f0e6;
    --site-line: #4b605a;
}
.balance-header,
.balance-footer {
    padding: 28px 48px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 32px;
    max-width: 1440px;
    margin-inline: auto;
}
.balance-header {
    border-bottom: 1px solid var(--site-line);
}
.balance-brand {
    font-size: 23px;
    font-weight: 600;
    letter-spacing: -0.04em;
    overflow-wrap: anywhere;
    min-width: 0;
}
.brand-mark {
    color: #c8dc86;
    font-size: 32px;
    margin-right: 8px;
}
nav {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 30px;
    font-size: 14px;
    flex-shrink: 0;
}
.login-link {
    background: #c8dc86;
    color: #142727;
    padding: 12px 22px;
}
a {
    text-underline-offset: 6px;
}
a:hover,
a[aria-current='page'] {
    text-decoration: underline;
}
.balance-site :deep(a:focus-visible) {
    outline: 2px solid #c8dc86;
    outline-offset: 5px;
}
.skip-link {
    position: absolute;
    top: 8px;
    left: 8px;
    padding: 12px;
    background: #142727;
    z-index: 10;
    transform: translateY(-200%);
}
.skip-link:focus {
    transform: none;
}
.balance-footer {
    border-top: 1px solid var(--site-line);
    font-size: 12px;
    padding-block: 36px;
    flex-wrap: wrap;
}
.balance-site :deep(.balance-section) {
    max-width: 1440px;
    margin-inline: auto;
    padding: 80px 48px;
}
.balance-site :deep(.balance-eyebrow) {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.16em;
    color: #c8dc86;
}
.balance-site :deep(.balance-heading) {
    font-size: clamp(48px, 7.8vw, 112px);
    font-weight: 600;
    letter-spacing: -0.065em;
    line-height: 0.98;
    overflow-wrap: anywhere;
}
.balance-site :deep(.balance-copy) {
    font-size: 17px;
    line-height: 1.8;
    color: #c9d2c9;
    max-width: 48ch;
    white-space: pre-line;
    overflow-wrap: anywhere;
}
.balance-site :deep(.balance-button) {
    display: inline-flex;
    gap: 48px;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #c8dc86;
    padding-block: 15px;
    color: #c8dc86;
    font-size: 15px;
}
.balance-site :deep(.balance-contact) {
    background: #c8dc86;
    color: #142727;
}
.balance-site :deep(.balance-contact-inner) {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 64px;
}
.balance-site :deep(.balance-contact h2) {
    font-size: 28px;
    letter-spacing: -0.04em;
}
.balance-site :deep(.balance-contact dl) {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 28px;
    line-height: 1.7;
}
@media (max-width: 767px) {
    .balance-header {
        padding: 20px 24px;
        align-items: start;
        flex-direction: column;
        gap: 22px;
    }
    nav {
        width: 100%;
        justify-content: space-between;
        gap: 12px;
    }
    .balance-footer {
        padding: 30px 24px;
    }
    .balance-site :deep(.balance-section) {
        padding: 48px 24px;
    }
    .balance-site :deep(.balance-contact-inner) {
        grid-template-columns: 1fr;
        gap: 36px;
    }
}
</style>
