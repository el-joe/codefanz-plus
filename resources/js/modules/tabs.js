/**
 * Accessible tabs: [data-tabs] > [data-tablist] (hidden until this runs) with [data-tab] buttons
 * (aria-controls → [data-tab-panel]). Without JavaScript every panel stays visible, stacked.
 * The panel holding a validation error is opened first.
 */
export function init() {
    document.querySelectorAll('[data-tabs]').forEach((root) => {
        const tablist = root.querySelector('[data-tablist]');
        const tabs = [...root.querySelectorAll('[data-tab]')];
        const panels = tabs.map((tab) => document.getElementById(tab.getAttribute('aria-controls')));

        if (!tablist || tabs.length === 0 || panels.some((panel) => !panel)) {
            return;
        }

        const select = (index, focus = false) => {
            tabs.forEach((tab, position) => {
                const active = position === index;
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
                tab.tabIndex = active ? 0 : -1;
                panels[position].hidden = !active;
            });

            if (focus) {
                tabs[index].focus();
            }

            root.dispatchEvent(new CustomEvent('tabs:change', { detail: { panel: panels[index] } }));
        };

        tablist.hidden = false;
        panels.forEach((panel) => {
            panel.classList.remove('[&+&]:mt-10', '[&+&]:border-t', '[&+&]:pt-10');
            panel.querySelector('[data-panel-heading]')?.classList.add('sr-only');
            panel.tabIndex = 0;
        });

        const withError = panels.findIndex((panel) => panel.querySelector('[aria-invalid="true"], .text-fg-danger'));
        select(withError >= 0 ? withError : 0);

        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => select(index));
            tab.addEventListener('keydown', (event) => {
                const rtl = getComputedStyle(tablist).direction === 'rtl';
                const next = { ArrowRight: rtl ? -1 : 1, ArrowLeft: rtl ? 1 : -1 }[event.key];

                if (next !== undefined) {
                    event.preventDefault();
                    select((index + next + tabs.length) % tabs.length, true);
                } else if (event.key === 'Home' || event.key === 'End') {
                    event.preventDefault();
                    select(event.key === 'Home' ? 0 : tabs.length - 1, true);
                }
            });
        });
    });
}
