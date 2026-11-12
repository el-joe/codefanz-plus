/**
 * Ordered, repeatable form rows (e.g. service features and FAQs).
 *
 * Markup: [data-repeater] > [data-repeater-list] of [data-repeater-item], a <template data-repeater-template>
 * whose inputs use the __INDEX__ placeholder, and a [data-repeater-add] button. Rows submit in DOM order.
 * Controls are hidden until this module runs, so the form still works without JavaScript.
 */
export function init() {
    document.querySelectorAll('[data-repeater]').forEach((repeater) => {
        const list = repeater.querySelector('[data-repeater-list]');
        const template = repeater.querySelector('template[data-repeater-template]');
        const add = repeater.querySelector('[data-repeater-add]');

        if (!list || !template) {
            return;
        }

        let next = list.children.length;

        const reveal = (scope) => {
            scope.querySelectorAll('[data-repeater-controls]').forEach((controls) => controls.removeAttribute('hidden'));
        };

        reveal(list);

        if (add) {
            add.removeAttribute('hidden');
            add.addEventListener('click', () => {
                list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', `n${next++}`));
                const item = list.lastElementChild;
                reveal(item);
                item?.querySelector('input:not([type="hidden"]), textarea')?.focus();
            });
        }

        list.addEventListener('click', (event) => {
            const button = event.target.closest('button');
            const item = button?.closest('[data-repeater-item]');

            if (!item) {
                return;
            }

            if (button.hasAttribute('data-repeater-remove')) {
                item.remove();
            } else if (button.hasAttribute('data-repeater-up') && item.previousElementSibling) {
                item.previousElementSibling.before(item);
                button.focus();
            } else if (button.hasAttribute('data-repeater-down') && item.nextElementSibling) {
                item.nextElementSibling.after(item);
                button.focus();
            }
        });
    });
}
