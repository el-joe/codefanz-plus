/**
 * Live character counters: <input data-char-count="60"> followed by a [data-char-count-output] hint.
 * The hint keeps its original guidance and appends "n / recommended".
 * Public forms (Figma "0 / 2000" counter) use an empty output with data-char-count-quiet: the field's maxlength
 * already enforces the limit, so the counter is not announced on every keystroke.
 */
export function init() {
    document.querySelectorAll('[data-char-count]').forEach((field) => {
        const output = field.parentElement?.querySelector('[data-char-count-output]');

        if (!output) {
            return;
        }

        const hint = output.textContent.trim();
        const limit = Number(field.dataset.charCount);
        if (!('charCountQuiet' in output.dataset)) {
            output.setAttribute('aria-live', 'polite');
        }

        const update = () => {
            const length = [...field.value].length;
            output.textContent = `${hint} ${length} / ${limit}`.trim();
            output.classList.toggle('text-fg-warning', length > limit);
        };

        field.addEventListener('input', update);
        update();
    });
}
