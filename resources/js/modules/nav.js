/**
 * Mobile navigation disclosure: [data-nav-toggle] (aria-controls → panel id) shows/hides [data-nav-panel].
 * Without JavaScript the toggle stays hidden and the panel is always visible, so every link stays reachable.
 * Once enhanced the panel gets [data-enhanced] and is styled as a full-height drawer under the sticky header.
 */
export function init() {
    const toggle = document.querySelector('[data-nav-toggle]');
    const panel = toggle ? document.getElementById(toggle.getAttribute('aria-controls')) : null;

    if (!toggle || !panel) {
        return;
    }

    const openLabel = toggle.querySelector('[data-nav-label-open]');
    const closeLabel = toggle.querySelector('[data-nav-label-close]');

    const setOpen = (open) => {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        openLabel?.toggleAttribute('hidden', open);
        closeLabel?.toggleAttribute('hidden', !open);
    };

    toggle.hidden = false;
    panel.dataset.enhanced = '';
    setOpen(false);

    toggle.addEventListener('click', () => setOpen(panel.hidden));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            setOpen(false);
            toggle.focus();
        }
    });

    // Close when resizing to the desktop layout so the panel is not left open behind the desktop nav.
    window.matchMedia('(min-width: 80rem)').addEventListener('change', (query) => {
        if (query.matches) {
            setOpen(false);
        }
    });
}
