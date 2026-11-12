/**
 * Live character counters: <input data-char-count="60"> followed by a [data-char-count-output] hint.
 * The hint keeps its original guidance and appends "n / recommended".
 */
export function init() {
    document.querySelectorAll('[data-char-count]').forEach((field) => {
        const output = field.parentElement?.querySelector('[data-char-count-output]');

        if (!output) {
            return;
        }

        const hint = output.textContent.trim();
        const limit = Number(field.dataset.charCount);
        output.setAttribute('aria-live', 'polite');

        const update = () => {
            const length = [...field.value].length;
            output.textContent = `${hint} ${length} / ${limit}`;
            output.classList.toggle('text-fg-warning', length > limit);
        };

        field.addEventListener('input', update);
        update();
    });
}
