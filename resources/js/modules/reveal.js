/**
 * v2 reveal on scroll: [data-reveal] elements fade up once (300ms) when they enter the viewport; children of
 * [data-reveal-stagger] reveal 60ms apart (max 6 steps). The hidden state only exists after this module adds
 * .reveal-ready to <html>, and anything already on screen is marked revealed first, so there is no flash, no hidden
 * content without JavaScript and no delay on the LCP element. Reduced motion is handled in CSS (opacity only).
 */
const STEP_MS = 60;
const MAX_STEPS = 6;

export function init() {
    document.querySelectorAll('[data-reveal-stagger]').forEach((group) => {
        [...group.children].forEach((child, index) => {
            child.setAttribute('data-reveal', '');
            child.style.setProperty('--reveal-delay', `${Math.min(index, MAX_STEPS) * STEP_MS}ms`);
        });
    });

    const targets = [...document.querySelectorAll('[data-reveal]')];

    if (targets.length === 0 || !('IntersectionObserver' in window)) {
        return;
    }

    const viewport = window.innerHeight;
    const pending = targets.filter((target) => {
        const inView = target.getBoundingClientRect().top < viewport;

        if (inView) {
            target.classList.add('is-revealed');
        }

        return !inView;
    });

    if (pending.length === 0) {
        return;
    }

    // Threshold .15 of the element, or of the viewport for elements taller than the screen (rootMargin trims 15%).
    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-revealed');
                    observer.unobserve(entry.target);
                }
            });
        },
        { rootMargin: '0px 0px -15% 0px', threshold: 0 },
    );

    pending.forEach((target) => observer.observe(target));
    document.documentElement.classList.add('reveal-ready');
}
