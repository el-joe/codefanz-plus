/**
 * v2 glass header: transparent at the top of the page, glass (white 72% + blur) once the page scrolls past 8px.
 * Without this module the header keeps the glass state (see app.css), so it only opts in to the transparent top.
 */
const THRESHOLD = 8;

export function init() {
    const header = document.querySelector('[data-site-header]');

    if (!header) {
        return;
    }

    let ticking = false;
    const update = () => {
        header.classList.toggle('is-scrolled', window.scrollY > THRESHOLD);
        ticking = false;
    };

    update();
    header.setAttribute('data-scroll-enhanced', '');

    window.addEventListener(
        'scroll',
        () => {
            if (!ticking) {
                ticking = true;
                window.requestAnimationFrame(update);
            }
        },
        { passive: true },
    );
}
