import { onMounted, onUnmounted, type Ref } from 'vue';

/** Local progressive decoration. Reading and navigation never depend on motion. */
export function useLotusScene(root: Ref<HTMLElement | null>): void {
    let dispose = (): void => {};
    onMounted(() => {
        const element = root.value;
        if (!element) {
            return;
        }
        const preference = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        );
        const hero = element.querySelector<HTMLElement>('.landscape-story');
        const manifesto = element.querySelector<HTMLElement>('.manifesto');
        const studio = element.querySelector<HTMLElement>('.studio-spread');
        const breath = element.querySelector<HTMLElement>('.breathing-space');
        const stage = element.querySelector<HTMLElement>('.landscape-stage');
        const words = element.querySelectorAll<HTMLElement>('[data-word]');
        const animations = new Set<Animation>();
        let observer: IntersectionObserver | undefined;
        let frame = 0;
        const clamp = (value: number): number =>
            Math.max(0, Math.min(1, value));
        const render = (): void => {
            frame = 0;
            if (document.hidden) {
                return;
            }
            const height = window.innerHeight;
            if (hero) {
                const box = hero.getBoundingClientRect();
                hero.style.setProperty(
                    '--journey',
                    clamp(
                        -box.top /
                            Math.max(
                                1,
                                box.height - (stage?.offsetHeight ?? height),
                            ),
                    ).toFixed(4),
                );
            }
            if (manifesto) {
                const box = manifesto.getBoundingClientRect();
                const progress = clamp(
                    (height * 0.8 - box.top) / (box.height * 0.65),
                );
                words.forEach((word, index) => {
                    word.style.color =
                        progress >= index / words.length
                            ? 'var(--forest)'
                            : 'var(--muted)';
                });
            }
            if (studio) {
                const box = studio.getBoundingClientRect();
                if (box.top < height && box.bottom > 0) {
                    studio.style.setProperty(
                        '--drift',
                        `${Math.max(-24, Math.min(24, (height / 2 - box.top) * 0.04))}px`,
                    );
                }
            }
            if (breath) {
                const box = breath.getBoundingClientRect();
                if (box.top < height && box.bottom > 0) {
                    breath.style.setProperty(
                        '--breath',
                        clamp(
                            (height - box.top) / (height + box.height),
                        ).toFixed(4),
                    );
                }
            }
        };
        const schedule = (): void => {
            if (!frame && !document.hidden) {
                frame = window.requestAnimationFrame(render);
            }
        };
        const stop = (): void => {
            window.removeEventListener('scroll', schedule);
            window.removeEventListener('resize', schedule);
            document.removeEventListener('visibilitychange', schedule);
            window.cancelAnimationFrame(frame);
            frame = 0;
            observer?.disconnect();
            animations.forEach((animation) => animation.cancel());
            animations.clear();
            element.classList.remove('motion-ready');
            hero?.style.removeProperty('--journey');
            studio?.style.removeProperty('--drift');
            breath?.style.removeProperty('--breath');
            words.forEach((word) => word.style.removeProperty('color'));
        };
        const start = (): void => {
            stop();
            if (preference.matches) {
                return;
            }
            element.classList.add('motion-ready');
            window.addEventListener('scroll', schedule, { passive: true });
            window.addEventListener('resize', schedule, { passive: true });
            document.addEventListener('visibilitychange', schedule);
            if ('IntersectionObserver' in window) {
                observer = new IntersectionObserver(
                    (entries) => {
                        entries.forEach((entry) => {
                            if (!entry.isIntersecting) {
                                return;
                            }
                            observer?.unobserve(entry.target);
                            if (typeof entry.target.animate !== 'function') {
                                return;
                            }
                            const animation = entry.target.animate(
                                [
                                    {
                                        transform: 'translateY(26px)',
                                        opacity: 0.65,
                                    },
                                    { transform: 'translateY(0)', opacity: 1 },
                                ],
                                {
                                    duration: 850,
                                    easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
                                },
                            );
                            animations.add(animation);
                            animation.onfinish = () =>
                                animations.delete(animation);
                        });
                    },
                    { threshold: 0.12 },
                );
                element
                    .querySelectorAll('[data-enter]')
                    .forEach((target) => observer?.observe(target));
            }
            render();
        };
        preference.addEventListener('change', start);
        start();
        dispose = (): void => {
            stop();
            preference.removeEventListener('change', start);
        };
    });
    onUnmounted(() => dispose());
}
