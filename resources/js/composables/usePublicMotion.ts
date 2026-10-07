import { onMounted, onUnmounted, type Ref } from 'vue';

/** Decorative motion only: content remains visible without JavaScript. */
export function usePublicMotion(root: Ref<HTMLElement | null>): void {
    let dispose = (): void => {};

    onMounted(() => {
        const element = root.value;
        if (!element) {
            return;
        }

        const preference = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        );
        const desktop = window.matchMedia('(min-width: 768px)');
        let observer: IntersectionObserver | undefined;
        let frame = 0;
        const animations = new Set<Animation>();
        const decorations =
            element.querySelectorAll<HTMLElement>('[data-parallax]');

        const updateParallax = (): void => {
            frame = 0;
            decorations.forEach((decoration) => {
                const offset = Math.max(
                    -18,
                    Math.min(
                        18,
                        (window.innerHeight / 2 -
                            decoration.getBoundingClientRect().top) *
                            0.035,
                    ),
                );
                decoration.style.translate = `0 ${offset}px`;
            });
        };
        const onScroll = (): void => {
            if (!frame) {
                frame = window.requestAnimationFrame(updateParallax);
            }
        };
        const stop = (): void => {
            observer?.disconnect();
            window.removeEventListener('scroll', onScroll);
            window.removeEventListener('resize', onScroll);
            window.cancelAnimationFrame(frame);
            frame = 0;
            animations.forEach((animation) => animation.cancel());
            animations.clear();
            decorations.forEach((decoration) =>
                decoration.style.removeProperty('translate'),
            );
        };
        const start = (): void => {
            stop();
            if (preference.matches) {
                return;
            }
            if ('IntersectionObserver' in window) {
                observer = new IntersectionObserver(
                    (entries) => {
                        entries.forEach((entry) => {
                            if (!entry.isIntersecting) {
                                return;
                            }
                            observer?.unobserve(entry.target);
                            if (typeof entry.target.animate === 'function') {
                                const animation = entry.target.animate(
                                    [
                                        {
                                            transform: 'translateY(12px)',
                                            opacity: 0.75,
                                        },
                                        {
                                            transform: 'translateY(0)',
                                            opacity: 1,
                                        },
                                    ],
                                    { duration: 550, easing: 'ease-out' },
                                );
                                animations.add(animation);
                                animation.onfinish = () =>
                                    animations.delete(animation);
                            }
                        });
                    },
                    { threshold: 0.12 },
                );
                element
                    .querySelectorAll('[data-reveal]')
                    .forEach((target) => observer?.observe(target));
            }
            if (desktop.matches) {
                window.addEventListener('scroll', onScroll, { passive: true });
                window.addEventListener('resize', onScroll, { passive: true });
                onScroll();
            }
        };
        preference.addEventListener('change', start);
        desktop.addEventListener('change', start);
        start();
        dispose = (): void => {
            stop();
            preference.removeEventListener('change', start);
            desktop.removeEventListener('change', start);
        };
    });

    onUnmounted(() => dispose());
}
