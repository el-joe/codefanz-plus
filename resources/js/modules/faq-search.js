/**
 * Client-side FAQ filtering. Markup: [data-faq-search] wraps an <input>; questions are [data-faq-item]
 * inside [data-faq-group]; [data-faq-empty] shows when nothing matches. Without JavaScript the search
 * box stays hidden and every question is listed.
 */
const normalise = (text) => text.toLocaleLowerCase().normalize('NFKD').replace(/[ً-ٟ̀-ͯ]/g, '');

export function init() {
    document.querySelectorAll('[data-faq-search]').forEach((wrapper) => {
        const input = wrapper.querySelector('input');
        const root = wrapper.closest('[data-faq]') ?? document;
        const items = [...root.querySelectorAll('[data-faq-item]')];
        const groups = [...root.querySelectorAll('[data-faq-group]')];
        const empty = root.querySelector('[data-faq-empty]');
        const status = root.querySelector('[data-faq-status]');

        if (!input) {
            return;
        }

        const index = items.map((item) => [item, normalise(item.textContent ?? '')]);
        wrapper.hidden = false;

        input.addEventListener('input', () => {
            const terms = normalise(input.value.trim()).split(/\s+/).filter(Boolean);
            let visible = 0;

            index.forEach(([item, text]) => {
                const match = terms.every((term) => text.includes(term));
                item.hidden = !match;
                visible += match ? 1 : 0;

                if (match && terms.length > 0) {
                    item.open = false;
                }
            });

            groups.forEach((group) => {
                group.hidden = !group.querySelector('[data-faq-item]:not([hidden])');
            });

            if (empty) {
                empty.hidden = visible > 0;
            }

            if (status) {
                status.textContent = terms.length > 0 ? status.dataset.template.replace(':count', String(visible)) : '';
            }
        });
    });
}
