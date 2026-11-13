/**
 * Mobile navigation disclosure: [data-nav-toggle] (aria-controls → panel id) shows/hides [data-nav-panel].
 * Without JavaScript the toggle stays hidden and the panel is always visible, so every link stays reachable.
 * Once enhanced the panel gets [data-enhanced] and is styled as a full-height drawer under the sticky header.
 */
export function init() {
    initDropdownItems();
    initDrawerSubmenus();

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

/**
 * Desktop "Services ▾" item ([data-nav-item]): the trigger is a link to the parent page and the panel opens through CSS
 * (hover / focus-within). Here: aria-expanded follows the visible state, ArrowDown opens and focuses the first link,
 * Esc closes and returns focus to the trigger, and on touch screens the first tap opens the panel, the second navigates.
 */
function initDropdownItems() {
    document.querySelectorAll('[data-nav-item]').forEach((item) => {
        const trigger = item.querySelector('[data-nav-trigger]');
        const panel = item.querySelector('[data-nav-dropdown]');

        if (!trigger || !panel) {
            return;
        }

        let pointerType = 'mouse';
        let syncTimer = null;

        const isOpen = () => !item.hasAttribute('data-closed')
            && (item.hasAttribute('data-open') || item.matches(':focus-within') || (matchMedia('(hover: hover)').matches && item.matches(':hover')));

        const sync = (delay = 0) => {
            clearTimeout(syncTimer);
            syncTimer = setTimeout(() => trigger.setAttribute('aria-expanded', String(isOpen())), delay);
        };

        const close = () => {
            item.removeAttribute('data-open');
            sync();
        };

        item.addEventListener('mouseenter', () => sync());
        item.addEventListener('mouseleave', () => {
            item.removeAttribute('data-closed');
            if (pointerType !== 'touch') {
                item.removeAttribute('data-open');
            }
            sync(160);
        });
        item.addEventListener('focusin', () => sync());
        item.addEventListener('focusout', (event) => {
            if (!item.contains(event.relatedTarget)) {
                item.removeAttribute('data-closed');
                item.removeAttribute('data-open');
                sync();
            }
        });

        trigger.addEventListener('pointerdown', (event) => {
            pointerType = event.pointerType;
        });

        trigger.addEventListener('click', (event) => {
            if (pointerType === 'touch' && !item.hasAttribute('data-open')) {
                event.preventDefault();
                item.removeAttribute('data-closed');
                item.setAttribute('data-open', '');
                sync();
            }
        });

        trigger.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                item.removeAttribute('data-closed');
                item.setAttribute('data-open', '');
                sync();
                panel.querySelector('a')?.focus();
            }
        });

        item.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && isOpen()) {
                item.setAttribute('data-closed', '');
                item.removeAttribute('data-open');
                trigger.focus();
                sync();
            }
        });

        document.addEventListener('pointerdown', (event) => {
            if (!item.contains(event.target)) {
                close();
            }
        });
    });
}

/**
 * Mobile drawer rows with a sub-list: the label stays a link, the chevron button ([data-nav-sub-toggle], hidden
 * without JavaScript so the sub-list simply stays open) expands and collapses the list.
 */
function initDrawerSubmenus() {
    document.querySelectorAll('[data-nav-sub-toggle]').forEach((button) => {
        const list = document.getElementById(button.getAttribute('aria-controls'));

        if (!list) {
            return;
        }

        const setOpen = (open) => {
            list.hidden = !open;
            button.setAttribute('aria-expanded', String(open));
        };

        button.hidden = false;
        setOpen(false);
        button.addEventListener('click', () => setOpen(list.hidden));
    });
}
