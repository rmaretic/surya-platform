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
    <div ref="root" class="lotus-site min-h-screen">
        <a href="#public-content" class="skip-link">Preskoči na sadržaj</a>
        <header class="lotus-header">
            <Link :href="home()" class="lotus-brand"
                ><span aria-hidden="true">✳</span> {{ name }}</Link
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
                <Link :href="login()" class="login-link"
                    >Prijava <span aria-hidden="true">↗</span></Link
                >
            </nav>
        </header>
        <main id="public-content" tabindex="-1"><slot /></main>
        <footer class="lotus-footer">
            <Link :href="home()" class="lotus-brand">{{ name }}</Link>
            <p>Malo prostora za sebe.</p>
            <Link :href="login()">Prijava u studio ↗</Link>
        </footer>
    </div>
</template>
<style scoped>
.lotus-site {
    overflow-wrap: anywhere;
    background: #f5f1e8;
    color: #354336;
    --site-line: #c8cabc;
}
.lotus-header,
.lotus-footer {
    max-width: 1320px;
    margin-inline: auto;
    padding: 28px 40px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 28px;
}
.lotus-header {
    border-bottom: 1px solid var(--site-line);
}
.lotus-brand {
    font:
        28px/1.2 Georgia,
        serif;
    overflow-wrap: anywhere;
    min-width: 0;
}
.lotus-brand span {
    margin-right: 8px;
}
nav {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 28px;
    font-size: 14px;
    flex-shrink: 0;
}
.login-link {
    border: 1px solid var(--site-line);
    border-radius: 100px;
    padding: 10px 20px;
}
a {
    text-underline-offset: 6px;
}
a:hover,
a[aria-current='page'] {
    text-decoration: underline;
}
.lotus-site :deep(a:focus-visible) {
    outline: 2px solid #354336;
    outline-offset: 5px;
}
.skip-link {
    position: absolute;
    top: 8px;
    left: 8px;
    padding: 12px;
    background: #f5f1e8;
    z-index: 10;
    transform: translateY(-200%);
}
.skip-link:focus {
    transform: none;
}
.lotus-footer {
    border-top: 1px solid var(--site-line);
    font-size: 13px;
    padding-block: 36px;
    flex-wrap: wrap;
}
.lotus-site :deep(.lotus-section) {
    max-width: 1240px;
    margin-inline: auto;
    padding: 88px 40px;
}
.lotus-site :deep(.lotus-eyebrow) {
    font-size: 11px;
    letter-spacing: 0.2em;
    text-transform: uppercase;
}
.lotus-site :deep(.lotus-heading) {
    font-family: Georgia, 'Times New Roman', serif;
    font-weight: 400;
    font-size: clamp(44px, 5.8vw, 84px);
    line-height: 1.06;
    letter-spacing: -0.045em;
    overflow-wrap: anywhere;
}
.lotus-site :deep(.lotus-copy) {
    font-size: 17px;
    line-height: 1.85;
    max-width: 48ch;
    white-space: pre-line;
    overflow-wrap: anywhere;
}
.lotus-site :deep(.lotus-button) {
    display: inline-flex;
    justify-content: space-between;
    gap: 30px;
    align-items: center;
    padding: 15px 24px;
    border-radius: 100px;
    background: #354b3b;
    color: #fffaf0;
    font-size: 14px;
}
.lotus-site :deep(.lotus-contact) {
    background: #e7e9dc;
    border-radius: 130px 0 0 0;
}
.lotus-site :deep(.lotus-contact-inner) {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 64px;
}
.lotus-site :deep(.lotus-contact h2) {
    font-family: Georgia, serif;
    font-weight: 400;
    font-size: 28px;
}
.lotus-site :deep(.lotus-contact dl) {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 28px;
    line-height: 1.7;
}
@media (max-width: 767px) {
    .lotus-header {
        padding: 22px 24px;
        align-items: start;
        flex-direction: column;
        gap: 22px;
    }
    nav {
        width: 100%;
        justify-content: space-between;
        gap: 12px;
    }
    .lotus-brand {
        font-size: 25px;
    }
    .lotus-footer {
        padding: 30px 24px;
    }
    .lotus-site :deep(.lotus-section) {
        padding: 56px 24px;
    }
    .lotus-site :deep(.lotus-contact-inner) {
        grid-template-columns: 1fr;
        gap: 36px;
    }
    .lotus-site :deep(.lotus-contact) {
        border-radius: 64px 0 0 0;
    }
}
</style>
